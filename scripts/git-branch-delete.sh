#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

if [[ $# -eq 0 ]]; then
  echo "使い方: ./scripts/git-branch-delete.sh <ブランチ名>"
  exit 1
fi

branch="$1"
current="$(git branch --show-current)"

if [[ "$current" == "$branch" ]]; then
  echo "エラー: 現在 '$branch' にいます。別のブランチに切り替えてから実行してください。"
  exit 1
fi

# ローカル削除
git branch -d "$branch"
echo "ローカルブランチ '$branch' を削除しました。"

# リモート削除（存在する場合のみ）
if git ls-remote --exit-code origin "$branch" &>/dev/null; then
  git push origin --delete "$branch"
  echo "リモートブランチ 'origin/$branch' を削除しました。"
else
  echo "リモートブランチは存在しないためスキップしました。"
fi
