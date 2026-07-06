#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
git pull origin "$(git branch --show-current)"
