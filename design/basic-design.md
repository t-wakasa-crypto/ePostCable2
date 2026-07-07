# 請求書メール配信システム 基本設計書

> リバース元: `請求書メール配信システム仕様.md`（作成 2026-06-11 / 最終更新 2026-06-25）
> 入力: `design/requirements.md`（FR-01〜17 / NFR / BR-01〜10 / OQ-01〜12）/ `design/gathered-materials.md`
> 作成日: 2026-06-29 / 作成: basic-designer
> 性質: **実装済みシステムのリバース基本設計**。新規の技術選定は行わず、確定済み構成を基本設計として整理し、要件ID（FR / NFR / BR）と対応づけて検証可能にすることを目的とする。
> 更新: 2026-07-01 — 全論点（OQ-01〜12・DB-Q-01/02）解消済み。決定内容を本文へ反映（詳細は10章・`design/questions.md`参照）。

---

## 1. 設計方針

### 1.1 本書のゴール
- requirements.md の各要件（FR / NFR / BR）を、Laravel の層構造・コンテナ構成・データフローへ写像し、「どのコンポーネントがどの要件を満たすか」を一意にたどれる状態にする。
- リバースであるため、技術選定は「現状追認 ＋ 設計上の意味づけ」として整理する（新規比較検討は行わない）。
- 定量的非機能目標は2026-07-01のヒアリングで合意済み（requirements.md 6章参照。レスポンス3秒以内・稼働率99%・ログ保存期間7年 等）。

### 1.2 アーキテクチャ概要
- **方式**: Laravel MVC ＋ 非同期キュー（Redis）＋ スケジューラ（cron）の3系統を組み合わせた Web ＋ バッチ ＋ ジョブの複合アーキテクチャ。
- **同期系（Web）**: 管理画面の閲覧・操作。即応性を重視し、重い処理（バッチ・PDF生成・送信）は同期処理に含めない。
- **非同期系（Batch / Job）**: 出荷取得・送信バッチは `Artisan::queue` でキュー投入し、画面は即時に受付完了を返す（NFR-M-05）。PDF生成・メール送信はキュージョブ（worker）が処理する。
- **状態の単一情報源**: 書類（invoices / delivery_notes）の `status` カラムが処理進行の正本。バッチ・ジョブ・画面操作はすべてこの status を遷移させる（BR-01）。
- **冪等性・復旧**: バッチ二重起動防止（Cache::lock）、ジョブ重複防止（WithoutOverlapping）、残留救済（stuck差し戻し）、自動リトライ（max_retries / retry_backoff）の4層で信頼性を担保（NFR-R）。

---

## 2. システム構成（Docker Compose）

### 2.1 構成図（テキスト表現）

```
                          ┌─────────────────────────────────────────────┐
                          │              Docker Compose ホスト             │
                          │                                               │
   ブラウザ ──HTTP──▶ ┌──────────┐   FastCGI   ┌──────────┐            │
   (general/admin)      │  nginx   │ ──────────▶ │   php    │  (php-fpm) │
                        │ (Web)    │ ◀────────── │  app本体  │            │
                        └──────────┘             └────┬─────┘            │
                                                      │                   │
                          ┌───────────────────────────┼───────────────┐   │
                          │                            │               │   │
                     ┌────▼────┐   キュー投入     ┌────▼─────┐   ┌────▼────┐
                     │  redis  │ ◀────────────── │  worker  │   │scheduler │
                     │ (Queue/ │ ──ジョブ取得───▶ │ (queue   │   │ (cron:   │
                     │  Lock)  │                  │  work)   │   │ schedule │
                     └─────────┘                  └────┬─────┘   │  :run)   │
                          │                            │         └────┬────┘
                          │                            │              │
                          │              ┌─────────────┴──────────────┘
                          │              │ Artisan コマンド実行（バッチ）
                          ▼              ▼
                  ┌──────────┐     ┌──────────┐
                  │ mailpit  │     │  Storage  │  (local: PDF保存)
                  │ (SMTP受信)│     │ invoices/ │
                  └──────────┘     │ delivery- │
                       ▲           │  notes/   │
                       │SMTP送信    └──────────┘
                       │
                  （php / worker から送信）

   ── 外部（コンテナ外）────────────────────────────────────────────
   ┌────────────────────────┐        ┌─────────────────────────────┐
   │ SQL Server              │        │ 基幹システム API              │
   │ db-sv03.solid-corp.local│◀─sqlsrv│ BACKBONE_API_URL            │
   │ :1433 / DB=ePostCable   │  接続  │ (HTTP/REST・タイムアウト30秒) │
   └────────────────────────┘        │ 未設定時はダミー(空配列)       │
        ▲ php / worker / scheduler    └─────────────────────────────┘
        │ 全コンテナが外部DBへ接続              ▲
        └──────────────────────────────────────┘ ShipmentFetchService から接続
```

### 2.2 コンテナの役割と関係（BR-10）

| コンテナ | 役割 | 主な対応要件 | 接続先 |
|---------|------|-------------|--------|
| `php` | アプリ本体（php-fpm）。Web リクエスト処理・`Artisan::queue` によるバッチ投入元・PDF即時生成・テスト/手動操作の送信元 | FR-07〜17 / NFR-M-05 | redis / SQL Server / Storage / SMTP |
| `nginx` | リバースプロキシ／Web サーバ。HTTP を受け php へ FastCGI 中継 | FR-16（/login 等） | php |
| `redis` | キュー（ジョブ）バックエンド。キャッシュ/ロックバックエンド（切替可・NFR-E-03） | NFR-R-01/02 / FR-05/06 | （被接続） |
| `worker` | キューワーカー（`queue:work`）。`ProcessInvoiceJob` / `ProcessDeliveryNoteJob` を実行 | FR-05 / FR-06 / NFR-R-02/05/06 | redis / SQL Server / Storage / SMTP |
| `scheduler` | cron 常駐（`schedule:run` を毎分）。送信バッチを定刻起動 | FR-04 / NFR-R-03 | redis / SQL Server |
| `mailpit` | 開発用 SMTP 受信サーバ（送信メールの確認）。本番は実 SMTP に置換 | FR-14 | （被接続） |

- **DB コンテナは持たない**: 開発・本番とも外部 SQL Server（`db-sv03.solid-corp.local:1433` / `ePostCable`）へ接続する（BR-10）。
- **状態共有の経路**: php（投入）→ redis（キュー）→ worker（実行）、scheduler（定刻起動）→ ロック（redis/db）→ コマンド実行。書類状態は全コンテナが共通の SQL Server を介して共有する。
- **メール送信元**: 請求書/納品書メール・テストメールは php・worker から、バッチ完了通知（BatchSummaryMail）はバッチ実行プロセス（worker または scheduler 経由のコマンド）から SMTP へ送信。

---

## 3. コンポーネント設計（層構造）

### 3.1 層構造と責務

```
┌──────────────────────────────────────────────────────────────┐
│ Routes (web.php)  + Middleware (auth / admin / csrf)          │  認可ゲート
├──────────────────────────────────────────────────────────────┤
│ Controller 層   … HTTP 受付・入力検証(allowlist)・画面返却     │  FR-07〜13/16/17
│   Dashboard / Invoice / DeliveryNote / SendMailLog /          │
│   ShipmentFetchLog / User / SystemSetting / Login             │
├──────────────────────────────────────────────────────────────┤
│ Command 層      … バッチ起点・ロック・ログ・ディスパッチ        │  FR-01〜04
│   FetchShipmentData / SendInvoices / SendDeliveryNotes        │
├──────────────────────────────────────────────────────────────┤
│ Job 層          … 非同期 PDF生成+送信・リトライ・失敗分岐       │  FR-05/06
│   ProcessInvoiceJob / ProcessDeliveryNoteJob                  │
├──────────────────────────────────────────────────────────────┤
│ Service 層      … 業務ロジックの中核（再利用単位）             │
│   ShipmentFetchService(基幹取得) / PdfService(PDF生成)         │  FR-01/15
├──────────────────────────────────────────────────────────────┤
│ Mail 層         … Mailable（件名・添付・BCC）                  │  FR-14
│   InvoiceMail / DeliveryNoteMail / BatchSummaryMail / TestMail│
├──────────────────────────────────────────────────────────────┤
│ Model 層        … Eloquent・状態判定・スコープ・KVS            │  BR-01〜09
│   Invoice / DeliveryNote / *_items / SendMailLog(+Item) /     │
│   ShipmentFetchLog / SystemSetting / User                     │
├──────────────────────────────────────────────────────────────┤
│ Infrastructure  … SQL Server / Redis / Storage(local) / SMTP  │
└──────────────────────────────────────────────────────────────┘
```

### 3.2 各層の責務と依存関係

#### Middleware 層（FR-16 / FR-17 / NFR-S）
- `auth`: 管理画面全体を保護。未認証は `/login` へリダイレクト（FR-16 / NFR-S-02）。
- `admin`（AdminMiddleware）: 管理者専用ルート（システム設定・ユーザー管理・手動完了・バッチ手動起動・一括再キュー）を保護（FR-17 / NFR-S-02）。
- `@csrf`（VerifyCsrfToken）: 全 POST を保護（FR-17 / NFR-S-05）。
- ログイン試行制限（RateLimiter）: メール＋IP で5回失敗・約60秒ロック（FR-16 / NFR-S-03）。退職者拒否は LoginController が `isRetired()` で判定（FR-16 / NFR-S-04）。
- 依存: 下位の Controller を保護する。Controller からは原則上方向へ依存しない。

#### Controller 層（FR-07〜13 / FR-16 / FR-17）
- 責務: HTTP 受付、リクエスト検証（ステータス・role は allowlist／NFR-S-06）、Model/Service の呼び出し、ビュー返却。重い処理は持たず Command（Artisan::queue）・Job（dispatch）へ委譲。
- 主なコントローラと対応要件:

| Controller | 主な操作 | 対応 FR |
|-----------|---------|---------|
| DashboardController | 集計表示（status別件数・直近実行・手動再送除外） | FR-07 |
| InvoiceController | 一覧/詳細/resend/updateEmails/bulkRequeue/runBatch/PDF・CSV DL | FR-08 / FR-15 |
| DeliveryNoteController | FR-08 と同一（DeliveryNote 読替） | FR-09 |
| SendMailLogController | 一覧/詳細/complete（手動完了） | FR-10 |
| ShipmentFetchLogController | 履歴表示/バッチ手動起動 | FR-11 |
| UserController | ユーザー CRUD（admin） | FR-12 |
| SystemSettingController | 設定更新/テストメール（admin） | FR-13 |
| LoginController | 認証/退職者拒否/試行制限 | FR-16 |

- 依存: Controller → Service / Model / Mail / Command(Artisan::queue) / Job(dispatch)。
- 注記: `updateEmails` は admin 限定に変更（2026-07-01決定）。`complete` は廃止（2026-07-01決定・失敗ログは別画面のみ表示）。`bulkRequeue` の retry_count +1 は記録用カウンタ（>=3で確認ダイアログ、>=10は対象外）。

#### Command 層（FR-01〜04 / NFR-R-01/04）
- 責務: バッチの起点。ロック取得 → 実行ログ作成 → 業務処理 → ディスパッチ → 完了ログ → 通知。
- 共通設計:
  - 起動時 `Cache::lock(key, 3600)` で二重起動防止。取得失敗はスキップ（NFR-R-01）。
  - 実行ログ（ShipmentFetchLog / SendMailLog）を作成し履歴追跡可能化（NFR-M-02）。

| Command | 役割 | ロックキー | 対応 |
|---------|------|----------|------|
| `batch:fetch-shipment-data` | 基幹取得・書類作成 | `batch:fetch-shipment-data` | FR-01 |
| `batch:send-invoices` | pending 請求書をキュー投入 | `batch:send-invoices` | FR-02 |
| `batch:send-delivery-notes` | pending 納品書をキュー投入 | `batch:send-delivery-notes` | FR-03 |

- 依存: Command → Service(ShipmentFetchService) / Model / Job(dispatch) / Mail(BatchSummaryMail)。
- 注記: 出荷取得は毎日12:15〜12:30に自動実行するようスケジュール化（2026-07-01決定。失敗時は手動起動で救済）。

#### Job 層（FR-05 / FR-06 / NFR-R-02/05/06）
- 責務: 1書類の PDF生成 → 保存 → 送信 → 状態更新を非同期で完遂。失敗時の分岐記録。
- 共通設計:
  - コンストラクタで `system_settings` から `$maxExceptions`（max_retries）/ `$backoff`（retry_backoff・固定値）/ `$timeout`（pdf_timeout）を動的取得（NFR-M-04）。フォールバック値はシーダー値に統一済み（2026-07-01決定）。
  - `WithoutOverlapping($id)->releaseAfter(10)->expireAfter(300)` で重複実行防止（NFR-R-02。`expireAfter` を明示指定し、300秒ジョブの完了前にロックが失効しないようにする・2026-07-01決定）。
  - status ガード: `processing` 以外はスキップ（BR-01 の整合保持）。
  - 失敗分岐（`failed()`）: `PermanentJobFailureException` → `failed_permanent`（NFR-R-06）、それ以外 → `failed`。エラーは SendMailLogItem に1000文字まで記録（NFR-M-03）。
- 依存: Job → PdfService / Model / Mail(InvoiceMail・DeliveryNoteMail) / Storage / SystemSetting。

#### Service 層（FR-01 / FR-15）
- `ShipmentFetchService`: 基幹 API を HTTP Client でタイムアウト30秒取得（NFR-P-06）。`BACKBONE_API_URL` 未設定時はダミー（空配列）を返す（NFR-E-04）。取得データを Command が書類化。
- `PdfService`: DomPDF で A4縦の PDF を生成。日本語フォント事前読込（LoadDompdfFonts）、リモートリソース無効化・chroot（NFR-S-08）、空出力時 `RuntimeException`（FR-15）。
- 依存: Service は Model・外部 I/F（HTTP/DomPDF）に依存し、Controller/Command/Job から呼ばれる（下位部品として再利用）。

#### Mail 層（FR-14）
- 責務: 件名・本文・添付・BCC を定義。BCC は全 Mailable 共通で `SystemSetting::mailBccAddresses()` を `envelope()` で付与（未設定時 BCC なし）。

| Mailable | 件名 | 添付 | 発火元 |
|----------|------|------|--------|
| InvoiceMail | `【請求書】{invoice_number}` | PDF | ProcessInvoiceJob |
| DeliveryNoteMail | `【納品書】{delivery_number}` | PDF | ProcessDeliveryNoteJob |
| BatchSummaryMail | `【バッチ完了】{batch_name}…実行分` | なし | 送信 Command |
| TestMail | `【テスト】メール送信テスト` | なし | SystemSettingController |

#### Model 層（BR-01〜09）
- 状態判定・スコープ・KVS など業務ルールを保持。

| Model | 役割 | 主な業務ルール |
|-------|------|--------------|
| Invoice / DeliveryNote | 書類本体・status 正本・`recipientEmails()` | BR-01 / BR-04 / インデックス NFR-P-03 |
| invoice_items / delivery_note_items | 明細（親 FK cascade） | BR-09 |
| SendMailLog | バッチ実行ログ・`displayStatus()` | BR-03 / BR-07 |
| SendMailLogItem | 書類1通単位の送信明細（ポリモーフィック・restrictOnDelete） | BR-09 / NFR-E-02 |
| ShipmentFetchLog | 出荷取得実行ログ（書類と直接リレーションなし） | BR-09 |
| SystemSetting | KVS 設定・`get()` / `mailBccAddresses()` | FR-13 / BR-06 / NFR-E-01 |
| User | 認証・role・retired_at | FR-12 / FR-16 / BR-08 |

### 3.3 依存方向の原則
- 上位（Routes/Middleware → Controller/Command/Job）から下位（Service → Model → Infra）への単方向依存。
- 共通の重い処理（基幹取得・PDF生成）は Service へ集約し、Controller・Command・Job から再利用（保守性 NFR-M）。
- 書類種別差（Invoice / DeliveryNote）はポリモーフィック関連（SendMailLogItem.sendable）で吸収し、種別追加に強い構造（NFR-E-02）。

---

## 4. データフロー／処理方式

### 4.1 エンドツーエンドのデータフロー

```
[1] 出荷取得（手動・FR-01）
  admin ─runBatch─▶ Artisan::queue(batch:fetch-shipment-data)
     └▶ Cache::lock 取得 → ShipmentFetchLog(running)
        └▶ ShipmentFetchService.fetch()  ──HTTP(30s)──▶ 基幹API（未設定時 空配列）
           └▶ 出荷1件ごとに DBトランザクション:
                重複チェック(delivery_number/invoice_number) → 既存ならskip(skipped_count++)
                納品書+明細 / 請求書+明細 を status=pending で作成
                tax=10固定 / tax_amount=round(amount×tax/100)   （BR-02）
           └▶ 成功:ShipmentFetchLog(completed)+カウント / 例外:failed+error_message

[2] 送信バッチ（cron or 手動・FR-02/03/04）
  scheduler(定刻) or admin(runBatch) ─▶ Artisan::queue(batch:send-invoices)
     └▶ Cache::lock 取得 → SendMailLog(batch_key,batch_name,started_at)
        ├▶ stuck差し戻し: processing≧stuck-timeout(60分) → pending, retry_count++ (reset_count)
        ├▶ (--retry-failed) failed → pending, retry_count++ (retry_failed_count)
        ├▶ pending を created_at 昇順で最大 limit(100) 件取得   （NFR-P-02/03）
        └▶ 各書類: DBトランザクション+行ロック(NFR-P-04)
                   → status=processing → SendMailLogItem 作成 → ProcessInvoiceJob dispatch
        └▶ 完了: SendMailLog(completed_at,dispatched_count,execution_seconds)
           +admin_notification_emails 設定時 BatchSummaryMail（未設定は警告ログ）

[3] キュージョブ（worker・FR-05/06）
  worker ─queue:work─▶ ProcessInvoiceJob
     └▶ WithoutOverlapping(id)->releaseAfter(10)
        └▶ 書類なし→ログ終了 / processing以外→skip
           └▶ PdfService.generate() → Storage: invoices/{年}/{月}/invoice_{番号}.pdf
              └▶ recipientEmails() 空 → PermanentJobFailureException
                 各アドレス FILTER_VALIDATE_EMAIL → 無効1件でも PermanentJobFailureException
                 → InvoiceMail(PDF添付) を全送付先へ送信
                 → 成功: status=sent, sent_at, SendMailLogItem=sent
        └▶ failed(): PermanentJobFailureException→failed_permanent / 他→failed
                     SendMailLogItem に status+error_message(1000字)

[4] 手動是正（画面・FR-08〜10）
  resend     : status=processing → 当日 manual-resend 親に Item 作成・dispatched_count++ → Job dispatch
  bulkRequeue: failed → pending 一括・retry_count++（記録用カウンタ。>=3で確認ダイアログ、>=10は対象外）
  updateEmails: failed/failed_permanent のみ・1〜3件・null正規化（admin限定）
  complete   : 廃止（2026-07-01決定。失敗ログは別画面のみ表示）
```

### 4.2 非同期処理方式
- **バッチ起動**: `Artisan::queue(...)` でコマンド自体をキューに投入。Web は即時に受付完了を返す（NFR-M-05）。worker が当該コマンドジョブを取り出して実行。
- **ジョブ実行**: 送信 Command が書類ごとに `ProcessInvoiceJob` / `ProcessDeliveryNoteJob` をディスパッチ → worker（`queue:work`）が並行処理。
- **キューバックエンド**: Redis（`QUEUE_CONNECTION=redis`）。

### 4.3 二重起動防止・残留救済（NFR-R）

| レイヤ | 仕組み | キー/値 | 対応 |
|--------|--------|---------|------|
| バッチ | `Cache::lock(key, 3600)` 失敗時スキップ | fetch/send-invoices/send-delivery-notes | NFR-R-01 |
| スケジュール | `withoutOverlapping()` + `runInBackground()` | — | NFR-R-03 |
| ジョブ | `WithoutOverlapping($id)->releaseAfter(10)->expireAfter(300)` | 書類ID | NFR-R-02 |
| 残留救済 | stuck-timeout(60分)以上 processing を pending 差し戻し | reset_count 計上 | NFR-R-04 |
| 自動リトライ | max_retries(3)・retry_backoff(30s 固定) | system_settings | NFR-R-05 |
| 恒久失敗 | failed_permanent はリトライ対象外・手動対応 | — | NFR-R-06 |

- ロック/キャッシュは database（既定）または redis を切替可能（NFR-E-03）。

---

## 5. 外部インターフェース定義

### 5.1 基幹システム API（ShipmentFetchService）
- 方式: Laravel HTTP Client（REST、想定 GET/POST）。
- 設定: `BACKBONE_API_URL`（`config services.backbone.url`）。
- タイムアウト: 30秒（NFR-P-06）。
- フォールバック: 未設定時はダミーデータ（空配列）を返し、連携先なしでも起動可能（NFR-E-04）。
- データ契約: 出荷データ（delivery_number / invoice_number / amount / customer_email 系等）。空白のみ customer_email は出荷取得バッチでバリデーションしスキップする（2026-07-01決定）。
- エラー処理: 例外時は ShipmentFetchLog を `failed` ＋ error_message。

### 5.2 SQL Server（永続化）
- ドライバ: sqlsrv。接続先: `db-sv03.solid-corp.local:1433` / DB `ePostCable`（BR-10）。
- 用途: 書類・明細・各種ログ・system_settings・users・セッション（DB セッション・NFR-S-07）。
- 性能: 送信待ち取得は複合インデックス `(status, created_at)`、`status` 単独も保持（NFR-P-03）。
- コンテナ非保有: 開発・本番とも外部接続。

### 5.3 SMTP（メール送信）
- 方式: Laravel Mailer（`MAIL_MAILER=smtp`）。開発は Mailpit コンテナ、本番は実 SMTP。
- 共通仕様: 全 Mailable に BCC を `mail_bcc_address` から付与（未設定時なし）（FR-14）。

### 5.4 ストレージ（PDF）
- ドライバ: `local`。
- 保存規則: バッチ処理時は保存（`invoices/{年}/{月}/invoice_{番号}.pdf` / `delivery-notes/{年}/{月}/delivery_{番号}.pdf`）（NFR-P-05）。納品書の {年}/{月} は `delivery_date`（納品・出荷日）基準（2026-07-01決定）。
- ダウンロード時: Storage にあれば返却、なければ即時生成し保存しない（FR-15 / NFR-P-05）。

### 5.5 Redis
- 用途: キュー（必須）、キャッシュ/ロック（database と切替可）（NFR-E-03）。

---

## 5A. 画面デザイン方針（FA-01・2026-07-07 追加）

> 追加経緯: 実装済み管理画面に Tailwind のスタイリングが適用されておらず無装飾の
> HTML として表示されていたため、あらためて画面デザインの方針を基本設計へ定義する
> （機能追加 FA-01）。業務ロジック・データ構造・要件（FR / NFR / BR）の変更は伴わない。

### 5A.1 デザイン方針
- **コンセプト**: シンプルな業務管理画面 UI。装飾は最小限とし、一覧・詳細・操作の
  視認性と操作性を優先する。デザインツール（Figma 等）は用いず、既存の技術スタックの
  範囲で見た目を整え、実装量を抑える。
- **UI フレームワーク**: Tailwind CSS の標準ユーティリティクラスの組み合わせのみで
  構成する。追加の UI コンポーネントライブラリ（Flowbite / daisyUI 等）は導入しない。
- **既存スタックとの整合**: 技術スタック（第6章）の「Front: Tailwind CSS (Vite)」に
  準拠する。ビルドは既存の Vite パイプライン（`resources/css`・`resources/js` →
  ビルド資産）を利用し、サーバサイドレンダリング（Blade）中心の構成を維持する。
  新規ライブラリ・ビルド構成の追加は行わない。
- **対応ブラウザ**: 最新モダンブラウザ（NFR-S-11）を前提とする。

### 5A.2 全体レイアウト
- 管理画面の定番構成である **サイドナビ（左固定）+ ヘッダー（上部）+ メインコンテンツ領域**
  とする。
  - **サイドナビ（左固定）**: 各管理画面への主要ナビゲーション（ダッシュボード・請求書・
    納品書・メール送信履歴・出荷取得履歴・ユーザー管理・システム設定）を配置。ユーザー管理・
    システム設定など admin 専用画面へのリンクは admin ロール時のみ表示する（FR-17 の認可と
    整合。表示制御はあくまで UI 補助であり、保護の本体は auth / admin ミドルウェア）。
  - **ヘッダー（上部）**: 画面タイトル、ログインユーザー名・ロール表示、ログアウト操作を配置。
  - **メインコンテンツ領域**: 各画面固有の一覧・詳細・フォームを表示。一覧のフィルタ・
    ページネーション・状態バッジ・操作ボタン等を Tailwind 標準クラスで整える。
- 共通レイアウトは Blade レイアウト（例: `layouts/app`）として一元管理し、各管理画面が
  これを継承する。ナビ項目・ヘッダーの変更が全画面へ一括反映される構造とする（保守性 NFR-M）。

### 5A.3 対象画面
| 分類 | 画面 | レイアウト | 対応 FR |
|------|------|-----------|---------|
| 認証 | ログイン画面 | 共通レイアウト非継承の単独ページ（サイドナビ・ヘッダーなし。中央寄せのログインフォーム） | FR-16 |
| 管理 | ダッシュボード | 共通レイアウト継承 | FR-07 |
| 管理 | 請求書 一覧・詳細 | 共通レイアウト継承 | FR-08 |
| 管理 | 納品書 一覧・詳細 | 共通レイアウト継承 | FR-09 |
| 管理 | メール送信履歴 一覧・詳細 | 共通レイアウト継承 | FR-10 |
| 管理 | 出荷取得履歴 | 共通レイアウト継承 | FR-11 |
| 管理 | ユーザー管理（admin） | 共通レイアウト継承 | FR-12 |
| 管理 | システム設定（admin） | 共通レイアウト継承 | FR-13 |

### 5A.4 対象外
- PDF 出力用テンプレート（`invoice` / `delivery_note` の `pdf/` 配下。既に Tailwind
  クラス適用済みかつ DomPDF 用の独自スタイルであり、本方針の対象外）。
- メール本文テンプレート（`emails/` 配下）。
- 業務ロジック・データ構造・画面の機能／操作フローの変更（本 FA はスタイリング方針の
  定義であり、機能変更を伴わない）。

---

## 6. 技術スタック（採用技術と採用理由）

> リバースのため「現状追認 ＋ 設計上の意味づけ」として整理する。

| 区分 | 採用技術 | バージョン | 採用理由（設計上の意味づけ） |
|------|---------|-----------|------------------------------|
| 言語 | PHP | ^8.3 | 既存資産・型機能。BR-10 で確定 |
| FW | Laravel | ^13.7 | Queue / Scheduler / Mailable / Eloquent / Middleware を標準提供し、本システムの非同期・バッチ・認可をフレームワーク機能で実現できる |
| PDF | barryvdh/laravel-dompdf | ^3.1 | HTML テンプレートから A4 帳票を生成。chroot・リモート無効化でローカル参照を制限可能（NFR-S-08） |
| DB | SQL Server (sqlsrv) | — | 基幹/社内資産との親和。外部DB（ePostCable）を共有（BR-10） |
| キュー | Redis | — | 非同期ジョブの低レイテンシ処理。ロック/キャッシュにも転用可（NFR-E-03） |
| キャッシュ/ロック | database / redis | — | Cache::lock による二重起動防止の基盤（NFR-R-01） |
| セッション | database | — | コンテナ間でセッション共有・水平展開耐性（NFR-S-07） |
| メール | SMTP / Mailpit | — | 本番 SMTP・開発は Mailpit で送信内容を安全に検証（FR-14） |
| HTTP連携 | Laravel HTTP Client | — | 基幹 API 連携。タイムアウト・フォールバック制御（NFR-P-06 / NFR-E-04） |
| Front | Tailwind CSS (Vite) | — | 管理画面 UI。サーバサイドレンダリング中心。標準ユーティリティクラスのみで業務管理画面 UI を構成する（デザイン方針は第5A章・FA-01） |
| テスト | PestPHP | ^4.7 | 受入条件（FR/NFR/BR）をテストケース化し仕様ベース検証を支える |
| 実行環境 | Docker Compose | — | php/nginx/redis/mailpit/worker/scheduler を役割分離（第2章） |

- 設計上の含意: Laravel の標準機能（Queue・Schedule・WithoutOverlapping・Cache::lock・Mailable envelope）に NFR-R / FR-04 / FR-14 を素直に対応づけられる構成であり、リバースとして妥当。

---

## 7. 非機能要件の実現方式（設計レベル）

### 7.1 認証・認可（NFR-S）
- `auth` ミドルウェアで管理画面全体、`admin` ミドルウェアで管理者専用ルートを2段で保護（NFR-S-02 / FR-17）。
- パスワードはハッシュ保存・min:8・confirmed（NFR-S-01）。試行制限（メール＋IP・5回・約60秒）（NFR-S-03）。退職者拒否（NFR-S-04）。
- 全 POST に CSRF（NFR-S-05）、入力フィルタは allowlist（NFR-S-06）、セッションは DB（NFR-S-07）、PDF 生成は chroot・リモート無効化（NFR-S-08）。

### 7.2 信頼性（NFR-R）
- 4層防御（第4.3節）: バッチロック → スケジュール overlapping 防止 → ジョブ重複防止 → 残留救済 ＋ 自動リトライ ＋ 恒久失敗隔離。
- 完了通知 BatchSummaryMail（NFR-R-07）。
- 設計判断（2026-07-01決定）: `expireAfter(300)` を明示指定して WithoutOverlapping releaseAfter(10) と最大300秒ジョブの整合性を担保。フォールバック値はシーダー値に統一。

### 7.3 性能（NFR-P）
- ページネーション（一覧20件・履歴詳細50件）（NFR-P-01）、バッチ limit 100（NFR-P-02）、複合インデックス `(status, created_at)`（NFR-P-03）、行ロックでの競合制御（NFR-P-04）、PDF 即時生成は非保存（NFR-P-05）、基幹 API 30秒（NFR-P-06）。
- 定量目標: レスポンスタイム3秒以内、同時接続10人前後（2026-07-01決定・requirements.md 6章参照）。

### 7.4 保守性・運用性（NFR-M）
- 動作パラメータは system_settings（画面）で管理し再デプロイ不要（NFR-M-01）。実行は ShipmentFetchLog / SendMailLog / SendMailLogItem に記録し画面追跡（NFR-M-02）。エラーは1000文字まで記録（NFR-M-03）。設定反映は次回ジョブ生成時でワーカー再起動不要（NFR-M-04）。バッチは非同期受付（NFR-M-05）。
- ログ保存期間は7年間（2026-07-01決定）。監視はアプリログ（Laravel Log）を目視で定期確認する運用とし、専用の監視・アラートツールは導入しない。

### 7.5 拡張性・移植性（NFR-E）
- KVS 設定の追加容易性（NFR-E-01）、ポリモーフィック関連での種別追加耐性（NFR-E-02）、ロック/キャッシュ切替（NFR-E-03）、基幹未設定時ダミー起動（NFR-E-04）。

---

## 8. 要件マッピング一覧（コンポーネント ⇔ 要件）

| 要件 | 主担当コンポーネント |
|------|---------------------|
| FR-01 | FetchShipmentData(Command) / ShipmentFetchService / ShipmentFetchLog / Invoice・DeliveryNote(+items) |
| FR-02/03 | SendInvoices・SendDeliveryNotes(Command) / SendMailLog(+Item) / Process*Job(dispatch) |
| FR-04 | Scheduler コンテナ / Kernel schedule（withoutOverlapping・runInBackground） |
| FR-05/06 | ProcessInvoiceJob・ProcessDeliveryNoteJob / PdfService / *Mail / Storage / SystemSetting |
| FR-07 | DashboardController / 各 Model 集計 / displayStatus() |
| FR-08/09 | Invoice・DeliveryNoteController / PdfService / CSV 出力 |
| FR-10 | SendMailLogController / SendMailLog(+Item) |
| FR-11 | ShipmentFetchLogController |
| FR-12 | UserController / User |
| FR-13 | SystemSettingController / SystemSetting / TestMail |
| FR-14 | InvoiceMail / DeliveryNoteMail / BatchSummaryMail / TestMail（envelope BCC） |
| FR-15 | PdfService（DomPDF・chroot） |
| FR-16/17 | LoginController / auth・admin Middleware / CSRF / RateLimiter |
| NFR-R | Cache::lock / WithoutOverlapping / stuck差し戻し / max_retries・retry_backoff / failed_permanent |
| NFR-P | ページネーション / limit / `(status, created_at)` / 行ロック |
| NFR-S | auth・admin / hash / RateLimiter / CSRF / allowlist / DBセッション / chroot |
| NFR-M | system_settings / 各ログ / Artisan::queue |
| NFR-E | KVS / morph / cache切替 / API ダミー |

---

## 9. 懸念事項・リスク（解消済み・2026-07-01）

| # | 懸念 | 決定内容 | 関連 |
|---|------|---------|------|
| R-1 | ジョブのフォールバック値とシーダー初期値の不一致 | シーダー値に統一（pdf_timeout=60秒 / retry_backoff=30秒） | OQ-01 / BR-06 |
| R-2 | WithoutOverlapping releaseAfter(10) と最大300秒ジョブ | `expireAfter(300)` を明示指定 | OQ-04 |
| R-3 | updateEmails が general 操作可 | admin 限定に変更 | OQ-03 |
| R-4 | bulkRequeue で retry_count +1 | 記録用カウンタとして確定。>=3で確認ダイアログ、>=10は対象外 | OQ-05 |
| R-5 | send_mail_log_items の nullOnDelete | restrictOnDelete に変更（削除機能なし・計画もないため） | OQ-06 |
| R-6 | 出荷取得のスケジュール未登録 | 毎日12:15〜12:30に自動実行 | OQ-07 |
| R-7 | customer_email 空白のみ → failed_permanent 誘発 | 出荷取得バッチでバリデーション追加 | OQ-09 |
| R-8 | CSV 文字コード（UTF-8 BOM） | UTF-8 BOM 付きで確定 | OQ-10 |
| R-9 | 納品書 PDF パスの基準日 | delivery_date 基準で確定 | OQ-11 |
| R-10 | 定量的非機能目標の未定義 | 具体的目標値を合意（requirements.md 6章） | OQ-12 |
| R-11 | 外部 SQL Server 依存 | DB 障害/ネットワーク断で全機能停止するリスクは残存。接続監視・タイムアウト/再接続方針は今後の運用設計課題として残す | — |

---

## 10. 論点の解消状況

OQ-01〜12・DB-Q-01/02 の計14件は2026-07-01のヒアリングで全件解消済み。決定内容は本書の該当箇所へ反映済み（詳細は `design/questions.md` 参照）。R-11（外部 SQL Server 依存）のみ運用設計課題として残る。

---

## 変更履歴

- 2026-07-07 機能追加 FA-01: 管理画面の UI デザイン方針（Tailwind 標準ユーティリティ／サイドナビ+ヘッダー構成／対象画面）を第5A章として追加。第6章の Front 行に第5A章への参照を追記。業務ロジック・データ構造・要件の変更は伴わない。
