#!/bin/sh
set -eu

usage() {
  echo "usage: safe-replace.sh WP_ROOT CANDIDATE_DIR BACKUP_DIR [single|network]" >&2
  exit 64
}

[ "$#" -eq 4 ] || usage
WP_ROOT=$1
CANDIDATE=$2
BACKUP_ROOT=$3
MODE=$4

case "$MODE" in
  single) NETWORK_FLAG='' ;;
  network) NETWORK_FLAG='--network' ;;
  *) usage ;;
esac

PLUGIN_ROOT="$WP_ROOT/wp-content/plugins"
CURRENT="$PLUGIN_ROOT/wpavatar"
STAMP=$(date -u +%Y%m%dT%H%M%SZ)-$$
BACKUP="$BACKUP_ROOT/wpavatar-$STAMP"
STAGE="$PLUGIN_ROOT/.wpavatar-next-$STAMP"
FAILED="$PLUGIN_ROOT/.wpavatar-failed-$STAMP"

[ -d "$WP_ROOT" ] || { echo "WordPress root not found: $WP_ROOT" >&2; exit 1; }
[ -f "$CURRENT/wpavatar.php" ] || { echo "Installed WPAvatar not found: $CURRENT" >&2; exit 1; }
[ -f "$CANDIDATE/wpavatar.php" ] || { echo "Candidate WPAvatar not found: $CANDIDATE" >&2; exit 1; }
[ ! -e "$BACKUP" ] || { echo "Backup path already exists: $BACKUP" >&2; exit 1; }
[ ! -e "$STAGE" ] || { echo "Stage path already exists: $STAGE" >&2; exit 1; }
mkdir -p "$BACKUP_ROOT"

# Fail before touching the active installation if any candidate PHP file cannot parse.
find "$CANDIDATE" -type f -name '*.php' -exec php -l '{}' ';' >/dev/null

# Database and installed-code backups are retained. Nothing is deleted by this entrypoint.
wp --path="$WP_ROOT" --skip-plugins --skip-themes db export "$BACKUP_ROOT/database-$STAMP.sql" --quiet
cp -a "$CURRENT" "$BACKUP"

WAS_ACTIVE=0
if wp --path="$WP_ROOT" --skip-plugins --skip-themes plugin is-active wpavatar $NETWORK_FLAG; then
  WAS_ACTIVE=1
  wp --path="$WP_ROOT" --skip-plugins --skip-themes plugin deactivate wpavatar $NETWORK_FLAG
fi

mkdir "$STAGE"
cp -a "$CANDIDATE/." "$STAGE/"

# Both renames occur on the plugin filesystem. The old tree remains as a rollback copy.
mv "$CURRENT" "$CURRENT.rollback-$STAMP"
mv "$STAGE" "$CURRENT"

if [ "$WAS_ACTIVE" -eq 1 ]; then
  if ! wp --path="$WP_ROOT" --skip-plugins --skip-themes plugin activate wpavatar $NETWORK_FLAG; then
    mv "$CURRENT" "$FAILED"
    mv "$CURRENT.rollback-$STAMP" "$CURRENT"
    echo "Candidate activation failed. Previous files restored inactive; DB/code backups retained in $BACKUP_ROOT" >&2
    exit 1
  fi
fi

printf '%s\n' "SAFE_REPLACE=PASS" "MODE=$MODE" "BACKUP_DIR=$BACKUP" "ROLLBACK_DIR=$CURRENT.rollback-$STAMP"
