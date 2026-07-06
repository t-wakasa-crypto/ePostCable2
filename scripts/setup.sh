#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR/.."

echo "================================================"
echo " ePostCable 初期セットアップ"
echo "================================================"
echo ""

# -------------------------------------------------------
# [1/10] Git hooks
# -------------------------------------------------------
echo "[1/10] Git hooks を設定しています..."
"$SCRIPT_DIR/git-setup.sh"
echo ""

# -------------------------------------------------------
# [2/10] .env ファイルの準備
# -------------------------------------------------------
echo "[2/10] .env ファイルを準備しています..."
if [ ! -f .env ]; then
    cp .env.example .env
    echo "  作成: .env (.env.example からコピー)"
else
    echo "  スキップ: .env は既に存在します"
fi

if [ ! -f src/.env ]; then
    cp src/.env.example src/.env
    echo "  作成: src/.env (src/.env.example からコピー)"
else
    echo "  スキップ: src/.env は既に存在します"
fi
echo ""

# -------------------------------------------------------
# [3/10] Podman セットアップ (AlmaLinux のみ・他 OS は自動スキップ)
# -------------------------------------------------------
echo "[3/10] Podman をセットアップしています..."
"$SCRIPT_DIR/podman-setup.sh"

# podman-setup.sh はサブシェルで実行されるため、DOCKER_HOST を親シェルにも反映する
USER_PODMAN_SOCK="/run/user/$(id -u)/podman/podman.sock"
if [ -S "$USER_PODMAN_SOCK" ]; then
    export DOCKER_HOST="unix://$USER_PODMAN_SOCK"
fi
echo ""

# -------------------------------------------------------
# [4/10] Docker コンテナ起動
# -------------------------------------------------------
echo "[4/10] Docker コンテナを起動しています..."
"$SCRIPT_DIR/docker-up.sh"

echo "  php コンテナの起動を待機中..."
until docker compose exec php php -r "echo 'ok';" 2>/dev/null | grep -q "ok"; do
    sleep 2
done
echo "  php コンテナが起動しました"
echo ""

# -------------------------------------------------------
# [5/10] ストレージ・キャッシュディレクトリのパーミッション修正 (AlmaLinux のみ)
# Podman rootless ではホストユーザー(UID 1000) がコンテナ内の root に
# マッピングされるため、git clone 直後の storage/ は root 所有に見える。
# PHP-FPM は laravel(UID 1000) で動くため書き込み不可になり 500 エラーになる。
# Ubuntu + Docker ではこの問題は発生しないためスキップする。
# -------------------------------------------------------
_OS_ID=$(. /etc/os-release 2>/dev/null && echo "${ID:-}" || echo "")
if [ "$_OS_ID" = "almalinux" ]; then
    echo "[5/10] storage / bootstrap/cache のパーミッションを修正しています (AlmaLinux)..."
    docker compose exec --user root php \
        chown -R laravel:laravel \
            /var/www/html/storage \
            /var/www/html/bootstrap/cache
    docker compose exec --user root php \
        chmod -R 775 \
            /var/www/html/storage \
            /var/www/html/bootstrap/cache
    echo "  完了: storage / bootstrap/cache を laravel:laravel (775) に変更しました"
    echo ""
fi

# -------------------------------------------------------
# [6/10] Composer パッケージインストール
# artisan コマンドより前に vendor/ を用意する必要がある
# -------------------------------------------------------
echo "[6/10] Composer パッケージをインストールしています..."
"$SCRIPT_DIR/composer-update.sh"
echo ""

# -------------------------------------------------------
# [7/10] 日本語フォント設定
# -------------------------------------------------------
echo "[7/10] 日本語フォントを設定しています..."
"$SCRIPT_DIR/docker-setup-fonts.sh"
echo ""

# -------------------------------------------------------
# [8/10] データベース初期化
# -------------------------------------------------------
echo "[8/10] データベースを初期化しています (migrate:fresh --seed)..."
"$SCRIPT_DIR/artisan-fresh.sh"
echo ""

# -------------------------------------------------------
# [9/10] Node.js セットアップ (nvm + Node + npm install)
# -------------------------------------------------------
echo "[9/10] Node.js をセットアップしています..."
"$SCRIPT_DIR/node-setup.sh"
echo ""

# -------------------------------------------------------
# [10/10] フロントエンドビルド
# node-setup.sh はサブシェルで動くため nvm を再度有効化する
# -------------------------------------------------------
echo "[10/10] フロントエンドをビルドしています..."
export NVM_DIR="$HOME/.nvm"
# shellcheck source=/dev/null
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
"$SCRIPT_DIR/npm-build.sh"
echo ""

# -------------------------------------------------------
# 完了
# -------------------------------------------------------
echo "================================================"
echo " セットアップ完了!"
echo "================================================"
echo ""
echo "  Web アプリ : http://localhost:8080"
echo "  Mailpit UI : http://localhost:8025"
echo ""
echo "※ nvm を新しいターミナルでも使うには 'source ~/.bashrc' を実行してください。"
