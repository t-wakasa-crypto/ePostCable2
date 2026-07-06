<?php

/**
 * DB ドライバ切替（mysql / sqlsrv）動作確認テスト（T113 / 開発環境設計書 §9 / RK-8）。
 *
 * 本番の SQL Server（db-sv03.solid-corp.local・BR-10）は開発環境から到達不可のため、
 * 実インスタンスに対するスイート実行は行えない。代わりに「ドライバ非依存であること」を
 * 静的に保証する:
 *   - sqlsrv 接続が config/database.php に定義され、PDO ドライバ（pdo_sqlsrv）が利用可能。
 *   - マイグレーションが Schema ビルダのみで記述され、ドライバ固有のカラム型・エンジン指定が無い。
 *   - モジュールコードにドライバ固有の生 SQL 構文（GROUP_CONCAT / TOP / OFFSET FETCH 等）が無い。
 * これにより DB_CONNECTION を mysql ⇄ sqlsrv に切り替えても動作する状態を担保する（§9）。
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/** モジュール・共通コードのソースファイルを列挙する（テスト・vendor は除外） */
function portabilitySourceFiles(): array
{
    $base = base_path();
    $files = [];

    foreach ([$base.'/Modules', $base.'/app'] as $root) {
        if (! is_dir($root)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $path = $file->getPathname();
            if (! str_ends_with($path, '.php')) {
                continue;
            }
            if (str_contains($path, '/tests/')) {
                continue;
            }
            $files[] = $path;
        }
    }

    return $files;
}

it('sqlsrv 接続が定義され pdo_sqlsrv ドライバが利用可能である', function () {
    // 本番で DB_CONNECTION=sqlsrv に切り替えるための接続定義が存在する（§9.2）
    expect(config('database.connections.sqlsrv'))->not->toBeNull();
    expect(config('database.connections.sqlsrv.driver'))->toBe('sqlsrv');
    // SQL Server 用 PDO ドライバがコンテナに導入されている
    expect(extension_loaded('pdo_sqlsrv'))->toBeTrue();
});

it('ドライバ固有の生 SQL 構文がモジュールコードに存在しない', function () {
    // MySQL 固有 / SQL Server 固有の構文を検出したら失敗させる（RK-8 / §9）
    $forbidden = [
        'GROUP_CONCAT',
        'STRING_AGG',
        'DB::statement',
        '->toRawSql',
        'OFFSET',            // OFFSET ... FETCH（SQL Server 固有ページング）
    ];

    $violations = [];
    foreach (portabilitySourceFiles() as $path) {
        // Q-17（2026-07-06決定）で許容された唯一の例外: 文字列カラムを varchar 型へ強制する
        // ためだけに sqlsrv 接続時のみ DB::statement(ALTER COLUMN) を用いる補助クラス
        // （db-design §5.3 に明記）。これ以外でのドライバ固有生 SQL は引き続き禁止。
        if (str_ends_with($path, 'Database/Support/SqlServerVarchar.php')) {
            continue;
        }

        $content = file_get_contents($path);
        foreach ($forbidden as $needle) {
            if (str_contains($content, $needle)) {
                $violations[] = basename($path).' に '.$needle;
            }
        }
    }

    expect($violations)->toBe([]);
});

it('マイグレーションにドライバ固有のカラム型・エンジン指定が存在しない', function () {
    // Schema ビルダ以外・ドライバ固有の構文（engine/charset/collation 指定）を検出したら失敗させる（§9）。
    // なお $table->enum() は Schema ビルダのメソッドであり、SQL Server では CHECK 制約付き
    // varchar へ、MySQL では native ENUM へと Laravel が自動変換するためドライバ非依存であり対象外。
    $forbidden = ['->engine', '->charset(', '->collation('];

    $violations = [];
    foreach (portabilitySourceFiles() as $path) {
        if (! str_contains($path, '/migrations/')) {
            continue;
        }
        $content = file_get_contents($path);
        foreach ($forbidden as $needle) {
            if (str_contains($content, $needle)) {
                $violations[] = basename($path).' に '.$needle;
            }
        }
    }

    expect($violations)->toBe([]);
});

it('現行 mysql ドライバでスキーマ構築とクエリが成立する', function () {
    // RefreshDatabase により全マイグレーションが適用済みであることを確認（両ドライバ共通の Schema 定義）
    expect(Schema::hasTable('invoices'))->toBeTrue();
    expect(Schema::hasTable('delivery_notes'))->toBeTrue();
    expect(Schema::hasTable('send_mail_logs'))->toBeTrue();
    expect(Schema::hasColumn('invoices', 'tax_amount'))->toBeTrue();
});
