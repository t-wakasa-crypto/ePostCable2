#!/usr/bin/env bash
cat <<'EOF'
使用可能なスクリプト一覧:

  コンテナ
    docker-up.sh           コンテナ起動 (docker compose up -d)
    docker-down.sh         コンテナ停止 (docker compose down)
    docker-setup-fonts.sh  日本語フォント (IPAex) をコンテナにインストールし dompdf フォントキャッシュを登録

  データベース
    artisan-migrate.sh     マイグレーション実行
    artisan-fresh.sh       DB初期化＋シード (migrate:fresh --seed)

  開発ツール
    artisan-test.sh        テスト実行 (引数可: ./artisan-test.sh --filter ClassName)
    artisan-routes.sh      ルート一覧 (引数可: ./artisan-routes.sh --path admin)
    artisan-modules.sh     モジュール一覧
    artisan-list.sh        artisanコマンド一覧（バッチ確認用）
                           ・全コマンドを確認: ./artisan-list.sh
                           ・namespaceで絞り込み: ./artisan-list.sh queue
                             （make / migrate / queue 等の標準以外が独自コマンド）
    artisan-schedule.sh    定期実行バッチの一覧と実行タイミングを確認
                           ・登録済みスケジュールを表示: ./artisan-schedule.sh
                             （cron式・次回実行時刻が見られる）
    artisan-clear.sh       キャッシュクリア (optimize:clear)
    composer-autoload.sh   オートローダー更新 (composer dump-autoload -o)
    composer-update.sh     Composerパッケージ更新 (引数可: ./composer-update.sh vendor/package)
    artisan-pint.sh        コードフォーマット (Laravel Pint)

  フロントエンド
    node-setup.sh          WSL初回セットアップ: nvm + Node.js + npm install
    npm-dev.sh             Viteウォッチモード (npm run dev)
    npm-build.sh           Vite本番ビルド (npm run build)

  Git
    git-status.sh      作業状態確認
    git-log.sh         コミット履歴（グラフ表示、デフォルト20件）
    git-branch.sh      ブランチ一覧
    git-switch.sh      ブランチ切り替え: ./git-switch.sh <ブランチ名>
    git-new.sh         新しいブランチ作成: ./git-new.sh <ブランチ名>
    git-pull.sh        現在のブランチをpull
    git-push.sh        現在のブランチをpush
    git-commit.sh      対話的ステージ＋コミット: ./git-commit.sh "メッセージ"
    git-diff.sh        差分表示 (引数可: ./git-diff.sh HEAD~1)
    git-merge.sh       現在のブランチをdevelopにマージ (引数可: ./git-merge.sh <ブランチ名>)
    git-branch-delete.sh ブランチ削除（ローカル＋リモート）: ./git-branch-delete.sh <ブランチ名>

  その他
    setup.sh               WSL初回セットアップ (git hooks・.env コピー・Docker起動・DB初期化・フォント設定・フロントビルドを一括実行)
    podman-setup.sh        AlmaLinux向け Podmanセットアップ (podman / podman-docker / podman-compose インストール・ソケット有効化)
    help.sh                使用可能なスクリプト一覧を表示

EOF
