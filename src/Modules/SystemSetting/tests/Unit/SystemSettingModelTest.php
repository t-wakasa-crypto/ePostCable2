<?php

/**
 * SystemSetting モデルの取得・BCC 配列化を検証するテスト
 * （T031 / 詳細設計 §4.6 / FR-13 / FR-14 / BR-06）。
 *
 * get() のフォールバック・integer キャスト、mailBccAddresses の改行分割を確認する。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SystemSetting\Models\SystemSetting;

uses(RefreshDatabase::class);

it('get は未登録キーでフォールバック値を返す', function () {
    expect(SystemSetting::get('pdf_timeout', 60))->toBe(60);
});

it('get は integer 型を数値にキャストして返す', function () {
    SystemSetting::create(['key' => 'pdf_timeout', 'value' => '120', 'type' => 'integer', 'min_value' => 10, 'max_value' => 300]);

    expect(SystemSetting::get('pdf_timeout', 60))->toBe(120);
});

it('get は value が null ならフォールバック値を返す', function () {
    SystemSetting::create(['key' => 'mail_bcc_address', 'value' => null, 'type' => 'emails']);

    expect(SystemSetting::get('mail_bcc_address', 'x'))->toBe('x');
});

it('mailBccAddresses は改行区切りを trim・空除去して配列化する', function () {
    SystemSetting::create([
        'key' => 'mail_bcc_address',
        'value' => "a@example.com\n b@example.com \n\nc@example.com",
        'type' => 'emails',
    ]);

    expect(SystemSetting::mailBccAddresses())->toBe(['a@example.com', 'b@example.com', 'c@example.com']);
});

it('mailBccAddresses は未設定時に空配列を返す', function () {
    expect(SystemSetting::mailBccAddresses())->toBe([]);
});
