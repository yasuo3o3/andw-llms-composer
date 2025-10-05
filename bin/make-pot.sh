#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="${SCRIPT_DIR}/.."

wp i18n make-pot "${ROOT_DIR}" "${ROOT_DIR}/languages/andw-llms-composer.pot" \
  --exclude="docs,zips,bin" \
  --headers='{"Project-Id-Version":"andw-llms-composer 0.0.1","Report-Msgid-Bugs-To":"plugins-dev@yasuo-o.xyz","Last-Translator":"Auto Generated","Language-Team":"andW <plugins-dev@yasuo-o.xyz>"}'
