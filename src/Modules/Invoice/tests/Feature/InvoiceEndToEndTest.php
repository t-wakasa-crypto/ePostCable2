<?php

/**
 * MVP 縦串 E2E テスト（T080 / FR-01 / FR-02 / FR-05 / FR-08）。
 *
 * 出荷取得バッチ → 送信バッチ → キュージョブ（sync 実行）→ sent 反映を、
 * 画面（一覧・詳細）から確認できることを検証する。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Modules\Invoice\Mail\InvoiceMail;
use Modules\Invoice\Models\Invoice;
use Modules\ShipmentFetch\Services\ShipmentFetchService;
use Modules\User\Models\User;

uses(RefreshDatabase::class);

it('出荷取得→送信→sent 反映が画面から確認できる', function () {
    Mail::fake();
    Storage::fake();

    // 基幹 API の出荷データをモック
    $mock = Mockery::mock(ShipmentFetchService::class);
    $mock->shouldReceive('fetch')->andReturn([[
        'delivery_number' => 'DN-E2E',
        'invoice_number' => 'INV-E2E',
        'customer_name' => 'E2E 商事',
        'customer_email' => 'to@example.com',
        'amount' => 1000,
        'issue_date' => '2026-06-01',
        'delivery_date' => '2026-06-01',
        'items' => [['item_name' => '商品', 'quantity' => 1, 'unit_price' => 1000, 'amount' => 1000]],
    ]]);
    app()->instance(ShipmentFetchService::class, $mock);

    // 1) 出荷取得バッチ → pending の請求書が作成される
    $this->artisan('batch:fetch-shipment-data')->assertSuccessful();
    expect(Invoice::where('invoice_number', 'INV-E2E')->where('status', 'pending')->exists())->toBeTrue();

    // 2) 送信バッチ（QUEUE=sync のためジョブが同期実行され送信・sent 更新される）
    $this->artisan('batch:send-invoices')->assertSuccessful();

    Mail::assertSent(InvoiceMail::class);
    $invoice = Invoice::where('invoice_number', 'INV-E2E')->first();
    expect($invoice->status)->toBe('sent');

    // 3) 画面（詳細）から sent が確認できる
    $this->actingAs(User::factory()->create())
        ->get('/invoices/'.$invoice->id)
        ->assertOk()
        ->assertSee('sent');
});
