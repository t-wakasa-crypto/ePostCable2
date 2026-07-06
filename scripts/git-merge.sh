#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

feature_branch="$(git branch --show-current)"
target_branch="${1:-develop}"

if [[ "$feature_branch" == "$target_branch" ]]; then
  echo "エラー: 現在のブランチが $target_branch です。featureブランチに切り替えてから実行してください。"
  exit 1
fi

echo "マージ: $feature_branch → $target_branch"
git switch "$target_branch"
git merge --no-ff "$feature_branch" -m "Merge branch '$feature_branch' into $target_branch"
git switch "$feature_branch"
echo "完了: $feature_branch を $target_branch にマージしました。"
