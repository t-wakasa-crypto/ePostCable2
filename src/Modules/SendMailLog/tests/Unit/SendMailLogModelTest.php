<?php

/**
 * SendMailLog / SendMailLogItem モデルの判定ロジックを検証するテスト
 * （T031 / 詳細設計 §4.3 / §4.4 / BR-03 / BR-07）。
 *
 * displayStatus 優先順位（failed_at 最優先）、manualResendBucket の当日集約、
 * morphTo 解決、フィルタスコープを確認する。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Invoice\Models\Invoice;
use Modules\SendMailLog\Models\SendMailLog;
use Modules\SendMailLog\Models\SendMailLogItem;

uses(RefreshDatabase::class);

it('displayStatus は failed_at を最優先で failed と判定する', function () {
    // failed_at と completed_at が同時でも failed
    $log = SendMailLog::factory()->make(['failed_at' => now(), 'completed_at' => now()]);
    expect($log->displayStatus())->toBe('failed');
});

it('displayStatus は completed_at のみなら completed を返す', function () {
    $log = SendMailLog::factory()->make(['failed_at' => null, 'completed_at' => now()]);
    expect($log->displayStatus())->toBe('completed');
});

it('displayStatus は両方 null なら running を返す', function () {
    $log = SendMailLog::factory()->make(['failed_at' => null, 'completed_at' => null]);
    expect($log->displayStatus())->toBe('running');
});

it('manualResendBucket は当日分を1件に集約する', function () {
    $first = SendMailLog::manualResendBucket();
    $second = SendMailLog::manualResendBucket();

    expect($first->id)->toBe($second->id);
    expect(SendMailLog::where('batch_key', 'manual-resend')->count())->toBe(1);
});

it('sendable の morphTo が Invoice を解決する', function () {
    $invoice = Invoice::factory()->create();
    $log = SendMailLog::factory()->create();
    $item = SendMailLogItem::factory()->create([
        'send_mail_log_id' => $log->id,
        'sendable_type' => Invoice::MORPH_ALIAS,
        'sendable_id' => $invoice->id,
    ]);

    expect($item->sendable)->toBeInstanceOf(Invoice::class);
    expect($item->sendable->id)->toBe($invoice->id);
});

it('filter スコープは manual-resend を completed/running/failed から除外する', function () {
    SendMailLog::factory()->completed()->create();
    SendMailLog::factory()->manualResend()->create(['completed_at' => null, 'failed_at' => null]);

    // manual-resend は running 概念を持たず除外される
    expect(SendMailLog::query()->filter('running')->count())->toBe(0);
    expect(SendMailLog::query()->filter('completed')->count())->toBe(1);
    expect(SendMailLog::query()->filter('manual_resend')->count())->toBe(1);
});
