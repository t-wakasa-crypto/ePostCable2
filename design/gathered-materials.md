# 収集材料

> リバース元: `請求書メール配信システム仕様.md`（作成 2026-06-11 / 最終更新 2026-06-25）
> 検証方針: 仕様ベース（実装コードは存在しないため仕様書の自己整合性・カバレッジ・記述の矛盾を検証）

---

## 既存ドキュメントサマリー

### システムの目的・利用者・利用シーン

**目的**
顧客への請求書・納品書を PDF で生成しメールで自動配信する。請求書・納品書の作成〜PDF化〜メール送付の手作業を排除し、送付漏れ・誤送信を防ぎ、送信状況を追跡可能にする。

**利用者と権限**

| ロール | 画面権限 | 主な操作 |
|--------|---------|---------|
| general（営業・カスタマー担当） | 要認証の全画面 | 送信状況確認・手動再送・PDF/CSVダウンロード・メールアドレス編集 |
| admin（システム管理者） | 上記＋管理者専用 | バッチ手動起動・一括再キュー・ユーザー管理・システム設定・メール送信履歴の手動完了 |

**利用シーン**
1. 毎日深夜（01:00〜01:30）にスケジューラが納品書・請求書メール送信バッチを自動起動
2. 毎週月曜早朝（02:00〜02:30）に `--retry-failed` 付きバッチが失敗レコードを再送
3. 担当者が管理画面から送信状況を確認し、必要に応じて個別再送
4. 管理者が管理画面からバッチを手動起動・システム設定を変更
5. 管理者がシステム設定画面でテストメールを送信して動作確認

---

### 機能一覧と各機能の振る舞い

#### 1. 出荷データ取得バッチ（`batch:fetch-shipment-data`）

- **起動方法**: 管理画面から手動のみ（スケジュール未登録）。`Artisan::queue` で非同期起動。
- **二重起動防止**: `Cache::lock('batch:fetch-shipment-data', 3600)` による排他制御。ロック取得失敗時はスキップ。
- **処理内容**:
  1. `ShipmentFetchLog` レコード作成（status: `running`）
  2. `ShipmentFetchService` で基幹システムAPI（`BACKBONE_API_URL`）から出荷データ取得（タイムアウト30秒）。未設定時はダミーデータ（空配列）
  3. 出荷1件ごとにDBトランザクション内で納品書・請求書・各明細を作成（status: `pending`）
  4. `delivery_number` / `invoice_number` が既存ならスキップ（重複防止）
  5. 消費税: `round(amount × tax / 100)`。`tax` は10固定
  6. 完了時に `ShipmentFetchLog` を `completed` に更新。例外時は `failed` に更新

#### 2. 請求書メール送信バッチ（`batch:send-invoices`）

- **起動方法**: スケジューラ（毎日01:30 / 毎週月曜02:30 `--retry-failed`）または管理画面からの手動起動（`Artisan::queue` で非同期）
- **オプション**: `--limit`（デフォルト100）・`--stuck-timeout`（デフォルト60分）・`--retry-failed`
- **二重起動防止**: `Cache::lock('batch:send-invoices', 3600)`
- **処理内容**:
  1. `SendMailLog` レコード作成
  2. `stuck()` スコープで残留 `processing` レコードを `pending` に差し戻し（`retry_count` +1）
  3. `--retry-failed` 時: `failed` レコードを `pending` に差し戻し（`retry_count` +1）
  4. `pending` レコードを作成日時昇順で最大 `limit` 件取得
  5. DBトランザクション・行ロック → `processing` 更新 → `SendMailLogItem` 作成 → `ProcessInvoiceJob` ディスパッチ
  6. `SendMailLog` を完了更新（`completed_at`・`dispatched_count`・実行時間）
  7. `admin_notification_emails` が設定済みなら `BatchSummaryMail` 送信（未設定時は警告ログのみ）

#### 3. 納品書メール送信バッチ（`batch:send-delivery-notes`）

請求書バッチと同一仕様。ロックキー: `batch:send-delivery-notes`。スケジュール: 毎日01:00 / 毎週月曜02:00 `--retry-failed`。

#### 4. キュージョブ（`ProcessInvoiceJob` / `ProcessDeliveryNoteJob`）

- **設定値**: ジョブコンストラクタ時に `system_settings` から動的取得

| 設定キー | 内容 | デフォルト（フォールバック） |
|---------|------|--------------------------|
| `max_retries` | 最大リトライ回数（`$maxExceptions`） | 3 |
| `retry_backoff` | リトライ間隔秒（固定値・`$backoff`） | 60 |
| `pdf_timeout` | タイムアウト秒（`$timeout`） | 120 |

- **重複実行防止**: `WithoutOverlapping($id)->releaseAfter(10)` ミドルウェア
- **処理フロー**:
  1. 書類レコード取得（存在しなければログ出力して終了）
  2. `processing` 以外はスキップ
  3. `PdfService` でPDF生成（DomPDF）
  4. Storageに保存（`invoices/{年}/{月}/invoice_{番号}.pdf` / `delivery-notes/{年}/{月}/delivery_{番号}.pdf`）
  5. `recipientEmails()` でアドレス取得。空なら `PermanentJobFailureException`
  6. 各アドレスを `filter_var(FILTER_VALIDATE_EMAIL)` 検証。無効が1件でも `PermanentJobFailureException`
  7. `InvoiceMail` / `DeliveryNoteMail` を全送付先へ送信（PDF添付）
  8. レコードを `sent` 更新・`sent_at` 記録・`SendMailLogItem` を `sent` 更新
- **失敗処理（`failed()` メソッド）**:
  - `PermanentJobFailureException` → `failed_permanent`
  - それ以外 → `failed`
  - `SendMailLogItem` にステータスとエラーメッセージ（1000文字まで）を記録

#### 5. 管理画面: ダッシュボード

- 請求書・納品書のステータス別件数集計
- メール送信履歴の直近実行・全体集計（実行中判定は `failed_at` 優先・手動再送除外）
- 出荷データ取得バッチの直近実行・履歴
- 請求書・納品書バッチの最終実行情報

#### 6. 管理画面: 請求書一覧・詳細

- 一覧: ステータスフィルタ・20件/ページ・ステータス別件数サマリー
- 詳細: 明細・全メール送信履歴（`SendMailLogItem`）表示
- 手動再送（`resend`）: `processing` に更新 → 当日分の `manual-resend` 親に `SendMailLogItem` 作成・`dispatched_count` +1 → `ProcessInvoiceJob` ディスパッチ
- メールアドレス編集（`updateEmails`）: **`failed` / `failed_permanent` 状態のみ可**。1〜3件、`email` バリデーション、未入力は null 正規化
- 一括再キュー（`bulkRequeue`）: `failed` レコードを `pending` に一括更新・`retry_count` +1（管理者のみ）
- バッチ手動起動（`runBatch`）: `Artisan::queue` で非同期起動（管理者のみ）
- PDF ダウンロード: Storage にあればダウンロード、なければ即時生成（保存しない）
- CSV ダウンロード: UTF-8 BOM付き・複数送付先は ` / ` 区切り

#### 7. 管理画面: 納品書一覧・詳細

請求書と同一仕様（Invoice → DeliveryNote 読み替え）。

#### 8. 管理画面: メール送信履歴

- 一覧: ステータスフィルタ・20件/ページ
- フィルタ値: `completed` / `running` / `manual_resend` / `has_pending` / `has_sent` / `has_failure` / `has_failure_permanent` / `failed`
- 詳細: 送信書類1通ごとの明細・50件/ページ
- 手動完了（`complete`）: 管理者のみ。未完了・未失敗の場合のみ `completed_at` をセット（失敗済みはブロック）
- 「送信済みにする」ボタン: 管理者・未完了・未失敗・`manual-resend` でない場合のみ表示

**手動再送のまとめ親（`batch_key = manual-resend`）**
当日分の専用親レコード1件に手動再送の明細を集約。`dispatched_count` は再送ごとに加算。完了/実行中の概念なし（フィルタ・集計から除外）。

#### 9. 管理画面: 出荷取得履歴

- 出荷取得バッチの実行履歴をステータスフィルタ付きでページネーション表示
- バッチ手動起動ボタン（管理者のみ）

#### 10. 管理画面: ユーザー管理（管理者のみ）

- 一覧: 20件/ページ・`role` フィルタ・退職者デフォルト除外（`include_retired` 指定で表示）
- 作成: `name` / `email`（unique）/ `password`（min:8・confirmed）/ `role`（in:general,admin）
- 編集: 同上。`password` は任意（入力時のみ更新）。`retired` チェックで `retired_at` をセット/解除
- 削除: 物理削除。自分自身は削除不可

#### 11. 管理画面: システム設定（管理者のみ）

| 設定キー | 型 | 既定値 | 範囲 | 説明 |
|---------|-----|-------|------|------|
| `pdf_timeout` | integer | 60 | 10〜300 | PDF生成タイムアウト（秒）。ジョブ `$timeout` に反映 |
| `retry_backoff` | integer | 30 | 0〜3600 | リトライ間隔（秒）。ジョブ `$backoff` に反映 |
| `max_retries` | integer | 3 | 0〜10 | 最大リトライ回数。ジョブ `$maxExceptions` に反映 |
| `admin_notification_emails` | emails | 管理者宛 | - | バッチ完了通知の送信先（改行区切り・複数可） |
| `mail_bcc_address` | emails | 空 | - | 全メールのBCC送信先（改行区切り・複数可） |

- `emails` 型は `FILTER_VALIDATE_EMAIL` で1行1アドレスを検証後、改行区切りで保存
- テストメール送信: 任意アドレス宛に `TestMail` を送信（管理者のみ）

#### 12. メール送信

| メール種別 | 件名形式 | 添付 | BCC |
|----------|---------|------|-----|
| 請求書メール（`InvoiceMail`） | `【請求書】{invoice_number}` | PDF | `mail_bcc_address` |
| 納品書メール（`DeliveryNoteMail`） | `【納品書】{delivery_number}` | PDF | `mail_bcc_address` |
| バッチ結果レポート（`BatchSummaryMail`） | `【バッチ完了】{batch_name}メール送信 {実行開始日時} 実行分` | なし | `mail_bcc_address` |
| テストメール（`TestMail`） | `【テスト】メール送信テスト` | なし | `mail_bcc_address` |

BCC は全 Mailable 共通で `SystemSetting::mailBccAddresses()` が `envelope()` で付与。未設定時は BCC なし。

#### 13. PDF生成（`PdfService`）

- ライブラリ: `barryvdh/laravel-dompdf` ^3.1
- 用紙: A4縦
- 日本語フォント: `LoadDompdfFonts` コマンドで事前読込
- セキュリティ設定: リモートリソース無効化・chroot設定適用
- 出力が空の場合: `RuntimeException` をスロー
- ダウンロード時: Storage に PDF があればダウンロード、なければ即時生成（Storageへ保存しない）

---

### 技術スタック・アーキテクチャ・外部連携

#### 技術スタック

| 種別 | 採用技術 | バージョン |
|------|---------|-----------|
| 言語 | PHP | ^8.3 |
| フレームワーク | Laravel | ^13.7 |
| PDF生成 | barryvdh/laravel-dompdf | ^3.1 |
| フロントエンド | Tailwind CSS（Vite経由） | - |
| DB | SQL Server（sqlsrv）。開発・本番とも外部 `db-sv03.solid-corp.local` | - |
| キュー | Redis | - |
| キャッシュ/ロック | database（既定）または redis | - |
| セッション | database | - |
| メール | SMTP（開発は Mailpit） | - |
| HTTP連携 | Laravel HTTP Client | - |
| テスト | PestPHP | ^4.7 |
| 実行環境 | Docker Compose（php / nginx / redis / mailpit / worker / scheduler） | - |

#### アーキテクチャ

- Laravel MVC + Queue（Redis）+ Scheduler（cron）
- DB は Docker コンテナなし（常に外部 SQL Server に接続）
- キューワーカーとスケジューラは独立コンテナ（`worker` / `scheduler`）
- ファイルストレージ: `local`（PDF保存先）
- バッチコマンドは `Artisan::queue` で非同期起動（画面はすぐ「受け付け完了」を返す）

#### 外部連携

| 連携先 | 方式 | 設定 | 備考 |
|-------|------|------|------|
| 基幹システム | Laravel HTTP Client（REST API） | `BACKBONE_API_URL` / `services.backbone.url` | 未設定時はダミーデータ（空配列）。タイムアウト30秒 |
| SQL Server | sqlsrv ドライバ | `DB_HOST` = `db-sv03.solid-corp.local`（開発） | ポート1433 / DB名 `ePostCable` |
| SMTP | Laravel Mailer | `MAIL_MAILER=smtp` | 開発は Mailpit |
| Redis | キュー | `QUEUE_CONNECTION=redis` | - |

---

### データ構造（主要テーブルとリレーション）

#### ER概要

```
shipment_fetch_logs（バッチ実行ログ・書類と直接リレーションなし）

invoices          1 --- N  invoice_items
delivery_notes    1 --- N  delivery_note_items

invoices        ─── MorphMany ──→ send_mail_log_items（sendable_type = Invoice）
delivery_notes  ─── MorphMany ──→ send_mail_log_items（sendable_type = DeliveryNote）

send_mail_logs  1 --- N  send_mail_log_items（nullOnDelete）
```

#### 主要テーブル要約

| テーブル | 主なカラム | 備考 |
|---------|----------|------|
| `invoices` | `invoice_number`（unique）/ `customer_email`〜3 / `amount`（税抜）/ `tax`（税率%）/ `tax_amount`（税額円）/ `status` / `retry_count` / `sent_at` / `pdf_path` | インデックス: `status`, `(status, created_at)` |
| `invoice_items` | `invoice_id`（FK cascade）/ `name` / `quantity` / `unit` / `unit_price` / `sort_order` | - |
| `delivery_notes` | `delivery_number`（unique）/ 請求書と同構造 ＋ `delivery_date` / `issue_date`（due_dateなし） | インデックス: `status`, `(status, created_at)` |
| `delivery_note_items` | 納品書ID（FK cascade）/ 請求書明細と同構造 | - |
| `send_mail_logs` | `batch_key` / `batch_name` / `started_at` / `completed_at` / `failed_at` / `dispatched_count` / `reset_count` / `retry_failed_count` / `execution_seconds` / `error_message` | 状態判定: `failed_at` 最優先 |
| `send_mail_log_items` | `send_mail_log_id`（nullOnDelete）/ `sendable_type` / `sendable_id` / `dispatched_at` / `status` / `error_message` | ポリモーフィック |
| `shipment_fetch_logs` | `status` / `started_at` / `completed_at` / `fetched_count` / `created_delivery_note_count` / `created_invoice_count` / `skipped_count` / `execution_seconds` / `error_message` | - |
| `system_settings` | `key`（unique）/ `value`（改行区切り対応）/ `label` / `type`（integer/emails）/ `sort_order` / `min_value` / `max_value` | KVS形式 |
| `users` | `email`（unique/ログインID）/ `password`（ハッシュ）/ `role`（admin/general）/ `retired_at` | - |

#### 重要な命名上の注意点

- `invoices.tax` = **税率（%）**（出荷取得時は 10 固定）
- `invoices.tax_amount` = **税額（円）**（バッチ作成時に `round(amount × tax / 100)` で算出）
- 両カラムは型・命名が近く混同しやすい（仕様書 指摘 #10）

---

### 非機能要件に関わる記述

#### セキュリティ・認証認可

- Laravel 標準 `auth` ミドルウェアで管理画面全体を保護（未認証 → `/login` リダイレクト）
- `admin` ミドルウェア（`AdminMiddleware`）で管理者専用ルートを保護（`bootstrap/app.php` でエイリアス登録）
- 管理者専用機能: システム設定・ユーザー管理・メール送信履歴の手動完了・バッチ手動起動・一括再キュー
- 退職者ログイン拒否: `LoginController` で `isRetired()` を判定（認証情報が正しくてもブロック）
- ログイン試行回数制限（`RateLimiter`）: メールアドレス＋IP単位で5回失敗 → 約60秒ロック（成功でカウンタリセット）
- CSRF: 全 POST に `@csrf` ディレクティブ
- ステータスフィルタは許可リスト（allowlist）方式で検証
- セッション: DB保存
- パスワード: ハッシュ保存・min:8・confirmed バリデーション

#### リトライ・タイムアウト

- ジョブの `$maxExceptions` / `$backoff` / `$timeout` は `system_settings` から動的取得
  - `max_retries`: 初期値3（0〜10）
  - `retry_backoff`: 初期値30秒（0〜3600）・固定値（段階配列ではない）
  - `pdf_timeout`: 初期値60秒（10〜300）。ジョブのフォールバック既定値は120秒
- 基幹APIタイムアウト: 30秒

#### 二重起動防止

| 対象 | ロックキー | ロック時間 |
|------|----------|----------|
| 出荷取得バッチ | `batch:fetch-shipment-data` | 3600秒 |
| 請求書送信バッチ | `batch:send-invoices` | 3600秒 |
| 納品書送信バッチ | `batch:send-delivery-notes` | 3600秒 |
| ジョブ単位 | `WithoutOverlapping($id)->releaseAfter(10)` | 10秒 |

スケジュール登録時は `withoutOverlapping()` + `runInBackground()` も付加。

#### 残留レコード救済

メール送信バッチ起動時、`stuck-timeout`（デフォルト60分）以上 `processing` のままのレコードを `pending` に差し戻し。キューワーカーのクラッシュや予期せぬタイムアウトからの復旧に使用。

#### バッチスケジュール

| コマンド | 実行タイミング | オプション |
|---------|--------------|----------|
| `batch:send-delivery-notes` | 毎日 01:00 | なし |
| `batch:send-invoices` | 毎日 01:30 | なし |
| `batch:send-delivery-notes` | 毎週月曜 02:00 | `--retry-failed` |
| `batch:send-invoices` | 毎週月曜 02:30 | `--retry-failed` |

※ `batch:fetch-shipment-data` はスケジュール未登録（管理画面からの手動起動のみ）。

#### パフォーマンス関連

- ページネーション: 書類一覧・送信履歴一覧 20件/ページ、送信履歴詳細 50件/ページ
- バッチ処理件数: `--limit` オプションで制御（デフォルト100件）
- DBトランザクション・行ロック: ジョブディスパッチ時の競合防止
- 複合インデックス: `(status, created_at)` で送信待ち取得を最適化
- PDF即時生成（ダウンロード時）は Storage 保存なし（バッチ処理時は保存あり）

---

### 業務フロー

#### 全体フロー

```
基幹システム → 出荷取得バッチ（手動起動）
                  ├─ 重複チェック（delivery_number / invoice_number）
                  ├─ 納品書 + 明細 作成（pending）
                  └─ 請求書 + 明細 作成（pending）

スケジューラ または 管理者手動
  → 納品書送信バッチ / 請求書送信バッチ
      ├─ 残留 processing → pending リセット
      ├─ （--retry-failed 時）failed → pending
      ├─ pending を limit 件取得 → processing → キュー投入
      └─ BatchSummaryMail を管理者へ送信

キューワーカー（ProcessInvoiceJob / ProcessDeliveryNoteJob）
  → PDF生成 → Storage保存 → メール送信（PDF添付）→ sent
  → 失敗時: failed / failed_permanent
```

#### ステータス遷移

**書類（invoices / delivery_notes）**

```
pending → processing → sent
                    → failed → pending（--retry-failed または bulkRequeue）
                    → failed_permanent（PermanentJobFailureException・手動対応必要）
```

`failed_permanent` への遷移条件:
- 送付先メールアドレスが0件（`recipientEmails()` が空配列）
- いずれかのアドレスが `filter_var(FILTER_VALIDATE_EMAIL)` で無効

**出荷取得ログ（shipment_fetch_logs）**

```
running → completed
        → failed
```

**メール送信ログ（send_mail_logs）の表示状態**

```
displayStatus() の判定:
  failed_at あり → "failed"（失敗）
  completed_at あり（failed_atなし） → "completed"（完了）
  両方 NULL（manual-resend以外） → "running"（実行中）
```

---

## 競合・類似サービス調査

対象外（既存自社システムのリバースのため実施しない）。

---

## 技術調査結果

対象外（仕様書に技術詳細が網羅されているため WebSearch/WebFetch による外部調査は実施しない）。

---

## 確認が必要な事項

### 仕様上の矛盾・曖昧・要確認点

1. **[指摘 #10] `tax` と `tax_amount` の混同リスク**
   - `tax`（税率%）と `tax_amount`（税額円）は型・命名が近く混同しやすい
   - `tax_amount` は出荷取得バッチ作成時に `round(amount × tax / 100)` で算出し、以後の再計算は行わない
   - 現状は画面からの金額・明細編集機能がないため不整合は生じないが、**将来編集機能を追加する場合は保存時に `tax_amount` の再計算が必須**
   - 要件として「将来の編集機能追加時の再計算要件」を明示すべきか要確認

2. **ジョブのフォールバック既定値の不一致**
   - `pdf_timeout` のシーダー既定値は **60秒**（system_settings）
   - ジョブコンストラクタのフォールバック値は **120秒**（`SystemSetting::get('pdf_timeout', 120)`）
   - 通常はシーダー値が使われるが、シーダー未実行時に意図しない値（120秒）が適用される。フォールバックはシーダー値（60秒）に合わせるべきか要確認

3. **`retry_backoff` シーダー値とジョブフォールバック値の不一致**
   - シーダー既定値: **30秒** / ジョブフォールバック: **60秒**
   - `pdf_timeout` と同様の問題。シーダー未実行環境で動作が異なる

4. **`updateEmails`（メールアドレス編集）の認証要件**
   - ルート定義では「要認証」（`auth` ミドルウェアのみ）
   - general ユーザーもメールアドレス編集が可能な設計
   - 誤送信リスクがあるが、意図的な権限設計かどうかを要確認（管理者のみに制限すべきか？）

5. **`bulkRequeue` で `retry_count` をインクリメントする意図**
   - `bulkRequeue` は管理者が手動で `failed → pending` に差し戻す操作だが、`retry_count` が +1 される
   - ジョブ自動リトライとは別の操作のため、`retry_count` に含めることが意図的かどうか要確認
   - `max_retries` と `retry_count` の関係性（ジョブレベルの `$maxExceptions` とは別物で、`retry_count` は記録用か？）が仕様書から不明

6. **`send_mail_log_items.send_mail_log_id` の nullOnDelete について**
   - 親（`send_mail_logs`）を削除すると子（`send_mail_log_items`）の外部キーが NULL になる設計
   - 親を削除するユースケースが仕様書に記載されていないが、意図的に NULL を許容する理由と運用手順が不明

7. **`batch:fetch-shipment-data` のスケジュール未登録の意図**
   - 請求書・納品書送信バッチはスケジュール登録されているが、出荷取得バッチは手動のみ
   - 出荷取得を手動のみとした業務上の理由（タイミングの柔軟性確保？基幹システムの都合？）が仕様書に記載なし

8. **`SendMailLogController#complete` の用途**
   - 管理者が手動で送信履歴を「完了済み」にマークできる機能だが、どのユースケースで使用するかが不明
   - ジョブが `sent` に更新しても `SendMailLog` の `completed_at` がセットされないケース（バッチ処理中に例外が発生し `failed_at` はないが `completed_at` もない状態）の救済用と推測されるが、要確認

9. **メールアドレスが1件も存在しない場合の `PermanentJobFailureException`**
   - 出荷取得バッチ作成時に `customer_email`（NOT NULL）は必須だが、その後のジョブ実行時に `recipientEmails()` が空になるケースとして、`customer_email` が空白のみの場合（trim後空）が該当すると推測される
   - DB制約は `NOT NULL` だが、空文字列は許容される可能性がある。出荷取得バッチ側でのバリデーションの有無が仕様書に記載なし

10. **CSV ダウンロードの文字コード**
    - UTF-8 BOM付きと記載されているが、Excel 等での開き方を想定した仕様かどうかの確認。SQL Server との文字コード整合性（Shift-JIS環境の場合）も要確認

11. **`WithoutOverlapping` のリリース後10秒とジョブタイムアウト（`pdf_timeout` 最大300秒）の整合性**
    - `releaseAfter(10)` はロックを解放するまでの待機秒。ジョブが最大300秒かかる場合、10秒後にロックが解放され同一書類IDのジョブが再度実行される可能性があるか要確認（`releaseAfter` がジョブ完了まで延長されるのか、それとも固定10秒か）

12. **`delivery_note_items` に `invoice_id` 相当のカラム名**
    - 仕様書では `delivery_note_id` と記載されているが、テーブル定義の説明欄に「納品書ID（FK cascade）」とあり一致している。ただし明細テーブルの `cascade delete` が本番運用で想定するデータ削除パターンを踏まえた設計かどうかは要確認

