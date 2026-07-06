<?php

/**
 * ダッシュボードを検証するテスト（T100 / FR-07 / BR-03 / FR-10）。
 *
 * 認証・書類 status 別件数・送信ログ集計（manual-resend 除外・failed 除外）・
 * 出荷取得直近を確認する。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Invoice\Models\Invoice;
use Modules\SendMailLog\Models\SendMailLog;
use Modules\ShipmentFetch\Models\ShipmentFetchLog;
use Modules\User\Models\User;

uses(RefreshDatabase::class);

it('未認証は / で /login へリダイレクトされる', function () {
    $this->get('/')->assertRedirect('/login');
});

it('認証済みでダッシュボードが表示され書類件数が集計される', function () {
    Invoice::factory()->pending()->count(2)->create();
    Invoice::factory()->sent()->create();

    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertSee('請求書ステータス別件数')
        ->assertSee('pending: 2', false)
        ->assertSee('sent: 1', false);
});

it('送信ログ集計は manual-resend と failed を除外する', function () {
    // completed（集計対象）
    SendMailLog::factory()->create([
        'batch_key' => SendMailLog::BATCH_SEND_INVOICES,
        'completed_at' => now(),
    ]);
    // failed（除外）
    SendMailLog::factory()->create([
        'batch_key' => SendMailLog::BATCH_SEND_INVOICES,
        'failed_at' => now(),
    ]);
    // manual-resend（除外）
    SendMailLog::factory()->create([
        'batch_key' => SendMailLog::BATCH_MANUAL_RESEND,
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        // completed 1件のみ集計される
        ->assertSee('completed: 1', false);
});

it('出荷取得バッチの直近実行が表示される', function () {
    ShipmentFetchLog::factory()->create([
        'status' => ShipmentFetchLog::STATUS_COMPLETED,
        'fetched_count' => 7,
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertSee('出荷取得バッチ直近実行');
});
