<?php

/**
 * 請求書一覧・詳細の閲覧を検証するテスト（T080 / FR-08 / NFR-P-01 / NFR-S-06）。
 *
 * 認証・一覧表示・フィルタ allowlist・詳細表示・20件/ページを確認する。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Invoice\Models\Invoice;
use Modules\SendMailLog\Models\SendMailLog;
use Modules\SendMailLog\Models\SendMailLogItem;
use Modules\User\Models\User;

uses(RefreshDatabase::class);

it('未認証は一覧で /login へリダイレクトされる', function () {
    $this->get('/invoices')->assertRedirect('/login');
});

it('認証済みで一覧が表示される', function () {
    Invoice::factory()->create(['invoice_number' => 'INV-VIEW-1']);

    $this->actingAs(User::factory()->create())
        ->get('/invoices')
        ->assertOk()
        ->assertSee('INV-VIEW-1');
});

it('status フィルタ（allowlist）で絞り込める', function () {
    Invoice::factory()->pending()->create(['invoice_number' => 'INV-PENDING']);
    Invoice::factory()->failed()->create(['invoice_number' => 'INV-FAILED']);

    $this->actingAs(User::factory()->create())
        ->get('/invoices?status=failed')
        ->assertOk()
        ->assertSee('INV-FAILED')
        ->assertDontSee('INV-PENDING');
});

it('allowlist 外の status は無視され全件表示される', function () {
    Invoice::factory()->pending()->create(['invoice_number' => 'INV-A']);

    $this->actingAs(User::factory()->create())
        ->get('/invoices?status=invalid')
        ->assertOk()
        ->assertSee('INV-A');
});

it('一覧は20件/ページでページネーションする', function () {
    Invoice::factory()->count(25)->create();

    $response = $this->actingAs(User::factory()->create())->get('/invoices');

    // 2ページ目リンクが存在する（20件/ページ）
    $response->assertOk()->assertSee('page=2', false);
});

it('詳細で明細と送信履歴が表示される', function () {
    $invoice = Invoice::factory()->create();
    $invoice->items()->create(['item_name' => '詳細品目', 'quantity' => 1, 'unit_price' => 100, 'amount' => 100]);
    $log = SendMailLog::factory()->create();
    SendMailLogItem::factory()->sent()->create([
        'send_mail_log_id' => $log->id,
        'sendable_type' => Invoice::MORPH_ALIAS,
        'sendable_id' => $invoice->id,
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/invoices/'.$invoice->id)
        ->assertOk()
        ->assertSee('詳細品目')
        ->assertSee('sent');
});
