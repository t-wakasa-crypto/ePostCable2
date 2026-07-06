<?php

/**
 * ProcessDeliveryNoteJob の PDF 生成・送信・状態遷移を検証するテスト
 * （T061 / 詳細設計 §1.2.5 / FR-06）。
 *
 * ProcessInvoiceJob 相当に加え、保存先が delivery_date 基準であることを確認する。
 */

use App\Exceptions\PermanentJobFailureException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Modules\DeliveryNote\Jobs\ProcessDeliveryNoteJob;
use Modules\DeliveryNote\Mail\DeliveryNoteMail;
use Modules\DeliveryNote\Models\DeliveryNote;
use Modules\SendMailLog\Models\SendMailLog;
use Modules\SendMailLog\Models\SendMailLogItem;
use Modules\Shared\Services\PdfService;
use Modules\SystemSetting\Models\SystemSetting;

uses(RefreshDatabase::class);

it('max_retries=0 のとき tries は 1（無制限にならず1回のみ試行）になる', function () {
    // Laravel は $tries=0/null を「無制限」と解釈するため、下限 1 を保証していること（BR-06）。
    SystemSetting::create(['key' => 'max_retries', 'value' => '0', 'type' => 'integer', 'min_value' => 0, 'max_value' => 10]);

    $job = new ProcessDeliveryNoteJob(1, 1);

    expect($job->maxExceptions)->toBe(0);
    expect($job->tries)->toBe(1);
});

function makeProcessingDeliveryNote(array $overrides = []): array
{
    $note = DeliveryNote::factory()->processing()->create(array_merge([
        'delivery_date' => '2026-05-20',
        'customer_email' => 'to@example.com',
    ], $overrides));
    $note->items()->create(['item_name' => '品目', 'quantity' => 1, 'unit_price' => 1000, 'amount' => 1000]);
    $log = SendMailLog::factory()->create(['batch_key' => 'send-delivery-notes', 'batch_name' => '納品書']);
    $item = SendMailLogItem::factory()->create([
        'send_mail_log_id' => $log->id,
        'sendable_type' => DeliveryNote::MORPH_ALIAS,
        'sendable_id' => $note->id,
        'status' => SendMailLogItem::STATUS_PROCESSING,
    ]);

    return [$note, $item];
}

it('正常時に delivery_date 基準パスへ保存し sent へ更新する', function () {
    Mail::fake();
    Storage::fake();
    [$note, $item] = makeProcessingDeliveryNote();

    (new ProcessDeliveryNoteJob($note->id, $item->id))->handle(app(PdfService::class));

    Storage::assertExists('delivery-notes/2026/05/delivery_'.$note->delivery_number.'.pdf');
    Mail::assertSent(DeliveryNoteMail::class);
    expect($note->fresh()->status)->toBe('sent');
    expect($item->fresh()->status)->toBe('sent');
});

it('無効アドレスは PermanentJobFailureException をスローする', function () {
    Storage::fake();
    [$note, $item] = makeProcessingDeliveryNote(['customer_email' => 'invalid']);

    expect(fn () => (new ProcessDeliveryNoteJob($note->id, $item->id))->handle(app(PdfService::class)))
        ->toThrow(PermanentJobFailureException::class);
});

it('failed() は恒久失敗を failed_permanent に更新する', function () {
    [$note, $item] = makeProcessingDeliveryNote();

    (new ProcessDeliveryNoteJob($note->id, $item->id))->failed(new PermanentJobFailureException('恒久失敗'));

    expect($note->fresh()->status)->toBe('failed_permanent');
    expect($item->fresh()->status)->toBe('failed_permanent');
});
