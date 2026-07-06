<?php

/**
 * シーダーの投入内容を検証するテスト（T018 / db-design §4 / BR-06 / OQ-01）。
 *
 * system_settings の初期値・値域がフォールバック値（シーダー値）と一致すること、
 * 初期管理者が投入されることを確認する。
 */

use Illuminate\Support\Facades\DB;
use Modules\SystemSetting\Database\Seeders\SystemSettingSeeder;
use Modules\User\Database\Seeders\AdminUserSeeder;
use Modules\User\Models\User;

it('SystemSettingSeeder が既定値・値域どおりに投入する', function () {
    $this->seed(SystemSettingSeeder::class);

    $expected = [
        'pdf_timeout' => ['60', 'integer', 10, 300],
        'retry_backoff' => ['30', 'integer', 0, 3600],
        'max_retries' => ['3', 'integer', 0, 10],
    ];

    foreach ($expected as $key => [$value, $type, $min, $max]) {
        $row = DB::table('system_settings')->where('key', $key)->first();
        expect($row)->not->toBeNull();
        expect($row->value)->toBe($value);
        expect($row->type)->toBe($type);
        expect((int) $row->min_value)->toBe($min);
        expect((int) $row->max_value)->toBe($max);
    }

    // emails 型は value NULL・値域 NULL
    $bcc = DB::table('system_settings')->where('key', 'mail_bcc_address')->first();
    expect($bcc->type)->toBe('emails');
    expect($bcc->value)->toBeNull();
});

it('SystemSettingSeeder は冪等（複数回実行で重複しない）', function () {
    $this->seed(SystemSettingSeeder::class);
    $this->seed(SystemSettingSeeder::class);

    expect(DB::table('system_settings')->count())->toBe(5);
});

it('AdminUserSeeder が admin ユーザーを投入する', function () {
    $this->seed(AdminUserSeeder::class);

    $admin = User::where('email', 'admin@example.com')->first();
    expect($admin)->not->toBeNull();
    expect($admin->isAdmin())->toBeTrue();
});
