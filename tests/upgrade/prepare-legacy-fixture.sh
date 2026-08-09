#!/usr/bin/env bash
set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
OUTPUT=${1:?"usage: prepare-legacy-fixture.sh OUTPUT_DIR"}
OFFICIAL="$OUTPUT/legacy-official/wpavatar"
FIXED="$OUTPUT/legacy-syntax-fixed/wpavatar"

if [[ -e "$OUTPUT/legacy-official" || -e "$OUTPUT/legacy-syntax-fixed" ]]; then
  echo "Refusing to overwrite an existing fixture: $OUTPUT" >&2
  exit 1
fi

mkdir -p "$OFFICIAL" "$FIXED"
git -C "$ROOT" archive v1.9.5 | tar -x -C "$OFFICIAL"
cp -a "$OFFICIAL/." "$FIXED/"
patch --batch --forward -d "$FIXED" -p1 < "$ROOT/tests/upgrade/legacy-1.9.5-syntax.patch"

set +e
php -l "$OFFICIAL/includes/multisite.php" > "$OUTPUT/official-lint.log" 2>&1
OFFICIAL_LINT_EXIT=$?
set -e
if [[ $OFFICIAL_LINT_EXIT -eq 0 ]]; then
  echo "Expected the published v1.9.5 multisite parser failure, but lint passed" >&2
  exit 1
fi

while IFS= read -r -d '' file; do
  php -l "$file"
done < <(find "$FIXED" -type f -name '*.php' -print0)

echo "LEGACY_TAG=v1.9.5"
echo "LEGACY_OFFICIAL_LINT_EXIT=$OFFICIAL_LINT_EXIT"
echo "LEGACY_SYNTAX_FIXED_LINT=PASS"
echo "LEGACY_FIXTURE_ROOT=$OUTPUT"
