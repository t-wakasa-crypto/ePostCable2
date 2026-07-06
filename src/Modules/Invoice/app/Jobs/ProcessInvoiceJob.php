<?php

namespace Modules\Invoice\Jobs;

use App\Exceptions\PermanentJobFailureException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Modules\Invoice\Mail\InvoiceMail;
use Modules\Invoice\Models\Invoice;
use Modules\SendMailLog\Models\SendMailLogItem;
use Modules\Shared\Services\PdfService;
use Modules\SystemSetting\Models\SystemSetting;
use Throwable;

/**
 * 請求書 PDF 生成・メール送信ジョブ（詳細設計 §1.2 / §3.3 / FR-05 / NFR-R-02/05/06 / NFR-M-03/04）。
 *
 * コンストラクタで system_settings を動的取得し（設定変更をワーカー再起動なしに反映・NFR-M-04）、
 * PDF 生成 → Storage 保存 → 送付先検証 → InvoiceMail 送信を行う。
 * 送付先0件・無効アドレスは PermanentJobFailureException（failed_permanent）で恒久失敗扱い。
 */
class ProcessInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** 自動リトライ回数（max_retries・NFR-R-05） */
    public int $tries;

    /** 例外による最大失敗許容回数（max_retries・NFR-R-05） */
    public int $maxExceptions;

    /** リトライ間隔秒（retry_backoff・固定値・NFR-R-05） */
    public int $backoff;

    /** ジョブタイムアウト秒（pdf_timeout・NFR-R-05 / FR-15） */
    public int $timeout;

    /**
     * @param  int  $invoiceId  対象請求書 ID
     * @param  int  $sendMailLogItemId  送信明細ログ ID
     */
    public function __construct(
        public int $invoiceId,
        public int $sendMailLogItemId,
    ) {
        // system_settings から動的取得（未取得時フォールバックはシーダー値と統一・OQ-01）
        $this->maxExceptions = (int) SystemSetting::get('max_retries', 3);
        // $tries は「総試行回数」。Laravel は 0/null を「無制限」と解釈するため、
        // max_retries=0（＝リトライなし・1回のみ試行）を明示的に 1 として下限を保証する（BR-06）。
        $this->tries = max(1, $this->maxExceptions);
        $this->backoff = (int) SystemSetting::get('retry_backoff', 30);
        $this->timeout = (int) SystemSetting::get('pdf_timeout', 60);
    }

    /**
     * 重複実行防止（NFR-R-02 / OQ-04）。
     *
     * releaseAfter はロック取得失敗時の再試行遅延であり多重実行防止とは無関係。
     * expireAfter は pdf_timeout の最大値（300秒）以上を明示し、処理完了前のロック失効を防ぐ。
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->invoiceId))->releaseAfter(10)->expireAfter(300),
        ];
    }

    public function handle(PdfService $pdfService): void
    {
        $invoice = Invoice::find($this->invoiceId);

        // 対象なし → ログ出力して終了（NFR-M-02）
        if ($invoice === null) {
            Log::info('ProcessInvoiceJob: 対象請求書が存在しません', ['invoice_id' => $this->invoiceId]);

            return;
        }

        // processing 以外はスキップ（status ガード・BR-01）
        if ($invoice->status !== Invoice::STATUS_PROCESSING) {
            return;
        }

        // PDF 生成（空出力なら PdfService が RuntimeException → failed 分岐・FR-15）
        $pdf = $pdfService->generate('invoice::pdf.invoice', ['invoice' => $invoice->load('items')]);

        // Storage 保存（invoices/{年}/{月}/invoice_{番号}.pdf・年月は issue_date 基準・NFR-P-05）
        $basis = $invoice->issue_date ?? now();
        $path = 'invoices/'.$basis->format('Y').'/'.$basis->format('m').'/invoice_'.$invoice->invoice_number.'.pdf';
        Storage::put($path, $pdf);

        // 送付先検証（BR-01 / BR-04）
        $emails = $invoice->recipientEmails();

        if ($emails === []) {
            throw new PermanentJobFailureException('送付先メールアドレスが0件です。');
        }

        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new PermanentJobFailureException('無効なメールアドレスが含まれています: '.$email);
            }
        }

        // 送信（PDF 添付・共通 BCC・FR-14）
        Mail::to($emails)->send(new InvoiceMail($invoice, $pdf));

        // 成功時の状態更新（NFR-M-02）
        $now = now();
        $invoice->update(['status' => Invoice::STATUS_SENT, 'sent_at' => $now]);
        SendMailLogItem::where('id', $this->sendMailLogItemId)->update([
            'status' => SendMailLogItem::STATUS_SENT,
            'sent_at' => $now,
        ]);
    }

    /**
     * 失敗時の状態遷移（NFR-R-06 / NFR-M-03 / BR-01）。
     */
    public function failed(Throwable $e): void
    {
        $isPermanent = $e instanceof PermanentJobFailureException;
        $status = $isPermanent ? Invoice::STATUS_FAILED_PERMANENT : Invoice::STATUS_FAILED;

        Invoice::where('id', $this->invoiceId)->update(['status' => $status]);

        SendMailLogItem::where('id', $this->sendMailLogItemId)->update([
            'status' => $status,
            'error_message' => mb_substr($e->getMessage(), 0, 1000),
        ]);
    }
}
