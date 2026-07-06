#!/bin/sh
# リポジトリのローカルセットアップスクリプト
# クローン後に一度だけ実行してください

cd "$(dirname "$0")/.."
echo "git hooksを設定しています..."
git config core.hooksPath .githooks
echo "完了: .githooks が有効になりました"
echo ""
echo "設定内容:"
git config core.hooksPath
