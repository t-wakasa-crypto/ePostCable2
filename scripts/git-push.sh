#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
branch="$(git branch --show-current)"
git push origin "$branch"
