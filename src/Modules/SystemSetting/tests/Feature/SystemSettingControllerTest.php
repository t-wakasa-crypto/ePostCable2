<?php

/**
 * システム設定画面・テストメール送信を検証するテスト（T104 / FR-13 / BR-06 / NFR-M-01/04）。
 *
 * admin 限定・integer 範囲検証・emails 検証（改行区切り保存）・テストメール送信を確認する。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\SystemSetting\Mail\TestMail;
use Modules\SystemSetting\Models\SystemSetting;
use Modules\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 検証用の設定を投入
    SystemSetting::create(['key' => 'pdf_timeout', 'value' => '60', 'type' => 'integer', 'min_value' => 10, 'max_value' => 300]);
    SystemSetting::create(['key' => 'mail_bcc_address', 'value' => null, 'type' => 'emails']);
});

it('非 admin は設定画面で 403 になる', function () {
    $this->actingAs(User::factory()->create())
        ->get('/system-settings')
        ->assertForbidden();
});

it('admin は設定画面を表示できる', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/system-settings')
        ->assertOk()
        ->assertSee('pdf_timeout');
});

it('integer は範囲内の値を更新できる', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post('/system-settings', ['settings' => ['pdf_timeout' => '120']])
        ->assertRedirect(route('system-settings.index'));

    expect(SystemSetting::get('pdf_timeout'))->toBe(120);
});

it('integer は範囲外の値を拒否する', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post('/system-settings', ['settings' => ['pdf_timeout' => '999']])
        ->assertSessionHasErrors('settings.pdf_timeout');

    // 値は変更されない
    expect(SystemSetting::get('pdf_timeout'))->toBe(60);
});

it('emails は複数行を検証し改行区切りで保存する', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post('/system-settings', ['settings' => ['mail_bcc_address' => "a@example.com\nb@example.com"]])
        ->assertRedirect();

    expect(SystemSetting::mailBccAddresses())->toBe(['a@example.com', 'b@example.com']);
});

it('emails は無効なアドレスを拒否する', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post('/system-settings', ['settings' => ['mail_bcc_address' => "a@example.com\ninvalid-email"]])
        ->assertSessionHasErrors('settings.mail_bcc_address');
});

it('admin はテストメールを送信できる', function () {
    Mail::fake();

    $this->actingAs(User::factory()->admin()->create())
        ->post('/system-settings/test-mail', ['email' => 'to@example.com'])
        ->assertRedirect();

    Mail::assertSent(TestMail::class);
});

it('非 admin はテストメール送信で 403 になる', function () {
    $this->actingAs(User::factory()->create())
        ->post('/system-settings/test-mail', ['email' => 'to@example.com'])
        ->assertForbidden();
});
