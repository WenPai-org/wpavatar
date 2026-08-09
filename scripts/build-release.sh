#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
version="$(sed -n "s/^define( 'WPAVATAR_VERSION', '\([^']*\)' );/\1/p" wpavatar.php)"
readme_version="$(sed -n 's/^Stable tag: //p' readme.txt | tr -d '\r')"
if [[ -z "$version" || "$version" != "$readme_version" ]]; then
  echo "Version mismatch: header=$version readme=$readme_version" >&2
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
( cd "$staging"; zip -qr "$OLDPWD/$artifact" wpavatar )
unzip -t "$artifact"
if unzip -Z1 "$artifact" | grep -Eq '(^|/)(\.git|\.forgejo|tests|docs|scripts|dist|vendor|node_modules)(/|$)'; then
  echo "Development files leaked into release package" >&2
  exit 1
fi
sha256sum "$artifact"
