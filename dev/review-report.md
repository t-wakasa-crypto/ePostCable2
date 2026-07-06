# コードレビュー報告（再レビュー: Q-17 varchar 統一）

**総合評価**: OK

> 対象: 請求書メール配信システム（`src/` 配下）
> 再レビュー範囲: Q-17（全文字列カラムを varchar に統一・nvarchar 不使用）に伴う追加実装
> 前回レビュー: 総合評価「OK」（`dev/review-report.md` 旧版）
> レビュー日: 2026-07-06 / レビュア: code-reviewer
> テスト状況: 193 passed / 1 skipped（申告値）

---

## 1. 再レビューのスコープと確認結果

今回の設計変更（Q-17・2026-07-06決定）は「全文字列カラムを実際に varchar 型で統一し、
SQL Server でも nvarchar を使わない」というもの。前回 OK 評価済みの全体は再検証せず、
本変更の差分に絞って以下を確認した。

- `design/questions.md` Q-17 の決定内容（実体・表記とも varchar 統一、事後 ALTER 方式）
- `design/db-design.md` §2 冒頭注記・§5.3「varchar」行・各テーブル定義の型表記
- 追加された補助クラス `Modules/Shared/app/Database/Support/SqlServerVarchar.php`
- 9 テーブルのマイグレーションの varchar 強制処理（インデックス列の drop/recreate 含む）
- テスト `SqlServerNvarcharTest.php` / `DatabaseDriverPortabilityTest.php`

結論として、設計どおりに実装されており、T-SQL 構文・§9 例外の限定範囲ともに問題なし。
重大・中程度の指摘はなく、軽微な観察のみ。

---

## 2. 設計適合性チェック

### 2.1 差異なし（設計どおり）

| 観点 | 設計（db-design §2/§5.3・Q-17） | 実装 | 判定 |
|------|------|------|------|
| varchar 統一方針 | sqlsrv 接続時のみ `ALTER COLUMN ... varchar(...)` を事後実行 | `SqlServerVarchar::alter()` が `isSqlServer()` ガード下で ALTER 実行 | 一致 |
| §9 例外の限定 | 「ドライバ固有構文禁止」の例外はこの1点（文字列型指定）のみ | 生 SQL は `SqlServerVarchar` に集約。移植性テストで同ファイルのみ除外 | 一致 |
| MySQL 側 | string()/text()/char() はそのまま varchar 等になり追加処理不要 | `alter()` が sqlsrv 以外で即 return。MySQL は無変換 | 一致 |
| 対象カラム網羅 | db-design §2 の全文字列カラム（9 業務テーブル） | 9 テーブル全てで対象カラムを ALTER。型長も db-design と一致（例: status varchar(30)・batch_key varchar(50)・error_message varchar(1000)/varchar(max)・key varchar(100)） | 一致 |
| インデックス/ユニーク列の扱い | インデックス列は直接 ALTER 不可のため drop→ALTER→再作成が必要 | invoice_number/delivery_number/email/key（unique）、status・(status,created_at)・batch_key・sendable・status（index）を drop→ALTER→再作成 | 一致 |
| varchar(max) | text()/longText() 起点に sqlsrv で varchar(max) へ | value・error_message を `varchar(max)` へ ALTER | 一致 |

### 2.2 軽微な差異

| # | 内容 | 重大度 |
|---|------|--------|
| D-1 | Laravel 標準テーブル（`sessions`・`password_reset_tokens`・`cache` 等）の文字列カラム（`ip_address`/`user_agent`/`payload`/`token` 等）は varchar 強制の対象外で、sqlsrv では nvarchar 系のまま残る。Q-17 の文言は「全文字列カラム」だが、db-design §2 は業務 9 テーブルのみを varchar 統一対象として定義しており（§2.10 の標準テーブルはフレームワーク管理で型指定なし）、実装は db-design の範囲どおり。運用上の実害はないが、「全カラム」という Q-17 の表現との厳密な差分として認識しておくとよい | 軽微 |

---

## 3. T-SQL 構文の確認

- `ALTER TABLE [table] ALTER COLUMN [column] varchar(N) NOT NULL/NULL` は正当な T-SQL。
  識別子は角括弧でクォート済み。NULL/NOT NULL を各定義で明示しており、
  ALTER COLUMN で NULL 許可が意図せず変わる事故を防いでいる（補助クラスの docblock でも注意喚起）。
- インデックス列の drop→ALTER→再作成順序は正しい。SQL Server は
  インデックス/ユニーク制約に含まれるカラムを直接 ALTER COLUMN できないため、
  この手順は必須であり適切に実装されている。
- enum カラム（status/role/batch_key/type）は sqlsrv で「nvarchar + CHECK 制約」に
  展開されるが、ALTER COLUMN の型変更（nvarchar→varchar・互換変換）では CHECK・DEFAULT
  制約は保持されるため drop 不要。補助クラスのコメントの記述と実 SQL Server 挙動は整合。
- unique/index を無名で再作成しているが、Laravel の既定命名規則により元と同名
  （例: `invoices_invoice_number_unique`）になり、drop 時の規約名指定と齟齬しない。

いずれも構文上の誤りは検出されなかった。

---

## 4. 品質指摘

| # | 内容 | 重大度 |
|---|------|--------|
| Q-1 | テストファイル名が `SqlServerNvarcharTest.php` のままだが、内容は varchar 強制の検証に更新済み。ファイル名と主旨が逆の意味に読めるため、`SqlServerVarcharTest.php` 等へのリネームを推奨（Q-17 反映先にも旧名が記載されているため許容範囲だが可読性の観点で） | 軽微 |
| Q-2 | `SqlServerVarchar::alter()` は識別子・型定義を文字列補間で組み立てる。値はすべて開発者定義の定数（ユーザー入力なし）のため注入リスクはないが、補助クラスの利用箇所が今後増える場合はカラム名 allowlist 等の防御を検討 | 軽微 |
| Q-3 | varchar 強制処理が 9 マイグレーションにコピー展開されており、対象カラムの追加漏れが起きやすい。現状は `SqlServerNvarcharTest` の「漏れ検出」テストで担保されているため実害なし（テストが安全網として機能） | 軽微 |

コード品質は良好。テストが「(1) SqlServerGrammar が nvarchar へマッピングし続けること（事後 ALTER が必要な根拠の維持）」「(2) 全テーブルに sqlsrv ガード付き varchar 強制があること」「(3) 想定カラムの網羅（漏れ検出）」の3層で退行を検知する設計になっており、変更の堅牢性が高い。

---

## 5. セキュリティ指摘

- 本変更に起因する新規の脆弱性は検出されなかった。
- `DB::statement()` による生 SQL はドライバ固有の型強制のみに限定され、ユーザー入力を
  含まない。移植性テストが `SqlServerVarchar.php` 以外での生 SQL 使用を継続的に禁止しており、
  例外の横展開を機械的に防いでいる。

---

## 6. 未実装項目

- 本再レビュー範囲（Q-17）に関する未実装はなし。db-design §2 の業務 9 テーブルの
  varchar 強制はすべて実装・テスト済み。
- （D-1 参照）フレームワーク標準テーブルの文字列カラムは varchar 化対象外だが、
  これは db-design の定義範囲に沿った実装であり「未実装」ではなく仕様範囲の解釈事項。

---

## 7. 修正推奨事項（優先度付き）

| 優先度 | 項目 | 対応 |
|--------|------|------|
| 低 | Q-1: テストファイル名 `SqlServerNvarcharTest.php` → `SqlServerVarcharTest.php` へのリネーム | 任意（可読性向上） |
| 低 | D-1: Q-17「全カラム」と db-design「業務9テーブル」の範囲差の明文化。標準テーブルも varchar 統一するか設計側で最終確認 | 設計確認事項（実害なし） |
| 低 | Q-2: 補助クラスのカラム名 allowlist 化 | 任意（将来の拡張時） |

---

## 8. 総括

Q-17 の設計変更は、db-design §5.3 に明記された「sqlsrv 接続時のみ事後 ALTER で varchar 強制」
方針どおりに実装されている。生 SQL は補助クラス 1 点に集約され、開発環境設計書 §9
「ドライバ固有構文禁止」の例外が適切に限定されている（移植性テストで境界を強制）。
インデックス列の drop→ALTER→再作成を含む T-SQL 構文に誤りはなく、テストは退行検出まで
カバーしている。重大・中程度の指摘はないため **総合評価: OK** とする。軽微な推奨事項
（テスト名リネーム・標準テーブル範囲の明文化）は任意対応でよい。
