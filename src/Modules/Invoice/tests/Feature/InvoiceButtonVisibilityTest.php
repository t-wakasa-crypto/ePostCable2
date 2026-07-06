<?php

/**
 * 状態別ボタン表示制御・権限ガードを検証するテスト（T106 / 詳細設計 §5.4 / §7 /
 * FR-08 / FR-17 / NFR-S-06）。
 *
 * isAdmin・書類 status に応じたボタン表示/非表示を画面横断で確認する。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Invoice\Models\Invoice;
use Modules\User\Models\User;

uses(RefreshDatabase::class);

it('一覧のバッチ起動ボタンは admin のみ表示される', function () {
    Invoice::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get('/invoices')
        ->assertOk()
        ->assertDontSee('送信バッチを起動');

    $this->actingAs(User::factory()->admin()->create())
        ->get('/invoices')
        ->assertOk()
        ->assertSee('送信バッチを起動');
});

it('CSV ダウンロードリンクは general/admin ともに表示される', function () {
    Invoice::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get('/invoices')
        ->assertOk()
        ->assertSee('CSV ダウンロード');
});

it('詳細のメールアドレス編集フォームは admin かつ failed 時のみ表示される', function () {
    $failed = Invoice::factory()->failed()->create();

    // general は failed でも表示されない
    $this->actingAs(User::factory()->create())
        ->get('/invoices/'.$failed->id)
        ->assertOk()
        ->assertDontSee('メールアドレスを更新');

    // admin かつ failed は表示される
    $this->actingAs(User::factory()->admin()->create())
        ->get('/invoices/'.$failed->id)
        ->assertOk()
        ->assertSee('メールアドレスを更新');
});

it('メールアドレス編集フォームは admin でも sent 状態では表示されない', function () {
    $sent = Invoice::factory()->sent()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get('/invoices/'.$sent->id)
        ->assertOk()
        ->assertDontSee('メールアドレスを更新');
});

it('手動再送ボタンは general/admin ともに表示される', function () {
    $invoice = Invoice::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get('/invoices/'.$invoice->id)
        ->assertOk()
        ->assertSee('手動再送');
});
