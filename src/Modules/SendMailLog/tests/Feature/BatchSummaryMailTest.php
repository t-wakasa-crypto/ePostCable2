<?php

/**
 * BatchSummaryMail の件名・BCC を検証するテスト（T074 / 詳細設計 §1.5 / FR-14 / NFR-R-07）。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SendMailLog\Mail\BatchSummaryMail;
use Modules\SendMailLog\Models\SendMailLog;
use Modules\SystemSetting\Models\SystemSetting;

uses(RefreshDatabase::class);

it('件名は【バッチ完了】{batch_name}メール送信 {実行開始日時} 実行分 形式', function () {
    $log = SendMailLog::factory()->create([
        'batch_name' => '請求書',
        'started_at' => '2026-07-06 01:30:00',
    ]);

    expect((new BatchSummaryMail($log))->envelope()->subject)
        ->toBe('【バッチ完了】請求書メール送信 2026-07-06 01:30 実行分');
});

it('共通 BCC を付与する', function () {
    SystemSetting::create(['key' => 'mail_bcc_address', 'value' => 'bcc@example.com', 'type' => 'emails']);
    $log = SendMailLog::factory()->create();

    $bcc = collect((new BatchSummaryMail($log))->envelope()->bcc)->pluck('address')->all();

    expect($bcc)->toBe(['bcc@example.com']);
});
