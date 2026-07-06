<?php

/**
 * send_mail_logs / send_mail_log_items テーブルのスキーマが db-design §2.6 / §2.7 と
 * 一致することを検証するテスト（T015 / T016）。
 *
 * restrictOnDelete（親削除不可）とポリモーフィックカラムの存在を確認する。
 */

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('send_mail_logs が必要なカラムを持つ', function () {
    expect(Schema::hasColumns('send_mail_logs', [
        'id', 'batch_key', 'batch_name', 'started_at', 'completed_at', 'failed_at',
        'dispatched_count', 'reset_count', 'retry_failed_count', 'execution_seconds',
        'error_message', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('send_mail_log_items がポリモーフィックカラムを持つ', function () {
    expect(Schema::hasColumns('send_mail_log_items', [
        'id', 'send_mail_log_id', 'sendable_type', 'sendable_id', 'status',
        'error_message', 'sent_at',
    ]))->toBeTrue();
});

it('明細が存在する親 send_mail_log は削除できない（restrictOnDelete）', function () {
    $logId = DB::table('send_mail_logs')->insertGetId([
        'batch_key' => 'send-invoices', 'batch_name' => '請求書', 'started_at' => now(),
    ]);
    DB::table('send_mail_log_items')->insert([
        'send_mail_log_id' => $logId,
        'sendable_type' => 'invoice', 'sendable_id' => 1, 'status' => 'pending',
    ]);

    expect(fn () => DB::table('send_mail_logs')->where('id', $logId)->delete())
        ->toThrow(QueryException::class);
});
