<?php

/**
 * SQL Server ドライバでの文字列カラム型（varchar 強制）を検証するテスト
 * （db-design §2 / §5.3 / BR-10 / Q-17 / 開発環境設計書 §9）。
 *
 * ■ 背景（Q-17・2026-07-06 決定）
 *   全カラムを実際に varchar 型で統一する方針。MySQL では `$table->string()`/
 *   `text()`/`char()` がそのまま varchar/text/char になるため対応不要だが、
 *   Laravel の `SqlServerGrammar` は sqlsrv 文法で `string()`→`nvarchar`、
 *   `text()`→`nvarchar(max)`、`char()`→`nchar` に自動マッピングしてしまうため、
 *   Schema ビルダのみでは実際に varchar 型を強制できない。
 *
 * ■ 検証の主旨
 *   SqlServerGrammar 自体の挙動（nvarchar への自動マッピング）は Laravel 側の仕様で
 *   変わらない。したがって本テストは 2 点を保証する。
 *     (1) SqlServerGrammar が今も string()→nvarchar 系にマッピングすること
 *         （= 事後 ALTER が必要である根拠が維持されていること）。
 *     (2) 各業務テーブルのマイグレーションに、sqlsrv 接続時のみ対象カラムを
 *         `ALTER COLUMN ... varchar(...)` へ変更する処理が含まれ、想定カラムが
 *         漏れなく網羅されていること（varchar への統一が退行しないことを検出）。
 */

use Illuminate\Database\Schema\Grammars\SqlServerGrammar;
use Illuminate\Database\SqlServerConnection;
use Illuminate\Support\Fluent;

/** SqlServerGrammar の型マッピング結果（typeXxx）を取得するヘルパ */
function sqlsrvColumnType(array $attributes): string
{
    // PDO は遅延解決（クロージャ）にして実接続なしに文法だけを利用する
    $connection = new SqlServerConnection(fn () => null, 'ePostCable');
    $grammar = new SqlServerGrammar($connection);

    $method = new ReflectionMethod($grammar, 'getType');
    $method->setAccessible(true);

    return $method->invoke($grammar, new Fluent($attributes));
}

/**
 * 業務テーブルのマイグレーションファイルと、そこで varchar へ強制すべき文字列カラム一覧。
 * db-design §2 のテーブル定義（Q-17: 全て varchar）と対応する。
 *
 * @return array<string, array{path: string, columns: array<int, string>}>
 */
function varcharMigrationExpectations(): array
{
    $base = dirname(__DIR__, 4); // .../src（tests/Feature → Shared → Modules → src）

    return [
        'users' => [
            'path' => $base.'/database/migrations/0001_01_01_000000_create_users_table.php',
            'columns' => ['name', 'email', 'password', 'role', 'remember_token'],
        ],
        'invoices' => [
            'path' => $base.'/Modules/Invoice/database/migrations/2026_07_06_000010_create_invoices_table.php',
            'columns' => ['invoice_number', 'customer_name', 'customer_email', 'customer_email_2', 'customer_email_3', 'status'],
        ],
        'invoice_items' => [
            'path' => $base.'/Modules/Invoice/database/migrations/2026_07_06_000011_create_invoice_items_table.php',
            'columns' => ['item_name'],
        ],
        'delivery_notes' => [
            'path' => $base.'/Modules/DeliveryNote/database/migrations/2026_07_06_000012_create_delivery_notes_table.php',
            'columns' => ['delivery_number', 'customer_name', 'customer_email', 'customer_email_2', 'customer_email_3', 'status'],
        ],
        'delivery_note_items' => [
            'path' => $base.'/Modules/DeliveryNote/database/migrations/2026_07_06_000013_create_delivery_note_items_table.php',
            'columns' => ['item_name'],
        ],
        'send_mail_logs' => [
            'path' => $base.'/Modules/SendMailLog/database/migrations/2026_07_06_000014_create_send_mail_logs_table.php',
            'columns' => ['batch_key', 'batch_name', 'error_message'],
        ],
        'send_mail_log_items' => [
            'path' => $base.'/Modules/SendMailLog/database/migrations/2026_07_06_000015_create_send_mail_log_items_table.php',
            'columns' => ['sendable_type', 'status', 'error_message'],
        ],
        'shipment_fetch_logs' => [
            'path' => $base.'/Modules/ShipmentFetch/database/migrations/2026_07_06_000016_create_shipment_fetch_logs_table.php',
            'columns' => ['status', 'error_message'],
        ],
        'system_settings' => [
            'path' => $base.'/Modules/SystemSetting/database/migrations/2026_07_06_000017_create_system_settings_table.php',
            'columns' => ['key', 'value', 'type'],
        ],
    ];
}

it('SqlServerGrammar は今も string()/text()/char() を nvarchar 系へマッピングする（varchar 強制が必要な根拠）', function () {
    // この挙動が維持される限り Schema ビルダだけでは varchar にならず、事後 ALTER が必要になる。
    expect(sqlsrvColumnType(['type' => 'string', 'name' => 'customer_name', 'length' => 255]))
        ->toBe('nvarchar(255)');
    expect(sqlsrvColumnType(['type' => 'text', 'name' => 'error_message']))
        ->toBe('nvarchar(max)');
    expect(sqlsrvColumnType(['type' => 'char', 'name' => 'code', 'length' => 10]))
        ->toBe('nchar(10)');
});

it('全業務テーブルのマイグレーションが sqlsrv 接続ガード付きで varchar 強制処理を持つ', function () {
    foreach (varcharMigrationExpectations() as $table => $spec) {
        expect(file_exists($spec['path']))->toBeTrue("マイグレーションが存在しない: {$spec['path']}");

        $source = file_get_contents($spec['path']);

        // sqlsrv 限定ガード（SqlServerVarchar 経由）で MySQL では何もしないこと
        expect(str_contains($source, 'SqlServerVarchar'))
            ->toBeTrue("[{$table}] varchar 強制処理（SqlServerVarchar）が見当たらない");
    }
});

it('各テーブルの想定文字列カラムがすべて varchar 強制の対象に含まれている（漏れ検出）', function () {
    foreach (varcharMigrationExpectations() as $table => $spec) {
        $source = file_get_contents($spec['path']);

        foreach ($spec['columns'] as $column) {
            // SqlServerVarchar::alter([...]) 内に "'カラム名' => 'varchar(...)" が存在すること
            $pattern = "/'".preg_quote($column, '/')."'\s*=>\s*'varchar\(/";
            expect((bool) preg_match($pattern, $source))
                ->toBeTrue("[{$table}] カラム {$column} の varchar 強制指定が漏れている");
        }
    }
});
