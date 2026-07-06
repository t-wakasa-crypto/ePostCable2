# 矛盾・不明点・前提確認事項（解消済み）

> 記録者: requirements-gatherer / requirements-analyst / db-designer
> 記録日: 2026-06-29
> 解消日: 2026-07-01（メインチャットでのヒアリングにより全14件解消）
> 参照元: `請求書メール配信システム仕様.md`（最終更新 2026-06-25）+ `design/context.md`

---

## 優先度: 高

### Q-01: ジョブフォールバック値とシーダー初期値の不一致 → 解消済み（反映済み）

**決定**: フォールバック値をシーダー既定値に統一する（`pdf_timeout`=60秒、`retry_backoff`=30秒）。
**反映先**: detailed-design.md（ジョブ実装のフォールバック値記述）

---

### Q-02: `tax` / `tax_amount` の将来的な編集機能追加時の要件 → 解消済み（反映済み）

**決定**: 金額・明細の編集機能は将来的にも追加しない。請求データは基幹システムの売上確定値をそのまま使用する。変更があり得るのは入金予定日のみ。将来課題としての記載も不要（完全にスコープ外として扱う）。
**反映先**: requirements.md（制約として「請求データは編集不可・確定データとして扱う」旨を明記）

---

### Q-03: `updateEmails`（メールアドレス編集）の権限レベル → 解消済み（反映済み）

**決定**: admin限定に変更する。
**反映先**: detailed-design.md（ルート定義・権限チェック）、requirements.md（FR）

---

### Q-04: `WithoutOverlapping->releaseAfter(10)` とジョブタイムアウトの整合性 → 解消済み（反映済み）

**調査結果**: `releaseAfter` はロック取得失敗時の再試行遅延であり、多重実行防止とは無関係。実際にロック保持期間を決めるのは `expireAfter`（未指定時はデフォルト約60秒）。300秒かかるジョブに対し `expireAfter` を明示指定していない場合、ロックが処理完了前に失効し多重実行の可能性がある。
**決定**: `expireAfter(300以上)` を明示指定するよう設計に記載する。
**反映先**: detailed-design.md（`WithoutOverlapping` ミドルウェア定義）

---

## 優先度: 中

### Q-05: `bulkRequeue` で `retry_count` をインクリメントする意図 → 解消済み（反映済み）

**決定**:
- `retry_count` は記録用カウンタ（ジョブ自動リトライの `$maxExceptions` とは連動しない）。
- 一括リトライ時、対象に `retry_count >= 3` の件を含む場合は確認ダイアログ「すでに n 件が 3 回以上リトライしています。一括リトライしますか？」を表示する（一括リトライ自体は制限しない）。
- `retry_count >= 10` の件は一括リトライ対象から除外し `failed` のまま残す（多発時は将来的に別対応を検討）。
**反映先**: detailed-design.md（`bulkRequeue` シーケンス）、requirements.md（FR）

---

### Q-06: `send_mail_log_items.send_mail_log_id` の nullOnDelete の運用 → 解消済み（反映済み）

**調査結果**: NULL化は「手動再送のまとめ親（`manual-resend` バケット、`manualResendBucket()`）」向けの設定。現行仕様には `send_mail_logs` の削除UI・機能自体が存在しない。
**決定**: 削除機能が存在・計画もないため `nullOnDelete` → `restrictOnDelete` に変更し、まとめ親レコードは削除不可とする。孤立明細の発生を防ぎ再送履歴の追跡性を守る。
**反映先**: db-design.md（外部キー制約定義）

---

### Q-07: `batch:fetch-shipment-data` をスケジュール未登録とした理由 → 解消済み（反映済み）

**決定**: スケジュール登録する。基幹システムの請求データ確定が翌日12:00のため、確定遅延バッファを見て12:15〜12:30に自動実行する。失敗時は従来どおり手動実行で救済する。
**反映先**: detailed-design.md（スケジュール登録一覧）、diagrams.md（バッチフロー図）

---

### Q-08: `SendMailLogController#complete`（手動完了）の想定ユースケース → 解消済み（反映済み）

**背景**: 元々はバッチ異常終了で `completed_at` 未セットのまま残るログの救済用に追加されたが、ログは失敗のまま残す方が適切と判断。
**決定**:
- `complete` 機能（ルート・ボタン）を廃止する。
- 失敗のまま残るログはダッシュボードのサマリ・履歴一覧の両方から除外し、別画面（詳細検索等）でのみ確認可能にする。
**反映先**: detailed-design.md（`SendMailLogController` 仕様・ダッシュボード集計仕様）、diagrams.md

---

## 優先度: 低

### Q-09: `customer_email`（NOT NULL）と `recipientEmails()` 空の矛盾可能性 → 解消済み（反映済み）

**決定**: 出荷取得バッチ（`batch:fetch-shipment-data`）側で `customer_email` の空白のみバリデーションを追加する。
**反映先**: detailed-design.md（出荷取得バッチ仕様）

---

### Q-10: CSV ダウンロードの文字コード → 解消済み（反映済み）

**決定**: UTF-8 BOM付きCSVのまま確定。
**反映先**: requirements.md（確定事項として明記）

---

### Q-11: PDF 保存パスの納品書の `{年}/{月}` 基準 → 解消済み（反映済み）

**決定**: `delivery_date`（納品・出荷日）基準で確定。
**反映先**: detailed-design.md（PDF保存パス仕様）、db-design.md

---

## 要件定義作成時に追加識別（requirements-analyst）

### Q-12: 定量的な非機能目標が仕様書に未定義 → 解消済み（反映済み）

**決定**（一般的な社内業務システム基準で合意）:

| 分類 | 目標値 |
|---|---|
| 画面レスポンスタイム | 3秒以内 |
| 稼働率目標 | 99%（平日日中） |
| RTO/RPO | 次営業日以内 |
| 同時接続数 | 10人前後 |
| ログ保存期間 | 7年間 |
| 監視体制 | アプリログ（Laravel Log）を目視で定期確認。専用監視ツールは導入しない |
| セキュリティ（通信） | TLS必須 |
| セキュリティ（個人情報） | 顧客情報は社内規則に準拠。脆弱性診断は実施予定なし |
| 対応ブラウザ | 最新モダンブラウザ |
| スケール方針 | Docker Compose単一サーバー想定で十分。将来的なスケールアウトは対応可能な構成とする |

**反映先**: requirements.md（第6章 非機能要件）

---

## DB設計作業中に識別（db-designer）

### DB-Q-01: `send_mail_log_items.error_message` の型と `mb_substr` の整合 → 解消済み（反映済み）

**決定**: `varchar(1000)` で確定する（`nvarchar` は使用しない）。マルチバイト文字によるバイト数超過切り詰めリスクは許容する。
**反映先**: db-design.md（カラム定義。nvarchar指定を varchar に修正）

---

### DB-Q-02: `invoices.amount` と `invoice_items` 合計値の整合保証なし → 解消済み（反映済み）

**決定**:
- 基幹取得時、送料・値引きの固定明細を通常明細に変換する処理を追加する。この変換により金額整合性の検証を行う前提とする。
- 変換後も合計金額（`invoices.amount`）自体は変わらないため、明細合計と不一致が検出された場合は伝票をエラーマーキングする。
- データ修正は当面DB直接編集で対応する。頻発する場合は将来的に修正機能を検討する。
**反映先**: detailed-design.md（出荷取得バッチの明細変換・整合性検証ロジック）、db-design.md（不一致マーキング用カラムの検討）

---

*以上 14 件すべて解消済み（反映済み）。決定日 2026-07-01。context.md〜diagrams.md への反映完了。*

---

## 横断レビューで追加識別（2026-07-02）

### Q-13: SQL Server（ePostCable検証系）の照合順序（Collation）確認 → 解消済み（反映済み）

**背景**: db-design.md 173・745行目に「照合順序を確認すること（→OQ-10）」という残留マーカーがあったが、Q-10の決定内容（CSV文字コードのみ）とは主題が異なる別論点であることが判明。
**決定**: 日本語対応の照合順序（Japanese_CI_AS等）で設定済みであることを確認済み。問題なし。
**反映先**: db-design.md（173・745行目付近の「→OQ-10」照合順序に関する残留マーカーを「日本語対応の照合順序で設定済み・問題なし」に修正し、Q-10とは独立した確認済み事項として明記）

---

### Q-14: 開発環境と検証環境の ePostCable 検証系DB共用リスク → 解消済み（反映済み）

**背景**: detailed-design.md 768行目に「環境差は要確認（→OQ-12）」という残留マーカーがあったが、Q-12の決定内容（非機能目標値の定義）とは無関係な別論点であることが判明。開発環境・検証環境が同一のePostCable検証系DBを共用しており、環境間でデータが互いに影響し合うリスクがある。
**決定**: 本プロジェクト（アプリケーション設計）のスコープ外とする。インフラ構成上の既知の制約として扱い、設計変更は行わない。
**反映先**: detailed-design.md（768行目付近の「→OQ-12」残留マーカーを削除し、「開発・検証環境はePostCable検証系DBを共用（インフラ構成上の既知の制約、本設計のスコープ外）」という注記に修正）

---

### Q-15: send_mail_log_items.sendable_type の保存値方式（モジュール型構成との齟齬） → 解消済み（反映済み）

**背景**: db-design.md ではポリモーフィック関連 `sendable_type` に `App\Models\Invoice` / `App\Models\DeliveryNote` という実クラス名を想定していたが、開発環境設計書§2で必須のモジュール型構成（`nwidart/laravel-modules`）採用により、実クラスは `Modules\Invoice\Models\Invoice` / `Modules\DeliveryNote\Models\DeliveryNote` となり記載値と不一致。Phase 1 実装（T016）時に implementer から報告。
**決定**: Eloquent の `morphMap` を使用し、`invoice` / `delivery_note` 等の短い論理名を DB に保存する方式を採用する（ユーザー承認、2026-07-06）。実クラスの namespace 変更に強く、モジュール型構成のベストプラクティスであるため。
**反映先**: db-design.md（sendable_type の記載を「morphMap による論理名（invoice / delivery_note）を保存」に修正）／detailed-design.md（該当箇所があれば同様に修正）。Phase 2 の T031（モデル実装）で `Relation::morphMap()` を `AppServiceProvider` 等に定義して反映する。

---

### Q-16: db-design §5.3「string() は varchar を生成する」の注記が Laravel の SQL Server 文法と不一致 → 解消済み（反映済み）

**背景**: コードレビュー（review-report.md 1回目）で「日本語列（customer_name / item_name / batch_name 等）が `$table->string()`（= varchar）のままで SQL Server 本番で文字化けリスク」と指摘。db-design §5.3 には「`$table->string('column')` が `varchar` を生成するため、日本語列は `nvarchar` を指定する」と明記されている。
**検証結果（2026-07-06 implementer）**: Laravel 13.18 の `Illuminate\Database\Schema\Grammars\SqlServerGrammar` は `string()`→`nvarchar(length)`、`text()/mediumText()/longText()`→`nvarchar(max)`、`char()`→`nchar` にマッピングする（ソース確認済み）。したがって現行のドライバ非依存マイグレーション（`$table->string()` のみ）でも、`DB_CONNECTION=sqlsrv` の本番では日本語列は自動的に `nvarchar` になる。db-design §5.3 の「string() は varchar を生成する」という注記は Laravel の Schema ビルダの挙動としては**不正確**（SQL Server ネイティブの CREATE TABLE を手書きした場合の話）。
**対応（実装）**: 開発環境設計書 §9 の「ドライバ固有構文を書かない・Schema ビルダのみで表現する」方針を優先し、マイグレーションは変更しない（driver 判定による型分岐や raw nvarchar 指定はむしろ §9 の禁止事項に抵触する）。代わりに `Modules/Shared/tests/Feature/SqlServerNvarcharTest.php` を追加し、SqlServerGrammar が string→nvarchar / text→nvarchar(max) / char→nchar にマッピングすることを型単位で保証（日本語列が将来 varchar へ退行しないことを検出）。
**決定**: db-design §5.3 の注記を「Laravel の Schema ビルダ（sqlsrv 文法）では `string()`→`nvarchar`、`text()`→`nvarchar(max)`、`char()`→`nchar` に自動マッピングされるため、日本語列にドライバ固有の型指定は不要」という趣旨に是正する（ユーザー承認、2026-07-06）。
**反映先**: db-design.md §5.3（nvarchar 行の注記を是正済み）。マイグレーション実装（`$table->string()` のみ）は変更不要。

---

### Q-17: 全カラムを実際に varchar 型で統一（SQL Server でも nvarchar を使わない） → 解消済み（反映済み）

**背景**: Q-16 で「Laravel の Schema ビルダは sqlsrv 文法で string()→nvarchar に自動変換するため実装変更不要」と決定したが、ユーザーから「nvarchar は実質意味が無いため全て varchar に統一したい」との明確な指示があった（2026-07-06）。

**決定**: db-design.md の全カラム型表記を `nvarchar`（および `nvarchar(max)`・`nchar`）から `varchar`（および `varchar(max)`・`char`）に統一する。実際のカラム型（本番 SQL Server 含む）も varchar にする（ユーザー承認、2026-07-06・表記のみでなく実体も統一）。

**技術的対応**: Laravel の `SqlServerGrammar` は Schema ビルダ経由の `string()`/`text()`/`char()` を自動的に nvarchar 系にマッピングするため、Schema ビルダのみでは実際に varchar 型を強制できない。開発環境設計書§9「ドライバ固有構文を書かない」の原則を維持しつつ、この1点（文字列カラムの型指定）に限り、sqlsrv 接続時のみ `DB::statement()` で `ALTER COLUMN ... varchar(...)` に変更する事後処理をマイグレーション末尾に追加する例外を許容する（MySQL では string()/text()/char() がそのまま varchar/text/char になるため変更不要）。

**反映先**: db-design.md（§2 各テーブル定義・§5.3 の nvarchar 表記を varchar に修正）／マイグレーション（sqlsrv 接続時のみ post-migration で varchar へ変更する処理を追加）／`Modules/Shared/tests/Feature/SqlServerVarcharTest.php`（旧 SqlServerNvarcharTest.php・AD-01でリネーム済み）の期待値を varchar 系に更新。

**適用範囲の明確化（2026-07-06・ユーザー確認）**: varchar 統一の対象は、このシステムが新規に持つ業務テーブル9件（users / invoices / invoice_items / delivery_notes / delivery_note_items / send_mail_logs / send_mail_log_items / shipment_fetch_logs / system_settings）に限る。Laravel フレームワーク標準テーブル（sessions・cache・jobs・failed_jobs・password_reset_tokens 等）は対象外とし、sqlsrv では Laravel 標準の nvarchar のままでよい（フレームワークコアのマイグレーションを個別プロジェクトで改変しないため）。
