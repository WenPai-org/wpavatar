#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
version="$(sed -n "s/^define( 'WPAVATAR_VERSION', '\([^']*\)' );/\1/p" wpavatar.php)"
plugin_header_version="$(sed -n 's/^ \* Version: //p' wpavatar.php | tr -d '\r')"
readme_version="$(sed -n 's/^Stable tag: //p' readme.txt | tr -d '\r')"
plugin_requires_wp="$(sed -n 's/^ \* Requires at least: //p' wpavatar.php | tr -d '\r')"
readme_requires_wp="$(sed -n 's/^Requires at least: //p' readme.txt | tr -d '\r')"
plugin_requires_php="$(sed -n 's/^ \* Requires PHP: //p' wpavatar.php | tr -d '\r')"
readme_requires_php="$(sed -n 's/^Requires PHP: //p' readme.txt | tr -d '\r')"
if [[ -z "$version" || "$version" != "$plugin_header_version" || "$version" != "$readme_version" ]]; then
  echo "Version mismatch: constant=$version header=$plugin_header_version readme=$readme_version" >&2
  exit 1
fi
if [[ "$plugin_requires_wp" != "5.4" || "$readme_requires_wp" != "$plugin_requires_wp" ]]; then
  echo "WordPress requirement mismatch: expected=5.4 header=$plugin_requires_wp readme=$readme_requires_wp" >&2
  exit 1
fi
if [[ "$plugin_requires_php" != "7.4" || "$readme_requires_php" != "$plugin_requires_php" ]]; then
  echo "PHP requirement mismatch: expected=7.4 header=$plugin_requires_php readme=$readme_requires_php" >&2
  exit 1
fi
if grep -Eq '^ \* Update URI:' wpavatar.php; then
  echo "Update URI is forbidden: WordPress.org is the only automatic update channel" >&2
  exit 1
fi
if grep -RIEq '(pre_set_site_transient_update_plugins|site_transient_update_plugins|plugins_api|update_plugins_[a-z0-9_.-]+)' wpavatar.php includes; then
  echo "Custom plugin updater hook found: WordPress.org must remain the only automatic update channel" >&2
  exit 1
fi
if ! grep -Fq "### $version ###" readme.txt; then
  echo "Missing changelog entry for $version" >&2
  exit 1
fi
for required in wpavatar.php readme.txt license.txt assets includes languages; do
  [[ -e "$required" ]] || { echo "Missing package input: $required" >&2; exit 1; }
done
package_paths=(wpavatar.php readme.txt license.txt assets includes languages)
if ! git diff --quiet -- "${package_paths[@]}" || ! git diff --cached --quiet -- "${package_paths[@]}"; then
  echo "Package inputs must match the reviewed Git commit" >&2
  exit 1
fi
untracked="$(git ls-files --others --exclude-standard -- "${package_paths[@]}")"
if [[ -n "$untracked" ]]; then
  echo "Untracked package input is forbidden:" >&2
  printf '%s\n' "$untracked" >&2
  exit 1
fi
mapfile -t package_files < <(git ls-files -- "${package_paths[@]}")
[[ ${#package_files[@]} -gt 0 ]] || { echo "No tracked package files found" >&2; exit 1; }
for file in "${package_files[@]}"; do
  [[ ! -L "$file" ]] || { echo "Symlink package input is forbidden: $file" >&2; exit 1; }
done
mkdir -p dist
artifact="dist/wpavatar-$version.zip"
[[ ! -e "$artifact" ]] || { echo "Refusing to overwrite $artifact" >&2; exit 1; }
staging="$(mktemp -d)"
trap 'rm -rf "$staging"' EXIT
mkdir -p "$staging/wpavatar"
for file in "${package_files[@]}"; do
  mkdir -p "$staging/wpavatar/$(dirname "$file")"
  cp "$file" "$staging/wpavatar/$file"
done
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
