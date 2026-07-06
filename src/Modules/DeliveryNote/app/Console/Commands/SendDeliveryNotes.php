<?php

namespace Modules\DeliveryNote\Console\Commands;

use Modules\DeliveryNote\Jobs\ProcessDeliveryNoteJob;
use Modules\DeliveryNote\Models\DeliveryNote;
use Modules\Invoice\Console\Commands\SendInvoices;
use Modules\SendMailLog\Models\SendMailLog;

/**
 * 納品書メール送信バッチ（詳細設計 §1.1.3 / FR-03）。
 *
 * SendInvoices と同一仕様。ロックキー・batch_key・対象モデル・ディスパッチ先ジョブのみ差し替える。
 */
class SendDeliveryNotes extends SendInvoices
{
    protected const LOCK_KEY = 'batch:send-delivery-notes';

    protected const BATCH_KEY = SendMailLog::BATCH_SEND_DELIVERY_NOTES;

    protected const BATCH_NAME = '納品書';

    protected const MORPH_ALIAS = DeliveryNote::MORPH_ALIAS;

    protected $signature = 'batch:send-delivery-notes {--limit=100} {--stuck-timeout=60} {--retry-failed}';

    protected $description = '送信待ちの納品書を取得しキューへ投入する';

    protected function modelClass(): string
    {
        return DeliveryNote::class;
    }

    protected function dispatchJob(int $documentId, int $sendMailLogItemId): void
    {
        ProcessDeliveryNoteJob::dispatch($documentId, $sendMailLogItemId);
    }
}
