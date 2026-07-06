#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
git log --oneline --graph --decorate "${@:---20}"
