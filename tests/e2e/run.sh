#!/usr/bin/env bash
set -euo pipefail
ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
COMPOSE_FILE="$ROOT/tests/e2e/compose.yml"
RUN_ID="${WPAVATAR_E2E_RUN_ID:-$$}"
PROJECT="wpavatar-e2e-r2-${RUN_ID}"
PORT="${WPAVATAR_E2E_PORT:-18993}"
TMP="${TMPDIR:-/tmp}/wpavatar-e2e-${RUN_ID}"
CANDIDATE="$TMP/candidate/wpavatar"
LEGACY="$TMP/legacy/wpavatar-legacy"
LOG="$TMP/commands.log"
KEEP_ENV="${WPAVATAR_E2E_KEEP_ENV:-0}"
mkdir -p "$CANDIDATE" "$LEGACY"
: > "$LOG"
cleanup() {
  local rc=$?
  if [[ "$KEEP_ENV" != 1 ]]; then
    WPAVATAR_CANDIDATE_DIR="$CANDIDATE" WPAVATAR_LEGACY_DIR="$LEGACY" WPAVATAR_E2E_PORT="$PORT" \
      docker compose -p "$PROJECT" -f "$COMPOSE_FILE" down --volumes --remove-orphans >>"$LOG" 2>&1 || true
  fi
  echo "E2E_ARTIFACT_DIR=$TMP"
  echo "E2E_EXIT=$rc"
  exit "$rc"
}
trap cleanup EXIT
run() {
  echo "+ $*" | tee -a "$LOG"
  "$@" 2>&1 | tee -a "$LOG"
  local rc=${PIPESTATUS[0]}
  echo "exit=$rc" | tee -a "$LOG"
  return "$rc"
}
run rsync -a --delete --exclude=.git --exclude=vendor --exclude=dist --exclude=tests --exclude=.phpunit.cache "$ROOT/" "$CANDIDATE/"
run bash -c "git -C '$ROOT' archive origin/main | tar -x -C '$LEGACY'"
export WPAVATAR_CANDIDATE_DIR="$CANDIDATE" WPAVATAR_LEGACY_DIR="$LEGACY" WPAVATAR_E2E_PORT="$PORT"
DC=(docker compose -p "$PROJECT" -f "$COMPOSE_FILE")
WP=("${DC[@]}" run --rm cli)
run "${DC[@]}" up -d db wordpress
for _ in $(seq 1 60); do
  curl -fsS "http://127.0.0.1:${PORT}/wp-admin/install.php" >/dev/null 2>&1 && break
  sleep 2
done
run curl -fsS "http://127.0.0.1:${PORT}/wp-admin/install.php" -o /dev/null
run "${WP[@]}" core install --url="http://127.0.0.1:${PORT}" --title=WPAvatar-E2E --admin_user=admin --admin_password=admin-pass --admin_email=admin@example.test --skip-email
set +e
php -l "$LEGACY/includes/multisite.php" >>"$LOG" 2>&1
LEGACY_LINT_EXIT=$?
set -e
echo "LEGACY_PLUGIN_LINT_EXIT=$LEGACY_LINT_EXIT (known origin/main parse defect; option fixture is seeded without loading legacy runtime)" | tee -a "$LOG"
run "${WP[@]}" eval 'update_option("wpavatar_enable_cravatar","1");update_option("wpavatar_cdn_type","custom");update_option("wpavatar_custom_cdn","127.0.0.1");update_option("wpavatar_cache_path","/tmp/wpavatar-outside");update_option("wpavatar_timeout","99");update_option("wpavatar_shortcode_size","900");update_option("wpavatar_shortcode_shape","circle");echo "LEGACY_OPTIONS_SEEDED\n";'
run "${WP[@]}" plugin activate wpavatar/wpavatar.php
UPGRADE_JSON=$("${WP[@]}" eval 'echo wp_json_encode(array("raw"=>array("cdn"=>get_option("wpavatar_custom_cdn"),"cache"=>get_option("wpavatar_cache_path"),"timeout"=>get_option("wpavatar_timeout"),"size"=>get_option("wpavatar_shortcode_size"),"shape"=>get_option("wpavatar_shortcode_shape")),"effective"=>array("cdn"=>wpavatar_get_cdn_domain(),"cache"=>wpavatar_get_cache_base_dir(),"timeout"=>wpavatar_sanitize_timeout(get_option("wpavatar_timeout")),"size"=>wpavatar_sanitize_avatar_size(get_option("wpavatar_shortcode_size")),"shape"=>wpavatar_sanitize_shortcode_shape(get_option("wpavatar_shortcode_shape")))));')
echo "UPGRADE_JSON=$UPGRADE_JSON" | tee -a "$LOG"
python3 - "$UPGRADE_JSON" <<'PYASSERT'
import json,sys
v=json.loads(sys.argv[1])
assert v['raw']=={'cdn':'127.0.0.1','cache':'/tmp/wpavatar-outside','timeout':'99','size':'900','shape':'circle'},v
assert v['effective']['cdn']=='cravatar.com',v
assert v['effective']['cache'].endswith('/wp-content/uploads/cravatar/'),v
assert v['effective']['timeout']==30,v
assert v['effective']['size']==512,v
assert v['effective']['shape']=='circle',v
print('UPGRADE_ASSERTIONS=PASS')
PYASSERT
upgrade_rc=$?; echo "exit=$upgrade_rc" | tee -a "$LOG"; [[ $upgrade_rc -eq 0 ]]
run "${WP[@]}" option update wpavatar_enable_cache 0
PAGE_ID=$("${WP[@]}" post create --post_type=page --post_status=publish --post_title=Avatar-E2E --post_content='[wpavatar email="e2e@example.com" size="80"]' --porcelain)
echo "PAGE_ID=$PAGE_ID" | tee -a "$LOG"
run "${WP[@]}" eval 'update_option("wpavatar_cache_path","/var/www/html/wp-content/uploads/cravatar/");wp_mkdir_p(wpavatar_get_cache_base_dir());file_put_contents(wpavatar_get_cache_base_dir()."e2e.jpg","e2e");echo "CACHE_FIXTURE_CREATED\n";'
run npm ci --prefix "$ROOT/tests/e2e" --no-audit --no-fund
run env WPAVATAR_E2E_URL="http://127.0.0.1:${PORT}" WPAVATAR_E2E_PAGE_ID="$PAGE_ID" "$ROOT/tests/e2e/node_modules/.bin/playwright" test "$ROOT/tests/e2e/wpavatar.spec.js" --workers=1 --reporter=line
REMAINING=$("${WP[@]}" eval 'echo file_exists(wpavatar_get_cache_base_dir()."e2e.jpg")?"1":"0";')
echo "CACHE_FILE_REMAINING=$REMAINING" | tee -a "$LOG"
[[ "$REMAINING" == 0 ]]
DEBUG_COUNT=$("${WP[@]}" eval '$p=WP_CONTENT_DIR."/debug.log";echo file_exists($p)?count(array_filter(file($p))):0;')
echo "DEBUG_NONEMPTY_LINES=$DEBUG_COUNT" | tee -a "$LOG"
if [[ "$DEBUG_COUNT" != 0 ]]; then
  "${WP[@]}" eval '$p=WP_CONTENT_DIR."/debug.log";if(file_exists($p)){echo file_get_contents($p);}' | tee -a "$LOG"
  echo "DEBUG_LOG_ASSERTION=FAIL"; exit 1
fi
echo "SETTINGS_E2E=PASS"
echo "AVATAR_REPLACEMENT_E2E=PASS"
echo "CACHE_PURGE_E2E=PASS"
echo "LEGACY_1_9_5_CONFIG_FIXTURE_UPGRADE=PASS"
echo "LEGACY_1_9_5_RUNTIME_UPGRADE=SKIP(parse defect in origin/main)"
