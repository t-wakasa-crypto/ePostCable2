# システム開発Docker環境設計書（docker/）

> 本書は [docker/CLAUDE.md](../docker/CLAUDE.md) から参照される詳細仕様書
> である。`docker/CLAUDE.md` は薄いポインタとして維持し、Docker構成方針は
> すべて本書に記載する。**Docker構成（コンテナ構成・Dockerfile配置規則等）に
> 追加・変更を行った場合は、必ず本書を更新すること**（`docker/CLAUDE.md` 側は
> 参照先や役割の説明が変わらない限り更新不要）。

## 現在の状態

Docker 構成は整備済みである。`docker-compose.yml`（プロジェクトルート）が
定義するサービス（`nginx` / `php` / `redis` / `mysql` / `worker` / `scheduler` /
`mailpit`）に対応して、以下が配置されている。

- `docker/php/Dockerfile`（PHP-FPM のビルド定義）
- `docker/php/entrypoint.sh`（コンテナ起動時の初期化処理。dompdf 用日本語
  フォントキャッシュの登録を含む）
- `docker/php/php.ini`（PHP 設定）
- `docker/nginx/default.conf`（Nginx の仮想ホスト設定）
- `mysql`（8.4系公式イメージをそのまま使用。専用Dockerfileは持たない）:
  開発環境のメインDB用コンテナ。接続情報は `.env` の `DB_DATABASE` /
  `DB_USERNAME` / `DB_PASSWORD` をそのまま MySQL の初期DB・ユーザー作成に
  流用する（`docs/システム開発環境設計書.md`「9. データベース接続」参照）。
  社内既存システムの SQL Server にはアプリの `legacy` 接続経由で別途
  接続する想定であり、コンテナ化はしていない。
  **`php`/`worker`/`scheduler` コンテナは `mysql` に `depends_on` していない**
  （現行の実プロジェクトはメインDBとして外部 SQL Server を使う設計のため、
  `mysql` は今後メインDBをMySQLに切り替えるプロジェクト向けの追加サービスと
  位置づけ、既存の起動フローに影響しないようにした）。

構成を変更する場合は、以下の「定義すべき内容」に沿って本書を更新してから
着手すること。

## 定義すべき内容（構成変更時に見直す）

- コンテナ構成一覧（現状: PHP-FPM / Nginx / Redis / MySQL / Worker /
  Scheduler / Mailpit。社内既存システムの SQL Server はコンテナ化せず
  アプリから直接接続する。変更する場合は `design/detailed-design.md` の
  技術選定・`design/db-design.md` の DB 種別を参照して決定する）
- 各コンテナの `Dockerfile` の配置規則（現状: `docker/php/Dockerfile` の
  ようにミドルウェアごとにサブディレクトリを切る方式。Nginx は設定ファイルの
  みで Dockerfile を持たず公式イメージをそのまま使用）
- 設定ファイルの配置規則（`php.ini`・`nginx/default.conf`等）と
  マウント方針（`docker-compose.yml` からの相対パス）
- ローカル開発用・本番相当用でDockerfileや設定を分ける場合はその切り分け方針
- イメージの命名規則・タグ付け方針（使う場合）

## 関連ファイル

- `docker-compose.yml`（プロジェクトルート）: コンテナ全体のオーケストレーション定義
- `scripts/docker-up.sh` / `scripts/docker-down.sh`: コンテナの起動・停止補助
  スクリプト（詳細は [システム開発スクリプト設計書.md](システム開発スクリプト設計書.md) 参照）

## 禁止事項

- 本書および `docker/` 配下の構成方針が未整備のまま、場当たり的に
  `Dockerfile` や設定ファイルを追加すること（`docker-compose.yml` との
  整合が崩れるため、追加前に本書へ構成方針を記載してから着手する）
