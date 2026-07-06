#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR/.."

echo "================================================"
echo " Podman セットアップ (AlmaLinux 向け)"
echo "================================================"
echo ""

# -------------------------------------------------------
# OS チェック: AlmaLinux 以外はスキップ
# -------------------------------------------------------
if [ ! -f /etc/os-release ]; then
    echo "  スキップ: /etc/os-release が見つかりません (AlmaLinux ではありません)"
    exit 0
fi

OS_ID=$(. /etc/os-release && echo "${ID:-}")
OS_NAME=$(. /etc/os-release && echo "${NAME:-}")

if [ "$OS_ID" != "almalinux" ]; then
    echo "  スキップ: このスクリプトは AlmaLinux 専用です"
    echo "  現在の OS: ${OS_NAME} (ID=${OS_ID})"
    exit 0
fi

echo "  OS 確認: ${OS_NAME} — セットアップを開始します"
echo ""

# -------------------------------------------------------
# [1/5] Podman インストール
# -------------------------------------------------------
echo "[1/5] Podman をインストールしています..."
if command -v podman &>/dev/null; then
    echo "  スキップ: Podman は既にインストールされています ($(podman --version))"
else
    sudo dnf install -y podman
    echo "  完了: $(podman --version)"
fi
echo ""

# -------------------------------------------------------
# [2/5] podman-docker インストール (docker CLI 互換レイヤー)
# -------------------------------------------------------
echo "[2/5] podman-docker をインストールしています..."
if rpm -q podman-docker &>/dev/null; then
    echo "  スキップ: podman-docker は既にインストールされています"
else
    sudo dnf install -y podman-docker
    echo "  完了: podman-docker インストール済み"
fi
echo ""

# -------------------------------------------------------
# [3/5] podman-compose インストール
# -------------------------------------------------------
echo "[3/5] podman-compose をインストールしています..."
if command -v podman-compose &>/dev/null; then
    echo "  スキップ: podman-compose は既にインストールされています ($(podman-compose --version))"
else
    # pip3 経由でインストール（dnf に無い場合はフォールバック）
    if dnf list available podman-compose &>/dev/null 2>&1; then
        sudo dnf install -y podman-compose
    else
        echo "  dnf に podman-compose が見つからないため pip3 でインストールします..."
        if ! command -v pip3 &>/dev/null; then
            sudo dnf install -y python3-pip
        fi
        sudo pip3 install podman-compose
    fi
    echo "  完了: $(podman-compose --version)"
fi
echo ""

# -------------------------------------------------------
# [4/5] Podman ソケット有効化 (Docker 互換ソケット)
# -------------------------------------------------------
echo "[4/5] Podman ソケットを有効化しています..."

# ユーザーレベルソケット (docker-compose が既定で参照するパス)
USER_PODMAN_SOCK="/run/user/$(id -u)/podman/podman.sock"
if systemctl --user is-active --quiet podman.socket 2>/dev/null; then
    echo "  スキップ: podman.socket (user) は既に起動しています"
else
    systemctl --user enable --now podman.socket
    echo "  完了: podman.socket (user) を有効化・起動しました"
fi

# ログアウト後もユーザーソケットを維持するために linger を有効化
if loginctl show-user "$USER" 2>/dev/null | grep -q "Linger=yes"; then
    echo "  スキップ: linger は既に有効です"
else
    sudo loginctl enable-linger "$USER"
    echo "  完了: linger を有効化しました"
fi

# DOCKER_HOST を ~/.bashrc に追記 (未設定の場合のみ)
DOCKER_HOST_EXPORT="export DOCKER_HOST=\"unix:///run/user/\$(id -u)/podman/podman.sock\""
if ! grep -q "DOCKER_HOST" ~/.bashrc 2>/dev/null; then
    echo "" >> ~/.bashrc
    echo "# Podman rootless socket (docker-compose 互換)" >> ~/.bashrc
    echo "$DOCKER_HOST_EXPORT" >> ~/.bashrc
    echo "  完了: DOCKER_HOST を ~/.bashrc に追加しました"
else
    echo "  スキップ: DOCKER_HOST は既に ~/.bashrc に設定されています"
fi

# 現在のセッションにも反映
export DOCKER_HOST="unix://$USER_PODMAN_SOCK"

# システムレベルソケット (後方互換・任意)
if systemctl is-active --quiet podman.socket 2>/dev/null; then
    echo "  スキップ: podman.socket (system) は既に起動しています"
else
    sudo systemctl enable --now podman.socket
    echo "  完了: podman.socket (system) を有効化・起動しました"
fi

echo ""

# -------------------------------------------------------
# [5/5] 動作確認
# -------------------------------------------------------
echo "[5/5] 動作確認..."
echo "  podman version    : $(podman --version)"
echo "  docker (alias)    : $(docker --version 2>/dev/null || echo '未確認 (新しいターミナルで再試行してください)')"
if command -v podman-compose &>/dev/null; then
    echo "  podman-compose    : $(podman-compose --version)"
fi
echo ""

# -------------------------------------------------------
# 完了
# -------------------------------------------------------
echo "================================================"
echo " セットアップ完了!"
echo "================================================"
echo ""
echo "  docker / docker-compose コマンドは Podman に委譲されます。"
echo "  既存の ./scripts/*.sh はそのまま使用できます。"
echo ""
echo "  DOCKER_HOST を明示的に切り替える場合:"
echo "    Podman : export DOCKER_HOST=unix:///run/podman/podman.sock"
echo "    Docker : export DOCKER_HOST=unix:///var/run/docker.sock"
echo ""
echo "※ podman-docker のエイリアスを反映するには 'source ~/.bashrc' を実行してください。"
