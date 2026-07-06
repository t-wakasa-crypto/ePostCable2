<?php

namespace Modules\Shared\Database\Support;

use Illuminate\Support\Facades\DB;

/**
 * SQL Server（sqlsrv）接続時に文字列カラムを varchar 型へ強制するための補助クラス
 * （db-design §2 / §5.3 / Q-17・2026-07-06決定）。
 *
 * ■ 背景
 *   全カラムを実際に varchar 型で統一する方針（Q-17）。MySQL では
 *   `$table->string()`/`text()`/`char()` がそのまま varchar/text/char になるため
 *   対応不要だが、Laravel の `SqlServerGrammar` は sqlsrv 文法で
 *   `string()`→`nvarchar`、`text()`→`nvarchar(max)`、`char()`→`nchar` に自動
 *   マッピングしてしまうため、`string()` だけでは実際に varchar 型にならない。
 *
 * ■ 方針
 *   開発環境設計書 §9「ドライバ固有構文を書かない」の原則を維持しつつ、この
 *   1点（文字列カラムの型指定）に限り、sqlsrv 接続時のみ `DB::statement()` で
 *   `ALTER COLUMN ... varchar(...)` へ変更する事後処理を許容する（db-design §5.3 に明記）。
 *   MySQL 接続時は {@see isSqlServer()} が false を返すため何もしない。
 *
 * ■ 注意（SQL Server の制約）
 *   インデックス・ユニーク制約に含まれるカラムは、そのまま ALTER COLUMN できない
 *   ため、呼び出し側で対象インデックスを drop → {@see alter()} → 再作成すること。
 *   CHECK 制約・DEFAULT 制約は ALTER COLUMN で保持されるため drop 不要。
 */
final class SqlServerVarchar
{
    /** 現在のメインDB接続が SQL Server（sqlsrv）かどうか。 */
    public static function isSqlServer(): bool
    {
        return DB::connection()->getDriverName() === 'sqlsrv';
    }

    /**
     * 指定テーブルの文字列カラムを varchar 型へ変更する。
     *
     * sqlsrv 以外の接続では何もしない（MySQL では string()/text()/char() が
     * そのまま varchar/text/char になるため）。
     *
     * @param  string  $table    対象テーブル名
     * @param  array<string, string>  $columns  [カラム名 => 'varchar(N) NOT NULL' 等の型定義]。
     *                                            NULL 許可有無を必ず明示すること（省略すると NULL 扱いになる）。
     */
    public static function alter(string $table, array $columns): void
    {
        if (! self::isSqlServer()) {
            return;
        }

        foreach ($columns as $column => $definition) {
            DB::statement("ALTER TABLE [{$table}] ALTER COLUMN [{$column}] {$definition}");
        }
    }
}
