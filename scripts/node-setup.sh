#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

NVM_VERSION="v0.40.3"
NODE_VERSION="24"

echo "==> nvm ${NVM_VERSION} をインストールします..."
curl -o- "https://raw.githubusercontent.com/nvm-sh/nvm/${NVM_VERSION}/install.sh" | bash

export NVM_DIR="$HOME/.nvm"
# shellcheck source=/dev/null
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"

echo "==> Node.js ${NODE_VERSION} をインストールします..."
nvm install "${NODE_VERSION}"
nvm alias default "${NODE_VERSION}"

echo "==> node_modules をインストールします..."
cd src
npm install

echo ""
echo "セットアップ完了"
echo "  node: $(node --version)"
echo "  npm:  $(npm --version)"
echo ""
echo "新しいターミナルを開くか 'source ~/.bashrc' を実行すると nvm が有効になります。"
