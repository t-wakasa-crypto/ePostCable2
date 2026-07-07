# コードレビュー報告: FA-01 管理画面 UI デザイン方針（TASK-116〜124）

**総合評価**: 要修正

> 対象: `design/requirements.md` / `design/basic-design.md`（§5A） / `design/detailed-design.md`（§1A） /
> `design/db-design.md` / `dev/implementation-plan.md`（TASK-116〜124）と、`src/Modules/` 配下の
> Blade 実装（共通レイアウト・状態バッジ・各モジュール master・各画面ビュー）の照合。
> レビュー日: 2026-07-07 / レビュアー: code-reviewer
> スコープ: FA-01 は UI スタイリングのみ（業務ロジック・データ構造・ルート・要件は変更なし）。
> 本レビューも UI 適合性・品質・セキュリティに限定し、業務ロジック層は対象外とした。

---

## 1. 総評

FA-01 の中心である「共通レイアウト（サイドナビ + ヘッダー + メイン）の一元化」「Tailwind 標準
ユーティリティのみでの装飾」「状態バッジ等の共通部品化」は、detailed-design §1A の指針どおりに
実装されており、完成度は高い。7 モジュールの `layouts/master` がすべて `x-shared::layouts.app`
へ委譲しており、ナビ・ヘッダーの一元管理（NFR-M）が正しく実現されている。配色・クラス方針も
§1A.1 の部品表とよく一致している。

一方、**ユーザー管理画面に「編集フォーム（retired トグル含む）」の UI が存在しない**点が、
detailed-design §1A.8 および TASK-123 の明記内容と乖離している（バックエンドの `users.update` は
実装済みだが UI から到達できない）。これは仕上げ漏れであり修正が必要なため「要修正」と判定した。
セキュリティ上の重大な問題・XSS・CSRF 欠落は検出されなかった。

---

## 2. 設計適合性チェック

### 差異なし（適合）

- **共通レイアウト（§1A.1 / TASK-116）**: `Shared/.../layouts/app.blade.php` が `flex min-h-screen`
  2 カラム、`w-64 shrink-0 bg-slate-800` サイドナビ、`h-14 ... bg-white border-b` ヘッダー、
  `flex-1 bg-gray-50 p-6` メイン、フラッシュ領域（success=green / error=red）を指針どおり実装。
- **サイドナビ 7 項目・表示順・admin 限定表示（§1A.1 / FR-17 / TASK-116）**: 7 項目が §1A.1 の順で
  定義され、ユーザー管理・システム設定は `isAdmin()` 時のみ `@continue` で非表示。現在ページ
  active 強調（`bg-slate-900 font-semibold`）も実装。保護本体が auth/admin ミドルウェアである点も
  コメントで明示。
- **ロールバッジ（§1A.1）**: admin=`bg-indigo-100 text-indigo-700` / general=`bg-gray-100 text-gray-600`、
  ログアウトは `@csrf` 付き POST フォーム。指針一致。
- **状態バッジ部品（§1A.1 / TASK-117）**: `status-badge.blade.php` の配色が §1A.1 の全 status
  （pending/processing/sent・completed/failed/failed_permanent/running）と完全一致。未知値は灰で既定。
- **ログイン画面（§1A.2 / TASK-118 / FR-16）**: 共通レイアウト非継承の単独ページ、中央寄せ
  `max-w-sm` カード、email/password/一次ボタン（`w-full`）縦積み、`@csrf`、エラーをフォーム上部に
  エラースタイル表示。指針一致。
- **ダッシュボード（§1A.3 / TASK-119 / FR-07）**: `grid md:grid-cols-3` カードグリッド、status 別件数
  （バッジ併記・数値強調）、直近送信バッチ（`displayStatus()`）・出荷取得情報カード、各一覧への
  リンク。manual-resend 除外の旨も見出しに明記。
- **請求書/納品書 一覧・詳細（§1A.4/§1A.5 / TASK-120 / FR-08/09）**: status サマリー、フィルタ select
  （allowlist）、CSV/バッチ起動/一括再送ボタン、テーブル、ページネーション、bulkRequeue の
  チェックボックス + `retry_count>=3` JS confirm、詳細のヘッダーカード・明細テーブル・送信履歴
  テーブル・resend/PDF/admin 限定 updateEmails フォーム（failed/failed_permanent のみ活性）まで
  網羅。請求書・納品書で構成が対称。
- **メール送信履歴（§1A.6 / TASK-121 / FR-10）**: filter select（allowlist）、一覧テーブル
  （displayStatus バッジ・カウント）、詳細のバッチ情報カード・明細テーブル。complete 廃止に伴い
  「送信済みにする」ボタンが表示されない点も設計どおり。
- **出荷取得履歴（§1A.7 / TASK-122 / FR-11）**: status フィルタ、admin 限定バッチ起動（confirm）、
  各カウント・execution_seconds・error_message を含むテーブル、ページネーション。
- **システム設定（§1A.9 / TASK-124 / FR-13）**: integer 型は `type=number` + `min`/`max` 属性を
  `min_value`/`max_value` に対応、emails 型は textarea（1 行 1 アドレス案内）、範囲併記、保存ボタン、
  別カードにテストメール送信フォーム。指針一致。

### 重大な差異

- **なし**（セキュリティ・データ破壊・必須業務機能の欠落は検出せず）。

### 中程度の差異

- **U-1（ユーザー管理の編集フォーム欠落）**: detailed-design §1A.8 は「作成/編集は標準フォーム…
  role（select）・retired（編集時チェック）」を要求し、TASK-123 も「作成/編集フォーム
  （name/email/password+confirmation/role/retired）」を明記している。しかし
  `src/Modules/User/resources/views/index.blade.php` には**新規作成フォームのみ**が存在し、
  各行に編集導線（編集ボタン・編集フォーム・retired トグル）が無い。バックエンドの
  `PUT /users/{user}`（`users.update`）・コントローラ・テスト（UserManagementTest.php:75）は
  実装済みのため、**UI からユーザー編集・退職トグルに到達できない**状態。FA-01 の対象画面かつ
  TASK-123 のスコープ内であり、仕上げ漏れと判断。→ 修正推奨（優先度: 高）。

### 軽微な差異

- **U-2（ダッシュボードの status ラベル重複）**: `Dashboard/index.blade.php` で status 別件数を
  `<x-shared::status-badge :status="$status" />` と `{{ $status }}: {{ $count }}` の双方で表示して
  おり、status 名が二重に出る。§1A.3 は「数値を大きく・ラベルは `text-sm text-gray-500`・バッジ
  併記」であり、数値の隣に再度 `status:` を出すのは冗長。表示上の実害はないが整理余地あり。
- **U-3（status バッジ文言の非日本語）**: バッジ内文言が英語キー（pending 等）のまま。§1A では
  配色のみ規定し文言の日本語化は要求していないため設計違反ではないが、運用者可読性の観点で
  日本語ラベル併用を検討余地（任意）。

---

## 3. 品質指摘

- **Q-1（軽微・保守性）**: 一覧テーブル・カード・フォーム入力・ボタン等の Tailwind クラス列が
  各ビューにインラインで繰り返されている。TASK-117 は「Blade コンポーネント／partial 化」を
  掲げるが、実際に部品化されているのは `status-badge` のみで、テーブル/カード/ボタンは
  クラス直書きの反復。動作・見た目は指針準拠だが、将来のスタイル一括変更時のコストが高い。
  `<x-shared::card>` `<x-shared::btn>` 等への抽出を推奨（任意・優先度低）。
- **Q-2（軽微・堅牢性）**: `SystemSetting/index.blade.php` が `$setting->description ?? false` を
  参照。モデルに description 属性が無い場合でも `?? false` でガードされ実害はないが、設計に
  description の定義が無いため意図が曖昧。不要なら削除、必要なら db/設計へ定義追加が望ましい。
- **Q-3（軽微・一貫性）**: 権限判定が画面により `auth()->user()->isAdmin()`（Invoice 系）と
  `@auth @if(auth()->user()->isAdmin())`（ShipmentFetch）で書き分けられている。動作は同等だが、
  レイアウト側で算出済みの `$isAdmin` を共有する等、統一すると可読性が上がる（任意）。
- **Q-4（情報・良好）**: JS confirm（bulkRequeue の `retry_count>=3`）は素の DOM API で実装され、
  追加ライブラリ非依存（§5A.1 準拠）。0 件選択時のガードもあり良好。

---

## 4. セキュリティ指摘

- **XSS**: 全ビューで出力は `{{ }}`（自動エスケープ）を使用。`error_message`・顧客名・品目名等の
  ユーザー/外部由来データも含めエスケープされており、`{!! !!}` の使用は検出せず。良好。
- **CSRF（NFR-S-05 / FR-17）**: ログアウト・resend・updateEmails・bulkRequeue・runBatch・
  users.store・users.destroy・system-settings.update・testMail・login の全 POST/DELETE フォームに
  `@csrf` を確認。DELETE は `@method('DELETE')` 併用。欠落なし。
- **認可の UI 表現（FR-17）**: admin 専用操作（バッチ起動・一括再送・updateEmails・ナビの
  ユーザー管理/システム設定）は `isAdmin()` で UI 制御。設計どおり「UI 補助であり保護本体は
  ミドルウェア」である旨もレイアウトにコメント。UI 側の非表示に依存しすぎない構造で妥当。
- **自己削除防止（BR-08）**: ユーザー一覧で `$user->id !== auth()->id()` により自分の削除ボタンを
  非表示。UI 側ガードは適切（本体はコントローラ側で担保される前提）。
- **機微情報のハードコード**: Blade 内に資格情報・API キー等のハードコードは検出せず。

重大なセキュリティ脆弱性は検出されなかった。

---

## 5. 未実装項目

- **U-1（再掲）**: ユーザー管理の編集 UI（編集フォーム・retired トグル）。detailed-design §1A.8 /
  TASK-123 のスコープに含まれるが未実装。バックエンド（`users.update`）は実装済みのため、
  現状 UI からユーザーの role 変更・退職/復帰・パスワード更新ができない。

上記以外に、FA-01（TASK-116〜124）のスコープで未実装の画面・部品は検出されなかった。

---

## 6. 修正推奨事項（優先度付き）

| 優先度 | ID | 内容 | 対応先 |
|-------|----|------|------|
| 高 | U-1 | ユーザー管理画面に編集フォーム（name/email/password 任意/role select/retired トグル）を追加し `PUT users.update` へ接続。項目別 `@error` 表示・自己に対する扱いも考慮 | `User/resources/views/index.blade.php`（別カード or 行内編集） |
| 低 | U-2 | ダッシュボード status 別件数の表示重複（バッジ + `status:` テキスト）を整理 | `Dashboard/index.blade.php` |
| 低 | Q-1 | テーブル/カード/ボタンの Tailwind クラスを Blade コンポーネント／partial 化（TASK-117 の徹底） | `Shared` に共通部品追加 |
| 低 | Q-2 | `SystemSetting` の `$setting->description` 参照の要否を確定（不要なら削除／必要なら設計・DB 定義追加） | `SystemSetting/index.blade.php` ほか |
| 任意 | Q-3 / U-3 | 権限判定の書き方統一、status バッジの日本語ラベル併用検討 | 各ビュー |

---

## 7. 再レビュー要否

U-1（ユーザー編集 UI 追加）は機能到達性に関わるため、修正後の再確認を推奨する。その他の
軽微指摘（U-2/U-3/Q-1〜Q-3）は修正後の再確認は不要。
