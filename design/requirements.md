# 請求書メール配信システム 要件定義書

> リバース元: `請求書メール配信システム仕様.md`（作成 2026-06-11 / 最終更新 2026-06-25）
> 作成日: 2026-06-29 / 作成: requirements-analyst
> 検証方針: 仕様ベース。本書は「実装済みシステムに内在する要件を、検証可能（テスト可能）な形で明文化」することを目的とする。
> 入力: `design/gathered-materials.md` / `design/context.md` / `design/questions.md`

---

## 1. 背景・目的

### 1.1 背景
顧客への請求書・納品書は従来、作成 → PDF 化 → メール送付までを手作業で実施していた。この運用には以下の課題があった。

- 送付漏れ・誤送信が発生しうる
- 送信状況が追跡できない（誰にいつ送ったかが残らない）
- 大量件数の処理に人的工数がかかる

### 1.2 目的
基幹システムの出荷データを起点に、納品書・請求書の作成 → PDF 生成 → メール配信を自動化し、送付漏れ・誤送信を防ぎ、送信状況を画面で追跡・是正できる状態にする。

### 1.3 本要件定義書のゴール
実装済みシステムについて、「実装が要件を満たしているか」を仕様ベースで検証できるよう、要件を検証可能（受入条件付き）な形で整理する。新規要件の創出ではなく、既存仕様に内在する要件の明文化が目的である。

---

## 2. システム概要・スコープ

### 2.1 概要
Laravel 製の Web 管理システム。基幹システムからの出荷データ取得バッチ、メール送信バッチ、キュージョブ（PDF 生成・送信）、および管理画面（送信状況確認・手動再送・各種設定）から構成される。

### 2.2 スコープに含むもの
- 出荷データ取得バッチ（基幹連携・重複スキップ・消費税算出）
- 請求書／納品書メール送信バッチ（limit / stuck-timeout / retry-failed・二重起動防止）
- キュージョブによる PDF 生成・メール送信（system_settings 由来のリトライ／タイムアウト）
- 管理画面（ダッシュボード・請求書／納品書一覧&詳細・メール送信履歴・出荷取得履歴・ユーザー管理・システム設定）
- メール送信（請求書／納品書／バッチ結果レポート／テスト、全メール共通 BCC）
- 認証・認可（auth / admin ミドルウェア・退職者ログイン拒否・試行回数制限・CSRF）

### 2.3 スコープに含まないもの（対象外）
- 競合・他社サービス調査
- 新規システム設計（本書はリバース）
- 画面からの金額・明細編集機能（現在・将来ともに提供しない。請求データは基幹システムの売上確定値をそのまま使用する制約とする。変更があり得るのは入金予定日のみ）

---

## 3. ステークホルダー・権限範囲

| ロール | 権限範囲 | 主な操作 |
|--------|---------|---------|
| general（営業・カスタマー担当） | 要認証の全画面（admin 専用を除く） | 送信状況確認・手動再送・PDF/CSV ダウンロード |
| admin（システム管理者） | general の全権限 ＋ 管理者専用機能 | バッチ手動起動・一括再キュー・ユーザー管理・システム設定・メールアドレス編集 |
| 退職者（retired_at セット済み） | ログイン不可 | （認証情報が正しくてもブロック） |

**管理者専用機能（admin ミドルウェアで保護）**: システム設定・ユーザー管理・バッチ手動起動・一括再キュー・メールアドレス編集（`updateEmails`）。

> 決定（2026-07-01）: メールアドレス編集は誤送信リスクが高いため admin 限定に変更する（旧仕様では general も可能だったが変更）。メール送信履歴の手動完了機能（`complete`）は廃止する（詳細は8章 FR-10 参照）。

---

## 4. 用語定義

| 用語 | 定義 |
|------|------|
| 書類 | 請求書（invoices）または納品書（delivery_notes）の総称 |
| 出荷取得バッチ | `batch:fetch-shipment-data`。基幹から出荷データを取得し書類を作成する |
| 送信バッチ | `batch:send-invoices` / `batch:send-delivery-notes`。pending 書類をキューへ投入する |
| キュージョブ | `ProcessInvoiceJob` / `ProcessDeliveryNoteJob`。PDF 生成・メール送信を行う |
| 残留レコード（stuck） | `processing` のまま `stuck-timeout`（既定60分）以上経過した書類 |
| 手動再送まとめ親 | `batch_key = manual-resend` の当日1件の send_mail_logs。手動再送明細を集約する |
| failed_permanent | 再試行で解決しない恒久的失敗（送付先0件・無効アドレス）。手動対応が必要 |
| tax | 税率（%）。出荷取得時は 10 固定 |
| tax_amount | 税額（円）。`round(amount × tax / 100)` で算出 |
| recipientEmails() | customer_email 系（最大3件）を trim・空除去して返す送付先配列 |
| displayStatus() | send_mail_logs の表示状態判定（failed_at 最優先） |

---

## 5. 機能要件

各機能要件は ID（FR-xx）と受入条件（検証可能・テスト可能）を付す。ロール権限を明示する。

### エピック A: 出荷データ取得

#### FR-01 出荷データ取得バッチ
**管理者として、基幹システムの出荷データを取り込み、納品書・請求書を自動作成したい。なぜなら手作業での書類作成をなくし、送信対象を準備するため。**

- 権限: 管理画面からの手動起動は admin のみ。スケジュールは毎日12:15〜12:30に自動実行する（基幹システムの請求データ確定が翌日12:00のため、確定遅延バッファを見て設定。失敗時は従来どおり手動実行で救済する）。
- 受入条件:
  - 起動時に `Cache::lock('batch:fetch-shipment-data', 3600)` を取得する。取得失敗時は処理をスキップする。
  - 起動時に `ShipmentFetchLog` を status=`running` で作成する。
  - `ShipmentFetchService` が `BACKBONE_API_URL`（services.backbone.url）へタイムアウト30秒で接続する。未設定時はダミーデータ（空配列）を返す。
  - `customer_email` が空白のみの文字列の場合はバリデーションエラーとして当該出荷データをスキップし、ログに記録する（NOT NULL 制約を満たしつつ空扱いになりジョブが `PermanentJobFailureException` を誘発する事象を未然に防止する）。
  - 送料・値引き等の固定明細は通常の明細形式に変換したうえで登録し、明細合計と `amount` の整合性を検証する。不一致を検出した場合は当該書類をエラーマーキングする（データ修正は当面 DB 直接編集で対応。頻発時は将来的に修正機能を検討）。
  - 出荷1件ごとに DB トランザクション内で納品書・請求書・各明細を status=`pending` で作成する。
  - `delivery_number` / `invoice_number` が既存の場合はスキップし、`skipped_count` に計上する。
  - 税額は `tax_amount = round(amount × tax / 100)`、`tax` は 10 固定で算出する。
  - 正常終了時に `ShipmentFetchLog` を `completed` に更新し、`fetched_count` / `created_delivery_note_count` / `created_invoice_count` / `skipped_count` / `execution_seconds` を記録する。
  - 例外時に `ShipmentFetchLog` を `failed` に更新し、`error_message` を記録する。
  - 起動は `Artisan::queue` による非同期で、画面は即座に受付完了を返す。

### エピック B: メール送信バッチ

#### FR-02 請求書メール送信バッチ
**システムとして、送信待ち（pending）の請求書を定時に取得しキューへ投入したい。なぜなら請求書を確実かつ自動的に配信するため。**

- 権限: スケジューラによる自動起動、または管理画面からの手動起動（admin のみ・`Artisan::queue` 非同期）。
- オプション: `--limit`（既定100）/ `--stuck-timeout`（既定60分）/ `--retry-failed`。
- 受入条件:
  - 起動時に `Cache::lock('batch:send-invoices', 3600)` を取得する。失敗時はスキップする。
  - `SendMailLog` レコードを作成する（batch_key・batch_name・started_at）。
  - `stuck-timeout` 以上 `processing` の残留レコードを `pending` へ差し戻し、`retry_count` を +1、`reset_count` を計上する。
  - `--retry-failed` 指定時、`failed` レコードを `pending` へ差し戻し、`retry_count` を +1、`retry_failed_count` を計上する。
  - `pending` を created_at 昇順で最大 `limit` 件取得する。
  - 取得した各書類を DB トランザクション＋行ロックで `processing` 更新 → `SendMailLogItem` 作成 → `ProcessInvoiceJob` をディスパッチする。
  - 完了時に `SendMailLog` の `completed_at` / `dispatched_count` / `execution_seconds` を記録する。
  - `admin_notification_emails` 設定済みの場合 `BatchSummaryMail` を送信する。未設定時は警告ログのみ出力する。

#### FR-03 納品書メール送信バッチ
**システムとして、送信待ちの納品書を定時に取得しキューへ投入したい。**

- 権限・受入条件: FR-02 と同一仕様（Invoice → DeliveryNote 読み替え）。ロックキーは `batch:send-delivery-notes`、ジョブは `ProcessDeliveryNoteJob`。

#### FR-04 バッチスケジュール
**システムとして、深夜帯に送信バッチを自動実行したい。なぜなら担当者の操作なしに定時配信するため。**

- 受入条件（cron 登録・`withoutOverlapping()` + `runInBackground()` 付加）:
  - 毎日 01:00 `batch:send-delivery-notes`（オプションなし）
  - 毎日 01:30 `batch:send-invoices`（オプションなし）
  - 毎週月曜 02:00 `batch:send-delivery-notes --retry-failed`
  - 毎週月曜 02:30 `batch:send-invoices --retry-failed`
  - `batch:fetch-shipment-data` は毎日12:15〜12:30に自動実行（基幹の請求データ確定〈翌日12:00〉からのバッファを見た時刻。失敗時は手動実行で救済）。

### エピック C: キュージョブ（PDF 生成・送信）

#### FR-05 請求書キュージョブ（ProcessInvoiceJob）
**システムとして、請求書の PDF を生成し送付先へメール送信したい。なぜなら送信実務を非同期で確実に処理するため。**

- 受入条件:
  - ジョブのコンストラクタで `system_settings` から `max_retries`（$maxExceptions）/ `retry_backoff`（$backoff・固定値）/ `pdf_timeout`（$timeout）を動的取得する。各キー未取得時のフォールバックはシーダー既定値と一致させる（max_retries=3 / retry_backoff=30 / pdf_timeout=60）。
  - `WithoutOverlapping($id)->releaseAfter(10)->expireAfter(300)` ミドルウェアで重複実行を防止する。`releaseAfter` はロック取得失敗時の再試行遅延であり多重実行防止とは無関係。実際のロック保持期間は `expireAfter` で決まるため、`pdf_timeout` の最大値（300秒）以上を明示指定し、処理完了前にロックが失効して多重実行が発生することを防ぐ。
  - 対象書類が存在しなければログ出力して終了する。
  - 書類が `processing` 以外の場合はスキップする。
  - `PdfService`（DomPDF）で PDF を生成し、Storage の `invoices/{年}/{月}/invoice_{番号}.pdf` に保存する。
  - `recipientEmails()` が空配列の場合 `PermanentJobFailureException` をスローする。
  - 各アドレスを `filter_var(FILTER_VALIDATE_EMAIL)` で検証し、1件でも無効なら `PermanentJobFailureException` をスローする。
  - 全送付先へ `InvoiceMail`（PDF 添付）を送信する。
  - 成功時、書類を `sent` 更新・`sent_at` 記録、`SendMailLogItem` を `sent` 更新する。
  - 失敗時（`failed()`）: `PermanentJobFailureException` は `failed_permanent`、それ以外は `failed` に更新し、`SendMailLogItem` にステータスとエラーメッセージ（1000文字まで）を記録する。

#### FR-06 納品書キュージョブ（ProcessDeliveryNoteJob）
**システムとして、納品書の PDF を生成し送付先へメール送信したい。**

- 受入条件: FR-05 と同一仕様。保存先は `delivery-notes/{年}/{月}/delivery_{番号}.pdf`、メールは `DeliveryNoteMail`。納品書の `{年}/{月}` は `delivery_date`（納品・出荷日）基準とする。

### エピック D: 管理画面

#### FR-07 ダッシュボード
**担当者として、配信状況の全体像を一目で把握したい。なぜなら異常や滞留を早期に検知するため。**

- 権限: general / admin（要認証）。
- 受入条件:
  - 請求書・納品書のステータス別件数を集計表示する。
  - メール送信履歴の直近実行・全体集計を表示する。実行中判定は `failed_at` 優先・手動再送（manual-resend）を除外する。
  - 出荷データ取得バッチの直近実行・履歴を表示する。
  - 請求書・納品書送信バッチの最終実行情報を表示する。

#### FR-08 請求書一覧・詳細
**担当者として、請求書の送信状況を確認し、必要に応じて是正したい。**

- 権限: 閲覧・手動再送・PDF/CSV DL は general / admin。メールアドレス編集・一括再キュー・バッチ手動起動は admin のみ。
- 受入条件:
  - 一覧はステータスフィルタ（allowlist）・20件/ページ・ステータス別件数サマリーを表示する。
  - 詳細は明細と全メール送信履歴（SendMailLogItem）を表示する。
  - 手動再送（`resend`）: 書類を `processing` 更新 → 当日分 `manual-resend` 親に `SendMailLogItem` 作成・`dispatched_count` +1 → `ProcessInvoiceJob` ディスパッチ。
  - メールアドレス編集（`updateEmails`、admin のみ）: `failed` / `failed_permanent` 状態のみ可。1〜3件・`email` バリデーション・未入力は null 正規化。誤送信リスクが高いため admin 限定とする。
  - 一括再キュー（`bulkRequeue`、admin のみ）: `failed` を `pending` へ一括更新・`retry_count` +1。対象に `retry_count >= 3` の件を含む場合は「すでに n 件が 3 回以上リトライしています。一括リトライしますか？」の確認ダイアログを表示する（一括リトライ自体は制限しない）。`retry_count >= 10` の件は一括リトライ対象から除外し `failed` のまま残す。
  - バッチ手動起動（`runBatch`、admin のみ）: `Artisan::queue` で非同期起動。
  - PDF ダウンロード: Storage にあればダウンロード、なければ即時生成（Storage 保存なし）。
  - CSV ダウンロード: UTF-8 BOM 付き、複数送付先は ` / ` 区切り（確定事項）。

#### FR-09 納品書一覧・詳細
- 権限・受入条件: FR-08 と同一仕様（Invoice → DeliveryNote 読み替え）。

#### FR-10 メール送信履歴
**担当者として、バッチ単位・書類1通単位で送信結果を追跡したい。なぜなら失敗の原因を特定し是正するため。**

- 権限: 閲覧は general / admin。
- 受入条件:
  - 一覧はステータスフィルタ（allowlist）・20件/ページ。フィルタ値: `completed` / `running` / `manual_resend` / `has_pending` / `has_sent` / `has_failure` / `has_failure_permanent` / `failed`。
  - 詳細は送信書類1通ごとの明細を 50件/ページで表示する。
  - 手動完了機能（`complete`）は廃止する。失敗のまま残るログは削除・上書きせずログとして保持するが、ダッシュボードのサマリー・本一覧の両方から除外し、別画面（詳細検索等）でのみ確認可能にする。
  - 手動再送まとめ親（batch_key=manual-resend）は当日1件に集約し、`dispatched_count` を加算する。完了/実行中の概念を持たず、フィルタ・集計から除外する。まとめ親レコードは削除不可（`restrictOnDelete`）とする。

#### FR-11 出荷取得履歴
**担当者として、出荷取得バッチの実行履歴を確認したい。**

- 権限: 閲覧は general / admin。バッチ手動起動は admin のみ。
- 受入条件: ステータスフィルタ付きページネーション表示。バッチ手動起動ボタンは admin のみ表示。

#### FR-12 ユーザー管理（admin のみ）
**管理者として、利用者アカウントを管理したい。なぜなら適切な権限とアクセス制御を維持するため。**

- 権限: admin のみ。
- 受入条件:
  - 一覧: 20件/ページ・`role` フィルタ・退職者は既定除外（`include_retired` 指定で表示）。
  - 作成: `name` / `email`（unique）/ `password`（min:8・confirmed）/ `role`（in:general,admin）。
  - 編集: 作成と同項目。`password` は入力時のみ更新。`retired` チェックで `retired_at` をセット/解除。
  - 削除: 物理削除。自分自身は削除不可。

#### FR-13 システム設定（admin のみ）
**管理者として、リトライ・タイムアウト・通知先などの動作パラメータを画面から変更したい。なぜなら環境や運用に応じて調整するため。**

- 権限: admin のみ。
- 受入条件（KVS: system_settings）:

| 設定キー | 型 | 既定値 | 範囲 |
|---------|-----|-------|------|
| `pdf_timeout` | integer | 60 | 10〜300 |
| `retry_backoff` | integer | 30 | 0〜3600 |
| `max_retries` | integer | 3 | 0〜10 |
| `admin_notification_emails` | emails | 管理者宛 | - |
| `mail_bcc_address` | emails | 空 | - |

  - integer 型は min_value〜max_value の範囲で検証する。
  - emails 型は `FILTER_VALIDATE_EMAIL` で1行1アドレスを検証し、改行区切りで保存する。
  - テストメール送信: 任意アドレス宛に `TestMail` を送信する（admin のみ）。
  - 変更値は次回ジョブのコンストラクタ取得時に反映される（$timeout / $backoff / $maxExceptions）。

### エピック E: メール送信

#### FR-14 メール送信仕様

| メール種別 | 件名形式 | 添付 | BCC |
|----------|---------|------|-----|
| 請求書（InvoiceMail） | `【請求書】{invoice_number}` | PDF | mail_bcc_address |
| 納品書（DeliveryNoteMail） | `【納品書】{delivery_number}` | PDF | mail_bcc_address |
| バッチ結果（BatchSummaryMail） | `【バッチ完了】{batch_name}メール送信 {実行開始日時} 実行分` | なし | mail_bcc_address |
| テスト（TestMail） | `【テスト】メール送信テスト` | なし | mail_bcc_address |

- 受入条件:
  - BCC は全 Mailable 共通で `SystemSetting::mailBccAddresses()` が `envelope()` で付与する。未設定時は BCC なし。
  - 請求書・納品書メールは PDF を添付する。

### エピック F: PDF 生成

#### FR-15 PDF 生成（PdfService）
- 受入条件:
  - ライブラリは barryvdh/laravel-dompdf ^3.1、用紙は A4 縦。
  - 日本語フォントは `LoadDompdfFonts` で事前読込する。
  - セキュリティ設定: リモートリソース無効化・chroot 設定を適用する。
  - 出力が空の場合 `RuntimeException` をスローする。
  - ダウンロード時は Storage に PDF があればダウンロード、なければ即時生成（Storage 保存なし）。

### エピック G: 認証・認可

#### FR-16 認証
**利用者として、メールアドレスとパスワードでログインしたい。**

- 受入条件:
  - 未認証で管理画面にアクセスすると `/login` へリダイレクトする。
  - ログイン ID はメールアドレス（unique）、パスワードはハッシュ保存。
  - 退職者（`isRetired()` が真）は認証情報が正しくてもログインを拒否する。
  - 同一メールアドレス＋IP 単位で5回失敗すると約60秒ロックする。成功でカウンタをリセットする。

#### FR-17 認可
- 受入条件:
  - `auth` ミドルウェアで管理画面全体を保護する。
  - `admin` ミドルウェア（AdminMiddleware）で管理者専用ルートを保護する。
  - 管理者専用機能: システム設定・ユーザー管理・バッチ手動起動・一括再キュー・メールアドレス編集。
  - 全 POST に `@csrf` を付与する。

---

## 6. 非機能要件

性能・セキュリティ・可用性/信頼性・保守性/運用性・拡張性の5軸で網羅性をチェックした。

### 6.1 性能（NFR-P）
- NFR-P-01: 書類一覧・送信履歴一覧は 20件/ページ、送信履歴詳細は 50件/ページでページネーションする。
- NFR-P-02: 送信バッチの1回あたり処理件数は `--limit`（既定100件）で制御する。
- NFR-P-03: 送信待ち取得は複合インデックス `(status, created_at)` で最適化する。`status` 単独インデックスも持つ。
- NFR-P-04: ジョブディスパッチ時は DB トランザクション＋行ロックで競合を防止する。
- NFR-P-05: PDF はバッチ処理時は Storage に保存し、ダウンロード時の即時生成は保存しない。
- NFR-P-06: 基幹 API 接続はタイムアウト30秒とする。
- NFR-P-07: 画面レスポンスタイムは3秒以内を目標とする。
- NFR-P-08: 同時接続数は10人前後を想定する（小規模社内業務システム）。

### 6.2 セキュリティ（NFR-S）
- NFR-S-01: 認証方式は ID（メール）＋パスワード。パスワードはハッシュ保存・min:8・confirmed。
- NFR-S-02: 認可は auth / admin の2段ミドルウェア（ロールベース）。
- NFR-S-03: ログイン試行回数制限（メール＋IP で5回失敗・約60秒ロック・成功でリセット）。
- NFR-S-04: 退職者はログイン拒否。
- NFR-S-05: 全 POST に CSRF トークンを付与。
- NFR-S-06: ステータス・role 等の入力フィルタは allowlist 方式で検証。
- NFR-S-07: セッションは DB 保存。
- NFR-S-08: PDF 生成はリモートリソース無効化・chroot 設定でローカルファイル参照を制限。
- NFR-S-09: 通信は TLS 必須とする。
- NFR-S-10: 顧客情報（個人情報）の取り扱いは社内規則に準拠する。脆弱性診断の定期実施は予定しない。
- NFR-S-11: 対応ブラウザは最新モダンブラウザとする（旧IE等は非対応）。

### 6.3 可用性・信頼性（NFR-R）
- NFR-R-01: バッチ二重起動防止 — 出荷取得 `batch:fetch-shipment-data` / 請求書 `batch:send-invoices` / 納品書 `batch:send-delivery-notes` を `Cache::lock(key, 3600)` で排他。失敗時はスキップ。
- NFR-R-02: ジョブ重複実行防止 — `WithoutOverlapping($id)->releaseAfter(10)`。
- NFR-R-03: スケジュール登録時は `withoutOverlapping()` + `runInBackground()` を付加。
- NFR-R-04: 残留レコード救済 — 送信バッチ起動時に `stuck-timeout`（既定60分）以上 `processing` のレコードを `pending` へ差し戻し（ワーカークラッシュ・予期せぬタイムアウトからの復旧）。
- NFR-R-05: ジョブ自動リトライ — `max_retries`（既定3・範囲0〜10）回、間隔 `retry_backoff`（既定30秒・固定値・範囲0〜3600）。
- NFR-R-06: 恒久的失敗（送付先0件・無効アドレス）は `failed_permanent` として自動リトライ対象外とし、手動対応に委ねる。
- NFR-R-07: バッチ完了は `BatchSummaryMail` で管理者に通知（`admin_notification_emails` 設定時）。
- NFR-R-08: 稼働率目標は99%（平日日中）とする。
- NFR-R-09: RTO/RPO は次営業日以内を目標とする。計画停止時間帯は特に定めない（小規模社内システムのため）。

### 6.4 保守性・運用性（NFR-M）
- NFR-M-01: リトライ・タイムアウト・通知先は `.env` ではなく system_settings（画面）で管理し、再デプロイなしに変更可能。
- NFR-M-02: バッチ実行は ShipmentFetchLog / SendMailLog に記録し、画面で履歴追跡できる。各書類の送信明細は SendMailLogItem（ポリモーフィック）に記録する。
- NFR-M-03: ジョブ失敗時はエラーメッセージを SendMailLogItem に1000文字まで記録する。
- NFR-M-04: 設定変更（system_settings）の反映は次回ジョブ生成時で、ワーカー再起動を要しない。
- NFR-M-05: バッチ起動は `Artisan::queue` 非同期で、画面は即時に受付完了を返す。
- NFR-M-06: ログ保存期間は7年間とする。
- NFR-M-07: 監視体制はアプリログ（Laravel Log）を目視で定期確認する運用とし、専用の監視・アラートツールは導入しない。

### 6.5 拡張性・移植性（NFR-E）
- NFR-E-01: 設定値は KVS（system_settings）で追加可能な構造。
- NFR-E-02: 書類種別は Invoice / DeliveryNote をポリモーフィック関連（send_mail_log_items）で扱い、種別追加に対応しやすい構造。
- NFR-E-03: キャッシュ/ロックは database（既定）または redis を切替可能。
- NFR-E-04: 基幹連携は `BACKBONE_API_URL` 未設定時にダミーデータ（空配列）で動作し、連携先なしでも起動可能。
- NFR-E-05: 現行の Docker Compose 単一サーバー構成で十分だが、将来的なスケールアウトに対応可能な構成とする。

---

## 7. 業務ルール・制約（BR）

### BR-01 ステータス遷移
**書類（invoices / delivery_notes）**
```
pending → processing → sent
                    → failed → pending（--retry-failed または bulkRequeue）
                    → failed_permanent（PermanentJobFailureException・手動対応）
```
- `failed_permanent` 遷移条件: ① 送付先メールアドレスが0件（recipientEmails() が空） ② いずれかのアドレスが FILTER_VALIDATE_EMAIL で無効。

**出荷取得ログ（shipment_fetch_logs）**
```
running → completed / failed
```

### BR-02 消費税算出
- `tax_amount = round(amount × tax / 100)`、`tax` は出荷取得時 10 固定。
- `tax`＝税率(%)、`tax_amount`＝税額(円)。
- 税額は出荷取得バッチ作成時に算出し、以後の再計算は行わない。金額・明細の編集機能は現在・将来ともに提供しないため、再計算ロジックは不要（2.3節参照）。

### BR-03 メール送信ログの状態判定（displayStatus）
```
failed_at あり                          → "failed"（失敗）
completed_at あり（failed_at なし）       → "completed"（完了）
両方 NULL（manual-resend 以外）          → "running"（実行中）
```
- failed_at が最優先。手動再送まとめ親（manual-resend）は状態判定・集計の対象外。

### BR-04 メールアドレス検証
- 送付先は最大3件（customer_email 系）。
- recipientEmails() は trim・空除去後の配列を返す。
- ジョブ実行時、各アドレスを FILTER_VALIDATE_EMAIL で検証し、1件でも無効なら failed_permanent。
- 画面編集（updateEmails、admin のみ）は `email` バリデーション・未入力は null 正規化・対象は failed / failed_permanent 状態のみ。
- 出荷取得バッチ側で customer_email の空白のみ文字列をバリデーションし、該当データはスキップする（NOT NULL を満たしつつ recipientEmails() が空になり failed_permanent を誘発する事象を防止）。

### BR-05 重複スキップ
- 出荷取得時、`delivery_number` / `invoice_number` が既存ならその書類作成をスキップし `skipped_count` に計上する。

### BR-06 リトライ・タイムアウト設定の制約
- 値域: pdf_timeout 10〜300秒 / retry_backoff 0〜3600秒 / max_retries 0〜10回。
- ジョブのフォールバック値はシーダー既定値と一致させる（pdf_timeout=60秒 / retry_backoff=30秒 / max_retries=3）。

### BR-07 手動再送・一括操作
- 手動再送（resend）は当日分 manual-resend 親に集約し dispatched_count を加算。完了/実行中の概念なし。まとめ親は削除不可（restrictOnDelete）。
- bulkRequeue（admin）は failed → pending に一括更新し retry_count を +1。retry_count >= 3 の件を含む場合は確認ダイアログを表示。retry_count >= 10 の件は対象外とし failed のまま残す。
- 手動完了機能（complete）は廃止。ログは保持するが、ダッシュボードのサマリー・一覧の両方から除外し別画面でのみ確認可能とする。

### BR-08 ユーザー管理の制約
- email は unique（ログイン ID）。role は general / admin のいずれか。
- パスワード min:8・confirmed。編集時は入力時のみ更新。
- 削除は物理削除。自分自身は削除不可。
- 退職者は retired_at をセットしログイン不可。一覧は既定で退職者を除外。

### BR-09 データ構造上の制約
- invoice_items / delivery_note_items は親への FK が cascade delete。
- send_mail_log_items.send_mail_log_id → send_mail_logs は restrictOnDelete（削除機能が存在・計画もないため、まとめ親レコードの削除自体を不可とし孤立明細の発生を防ぐ）。
- shipment_fetch_logs は書類と直接リレーションを持たない（実行ログのみ）。

### BR-10 技術制約（リバース対象として確定）
- PHP ^8.3 / Laravel ^13.7 / DomPDF ^3.1 / PestPHP ^4.7。
- DB は SQL Server（sqlsrv、db-sv03.solid-corp.local:1433、DB 名 ePostCable）。開発・本番とも外部接続で DB コンテナを持たない。
- キュー Redis、キャッシュ/ロック database または redis、セッション database、メール SMTP（開発 Mailpit）。
- 実行環境は Docker Compose（php / nginx / redis / mailpit / worker / scheduler）。

---

## 8. 検証マッピング（要件 ⇔ 検証観点）

実装が要件を満たすかを仕様ベースで検証するための観点。各 FR/NFR/BR の受入条件をテストケース化して照合する。

| 要件群 | 主な検証観点 |
|--------|-------------|
| FR-01 | ロック取得失敗時スキップ・重複スキップ・税額算出・status 遷移・ログ記録 |
| FR-02/03/04 | stuck 差し戻し・retry-failed 差し戻し・limit 件取得・行ロック・スケジュール時刻・通知送信 |
| FR-05/06 | 設定値動的取得・WithoutOverlapping・status ガード・PDF パス・アドレス検証・failed/failed_permanent 分岐 |
| FR-07〜13 | 権限（auth/admin）・ページネーション件数・フィルタ allowlist・状態別ボタン表示・各操作の状態前提 |
| FR-14/15 | 件名形式・PDF 添付・BCC 付与・空 PDF 例外 |
| FR-16/17 | 未認証リダイレクト・退職者拒否・試行回数制限・admin 保護・CSRF |
| NFR-R | 二重起動防止・残留救済・リトライ回数/間隔・恒久失敗の非リトライ |
| BR-01〜10 | 状態遷移・税額式・displayStatus 優先順位・重複スキップ・自己削除防止 |

---

## 9. 未確認事項リスト（解消済み）

questions.md にあった OQ-01〜OQ-12（+ DB-Q-01/02）の計14件は、2026-07-01 のヒアリングで全件解消済み。決定内容は本書の各該当セクションおよび `design/questions.md` に反映済み。

| ID | 論点 | 決定 |
|----|------|------|
| OQ-01 | ジョブフォールバック値とシーダー初期値の不一致 | シーダー値に統一（pdf_timeout=60秒 / retry_backoff=30秒）→ 5章 FR-05・7章 BR-06 |
| OQ-02 | tax / tax_amount の将来編集時の再計算要件 | 金額編集機能は現在・将来ともに提供しない（スコープ外） → 2章 2.3・7章 BR-02 |
| OQ-03 | updateEmails の権限レベル | admin 限定に変更 → 3章・5章 FR-08 |
| OQ-04 | WithoutOverlapping->releaseAfter(10) の整合性 | `expireAfter(300以上)` を明示指定 → 5章 FR-05 |
| OQ-05 | bulkRequeue の retry_count 加算意図 | 記録用カウンタ。retry_count>=3 は確認ダイアログ、>=10 は一括対象外 → 5章 FR-08・7章 BR-07 |
| OQ-06 | send_mail_log_items の nullOnDelete 運用 | restrictOnDelete に変更（削除機能なし・計画もないため） → 7章 BR-09 |
| OQ-07 | batch:fetch-shipment-data 未スケジュールの理由 | 毎日12:15〜12:30に自動実行するようスケジュール化 → 5章 FR-01・FR-04 |
| OQ-08 | SendMailLogController#complete の想定ユースケース | 手動完了機能は廃止。失敗ログはダッシュボード・一覧から除外し別画面のみで確認 → 5章 FR-10 |
| OQ-09 | customer_email の空白と recipientEmails() 空の矛盾 | 出荷取得バッチで空白バリデーションを追加 → 5章 FR-01・7章 BR-04 |
| OQ-10 | CSV ダウンロードの文字コード | UTF-8 BOM 付きで確定 → 5章 FR-08 |
| OQ-11 | 納品書 PDF 保存パスの基準日 | delivery_date（納品・出荷日）基準で確定 → 5章 FR-06 |
| OQ-12 | 定量的非機能目標の未定義 | 6章 NFR に具体的目標値を追記（性能・可用性・セキュリティ・運用・拡張性） |
| DB-Q-01 | error_message の型（varchar/nvarchar） | varchar(1000) で確定（マルチバイト切り詰めリスクは許容） → db-design.md |
| DB-Q-02 | invoices.amount と明細合計の整合保証 | 出荷取得時に固定明細を通常明細へ変換し整合性検証。不一致は伝票エラーマーキング → 5章 FR-01 |

詳細な検討経緯は `design/questions.md` を参照。
