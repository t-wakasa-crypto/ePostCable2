#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

if [[ $# -eq 0 ]]; then
  echo "使い方: ./scripts/git-commit.sh \"コミットメッセージ\""
  exit 1
fi

git add . && git commit -m "$1"
