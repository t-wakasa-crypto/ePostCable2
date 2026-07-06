<?php

namespace Modules\DeliveryNote\Jobs;

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
use Modules\DeliveryNote\Mail\DeliveryNoteMail;
use Modules\DeliveryNote\Models\DeliveryNote;
use Modules\SendMailLog\Models\SendMailLogItem;
use Modules\Shared\Services\PdfService;
use Modules\SystemSetting\Models\SystemSetting;
use Throwable;

/**
 * 納品書 PDF 生成・メール送信ジョブ（詳細設計 §1.2.5 / FR-06）。
 *
 * ProcessInvoiceJob と同一フロー。保存先は delivery-notes/{年}/{月}（年月は delivery_date 基準・Q-11）、
 * メールは DeliveryNoteMail。
 */
class ProcessDeliveryNoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $maxExceptions;

    public int $backoff;

    public int $timeout;

    public function __construct(
        public int $deliveryNoteId,
        public int $sendMailLogItemId,
    ) {
        $this->maxExceptions = (int) SystemSetting::get('max_retries', 3);
        // $tries は「総試行回数」。Laravel は 0/null を「無制限」と解釈するため、
        // max_retries=0（＝リトライなし・1回のみ試行）を明示的に 1 として下限を保証する（BR-06）。
        $this->tries = max(1, $this->maxExceptions);
        $this->backoff = (int) SystemSetting::get('retry_backoff', 30);
        $this->timeout = (int) SystemSetting::get('pdf_timeout', 60);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->deliveryNoteId))->releaseAfter(10)->expireAfter(300),
        ];
    }

    public function handle(PdfService $pdfService): void
    {
        $note = DeliveryNote::find($this->deliveryNoteId);

        if ($note === null) {
            Log::info('ProcessDeliveryNoteJob: 対象納品書が存在しません', ['delivery_note_id' => $this->deliveryNoteId]);

            return;
        }

        if ($note->status !== DeliveryNote::STATUS_PROCESSING) {
            return;
        }

        $pdf = $pdfService->generate('deliverynote::pdf.delivery_note', ['deliveryNote' => $note->load('items')]);

        // Storage 保存（delivery-notes/{年}/{月}・年月は delivery_date 基準・Q-11 / NFR-P-05）
        $basis = $note->delivery_date ?? now();
        $path = 'delivery-notes/'.$basis->format('Y').'/'.$basis->format('m').'/delivery_'.$note->delivery_number.'.pdf';
        Storage::put($path, $pdf);

        $emails = $note->recipientEmails();

        if ($emails === []) {
            throw new PermanentJobFailureException('送付先メールアドレスが0件です。');
        }

        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new PermanentJobFailureException('無効なメールアドレスが含まれています: '.$email);
            }
        }

        Mail::to($emails)->send(new DeliveryNoteMail($note, $pdf));

        $now = now();
        $note->update(['status' => DeliveryNote::STATUS_SENT, 'sent_at' => $now]);
        SendMailLogItem::where('id', $this->sendMailLogItemId)->update([
            'status' => SendMailLogItem::STATUS_SENT,
            'sent_at' => $now,
        ]);
    }

    public function failed(Throwable $e): void
    {
        $isPermanent = $e instanceof PermanentJobFailureException;
        $status = $isPermanent ? DeliveryNote::STATUS_FAILED_PERMANENT : DeliveryNote::STATUS_FAILED;

        DeliveryNote::where('id', $this->deliveryNoteId)->update(['status' => $status]);

        SendMailLogItem::where('id', $this->sendMailLogItemId)->update([
            'status' => $status,
            'error_message' => mb_substr($e->getMessage(), 0, 1000),
        ]);
    }
}
