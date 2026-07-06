#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
# 引数があればそのままartisanに渡す（例: ./scripts/test.sh --filter AdminInventoryTest）
docker compose exec -u root php php artisan test "$@"
