<?php

namespace Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Modules\DeliveryNote\Models\DeliveryNote;
use Modules\Invoice\Models\Invoice;
use Modules\SendMailLog\Models\SendMailLog;
use Modules\ShipmentFetch\Models\ShipmentFetchLog;

/**
 * ダッシュボード（詳細設計 §1.4.1 / FR-07 / BR-03）。
 *
 * 書類 status 別件数、送信ログの直近/集計（manual-resend 除外・failed_at 優先・
 * 失敗ログは集計から除外）、出荷取得の直近、各送信バッチ最終実行を表示する。
 */
class DashboardController extends Controller
{
    /**
     * ダッシュボード表示。
     */
    public function index(): View
    {
        return view('dashboard::index', [
            'invoiceSummary' => $this->statusSummary(Invoice::class),
            'deliveryNoteSummary' => $this->statusSummary(DeliveryNote::class),
            'sendMailLogSummary' => $this->sendMailLogSummary(),
            'recentSendMailLogs' => SendMailLog::query()
                ->excludeManualResend()
                ->orderByDesc('started_at')
                ->limit(5)
                ->get(),
            'recentFetchLogs' => ShipmentFetchLog::query()
                ->orderByDesc('started_at')
                ->limit(5)
                ->get(),
            'lastInvoiceBatch' => $this->lastBatch(SendMailLog::BATCH_SEND_INVOICES),
            'lastDeliveryNoteBatch' => $this->lastBatch(SendMailLog::BATCH_SEND_DELIVERY_NOTES),
        ]);
    }

    /**
     * 書類のステータス別件数を集計する。
     *
     * @param  class-string  $model
     * @return array<string, int>
     */
    private function statusSummary(string $model): array
    {
        return $model::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    /**
     * 送信ログの表示状態別件数（manual-resend 除外・failed_at 優先・BR-03）。
     *
     * 失敗ログはダッシュボードのサマリーから除外する（FR-10）。
     *
     * @return array<string, int>
     */
    private function sendMailLogSummary(): array
    {
        $logs = SendMailLog::query()->excludeManualResend()->get();

        $completed = 0;
        $running = 0;

        foreach ($logs as $log) {
            $display = $log->displayStatus();
            // 失敗ログはダッシュボードから除外（FR-10）
            if ($display === SendMailLog::DISPLAY_FAILED) {
                continue;
            }
            if ($display === SendMailLog::DISPLAY_COMPLETED) {
                $completed++;
            } else {
                $running++;
            }
        }

        return [
            SendMailLog::DISPLAY_COMPLETED => $completed,
            SendMailLog::DISPLAY_RUNNING => $running,
        ];
    }

    /**
     * 指定バッチ種別の最終実行ログを返す。
     */
    private function lastBatch(string $batchKey): ?SendMailLog
    {
        return SendMailLog::query()
            ->where('batch_key', $batchKey)
            ->orderByDesc('started_at')
            ->first();
    }
}
