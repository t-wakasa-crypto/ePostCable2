# 請求書メール配信システム 実装計画

> 作成日: 2026-07-06 / 作成: plan-designer
> 入力: `design/requirements.md`（FR-01〜17 / NFR / BR-01〜10）/ `design/basic-design.md` /
> `design/detailed-design.md` / `design/db-design.md` / `design/diagrams.md` /
> `docs/システム開発環境設計書.md`（技術スタック・モジュール型アーキテクチャ・コーディング規約）
> 性質: 実装済みシステムのリバース設計を、モジュール型 Laravel 13 プロジェクトとして
> 再構築するための実装計画。全タスクは初期状態（未完了 `- [ ]`）で生成する。
> チェックの更新は `implementer` の責務であり、本計画では行わない。

---

## 0. 前提・方針

- **アーキテクチャ**: `nwidart/laravel-modules` によるモジュール型構成（開発環境設計書 §2）。
  業務ドメイン単位で `Modules/` 配下に分割する。
- **想定モジュール**: `Shared`（横断・基盤）/ `Auth` / `Dashboard` / `Invoice` /
  `DeliveryNote` / `SendMailLog` / `ShipmentFetch` / `SystemSetting` / `User`。
  横断的コード（基底クラス・共通例外 `PermanentJobFailureException` 等）は
  `app/` 直下（`app/Support/` `app/Exceptions/`）に置く。
- **DB 方針**: 開発は MySQL コンテナ、本番は MySQL / SQL Server 切替可（開発環境設計書 §9）。
  Eloquent / Schema ビルダのみでドライバ非依存に実装する。設計書（db-design.md）の
  型・制約は Laravel `Schema` の標準機能で表現する。
- **ステータス管理**: 文字列直書きを禁止し `STATUS_*` クラス定数で定義（開発環境設計書 §4）。
- **テスト**: PestPHP + `RefreshDatabase`。各 FR/NFR/BR の受入条件をテスト化する。
- **工数目安**: 小（〜半日）/ 中（1〜2日）/ 大（3日以上）。「大」は原則さらに分割済み。

---

## 1. フェーズ定義

| フェーズ | 内容 | 主な狙い |
|---|---|---|
| Phase 1: 基盤 | 開発環境・モジュール基盤・DB マイグレーション・シーダー・認証 | 他タスクの土台。ここが揃うまで機能実装は始められない |
| Phase 2: MVP | 出荷取得〜書類作成〜送信バッチ〜ジョブ〜メール送信の最小 E2E と最低限の閲覧画面 | 「取り込み→配信」の縦串が1本通り、動作確認できる状態 |
| Phase 3: 基本機能 | requirements.md の全必須機能（各管理画面・手動是正・システム設定・全 NFR） | 要件をすべて満たす |
| Phase 4: 拡張・仕上げ | パフォーマンス最適化・運用補助・テスト網羅・仕上げ | 品質と運用性の向上 |

---

## 2. 依存関係の全体像

```
T001 環境 → T002 modules → T003 共通基盤(例外/定数)
   ├─ T010〜T017 DB(マイグレーション) → T018 シーダー
   ├─ T020〜T023 認証(Auth)  ← 早期実装（認可の影響範囲が大きいため）
   └─ 各モデル(T030〜) → Service(T040/T041) → Command(T050〜) → Job(T060〜) → Mail(T070〜)
                                              → 画面(Controller/View T080〜)
```

- DB・認証（Phase 1）→ モデル・Service → Command/Job（バッチ・ジョブ）→ 画面（Controller）の順。
- 外部サービス連携（基幹 API）は疎結合（未設定時ダミー空配列）で先行実装し、
  実 API 接続は後回しでよい（NFR-E-04）。

---

## 3. タスクリスト

### Phase 1: 基盤

#### [T001] 開発環境の起動確認（Docker Compose）
- **フェーズ**: Phase 1
- **工数**: 中
- **依存**: なし
- **内容**: php / nginx / redis / worker / scheduler / mailpit / MySQL コンテナで
  Laravel 13（PHP ^8.3）が起動することを確認する。`.env` を整備（DB/redis/mail/queue）。
- **完了条件**: `php artisan --version` が通り、ブラウザで Laravel 初期画面が表示され、
  worker/scheduler が起動する。
- [x] T001: 開発環境の起動確認（Docker Compose）

#### [T002] モジュール基盤の導入（laravel-modules）
- **フェーズ**: Phase 1
- **工数**: 小
- **依存**: T001
- **内容**: `nwidart/laravel-modules` を導入し、`Shared` / `Auth` / `Dashboard` /
  `Invoice` / `DeliveryNote` / `SendMailLog` / `ShipmentFetch` / `SystemSetting` /
  `User` モジュールの雛形を生成する（開発環境設計書 §2）。
- **完了条件**: 各モジュールが認識され、モジュール内 `routes/web.php` が読み込まれる。
- [x] T002: モジュール基盤の導入（laravel-modules）

#### [T003] 横断的な共通基盤の実装
- **フェーズ**: Phase 1
- **工数**: 小
- **依存**: T002
- **内容**: `app/Exceptions/PermanentJobFailureException`（RuntimeException 継承）、
  共通の基底 Command / Job、ステータス定数の方針を整備（開発環境設計書 §4 / BR-01 / NFR-R-06）。
- **完了条件**: 共通例外・基底クラスが各モジュールから利用可能。
- [x] T003: 横断的な共通基盤の実装

#### [T004] Laravel 標準テーブルのマイグレーション整備
- **フェーズ**: Phase 1
- **工数**: 小
- **依存**: T001
- **内容**: sessions（DB セッション NFR-S-07）/ cache（Cache::lock 用 NFR-R-01）/
  failed_jobs を用意（db-design §2.10 / §5.2）。QUEUE は redis。
- **完了条件**: `migrate` が通り、セッション DB 保存・Cache::lock が動作する。
- [x] T004: Laravel 標準テーブルのマイグレーション整備

#### [T010] users テーブル マイグレーション
- **フェーズ**: Phase 1
- **工数**: 小
- **依存**: T002
- **内容**: db-design §2.1 に従い users（email unique / role CHECK / retired_at）を作成。
- **完了条件**: マイグレーションが両ドライバで通り、制約が db-design と一致。
- [x] T010: users テーブル マイグレーション

#### [T011] invoices テーブル マイグレーション
- **フェーズ**: Phase 1
- **工数**: 小
- **依存**: T002
- **内容**: db-design §2.2（invoice_number unique / status CHECK / tax・tax_amount /
  retry_count / issue_date）＋インデックス `(status, created_at)`・`status`（§3.1 / NFR-P-03）。
- **完了条件**: スキーマ・インデックスが db-design と一致。
- [x] T011: invoices テーブル マイグレーション

#### [T012] invoice_items テーブル マイグレーション
- **フェーズ**: Phase 1
- **工数**: 小
- **依存**: T011
- **内容**: db-design §2.3（invoice_id FK cascade delete / FK インデックス明示・§3.1）。
- **完了条件**: FK cascade・インデックスが一致。
- [x] T012: invoice_items テーブル マイグレーション

#### [T013] delivery_notes テーブル マイグレーション
- **フェーズ**: Phase 1
- **工数**: 小
- **依存**: T002
- **内容**: db-design §2.4（delivery_number unique / status CHECK / delivery_date /
  issue_date）＋インデックス（§3.1）。
- **完了条件**: スキーマ・インデックスが一致。
- [x] T013: delivery_notes テーブル マイグレーション

#### [T014] delivery_note_items テーブル マイグレーション
- **フェーズ**: Phase 1
- **工数**: 小
- **依存**: T013
- **内容**: db-design §2.5（delivery_note_id FK cascade / FK インデックス）。
- **完了条件**: FK cascade・インデックスが一致。
- [x] T014: delivery_note_items テーブル マイグレーション

#### [T015] send_mail_logs テーブル マイグレーション
- **フェーズ**: Phase 1
- **工数**: 小
- **依存**: T002
- **内容**: db-design §2.6（batch_key CHECK / started_at・completed_at・failed_at /
  dispatched_count・reset_count・retry_failed_count / execution_seconds）＋
  batch_key・started_at インデックス（§3.1）。
- **完了条件**: スキーマ・インデックスが一致。
- [x] T015: send_mail_logs テーブル マイグレーション

#### [T016] send_mail_log_items テーブル マイグレーション（ポリモーフィック）
- **フェーズ**: Phase 1
- **工数**: 小
- **依存**: T015
- **内容**: db-design §2.7（sendable_type/sendable_id / status CHECK /
  error_message 1000字 / send_mail_log_id は restrictOnDelete=ON DELETE NO ACTION）＋
  log_id・sendable 複合・status インデックス（§3.1 / BR-09 / NFR-E-02）。
- **完了条件**: restrictOnDelete・複合インデックスが db-design と一致。
- [x] T016: send_mail_log_items テーブル マイグレーション（ポリモーフィック）

#### [T017] shipment_fetch_logs / system_settings テーブル マイグレーション
- **フェーズ**: Phase 1
- **工数**: 小
- **依存**: T002
- **内容**: db-design §2.8（shipment_fetch_logs：status CHECK / 各カウント）＋
  §2.9（system_settings：key unique / type CHECK / min_value・max_value）＋インデックス（§3.1）。
- **完了条件**: 両テーブルのスキーマ・インデックスが一致。
- [x] T017: shipment_fetch_logs / system_settings テーブル マイグレーション

#### [T018] シーダー実装（system_settings / 初期管理者）
- **フェーズ**: Phase 1
- **工数**: 小
- **依存**: T010, T017
- **内容**: db-design §4。SystemSettingSeeder（pdf_timeout=60 / retry_backoff=30 /
  max_retries=3 / admin_notification_emails / mail_bcc_address）と AdminUserSeeder
  （開発用管理者）。フォールバック値はシーダー値と一致（BR-06 / OQ-01）。
- **完了条件**: `db:seed` で初期設定・管理者が投入され、値域が設計と一致。
- [x] T018: シーダー実装（system_settings / 初期管理者）

#### [T020] User モデル実装
- **フェーズ**: Phase 1
- **工数**: 小
- **依存**: T010
- **内容**: 詳細設計 §4.7。`isAdmin()` / `isRetired()` / 退職者除外スコープ /
  role フィルタスコープ（allowlist）/ password ハッシュ（BR-08 / FR-12 / FR-16）。
- **完了条件**: 各メソッド・スコープの単体テストが通る。
- [x] T020: User モデル実装

#### [T021] 認証（ログイン/ログアウト）実装
- **フェーズ**: Phase 1
- **工数**: 中
- **依存**: T020, T004
- **内容**: 詳細設計 §1.4.8 / §3.5。LoginController（showLoginForm/login/logout）、
  メール+パスワード認証、退職者ログイン拒否（isRetired）、DB セッション（FR-16 / NFR-S-01/04/07）。
- **完了条件**: 未認証は /login リダイレクト、正常ログイン、退職者拒否がテストで確認できる。
- [x] T021: 認証（ログイン/ログアウト）実装

#### [T022] ログイン試行回数制限（RateLimiter）
- **フェーズ**: Phase 1
- **工数**: 小
- **依存**: T021
- **内容**: メール+IP で5回失敗・約60秒ロック・成功でリセット（FR-16 / NFR-S-03）。
- **完了条件**: 5回失敗でロック、成功でカウンタリセットがテストで確認できる。
- [x] T022: ログイン試行回数制限（RateLimiter）

#### [T023] 認可ミドルウェア（auth / admin）と CSRF
- **フェーズ**: Phase 1
- **工数**: 小
- **依存**: T021
- **内容**: 詳細設計 §7。auth（管理画面全体）+ admin（AdminMiddleware・isAdmin 判定）の
  2段保護、全 POST/PUT/DELETE の CSRF（FR-17 / NFR-S-02/05）。
- **完了条件**: 非 admin が admin ルートで 403、CSRF なし POST が拒否される。
- [x] T023: 認可ミドルウェア（auth / admin）と CSRF

### Phase 2: MVP（縦串 E2E）

#### [T030] Invoice / DeliveryNote モデル実装
- **フェーズ**: Phase 2
- **工数**: 中
- **依存**: T011, T013, T003
- **内容**: 詳細設計 §4.1。STATUS_* 定数、`recipientEmails()`（trim/空除去・最大3件）、
  `items()`（hasMany）、`sendMailLogItems()`（morphMany）、status フィルタスコープ
  （BR-01 / BR-04 / NFR-E-02）。
- **完了条件**: recipientEmails・スコープ・リレーションの単体テストが通る。
- [x] T030: Invoice / DeliveryNote モデル実装

#### [T031] items / SendMailLog / SendMailLogItem / ShipmentFetchLog / SystemSetting モデル実装
- **フェーズ**: Phase 2
- **工数**: 中
- **依存**: T012, T014, T015, T016, T017
- **内容**: 詳細設計 §4.2〜§4.6。invoice_items/delivery_note_items（belongsTo）、
  SendMailLog（`displayStatus()`・`manualResendBucket()`・フィルタスコープ）、
  SendMailLogItem（morphTo・restrictOnDelete）、ShipmentFetchLog、
  SystemSetting（`get()`・`mailBccAddresses()`・型検証）（BR-03/06/07/09 / NFR-M-03）。
- **完了条件**: displayStatus 優先順位・SystemSetting::get フォールバックの単体テストが通る。
- [x] T031: items / SendMailLog / SendMailLogItem / ShipmentFetchLog / SystemSetting モデル実装

#### [T040] ShipmentFetchService 実装（基幹 API・疎結合）
- **フェーズ**: Phase 2
- **工数**: 小
- **依存**: T003
- **内容**: 詳細設計 §1.3.1。`fetch()` が `BACKBONE_API_URL`（config services.backbone.url）へ
  HTTP Client timeout(30)、未設定時は空配列（NFR-P-06 / NFR-E-04）。
- **完了条件**: 未設定時に空配列を返す・タイムアウト設定のテストが通る（HTTP はモック）。
- [x] T040: ShipmentFetchService 実装（基幹 API・疎結合）

#### [T041] PdfService 実装（DomPDF）
- **フェーズ**: Phase 2
- **工数**: 中
- **依存**: T030
- **内容**: 詳細設計 §1.3.2。barryvdh/laravel-dompdf ^3.1、A4 縦、LoadDompdfFonts、
  isRemoteEnabled=false・chroot、空出力で RuntimeException（FR-15 / NFR-S-08）。
- **完了条件**: PDF バイナリ生成・空出力例外のテストが通る。
- [x] T041: PdfService 実装（DomPDF）

#### [T042] 帳票 Blade テンプレート（請求書 / 納品書）
- **フェーズ**: Phase 2
- **工数**: 中
- **依存**: T041
- **内容**: PdfService が使う請求書・納品書の Blade（明細・金額・税額・顧客名等）を実装。
  日本語フォント表示を確認（FR-15）。
- **完了条件**: 生成 PDF に明細・金額・税額が正しく表示される。
- [x] T042: 帳票 Blade テンプレート（請求書 / 納品書）

#### [T050] FetchShipmentData コマンド実装（batch:fetch-shipment-data）
- **フェーズ**: Phase 2
- **工数**: 大
- **依存**: T030, T031, T040
- **内容**: 詳細設計 §1.1.1 / §3.1。Cache::lock（3600）多重起動防止、ShipmentFetchLog(running)、
  ShipmentFetchService.fetch()、出荷1件ごとにトランザクションで重複スキップ・
  納品書/請求書+明細を pending 作成・tax=10/tax_amount 算出・customer_email 空白バリデーション、
  完了/失敗ログ更新（FR-01 / BR-02/05 / NFR-R-01 / OQ-09）。
- **完了条件**: 重複スキップ・税額算出・空白 email スキップ・ログ記録・ロック失敗スキップの
  各受入条件がテストで確認できる。
- [x] T050: FetchShipmentData コマンド実装（batch:fetch-shipment-data）

#### [T051] 固定明細変換・金額整合性検証（出荷取得内）
- **フェーズ**: Phase 2
- **工数**: 中
- **依存**: T050
- **内容**: 送料・値引き等の固定明細を通常明細形式へ変換し、明細合計と amount の整合性を検証。
  不一致は書類をエラーマーキング（FR-01 / DB-Q-02）。
- **完了条件**: 固定明細変換と不一致検出（エラーマーキング）のテストが通る。
- [x] T051: 固定明細変換・金額整合性検証（出荷取得内）

#### [T060] ProcessInvoiceJob 実装
- **フェーズ**: Phase 2
- **工数**: 大
- **依存**: T030, T041, T070
- **内容**: 詳細設計 §1.2 / §3.3。コンストラクタで system_settings 動的取得
  （$maxExceptions/$backoff/$timeout・フォールバック 3/30/60）、
  WithoutOverlapping(id)->releaseAfter(10)->expireAfter(300)、status ガード、
  PdfService 生成→Storage 保存（invoices/{年}/{月}）、recipientEmails 空/無効アドレスで
  PermanentJobFailureException、InvoiceMail 送信、成功で sent 更新、
  failed() 分岐（failed / failed_permanent・error_message 1000字）
  （FR-05 / NFR-R-02/05/06 / NFR-M-03/04）。
- **完了条件**: 動的設定取得・status ガード・PDF パス・アドレス検証分岐・failed 分岐が
  テストで確認できる。
- [x] T060: ProcessInvoiceJob 実装

#### [T061] ProcessDeliveryNoteJob 実装
- **フェーズ**: Phase 2
- **工数**: 中
- **依存**: T060, T071
- **内容**: 詳細設計 §1.2.5。ProcessInvoiceJob と同一。保存先 delivery-notes/{年}/{月}
  （年月基準は delivery_date）、DeliveryNoteMail（FR-06 / OQ-11）。
- **完了条件**: FR-05 相当の受入条件＋ delivery_date 基準パスがテストで確認できる。
- [x] T061: ProcessDeliveryNoteJob 実装

#### [T070] InvoiceMail 実装（PDF 添付 / BCC）
- **フェーズ**: Phase 2
- **工数**: 小
- **依存**: T031, T042
- **内容**: 詳細設計 §1.5。件名 `【請求書】{invoice_number}`、PDF 添付、
  envelope で `SystemSetting::mailBccAddresses()` を BCC 付与（未設定時なし）（FR-14）。
- **完了条件**: 件名・添付・BCC のテスト（Mail::fake）が通る。
- [x] T070: InvoiceMail 実装（PDF 添付 / BCC）

#### [T071] DeliveryNoteMail 実装
- **フェーズ**: Phase 2
- **工数**: 小
- **依存**: T070
- **内容**: 件名 `【納品書】{delivery_number}`、PDF 添付、共通 BCC（FR-14）。
- **完了条件**: 件名・添付・BCC のテストが通る。
- [x] T071: DeliveryNoteMail 実装

#### [T072] SendInvoices コマンド実装（batch:send-invoices）
- **フェーズ**: Phase 2
- **工数**: 大
- **依存**: T030, T031, T060
- **内容**: 詳細設計 §1.1.2 / §3.2。Cache::lock、SendMailLog 作成、
  stuck 差し戻し（processing≧stuck-timeout→pending・retry_count++・reset_count）、
  --retry-failed 差し戻し、pending を created_at 昇順 limit(100) 取得、
  各書類をトランザクション＋行ロックで processing 更新→SendMailLogItem 作成→
  ProcessInvoiceJob dispatch、完了ログ、BatchSummaryMail 通知
  （FR-02 / NFR-R-01/04 / NFR-P-02/03/04）。
- **完了条件**: stuck 差し戻し・retry-failed・limit 取得・行ロック・通知の受入条件がテストで確認できる。
- [x] T072: SendInvoices コマンド実装（batch:send-invoices）

#### [T073] SendDeliveryNotes コマンド実装（batch:send-delivery-notes）
- **フェーズ**: Phase 2
- **工数**: 中
- **依存**: T072, T061
- **内容**: 詳細設計 §1.1.3。SendInvoices と同一（DeliveryNote / ロックキー
  batch:send-delivery-notes / ProcessDeliveryNoteJob）（FR-03）。
- **完了条件**: FR-02 相当の受入条件がテストで確認できる。
- [x] T073: SendDeliveryNotes コマンド実装（batch:send-delivery-notes）

#### [T074] BatchSummaryMail 実装
- **フェーズ**: Phase 2
- **工数**: 小
- **依存**: T031
- **内容**: 件名 `【バッチ完了】{batch_name}メール送信 {実行開始日時} 実行分`、添付なし、
  共通 BCC。admin_notification_emails 未設定時は警告ログのみ（FR-14 / NFR-R-07）。
- **完了条件**: 件名・設定時送信/未設定時ログのテストが通る。
- [x] T074: BatchSummaryMail 実装

#### [T080] 最小 Invoice / DeliveryNote 一覧・詳細（閲覧のみ）
- **フェーズ**: Phase 2
- **工数**: 中
- **依存**: T030, T031, T023
- **内容**: 一覧（20件/ページ・status フィルタ allowlist・サマリー）と詳細（明細・
  送信履歴表示）の閲覧部分のみ（FR-08/09 の閲覧 / NFR-P-01 / NFR-S-06）。
- **完了条件**: 認証済みで一覧・詳細が表示され、フィルタが allowlist で動く。E2E で
  出荷取得→送信→sent 反映が画面から確認できる。
- [x] T080: 最小 Invoice / DeliveryNote 一覧・詳細（閲覧のみ）

### Phase 3: 基本機能

#### [T090] スケジュール定義（Kernel schedule）
- **フェーズ**: Phase 3
- **工数**: 小
- **依存**: T072, T073, T050
- **内容**: 詳細設計 §1.1.4。毎日01:00 delivery-notes / 01:30 invoices、
  毎週月曜02:00・02:30 --retry-failed、毎日12:15〜12:30 fetch-shipment-data。
  全て withoutOverlapping()+runInBackground()（FR-04 / NFR-R-03）。
- **完了条件**: `schedule:list` に全登録が現れ、修飾が付与されている。
- [x] T090: スケジュール定義（Kernel schedule）

#### [T091] Invoice 手動再送（resend）
- **フェーズ**: Phase 3
- **工数**: 中
- **依存**: T080, T060
- **内容**: 詳細設計 §1.4.7 / §3.4。トランザクションで processing 更新→当日 manual-resend
  親を取得/作成→SendMailLogItem 作成→dispatched_count++→ProcessInvoiceJob dispatch
  （FR-08 / BR-07）。general/admin 可。
- **完了条件**: 当日集約・dispatched_count 加算・ジョブ投入がテストで確認できる。
- [x] T091: Invoice 手動再送（resend）

#### [T092] Invoice メールアドレス編集（updateEmails・admin 限定）
- **フェーズ**: Phase 3
- **工数**: 小
- **依存**: T080, T023
- **内容**: 詳細設計 §1.4.2。failed/failed_permanent のみ・1〜3件・nullable email・
  未入力 null 正規化・admin 限定（FR-08 / BR-04 / OQ-03）。
- **完了条件**: 対象 status 制限・null 正規化・非 admin 403 がテストで確認できる。
- [x] T092: Invoice メールアドレス編集（updateEmails・admin 限定）

#### [T093] Invoice 一括再キュー（bulkRequeue・admin 限定）
- **フェーズ**: Phase 3
- **工数**: 中
- **依存**: T080, T023
- **内容**: 詳細設計 §1.4.2。failed→pending 一括・retry_count++、retry_count>=3 を含む場合は
  確認ダイアログ、retry_count>=10 は対象外（failed のまま）（FR-08 / BR-07 / OQ-05）。
- **完了条件**: 一括更新・>=3 ダイアログ・>=10 除外がテストで確認できる。
- [x] T093: Invoice 一括再キュー（bulkRequeue・admin 限定）

#### [T094] Invoice バッチ手動起動（runBatch・admin 限定）
- **フェーズ**: Phase 3
- **工数**: 小
- **依存**: T072, T023
- **内容**: 詳細設計 §1.4.2。`Artisan::queue('batch:send-invoices')` 非同期起動・即時受付
  （FR-08 / NFR-M-05）。
- **完了条件**: admin のみ・非同期投入がテストで確認できる。
- [x] T094: Invoice バッチ手動起動（runBatch・admin 限定）

#### [T095] Invoice PDF ダウンロード
- **フェーズ**: Phase 3
- **工数**: 小
- **依存**: T080, T041
- **内容**: 詳細設計 §1.4.2。Storage にあれば返却、なければ PdfService 即時生成（非保存）
  （FR-08 / FR-15 / NFR-P-05）。
- **完了条件**: 保存済み返却・未保存時即時生成（非保存）がテストで確認できる。
- [x] T095: Invoice PDF ダウンロード

#### [T096] Invoice CSV ダウンロード（UTF-8 BOM）
- **フェーズ**: Phase 3
- **工数**: 小
- **依存**: T080
- **内容**: 詳細設計 §1.4.2。status allowlist、UTF-8 BOM 付き、複数送付先 ` / ` 区切り
  （FR-08 / OQ-10）。
- **完了条件**: BOM 付与・区切り・フィルタがテストで確認できる。
- [x] T096: Invoice CSV ダウンロード（UTF-8 BOM）

#### [T097] DeliveryNote 一覧・詳細・各操作（Invoice と同等）
- **フェーズ**: Phase 3
- **工数**: 中
- **依存**: T091, T092, T093, T094, T095, T096, T073
- **内容**: 詳細設計 §1.4.3。DeliveryNoteController で resend/updateEmails/bulkRequeue/
  runBatch(batch:send-delivery-notes)/PDF/CSV を Invoice と同一仕様で実装（FR-09）。
- **完了条件**: FR-08 相当の各操作が納品書側でテスト確認できる。
- [x] T097: DeliveryNote 一覧・詳細・各操作（Invoice と同等）

#### [T100] Dashboard 実装
- **フェーズ**: Phase 3
- **工数**: 中
- **依存**: T030, T031
- **内容**: 詳細設計 §1.4.1。書類 status 別件数、SendMailLog 直近/集計
  （displayStatus・failed_at 優先・manual-resend 除外）、ShipmentFetchLog 直近、
  各送信バッチ最終実行（FR-07 / BR-03）。
- **完了条件**: 集計値・manual-resend 除外がテストで確認できる。
- [x] T100: Dashboard 実装

#### [T101] SendMailLog 一覧・詳細
- **フェーズ**: Phase 3
- **工数**: 中
- **依存**: T031, T023
- **内容**: 詳細設計 §1.4.4 / §5.3。一覧（filter allowlist: completed/running/
  manual_resend/has_pending/has_sent/has_failure/has_failure_permanent/failed・20件/ページ）、
  詳細（明細 50件/ページ）。complete 機能は実装しない（廃止）。失敗ログはダッシュボード・
  一覧から除外し別画面のみ表示（FR-10 / BR-07 / OQ-08 / NFR-P-01）。
- **完了条件**: フィルタ allowlist・ページ件数・除外仕様がテストで確認できる。
- [x] T101: SendMailLog 一覧・詳細

#### [T102] ShipmentFetchLog 一覧・バッチ手動起動
- **フェーズ**: Phase 3
- **工数**: 小
- **依存**: T031, T050, T023
- **内容**: 詳細設計 §1.4.5。status allowlist ページネーション、runBatch
  （`Artisan::queue('batch:fetch-shipment-data')`・admin のみ）（FR-11 / NFR-M-05）。
- **完了条件**: フィルタ・admin 限定起動がテストで確認できる。
- [x] T102: ShipmentFetchLog 一覧・バッチ手動起動

#### [T103] User 管理（CRUD・admin 限定）
- **フェーズ**: Phase 3
- **工数**: 中
- **依存**: T020, T023
- **内容**: 詳細設計 §1.4.6。一覧（20件/ページ・role フィルタ・退職者既定除外・
  include_retired）、作成（email unique/password min:8 confirmed/role in）、
  編集（password 入力時のみ/retired トグル）、削除（物理・自己削除不可）
  （FR-12 / BR-08）。
- **完了条件**: 各 CRUD・自己削除防止・退職者除外がテストで確認できる。
- [x] T103: User 管理（CRUD・admin 限定）

#### [T104] SystemSetting 設定画面・テストメール（admin 限定）
- **フェーズ**: Phase 3
- **工数**: 中
- **依存**: T031, T023, T074
- **内容**: 詳細設計 §1.4.7 / §4.6。設定一覧・更新（integer は min/max 検証、
  emails は 1行1アドレス FILTER_VALIDATE_EMAIL・改行区切り保存）、TestMail 送信。
  変更値は次回ジョブ生成時反映（FR-13 / BR-06 / NFR-M-01/04）。
- **完了条件**: 値域検証・emails 検証・テストメール送信がテストで確認できる。
- [x] T104: SystemSetting 設定画面・テストメール（admin 限定）

#### [T105] TestMail 実装
- **フェーズ**: Phase 3
- **工数**: 小
- **依存**: T031
- **内容**: 件名 `【テスト】メール送信テスト`、添付なし、共通 BCC（FR-14）。
- **完了条件**: 件名・BCC・送信のテストが通る。
- [x] T105: TestMail 実装

#### [T106] 状態別ボタン表示制御・権限ガード（画面横断）
- **フェーズ**: Phase 3
- **工数**: 中
- **依存**: T091, T092, T093, T097, T101
- **内容**: 詳細設計 §5.4 / §7。各画面で isAdmin・書類 status・batch_key に応じた
  ボタン表示制御と allowlist 入力検証を統一（FR-08〜13 / FR-17 / NFR-S-06）。
- **完了条件**: 権限・状態に応じたボタン表示/非表示がテストで確認できる。
- [x] T106: 状態別ボタン表示制御・権限ガード（画面横断）

### Phase 4: 拡張・仕上げ

#### [T110] エラーハンドリング方針の統一実装
- **フェーズ**: Phase 4
- **工数**: 中
- **依存**: T050, T060, T072
- **内容**: 詳細設計 §6。各層の失敗事象（API/DB 例外→ログ failed、PDF 空出力、
  恒久失敗、リトライ枯渇、残留救済）を方針表どおりに整備・記録（NFR-R / NFR-M-03）。
- **完了条件**: 各失敗ケースの記録先・遷移が設計表と一致することをテストで確認。
- [x] T110: エラーハンドリング方針の統一実装

#### [T111] 実 API 接続対応（基幹 API のデータ契約整形）
- **フェーズ**: Phase 4
- **工数**: 中
- **依存**: T040, T050
- **内容**: 詳細設計 §2.3。基幹 API レスポンス（delivery_number/invoice_number/amount/
  customer_email 系/明細/日付）を出荷データ配列へ整形する処理を実 API 仕様に合わせて確定
  （NFR-P-06 / NFR-E-04）。
- **完了条件**: 実データ契約でのマッピングがテスト（レスポンスモック）で確認できる。
- [x] T111: 実 API 接続対応（基幹 API のデータ契約整形）

#### [T112] キャッシュ/ロックの database⇄redis 切替確認
- **フェーズ**: Phase 4
- **工数**: 小
- **依存**: T050, T072
- **内容**: Cache::lock が database / redis の両バックエンドで動作することを確認（NFR-E-03）。
- **完了条件**: 両設定で二重起動防止が動作する。
- [x] T112: キャッシュ/ロックの database⇄redis 切替確認

#### [T113] DB ドライバ切替（mysql / sqlsrv）動作確認
- **フェーズ**: Phase 4
- **工数**: 中
- **依存**: T010〜T018
- **内容**: 開発環境設計書 §9。全マイグレーション・主要クエリが mysql / sqlsrv 双方で
  動作することを確認（ドライバ固有構文を排除）。
- **完了条件**: 両ドライバでテストスイートが通る。
- [x] T113: DB ドライバ切替（mysql / sqlsrv）動作確認

#### [T114] 受入テストの網羅（FR/NFR/BR 対応）
- **フェーズ**: Phase 4
- **工数**: 大
- **依存**: Phase 3 全タスク
- **内容**: requirements.md §8 検証マッピングに沿って、未カバーの FR/NFR/BR 受入条件を
  Pest テスト化し網羅する（開発環境設計書 §5）。
- **完了条件**: 検証マッピングの全観点にテストが存在し green。
- [x] T114: 受入テストの網羅（FR/NFR/BR 対応）

#### [T115] 運用補助（ログ確認・PDF パス・統計/インデックス運用メモ）
- **フェーズ**: Phase 4
- **工数**: 小
- **依存**: T114
- **内容**: db-design §6.3 の SQL Server 運用留意点（UPDATE STATISTICS・インデックス
  再編成）と、ログ保存7年・目視監視方針を運用ドキュメント化（NFR-M-06/07）。
- **完了条件**: 運用手順が docs にまとまる。
- [x] T115: 運用補助（ログ確認・PDF パス・統計/インデックス運用メモ）

---

## 4. 見積もりサマリー

| フェーズ | タスク数 | 目安 |
|---|---|---|
| Phase 1: 基盤 | 15（T001〜T004・T010〜T018・T020〜T023） | 環境・DB・認証の土台 |
| Phase 2: MVP | 15（T030〜T031・T040〜T042・T050〜T051・T060〜T061・T070〜T074・T080） | 縦串 E2E |
| Phase 3: 基本機能 | 15（T090〜T097・T100〜T106） | 全画面・全操作 |
| Phase 4: 拡張・仕上げ | 6（T110〜T115） | 品質・運用性 |

---

## 5. リスクと対処方針

| # | リスク | 対処方針 | 関連 |
|---|---|---|---|
| RK-1 | 外部 SQL Server 依存（DB 障害・NW 断で全機能停止） | 開発は MySQL で進め、接続タイムアウト/監視は運用設計へ委譲。Eloquent でドライバ非依存に保つ | basic R-11 / 開発環境設計書 §9 |
| RK-2 | tax（税率）と tax_amount（税額）の命名類似による混同 | 定数・コメントを徹底し、算出は出荷取得時のみ（再計算なし）に限定。テストで式を固定 | BR-02 / db-design §2.2 |
| RK-3 | WithoutOverlapping の expireAfter 未指定で 300 秒ジョブ中にロック失効 | expireAfter(300) を明示指定（設計どおり）。T060 で必須確認 | OQ-04 / 詳細 §1.2.2 |
| RK-4 | ジョブのフォールバック値とシーダー値の不一致 | フォールバックをシーダー値（60/30/3）に統一。T018/T060 で一致を検証 | OQ-01 / BR-06 |
| RK-5 | ポリモーフィック整合性は DB 制約で担保できない | Eloquent morph で管理し、restrictOnDelete で親削除を禁止。複合インデックス付与 | BR-09 / NFR-E-02 |
| RK-6 | customer_email 空白のみで failed_permanent 誘発 | 出荷取得バッチ（T050）で空白バリデーション・スキップを実装 | OQ-09 / BR-04 |
| RK-7 | 「大」タスク（T050/T060/T072/T114）の遅延 | 受入条件単位でサブ確認しつつ実装。T051 等で一部を切り出し済み | 本計画 §3 |
| RK-8 | DB ドライバ固有構文の混入で切替不能 | Eloquent/Schema のみ使用。T113 で両ドライバ検証 | 開発環境設計書 §9 |

---

## 6. 補足

- 本計画のタスクはすべて初期状態（`- [ ]` 未完了）で生成した。進捗チェックの更新は
  `implementer` が行う（本計画の再生成は既存チェックを失うため、機能追加時は追記モードで扱う）。
- 機能追加が発生した場合は `design/feature-log.md` 経由の追記モードで、
  最大タスク番号（現状 T115）の続きから新規タスクを起こす。
