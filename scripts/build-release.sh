#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
version="$(sed -n "s/^define( 'WPAVATAR_VERSION', '\([^']*\)' );/\1/p" wpavatar.php)"
plugin_header_version="$(sed -n 's/^ \* Version: //p' wpavatar.php | tr -d '\r')"
readme_version="$(sed -n 's/^Stable tag: //p' readme.txt | tr -d '\r')"
if [[ -z "$version" || "$version" != "$plugin_header_version" || "$version" != "$readme_version" ]]; then
  echo "Version mismatch: constant=$version header=$plugin_header_version readme=$readme_version" >&2
  exit 1
fi
if ! grep -Fq "### $version ###" readme.txt; then
  echo "Missing changelog entry for $version" >&2
  exit 1
fi
for required in wpavatar.php readme.txt license.txt assets includes languages; do
  [[ -e "$required" ]] || { echo "Missing package input: $required" >&2; exit 1; }
done
mkdir -p dist
artifact="dist/wpavatar-$version.zip"
[[ ! -e "$artifact" ]] || { echo "Refusing to overwrite $artifact" >&2; exit 1; }
staging="$(mktemp -d)"
trap 'rm -rf "$staging"' EXIT
mkdir -p "$staging/wpavatar"
cp -R wpavatar.php readme.txt license.txt assets includes languages "$staging/wpavatar/"
if find "$staging/wpavatar" -type l -print -quit | grep -q .; then
  echo "Symlinks are not allowed in the release package" >&2
  exit 1
fi
find "$staging/wpavatar" -type d -exec chmod 0755 {} +
find "$staging/wpavatar" -type f -exec chmod 0644 {} +
source_date_epoch="${SOURCE_DATE_EPOCH:-$(git log -1 --format=%ct)}"
[[ "$source_date_epoch" =~ ^[0-9]+$ ]] || { echo "Invalid SOURCE_DATE_EPOCH: $source_date_epoch" >&2; exit 1; }
export TZ=UTC
find "$staging/wpavatar" -exec touch -h -d "@$source_date_epoch" {} +
artifact_path="$PWD/$artifact"
(
  cd "$staging"
  find wpavatar -print | LC_ALL=C sort | zip -X -q "$artifact_path" -@
)
unzip -t "$artifact"
if unzip -Z1 "$artifact" | grep -Eq '(^|/)(\.git|\.forgejo|tests|docs|scripts|dist|vendor|node_modules)(/|$)'; then
  echo "Development files leaked into release package" >&2
  exit 1
fi
sha256sum "$artifact"
