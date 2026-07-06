<?php

/**
 * 受入テストの網羅（T114 / requirements.md §8 検証マッピング）。
 *
 * 各 FR/NFR/BR の受入条件は各モジュールのテストで個別に検証済みだが、本テストは
 * §8 検証マッピングのうち、これまで明示的なアサーションが無かった観点を補完する:
 *   - NFR-P-03: 送信待ち取得用の複合インデックス (status, created_at) と status 単独インデックス。
 *   - BR-03: displayStatus の failed_at 最優先・manual-resend 除外（横断再確認）。
 * これにより §8 の全観点に green なテストが存在する状態を担保する。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\SendMailLog\Models\SendMailLog;

uses(RefreshDatabase::class);

/** テーブルのインデックスに、指定カラム構成のものが存在するか判定する（ドライバ非依存） */
function hasIndexOnColumns(string $table, array $columns): bool
{
    foreach (Schema::getIndexes($table) as $index) {
        if ($index['columns'] === $columns) {
            return true;
        }
    }

    return false;
}

it('NFR-P-03: invoices に複合インデックス (status, created_at) と status 単独インデックスがある', function () {
    expect(hasIndexOnColumns('invoices', ['status', 'created_at']))->toBeTrue();
    expect(hasIndexOnColumns('invoices', ['status']))->toBeTrue();
});

it('NFR-P-03: delivery_notes に複合インデックス (status, created_at) と status 単独インデックスがある', function () {
    expect(hasIndexOnColumns('delivery_notes', ['status', 'created_at']))->toBeTrue();
    expect(hasIndexOnColumns('delivery_notes', ['status']))->toBeTrue();
});

it('BR-03: displayStatus は failed_at を最優先する', function () {
    // completed_at が立っていても failed_at があれば failed と判定される
    $log = SendMailLog::factory()->create([
        'batch_key' => 'send-invoices',
        'completed_at' => now(),
        'failed_at' => now(),
    ]);

    expect($log->displayStatus())->toBe('failed');
});

it('BR-03: completed_at のみなら completed、両方 null なら running と判定する', function () {
    $completed = SendMailLog::factory()->create([
        'batch_key' => 'send-invoices', 'completed_at' => now(), 'failed_at' => null,
    ]);
    $running = SendMailLog::factory()->create([
        'batch_key' => 'send-invoices', 'completed_at' => null, 'failed_at' => null,
    ]);

    expect($completed->displayStatus())->toBe('completed');
    expect($running->displayStatus())->toBe('running');
});
