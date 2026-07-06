<?php

/**
 * テストメール（TestMail）を検証するテスト（T105 / FR-14）。
 *
 * 件名・BCC 付与・送信を確認する。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\SystemSetting\Mail\TestMail;
use Modules\SystemSetting\Models\SystemSetting;

uses(RefreshDatabase::class);

it('件名は【テスト】メール送信テストである', function () {
    $envelope = (new TestMail)->envelope();

    expect($envelope->subject)->toBe('【テスト】メール送信テスト');
});

it('共通 BCC（mail_bcc_address）が envelope に付与される', function () {
    SystemSetting::create([
        'key' => 'mail_bcc_address',
        'value' => 'bcc@example.com',
        'type' => 'emails',
    ]);

    $envelope = (new TestMail)->envelope();

    expect(collect($envelope->bcc)->pluck('address')->all())->toContain('bcc@example.com');
});

it('テストメールを送信できる', function () {
    Mail::fake();

    Mail::to('to@example.com')->send(new TestMail);

    Mail::assertSent(TestMail::class);
});
