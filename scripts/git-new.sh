#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

if [[ $# -eq 0 ]]; then
  echo "使い方: ./scripts/git-new.sh <ブランチ名>"
  exit 1
fi

git switch -c "$1"
