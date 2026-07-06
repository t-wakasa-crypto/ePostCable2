<?php

/**
 * メール送信履歴の一覧・詳細を検証するテスト（T101 / FR-10 / BR-07 / OQ-08 / NFR-P-01）。
 *
 * filter allowlist・失敗ログ/手動再送の除外・ページ件数・complete ルート廃止を確認する。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Invoice\Models\Invoice;
use Modules\SendMailLog\Models\SendMailLog;
use Modules\SendMailLog\Models\SendMailLogItem;
use Modules\User\Models\User;

uses(RefreshDatabase::class);

it('未認証は一覧で /login へリダイレクトされる', function () {
    $this->get('/send-mail-logs')->assertRedirect('/login');
});

it('通常一覧は失敗ログと手動再送を除外する', function () {
    SendMailLog::factory()->completed()->create(['batch_name' => 'COMPLETED-LOG']);
    SendMailLog::factory()->failed()->create(['batch_name' => 'FAILED-LOG']);
    SendMailLog::factory()->manualResend()->create(['batch_name' => 'MANUAL-LOG']);

    $this->actingAs(User::factory()->create())
        ->get('/send-mail-logs')
        ->assertOk()
        ->assertSee('COMPLETED-LOG')
        ->assertDontSee('FAILED-LOG')
        ->assertDontSee('MANUAL-LOG');
});

it('filter=failed は失敗ログのみを別画面として表示する', function () {
    SendMailLog::factory()->completed()->create(['batch_name' => 'COMPLETED-LOG']);
    SendMailLog::factory()->failed()->create(['batch_name' => 'FAILED-LOG']);

    $this->actingAs(User::factory()->create())
        ->get('/send-mail-logs?filter=failed')
        ->assertOk()
        ->assertSee('FAILED-LOG')
        ->assertDontSee('COMPLETED-LOG');
});

it('allowlist 外の filter は無視され通常一覧になる', function () {
    SendMailLog::factory()->completed()->create(['batch_name' => 'COMPLETED-LOG']);

    $this->actingAs(User::factory()->create())
        ->get('/send-mail-logs?filter=__invalid__')
        ->assertOk()
        ->assertSee('COMPLETED-LOG');
});

it('一覧は20件/ページでページネーションする', function () {
    SendMailLog::factory()->completed()->count(25)->create();

    $this->actingAs(User::factory()->create())
        ->get('/send-mail-logs')
        ->assertOk()
        ->assertSee('page=2', false);
});

it('詳細は明細を50件/ページで表示する', function () {
    $log = SendMailLog::factory()->completed()->create();
    SendMailLogItem::factory()->count(55)->create([
        'send_mail_log_id' => $log->id,
        'sendable_type' => Invoice::MORPH_ALIAS,
        'sendable_id' => Invoice::factory(),
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/send-mail-logs/'.$log->id)
        ->assertOk()
        ->assertSee('page=2', false);
});

it('complete ルートは廃止され存在しない', function () {
    $log = SendMailLog::factory()->completed()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post('/send-mail-logs/'.$log->id.'/complete')
        ->assertNotFound();
});
