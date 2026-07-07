# 請求書メール配信システム 図解設計書

> リバース元: `detailed-design.md` / `basic-design.md`
> 作成日: 2026-06-29 / 作成: diagram-designer
> 性質: 実装済みシステムのリバース図解。Mermaid 形式で全体を俯瞰できる図を提供する。

---

## 1. システムアーキテクチャ図（Docker Compose 構成）

Docker Compose 上の各コンテナと外部システムの関係を示す。nginx がブラウザからの HTTP を受け php へ転送し、php/worker/scheduler はすべて外部 SQL Server へ接続する。redis はキュー・ロックのバックエンドとして全コンテナから参照される。

```mermaid
graph TD
    Browser["ブラウザ\n(general / admin)"]

    subgraph DockerCompose["Docker Compose ホスト"]
        nginx["nginx\n(リバースプロキシ)"]
        php["php\n(php-fpm / アプリ本体)"]
        redis["redis\n(Queue / Cache / Lock)"]
        worker["worker\n(queue:work)"]
        scheduler["scheduler\n(schedule:run 毎分)"]
        mailpit["mailpit\n(開発用 SMTP 受信)"]
        storage["Storage\n(local: PDF 保存)"]
    end

    SQLServer["SQL Server\ndb-sv03.solid-corp.local:1433\nDB: ePostCable"]
    BackboneAPI["基幹システム API\nBACKBONE_API_URL\n(HTTP/REST)"]
    RealSMTP["実 SMTP\n(本番メール)"]

    Browser -->|HTTP| nginx
    nginx -->|FastCGI| php
    php -->|キュー投入| redis
    php -->|sqlsrv| SQLServer
    php -->|SMTP| mailpit
    php -->|SMTP| RealSMTP
    php -->|PDF 保存/読込| storage

    worker -->|ジョブ取得| redis
    worker -->|sqlsrv| SQLServer
    worker -->|SMTP| mailpit
    worker -->|SMTP| RealSMTP
    worker -->|PDF 保存| storage
    worker -->|HTTP 30s| BackboneAPI

    scheduler -->|Artisan::queue 投入| redis
    scheduler -->|sqlsrv| SQLServer

    style DockerCompose fill:#f0f4ff,stroke:#6080c0
    style SQLServer fill:#ffe8cc,stroke:#c06000
    style BackboneAPI fill:#ffe8cc,stroke:#c06000
    style RealSMTP fill:#ffe8cc,stroke:#c06000
```

この図の読み方: 点線枠内がコンテナ群、枠外が外部システム。矢印方向が通信の起点を示す。開発環境では RealSMTP の代わりに mailpit が SMTP を受信する。

---

## 2. コンポーネント層構造図

Laravel アプリケーションの層構造と、各層の依存方向を示す。上位層から下位層への単方向依存を原則とする。

```mermaid
graph TD
    subgraph RouteMiddleware["Routes / Middleware 層"]
        RM1["web.php (ルーティング)"]
        RM2["auth Middleware"]
        RM3["admin Middleware"]
        RM4["VerifyCsrfToken"]
    end

    subgraph ControllerLayer["Controller 層 (FR-07〜13 / FR-16 / FR-17)"]
        C1["DashboardController"]
        C2["InvoiceController"]
        C3["DeliveryNoteController"]
        C4["SendMailLogController"]
        C5["ShipmentFetchLogController"]
        C6["UserController"]
        C7["SystemSettingController"]
        C8["LoginController"]
    end

    subgraph CommandLayer["Command 層 (FR-01〜04)"]
        CMD1["FetchShipmentData\nbatch:fetch-shipment-data"]
        CMD2["SendInvoices\nbatch:send-invoices"]
        CMD3["SendDeliveryNotes\nbatch:send-delivery-notes"]
    end

    subgraph JobLayer["Job 層 (FR-05 / FR-06)"]
        J1["ProcessInvoiceJob"]
        J2["ProcessDeliveryNoteJob"]
    end

    subgraph ServiceLayer["Service 層"]
        S1["ShipmentFetchService\n(基幹API取得)"]
        S2["PdfService\n(DomPDF PDF生成)"]
    end

    subgraph MailLayer["Mail 層 (FR-14)"]
        M1["InvoiceMail"]
        M2["DeliveryNoteMail"]
        M3["BatchSummaryMail"]
        M4["TestMail"]
    end

    subgraph ModelLayer["Model 層 (BR-01〜09)"]
        MO1["Invoice / DeliveryNote"]
        MO2["invoice_items / delivery_note_items"]
        MO3["SendMailLog / SendMailLogItem"]
        MO4["ShipmentFetchLog"]
        MO5["SystemSetting (KVS)"]
        MO6["User"]
    end

    subgraph InfraLayer["Infrastructure 層"]
        I1["SQL Server (sqlsrv)"]
        I2["Redis (Queue / Lock)"]
        I3["Storage (local PDF)"]
        I4["SMTP / Mailpit"]
    end

    RouteMiddleware --> ControllerLayer
    ControllerLayer --> CommandLayer
    ControllerLayer --> JobLayer
    ControllerLayer --> ServiceLayer
    ControllerLayer --> ModelLayer
    ControllerLayer --> MailLayer
    CommandLayer --> ServiceLayer
    CommandLayer --> JobLayer
    CommandLayer --> ModelLayer
    CommandLayer --> MailLayer
    JobLayer --> ServiceLayer
    JobLayer --> ModelLayer
    JobLayer --> MailLayer
    ServiceLayer --> ModelLayer
    ModelLayer --> InfraLayer
    ServiceLayer --> InfraLayer
    MailLayer --> InfraLayer

    style RouteMiddleware fill:#e8f0e8,stroke:#40a040
    style ControllerLayer fill:#e8f0ff,stroke:#4060c0
    style CommandLayer fill:#fff0e0,stroke:#c08000
    style JobLayer fill:#fff0e0,stroke:#c08000
    style ServiceLayer fill:#f0e8ff,stroke:#8040c0
    style MailLayer fill:#ffe8f0,stroke:#c04080
    style ModelLayer fill:#e8fff0,stroke:#008060
    style InfraLayer fill:#f8f8f8,stroke:#808080
```

この図の読み方: 矢印は「依存する方向（呼び出す方向）」を示す。InfraLayer が最下位で、上位層は下位層を呼び出すが逆方向の依存は持たない。

---

## 3. 全体データフロー図（E2E）

出荷取得から書類作成、送信バッチ、キュージョブ、メール送信までの End-to-End フローを示す。

```mermaid
graph TD
    A1["Admin: POST\n/shipment-fetch-logs/run-batch"]
    A2["Artisan::queue\nbatch:fetch-shipment-data"]
    A3["Cache::lock 取得\nbatch:fetch-shipment-data"]
    A4["ShipmentFetchLog\nstatus=running 作成"]
    A5["ShipmentFetchService.fetch()\nHTTP GET 基幹API\ntimeout 30s"]
    A6{{"出荷1件ごと\nDBトランザクション"}}
    A7{{"delivery_number\ninvoice_number 重複?"}}
    A8["skipped_count++\nスキップ"]
    A9["納品書+明細\n請求書+明細\nstatus=pending\ntax=10 固定"]
    A10["ShipmentFetchLog\nstatus=completed"]

    B1["scheduler(cron) / Admin\nbatch:send-invoices\n毎日01:30 / 月曜02:30"]
    B2["Cache::lock 取得\nbatch:send-invoices"]
    B3["SendMailLog 作成\nbatch_key=send-invoices"]
    B4["stuck 差し戻し\nprocessing かつ >=60分\n→ pending, retry_count++"]
    B5["pending を\ncreated_at 昇順\n最大100件取得"]
    B6{{"各書類\nDBトランザクション\n+ 行ロック"}}
    B7["status=processing\nSendMailLogItem 作成\nProcessInvoiceJob dispatch"]
    B8["SendMailLog\ncompleted_at 更新\nBatchSummaryMail 送信"]

    C1["worker: ProcessInvoiceJob\nWithoutOverlapping(id)"]
    C2{{"status == processing?"}}
    C3["スキップ / return"]
    C4["PdfService.generate()\nDomPDF A4 PDF 生成"]
    C5{{"PDF 空出力?"}}
    C6["RuntimeException\n→ failed()"]
    C7["Storage 保存\ninvoices/年/月/invoice_番号.pdf"]
    C8{{"recipientEmails()\n空 or 無効アドレス?"}}
    C9["PermanentJobFailureException\n→ failed_permanent"]
    C10["InvoiceMail 送信\nPDF 添付 / BCC 付与\n全送付先へ SMTP"]
    C11["status=sent\nsent_at=now()\nSendMailLogItem=sent"]

    A1 --> A2 --> A3 --> A4 --> A5 --> A6
    A6 --> A7
    A7 -->|既存| A8
    A7 -->|新規| A9
    A9 --> A10
    A8 --> A10

    B1 --> B2 --> B3 --> B4 --> B5 --> B6
    B6 --> B7 --> B8

    B7 -->|Job dispatch| C1
    C1 --> C2
    C2 -->|No| C3
    C2 -->|Yes| C4
    C4 --> C5
    C5 -->|Yes| C6
    C5 -->|No| C7
    C7 --> C8
    C8 -->|空/無効| C9
    C8 -->|全て有効| C10
    C10 --> C11

    A9 -.->|status=pending\n書類が蓄積| B5

    style A1 fill:#ddeeff,stroke:#336699
    style B1 fill:#ddeeff,stroke:#336699
    style C1 fill:#fff0cc,stroke:#996600
    style C9 fill:#ffdddd,stroke:#cc0000
    style C6 fill:#ffdddd,stroke:#cc0000
    style C11 fill:#ddffdd,stroke:#006600
```

この図の読み方: 上段が出荷取得フロー、中段が送信バッチフロー、下段がキュージョブフロー。点線矢印はフロー間のデータ受け渡し（pending 書類の蓄積）を示す。

---

## 4. 書類ステータス状態遷移図

Invoice / DeliveryNote の `status` カラムが取りうる状態と遷移条件を示す。`status` が処理進行の単一情報源となる。

```mermaid
stateDiagram-v2
    [*] --> pending : 出荷取得バッチで作成\n(FetchShipmentData)

    pending --> processing : 送信バッチがキュー投入\nまたは手動再送 (resend)

    processing --> sent : ジョブ送信成功\n(ProcessInvoiceJob / ProcessDeliveryNoteJob)

    processing --> failed : ジョブ一時失敗\n(PDF空出力・SMTP障害等)\nfailed() で記録

    processing --> failed_permanent : PermanentJobFailureException\n(送付先0件 / 無効アドレス)

    processing --> pending : stuck 差し戻し\n(processing かつ >=stuck-timeout 60分)\nretry_count++

    failed --> pending : retry-failed オプション\nまたは bulkRequeue\nretry_count++

    failed_permanent --> failed_permanent : 自動リトライ対象外\n(手動対応: updateEmails 後\nbulkRequeue で pending へ)

    sent --> [*]
```

この図の読み方: 通常フローは pending → processing → sent。失敗時は failed または failed_permanent へ分岐し、failed は retry で pending に戻せる。failed_permanent は手動是正が必要。

---

## 5. 出荷取得ログ（ShipmentFetchLog）状態遷移図

出荷取得バッチ実行ごとに作成される ShipmentFetchLog の状態遷移を示す。

```mermaid
stateDiagram-v2
    [*] --> running : FetchShipmentData 開始時\nShipmentFetchLog 作成

    running --> completed : 正常終了\n(fetched_count / created_delivery_note_count\n/ created_invoice_count / skipped_count 記録)

    running --> failed : 例外発生\n(error_message 記録)

    completed --> [*]
    failed --> [*]
```

この図の読み方: 実行開始で running、正常完了で completed、例外発生で failed となる。ログは読み取り専用で状態が戻ることはない。

---

## 6. 送信ログ（SendMailLog）displayStatus 判定遷移図

SendMailLog の `displayStatus()` が返す表示ステータスの判定ロジックを示す。`failed_at` の有無が最優先となる。

```mermaid
stateDiagram-v2
    [*] --> running : SendMailLog 作成\n(started_at=now)

    running --> completed : completed_at セット\n(バッチ正常完了)

    running --> failed : failed_at セット\n(バッチ例外発生)

    running --> manual_resend_excluded : batch_key が manual-resend\n(当日の手動再送まとめ親)

    completed --> [*]
    failed --> [*]
    manual_resend_excluded --> [*]

    note right of manual_resend_excluded
        ダッシュボード集計・
        フィルタ検索から除外
        displayStatus() 対象外
    end note
```

この図の読み方: running は `completed_at` と `failed_at` の両方が null の状態。failed_at が設定された場合は completed_at の有無にかかわらず failed と判定される。manual-resend は集計対象外。

---

## 7. ER 図（主要テーブルのリレーション）

主要テーブルの関係を示す。SendMailLogItem はポリモーフィック関連により Invoice と DeliveryNote の両方に紐づく。

```mermaid
erDiagram
    invoices {
        bigint id PK
        string delivery_number
        string invoice_number
        string status
        decimal amount
        decimal tax_amount
        int tax
        string customer_email
        string customer_email_2
        string customer_email_3
        datetime sent_at
        datetime created_at
        datetime updated_at
        int retry_count
    }

    delivery_notes {
        bigint id PK
        string delivery_number
        string invoice_number
        string status
        decimal amount
        decimal tax_amount
        int tax
        string customer_email
        string customer_email_2
        string customer_email_3
        datetime sent_at
        datetime created_at
        datetime updated_at
        int retry_count
    }

    invoice_items {
        bigint id PK
        bigint invoice_id FK
        string item_name
        int quantity
        decimal unit_price
        decimal amount
    }

    delivery_note_items {
        bigint id PK
        bigint delivery_note_id FK
        string item_name
        int quantity
        decimal unit_price
        decimal amount
    }

    send_mail_logs {
        bigint id PK
        string batch_key
        string batch_name
        datetime started_at
        datetime completed_at
        datetime failed_at
        int dispatched_count
        int reset_count
        int retry_failed_count
        int execution_seconds
        string error_message
    }

    send_mail_log_items {
        bigint id PK
        bigint send_mail_log_id FK
        string sendable_type
        bigint sendable_id
        string status
        string error_message
        datetime sent_at
    }

    shipment_fetch_logs {
        bigint id PK
        string status
        datetime started_at
        datetime completed_at
        int fetched_count
        int created_delivery_note_count
        int created_invoice_count
        int skipped_count
        int execution_seconds
        string error_message
    }

    system_settings {
        bigint id PK
        string key
        string value
        string type
        int min_value
        int max_value
    }

    users {
        bigint id PK
        string name
        string email
        string password
        string role
        datetime retired_at
    }

    invoices ||--o{ invoice_items : "hasMany (cascade)"
    delivery_notes ||--o{ delivery_note_items : "hasMany (cascade)"
    send_mail_logs ||--o{ send_mail_log_items : "hasMany (restrictOnDelete)"
    invoices ||--o{ send_mail_log_items : "morphMany (sendable)"
    delivery_notes ||--o{ send_mail_log_items : "morphMany (sendable)"
```

この図の読み方: `send_mail_log_items` の `sendable_type` / `sendable_id` がポリモーフィック外部キーであり、Invoice または DeliveryNote のどちらにも関連できる。親 SendMailLog（まとめ親含む）は削除不可（restrictOnDelete）。削除機能が存在・計画もないため、孤立明細の発生を防ぐ方針とした（2026-07-01決定・Q-06）。

---

## 8. シーケンス図: 出荷取得バッチ（FR-01）

Admin が管理画面からバッチを手動起動し、基幹 API から出荷データを取得して書類を作成するフローを示す。

```mermaid
sequenceDiagram
    actor Admin
    participant C as ShipmentFetchLogController
    participant Q as Queue(redis)
    participant W as worker
    participant CMD as FetchShipmentData
    participant Lock as Cache::lock
    participant SVC as ShipmentFetchService
    participant API as 基幹API
    participant DB as SQL Server

    Admin->>C: POST /shipment-fetch-logs/run-batch (admin)
    C->>Q: Artisan::queue(batch:fetch-shipment-data)
    C-->>Admin: 受付完了(即時) [NFR-M-05]
    W->>Q: ジョブ取得
    W->>CMD: 実行
    CMD->>Lock: get(batch:fetch-shipment-data, 3600)
    alt ロック取得失敗
        Lock-->>CMD: false
        CMD-->>W: スキップ [NFR-R-01]
    else 取得成功
        CMD->>DB: ShipmentFetchLog(status=running) 作成
        CMD->>SVC: fetch()
        SVC->>API: HTTP GET (timeout 30s) [NFR-P-06]
        API-->>SVC: 出荷データ配列 / 未設定時は空配列 [NFR-E-04]
        SVC-->>CMD: array
        loop 出荷1件ごと (トランザクション)
            CMD->>DB: 重複チェック(delivery_number / invoice_number)
            alt 既存
                CMD->>CMD: skipped_count++ [BR-05]
            else 新規
                CMD->>DB: 納品書+明細 / 請求書+明細\n(status=pending / tax=10 / tax_amount=round) [BR-02]
            end
        end
        CMD->>DB: ShipmentFetchLog(status=completed, counts更新)
        CMD->>Lock: release()
    end
```

この図の読み方: 管理画面からの操作は即時に受付完了を返し、実際のバッチ処理は worker が非同期で実行する。ロック取得失敗時は多重起動をスキップする。

---

## 9. シーケンス図: 送信バッチ→キュージョブ投入（FR-02/03/04）

スケジューラまたは Admin が送信バッチを起動し、pending の書類を ProcessInvoiceJob としてキューに投入するフローを示す。

```mermaid
sequenceDiagram
    participant SCH as scheduler(cron) / Admin
    participant Q as Queue(redis)
    participant W as worker
    participant CMD as SendInvoices
    participant Lock as Cache::lock
    participant DB as SQL Server

    SCH->>Q: Artisan::queue(batch:send-invoices)
    W->>Q: ジョブ取得
    W->>CMD: 実行
    CMD->>Lock: get(batch:send-invoices, 3600)
    alt ロック取得失敗
        CMD-->>W: スキップ [NFR-R-01]
    else 取得成功
        CMD->>DB: SendMailLog(batch_key=send-invoices, started_at) 作成
        CMD->>DB: processing かつ >=stuck-timeout を\npending に差し戻し, retry_count++ [NFR-R-04]
        opt --retry-failed 指定時
            CMD->>DB: failed を pending に差し戻し, retry_count++
        end
        CMD->>DB: pending を created_at 昇順で最大 limit(100) 件取得 [NFR-P-02/03]
        loop 各書類 (DBトランザクション + 行ロック) [NFR-P-04]
            CMD->>DB: status=processing に更新
            CMD->>DB: SendMailLogItem 作成(sendable=Invoice)
            CMD->>Q: ProcessInvoiceJob::dispatch(invoice_id, log_item_id)
            CMD->>CMD: dispatched_count++
        end
        CMD->>DB: SendMailLog(completed_at, dispatched_count, execution_seconds) 更新
        opt admin_notification_emails 設定あり
            CMD->>CMD: BatchSummaryMail 送信 [NFR-R-07]
        end
        CMD->>Lock: release()
    end
```

この図の読み方: stuck 差し戻し処理（processing 残留の救済）が pending 取得の前に実行される点が重要。行ロック（lockForUpdate）により複数ワーカーが同一書類を処理しない。

---

## 10. シーケンス図: キュージョブ実行（FR-05/06）

worker が ProcessInvoiceJob を実行し、PDF 生成・保存・メール送信・ステータス更新を行うフローを示す。

```mermaid
sequenceDiagram
    participant W as worker
    participant J as ProcessInvoiceJob
    participant MW as WithoutOverlapping
    participant DB as SQL Server
    participant PDF as PdfService
    participant ST as Storage
    participant SMTP as SMTP / Mailpit

    W->>J: handle()
    J->>MW: WithoutOverlapping(invoice_id).releaseAfter(10) [NFR-R-02]
    J->>DB: Invoice::find(invoice_id)
    alt 対象なし または status != processing
        J-->>W: return (skip) [BR-01]
    else status == processing
        J->>PDF: generate(invoice)
        alt PDF 空出力
            PDF-->>J: RuntimeException [FR-15]
            J->>DB: Invoice.status=failed\nSendMailLogItem.status=failed
        else PDF 正常生成
            PDF-->>J: PDF binary
            J->>ST: invoices/年/月/invoice_番号.pdf 保存 [NFR-P-05]
            J->>DB: recipientEmails() 取得
            alt 送付先が空 または 無効アドレスあり
                J->>DB: Invoice.status=failed_permanent\nSendMailLogItem.status=failed_permanent [BR-01]
            else 全アドレス有効
                J->>SMTP: InvoiceMail(PDF添付, BCC付与) 送信 [FR-14]
                J->>DB: Invoice.status=sent, sent_at=now()\nSendMailLogItem.status=sent, sent_at=now()
            end
        end
    end
    Note over J,DB: failed():\nPermanentJobFailureException → failed_permanent\nそれ以外 → failed\nerror_message を 1000字まで記録 [NFR-M-03]
```

この図の読み方: status ガード（processing 以外はスキップ）が二重処理防止の最終防衛線。PDF 生成失敗と送付先問題で failed/failed_permanent に分岐する。

---

## 11. シーケンス図: 手動再送（FR-08 / BR-07）

一般ユーザーまたは管理者が失敗した書類を画面から手動で再送するフローを示す。

```mermaid
sequenceDiagram
    actor User as general / admin
    participant C as InvoiceController
    participant DB as SQL Server
    participant Q as Queue(redis)

    User->>C: POST /invoices/{id}/resend (auth / CSRF)
    C->>DB: BEGIN TRANSACTION
    C->>DB: invoice.status=processing に更新
    C->>DB: 当日分 manual-resend 親 SendMailLog\n取得 または 作成 [BR-07]
    C->>DB: SendMailLogItem 作成\n(sendable=Invoice, status=processing)
    C->>DB: 親 SendMailLog.dispatched_count++
    C->>Q: ProcessInvoiceJob::dispatch(invoice_id, log_item_id)
    C->>DB: COMMIT
    C-->>User: 完了レスポンス
```

この図の読み方: 当日中の手動再送は1つの manual-resend 親ログにまとめられる（BR-07）。CSRF トークンが必須で、認証済みユーザーのみ操作可能。

---

## 12. シーケンス図: ログイン・認証フロー（FR-16 / NFR-S）

ユーザーのログイン時における試行制限・認証・退職者チェックのフローを示す。

```mermaid
sequenceDiagram
    actor User
    participant C as LoginController
    participant RL as RateLimiter
    participant DB as SQL Server (users)

    User->>C: POST /login (email / password / CSRF)
    C->>RL: tooManyAttempts(email+IP) [NFR-S-03]
    alt 5回超過
        RL-->>C: true (ロック中)
        C-->>User: 約60秒のロック / エラー表示
    else 試行可能
        C->>DB: email + password(hash) で認証照合 [NFR-S-01]
        alt 認証不一致
            C->>RL: hit(email+IP) / カウントアップ
            C-->>User: 認証失敗エラー
        else 認証一致
            C->>DB: isRetired() 判定 [NFR-S-04]
            alt 退職済み (retired_at != null)
                C-->>User: ログイン拒否
            else 現役
                C->>RL: clear(email+IP) / カウントリセット
                C-->>User: ログイン成功 → dashboard リダイレクト
            end
        end
    end
```

この図の読み方: 試行制限チェックが認証より先に実行される。成功時はカウンタをリセットし、退職者は認証成功後でも画面へ進めない。

---

## 13. 管理画面レイアウト構成図（FA-01）

管理画面の全体レイアウト（サイドナビ＋ヘッダー＋メインコンテンツ）の構成と、サイドナビのメニュー項目を示す（`design/basic-design.md` 第5A章参照）。ログイン画面のみ共通レイアウトを継承しない単独ページとなる。

```mermaid
graph TD
    Login["ログイン画面\n(単独ページ / レイアウト非継承)"]

    subgraph Layout["共通レイアウト (layouts/app)"]
        Header["ヘッダー（上部）\n画面タイトル / ユーザー名・ロール表示 / ログアウト"]
        subgraph SideNav["サイドナビ（左固定）"]
            N1["ダッシュボード"]
            N2["請求書"]
            N3["納品書"]
            N4["メール送信履歴"]
            N5["出荷取得履歴"]
            N6["ユーザー管理（admin限定表示）"]
            N7["システム設定（admin限定表示）"]
        end
        Main["メインコンテンツ領域\n(一覧・詳細・フォーム)"]
    end

    Login -.->|ログイン成功| Header
    N1 --> Main
    N2 --> Main
    N3 --> Main
    N4 --> Main
    N5 --> Main
    N6 --> Main
    N7 --> Main

    style Login fill:#fff0e0,stroke:#c08000
    style Layout fill:#f0f4ff,stroke:#6080c0
    style SideNav fill:#e8fff0,stroke:#008060
    style Header fill:#e8f0ff,stroke:#4060c0
    style N6 fill:#ffe8e8,stroke:#c04040
    style N7 fill:#ffe8e8,stroke:#c04040
```

この図の読み方: ログイン画面のみサイドナビ・ヘッダーを持たない単独ページ。ログイン成功後は共通レイアウトへ遷移し、以降の全管理画面（ダッシュボード〜システム設定）はサイドナビ＋ヘッダー＋メインコンテンツの構成を継承する。ユーザー管理・システム設定はadminロール時のみサイドナビに表示（表示制御はUI補助であり、保護の本体は`auth`/`admin`ミドルウェア）。実装はTailwind標準ユーティリティクラスのみで構成し、追加コンポーネントライブラリは使用しない。

---

## 付録: スケジュール定義一覧

scheduler コンテナが `schedule:run` を毎分実行し、以下のコマンドを定時起動する。

```mermaid
graph LR
    CRON["scheduler コンテナ\n(cron: schedule:run 毎分)"]

    D1["毎日 01:00\nbatch:send-delivery-notes"]
    D2["毎日 01:30\nbatch:send-invoices"]
    M1["毎週月曜 02:00\nbatch:send-delivery-notes\n--retry-failed"]
    M2["毎週月曜 02:30\nbatch:send-invoices\n--retry-failed"]
    F1["毎日 12:15〜12:30\nbatch:fetch-shipment-data"]

    CRON --> D1
    CRON --> D2
    CRON --> M1
    CRON --> M2
    CRON --> F1

    style CRON fill:#fff0cc,stroke:#996600
    style F1 fill:#e6f4ea,stroke:#2e7d32
```

この図の読み方: 各コマンドは `withoutOverlapping()` + `runInBackground()` 修飾により重複起動を防止する。出荷取得バッチは基幹システムの請求データ確定（翌日12:00）からのバッファを見て12:15〜12:30に自動実行する（2026-07-01決定。失敗時は管理画面から手動起動で救済）。

---

## 変更履歴

- 2026-07-07 機能追加 FA-01: 管理画面のUIデザイン方針追加（サイドナビ＋ヘッダー＋メインコンテンツ構成）に伴い「13. 管理画面レイアウト構成図（FA-01）」を新規追加。既存の図は変更なし。
