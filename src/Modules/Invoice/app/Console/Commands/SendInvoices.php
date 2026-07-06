<?php

namespace Modules\Invoice\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Invoice\Jobs\ProcessInvoiceJob;
use Modules\Invoice\Models\Invoice;
use Modules\SendMailLog\Mail\BatchSummaryMail;
use Modules\SendMailLog\Models\SendMailLog;
use Modules\SendMailLog\Models\SendMailLogItem;
use Modules\SystemSetting\Models\SystemSetting;
use Throwable;

/**
 * 請求書メール送信バッチ（詳細設計 §1.1.2 / §3.2 / FR-02 / NFR-R-01/04 / NFR-P-02/03/04）。
 *
 * pending の請求書を取得しキュージョブへ投入する。stuck 差し戻し・--retry-failed 差し戻し・
 * 二重起動防止・行ロック・完了通知を行う。
 */
class SendInvoices extends Command
{
    /** ロックキー（NFR-R-01） */
    protected const LOCK_KEY = 'batch:send-invoices';

    /** バッチ種別 */
    protected const BATCH_KEY = SendMailLog::BATCH_SEND_INVOICES;

    /** バッチ表示名 */
    protected const BATCH_NAME = '請求書';

    /** morphMap 論理名 */
    protected const MORPH_ALIAS = Invoice::MORPH_ALIAS;

    protected $signature = 'batch:send-invoices {--limit=100} {--stuck-timeout=60} {--retry-failed}';

    protected $description = '送信待ちの請求書を取得しキューへ投入する';

    /** 対象モデルクラス（DeliveryNote 側でオーバーライド） */
    protected function modelClass(): string
    {
        return Invoice::class;
    }

    /** ディスパッチするジョブ */
    protected function dispatchJob(int $documentId, int $sendMailLogItemId): void
    {
        ProcessInvoiceJob::dispatch($documentId, $sendMailLogItemId);
    }

    public function handle(): int
    {
        $lock = Cache::lock(static::LOCK_KEY, 3600);

        if (! $lock->get()) {
            Log::info(static::LOCK_KEY.': ロック取得失敗のためスキップします');

            return self::SUCCESS;
        }

        $startedAt = now();
        $model = $this->modelClass();
        $limit = (int) $this->option('limit');
        $stuckTimeout = (int) $this->option('stuck-timeout');

        $log = SendMailLog::create([
            'batch_key' => static::BATCH_KEY,
            'batch_name' => static::BATCH_NAME,
            'started_at' => $startedAt,
        ]);

        try {
            // stuck 差し戻し（NFR-R-04）
            $resetCount = 0;
            $stuckThreshold = now()->subMinutes($stuckTimeout);
            $model::where('status', $model::STATUS_PROCESSING)
                ->where('updated_at', '<=', $stuckThreshold)
                ->get()
                ->each(function ($document) use (&$resetCount) {
                    $document->update([
                        'status' => $document::STATUS_PENDING,
                        'retry_count' => $document->retry_count + 1,
                    ]);
                    $resetCount++;
                });

            // --retry-failed 差し戻し（任意）
            $retryFailedCount = 0;
            if ($this->option('retry-failed')) {
                $model::where('status', $model::STATUS_FAILED)
                    ->get()
                    ->each(function ($document) use (&$retryFailedCount) {
                        $document->update([
                            'status' => $document::STATUS_PENDING,
                            'retry_count' => $document->retry_count + 1,
                        ]);
                        $retryFailedCount++;
                    });
            }

            // pending を created_at 昇順で limit 件取得（NFR-P-02/03）
            $pendingIds = $model::where('status', $model::STATUS_PENDING)
                ->orderBy('created_at')
                ->limit($limit)
                ->pluck('id');

            $dispatchedCount = 0;

            foreach ($pendingIds as $id) {
                // 行ロックで競合防止（NFR-P-04）
                $dispatched = DB::transaction(function () use ($id, $model, $log) {
                    $document = $model::where('id', $id)->lockForUpdate()->first();

                    if ($document === null || $document->status !== $model::STATUS_PENDING) {
                        return false;
                    }

                    $document->update(['status' => $model::STATUS_PROCESSING]);

                    $item = SendMailLogItem::create([
                        'send_mail_log_id' => $log->id,
                        'sendable_type' => static::MORPH_ALIAS,
                        'sendable_id' => $document->id,
                        'status' => SendMailLogItem::STATUS_PROCESSING,
                    ]);

                    $this->dispatchJob($document->id, $item->id);

                    return true;
                });

                if ($dispatched) {
                    $dispatchedCount++;
                }
            }

            $log->update([
                'completed_at' => now(),
                'dispatched_count' => $dispatchedCount,
                'reset_count' => $resetCount,
                'retry_failed_count' => $retryFailedCount,
                'execution_seconds' => now()->diffInMilliseconds($startedAt) / 1000,
            ]);

            // 完了通知（NFR-R-07）
            $notificationEmails = SystemSetting::adminNotificationEmails();
            if ($notificationEmails !== []) {
                Mail::to($notificationEmails)->send(new BatchSummaryMail($log->fresh()));
            } else {
                Log::warning(static::BATCH_KEY.': admin_notification_emails 未設定のため通知を送信しません');
            }
        } catch (Throwable $e) {
            $log->update([
                'failed_at' => now(),
                'error_message' => $e->getMessage(),
                'execution_seconds' => now()->diffInMilliseconds($startedAt) / 1000,
            ]);
            Log::error(static::BATCH_KEY.': 例外発生', ['message' => $e->getMessage()]);
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
