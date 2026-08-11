#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
for tool in wp msgfmt msgcmp; do
  command -v "$tool" >/dev/null || { echo "BLOCKED: missing $tool" >&2; exit 2; }
done
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
wp i18n make-pot . "$work/wpavatar.pot" \
  --domain=wpavatar \
  --exclude=.git,node_modules,vendor,lib/plugin-update-checker \
  --skip-js >/dev/null
msgfmt --check --check-format --output-file=/dev/null languages/wpavatar-zh_CN.po
msgcmp --use-fuzzy languages/wpavatar-zh_CN.po "$work/wpavatar.pot"
printf 'PASS: wpavatar zh_CN catalog covers the current wpavatar source catalog\n'
