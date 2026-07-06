<?php

/**
 * FetchShipmentData コマンドを検証するテスト
 * （T050 / T051 / 詳細設計 §1.1.1 / FR-01 / BR-02 / BR-05 / NFR-R-01 / DB-Q-02）。
 *
 * 重複スキップ・税額算出・空白 email スキップ・ログ記録・ロック失敗スキップ・
 * 固定明細変換・金額整合性検証（エラーマーキング）を確認する。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\DeliveryNote\Models\DeliveryNote;
use Modules\Invoice\Models\Invoice;
use Modules\ShipmentFetch\Models\ShipmentFetchLog;
use Modules\ShipmentFetch\Services\ShipmentFetchService;

uses(RefreshDatabase::class);

/** ShipmentFetchService を差し替えて固定の出荷データを返す */
function fakeShipments(array $shipments): void
{
    $mock = Mockery::mock(ShipmentFetchService::class);
    $mock->shouldReceive('fetch')->andReturn($shipments);
    app()->instance(ShipmentFetchService::class, $mock);
}

/** 整合した1件分の出荷データ */
function sampleShipment(array $overrides = []): array
{
    return array_merge([
        'delivery_number' => 'DN-'.uniqid(),
        'invoice_number' => 'INV-'.uniqid(),
        'customer_name' => 'テスト商事',
        'customer_email' => 'to@example.com',
        'amount' => 1000,
        'delivery_date' => '2026-05-01',
        'issue_date' => '2026-05-10',
        'items' => [
            ['item_name' => '商品A', 'quantity' => 2, 'unit_price' => 300, 'amount' => 600],
            ['item_name' => '商品B', 'quantity' => 1, 'unit_price' => 400, 'amount' => 400],
        ],
    ], $overrides);
}

it('納品書・請求書を pending で作成し税額を算出する', function () {
    fakeShipments([sampleShipment(['amount' => 1000])]);

    $this->artisan('batch:fetch-shipment-data')->assertSuccessful();

    $invoice = Invoice::first();
    expect($invoice->status)->toBe('pending');
    expect((int) $invoice->tax)->toBe(10);
    // tax_amount = round(1000 * 10 / 100) = 100
    expect((int) $invoice->tax_amount)->toBe(100);
    expect(DeliveryNote::count())->toBe(1);
    expect($invoice->items()->count())->toBe(2);
});

it('既存番号は重複スキップし skipped_count に計上する', function () {
    Invoice::factory()->create(['invoice_number' => 'INV-DUP']);
    DeliveryNote::factory()->create(['delivery_number' => 'DN-DUP']);
    fakeShipments([sampleShipment(['invoice_number' => 'INV-DUP', 'delivery_number' => 'DN-DUP'])]);

    $this->artisan('batch:fetch-shipment-data')->assertSuccessful();

    $log = ShipmentFetchLog::first();
    expect($log->skipped_count)->toBe(2);
    expect($log->created_invoice_count)->toBe(0);
});

it('空白 customer_email の出荷データはスキップする', function () {
    fakeShipments([sampleShipment(['customer_email' => '   '])]);

    $this->artisan('batch:fetch-shipment-data')->assertSuccessful();

    expect(Invoice::count())->toBe(0);
    expect(DeliveryNote::count())->toBe(0);
});

it('正常終了時に completed ログとカウントを記録する', function () {
    fakeShipments([sampleShipment(), sampleShipment()]);

    $this->artisan('batch:fetch-shipment-data')->assertSuccessful();

    $log = ShipmentFetchLog::first();
    expect($log->status)->toBe('completed');
    expect($log->fetched_count)->toBe(2);
    expect($log->created_invoice_count)->toBe(2);
    expect($log->created_delivery_note_count)->toBe(2);
    expect($log->completed_at)->not->toBeNull();
});

it('ロック取得失敗時は処理をスキップしログを作らない', function () {
    // 事前にロックを保持しておく
    Cache::lock('batch:fetch-shipment-data', 10)->get();
    fakeShipments([sampleShipment()]);

    $this->artisan('batch:fetch-shipment-data')->assertSuccessful();

    expect(ShipmentFetchLog::count())->toBe(0);
    expect(Invoice::count())->toBe(0);
});

it('固定明細を通常明細へ変換して登録する', function () {
    fakeShipments([sampleShipment([
        'amount' => 1500,
        'items' => [['item_name' => '商品A', 'quantity' => 1, 'unit_price' => 1000, 'amount' => 1000]],
        'fixed_items' => [['item_name' => '送料', 'amount' => 500]],
    ])]);

    $this->artisan('batch:fetch-shipment-data')->assertSuccessful();

    $invoice = Invoice::first();
    // 通常明細1 + 固定明細1 = 2件、整合するため pending
    expect($invoice->items()->count())->toBe(2);
    expect($invoice->status)->toBe('pending');
    expect($invoice->items()->where('item_name', '送料')->first()->unit_price)->not->toBeNull();
});

it('明細合計と amount が不一致なら failed_permanent でエラーマーキングする', function () {
    fakeShipments([sampleShipment([
        'amount' => 9999, // 明細合計(1000) と不一致
        'items' => [['item_name' => '商品A', 'quantity' => 1, 'unit_price' => 1000, 'amount' => 1000]],
    ])]);

    $this->artisan('batch:fetch-shipment-data')->assertSuccessful();

    expect(Invoice::first()->status)->toBe('failed_permanent');
});

it('明細内訳が空（amount のみ）の場合は整合性検証をスキップし pending で作成する', function () {
    // 基幹 API が内訳を返さず amount のみのケース。itemsTotal=0 ≠ amount による
    // false-positive で誤って failed_permanent にならないこと（DB-Q-02 / FR-01）。
    fakeShipments([sampleShipment([
        'amount' => 5000,
        'items' => [],
    ])]);

    $this->artisan('batch:fetch-shipment-data')->assertSuccessful();

    $invoice = Invoice::first();
    expect($invoice->status)->toBe('pending');
    expect($invoice->items()->count())->toBe(0);
});
