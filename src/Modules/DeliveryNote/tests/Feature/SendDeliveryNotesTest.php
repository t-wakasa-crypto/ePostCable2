<?php

/**
 * SendDeliveryNotes コマンドを検証するテスト（T073 / 詳細設計 §1.1.3 / FR-03）。
 *
 * SendInvoices と同一仕様（ロックキー batch:send-delivery-notes / ProcessDeliveryNoteJob）。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\DeliveryNote\Jobs\ProcessDeliveryNoteJob;
use Modules\DeliveryNote\Models\DeliveryNote;
use Modules\SendMailLog\Models\SendMailLog;
use Modules\SendMailLog\Models\SendMailLogItem;

uses(RefreshDatabase::class);

it('pending を processing 更新し ProcessDeliveryNoteJob を投入する', function () {
    Queue::fake();
    DeliveryNote::factory()->pending()->count(2)->create();

    $this->artisan('batch:send-delivery-notes')->assertSuccessful();

    Queue::assertPushed(ProcessDeliveryNoteJob::class, 2);
    expect(DeliveryNote::where('status', 'processing')->count())->toBe(2);

    $log = SendMailLog::where('batch_key', 'send-delivery-notes')->first();
    expect($log->dispatched_count)->toBe(2);
    // 明細は morphMap 論理名 delivery_note で作成される
    expect(SendMailLogItem::where('sendable_type', 'delivery_note')->count())->toBe(2);
});
