<?php

/**
 * 納品書の操作系（手動再送・メール編集・一括再キュー・バッチ手動起動・PDF/CSV）を
 * 検証するテスト（T097 / FR-09 / FR-15 / BR-04 / BR-07 / NFR-P-05 / OQ-10）。
 *
 * InvoiceController と同一仕様のため、代表的な受入条件を納品書側で確認する。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\DeliveryNote\Jobs\ProcessDeliveryNoteJob;
use Modules\DeliveryNote\Models\DeliveryNote;
use Modules\SendMailLog\Models\SendMailLog;
use Modules\User\Models\User;

uses(RefreshDatabase::class);

it('手動再送は manual-resend 親へ集約しジョブを投入する', function () {
    Queue::fake();
    $note = DeliveryNote::factory()->failed()->create();

    $this->actingAs(User::factory()->create())
        ->post('/delivery-notes/'.$note->id.'/resend')
        ->assertRedirect();

    expect($note->fresh()->status)->toBe(DeliveryNote::STATUS_PROCESSING);
    expect(SendMailLog::where('batch_key', SendMailLog::BATCH_MANUAL_RESEND)->first()->dispatched_count)->toBe(1);
    Queue::assertPushed(ProcessDeliveryNoteJob::class);
});

it('非 admin はメールアドレス編集で 403 になる', function () {
    $note = DeliveryNote::factory()->failed()->create();

    $this->actingAs(User::factory()->create())
        ->post('/delivery-notes/'.$note->id.'/emails', ['emails' => ['x@example.com']])
        ->assertForbidden();
});

it('admin は failed のメールアドレスを編集でき未入力は null 正規化される', function () {
    $note = DeliveryNote::factory()->failed()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post('/delivery-notes/'.$note->id.'/emails', ['emails' => ['a@example.com', '']])
        ->assertRedirect();

    $note->refresh();
    expect($note->customer_email)->toBe('a@example.com');
    expect($note->customer_email_2)->toBeNull();
});

it('admin は failed を pending へ一括更新し retry_count>=10 は対象外', function () {
    $a = DeliveryNote::factory()->failed()->create(['retry_count' => 1]);
    $b = DeliveryNote::factory()->failed()->create(['retry_count' => 10]);

    $this->actingAs(User::factory()->admin()->create())
        ->post('/delivery-notes/bulk-requeue', ['ids' => [$a->id, $b->id]])
        ->assertRedirect();

    expect($a->fresh()->status)->toBe(DeliveryNote::STATUS_PENDING);
    expect($a->fresh()->retry_count)->toBe(2);
    expect($b->fresh()->status)->toBe(DeliveryNote::STATUS_FAILED);
});

it('非 admin はバッチ手動起動で 403 になる', function () {
    $this->actingAs(User::factory()->create())
        ->post('/delivery-notes/run-batch')
        ->assertForbidden();
});

it('PDF は未保存時に即時生成し保存しない（delivery_date 基準パス）', function () {
    Storage::fake();
    $note = DeliveryNote::factory()->create(['delivery_date' => '2026-04-20']);
    $note->items()->create(['item_name' => '品目', 'quantity' => 1, 'unit_price' => 100, 'amount' => 100]);
    $path = 'delivery-notes/2026/04/delivery_'.$note->delivery_number.'.pdf';

    $this->actingAs(User::factory()->create())
        ->get('/delivery-notes/'.$note->id.'/pdf')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    Storage::assertMissing($path);
});

it('CSV は UTF-8 BOM 付きで複数送付先を / 区切りで出力する', function () {
    DeliveryNote::factory()->create([
        'delivery_number' => 'DN-CSV-1',
        'customer_email' => 'a@example.com',
        'customer_email_2' => 'b@example.com',
    ]);

    $content = $this->actingAs(User::factory()->create())
        ->get('/delivery-notes/csv')
        ->streamedContent();

    expect(substr($content, 0, 3))->toBe("\xEF\xBB\xBF");
    expect($content)->toContain('a@example.com / b@example.com');
    expect($content)->toContain('DN-CSV-1');
});
