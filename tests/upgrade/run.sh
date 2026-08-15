#!/usr/bin/env bash
set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
COMPOSE_FILE="$ROOT/tests/upgrade/compose.yml"
RUN_ID="${WPAVATAR_UPGRADE_RUN_ID:-$$}"
TMP="${TMPDIR:-/tmp}/wpavatar-upgrade-${RUN_ID}"
CANDIDATE="$TMP/candidate/wpavatar"
FIXTURES="$TMP/fixtures"
BACKUPS="$TMP/backups"
KEEP_ENV="${WPAVATAR_UPGRADE_KEEP_ENV:-0}"
mkdir -p "$CANDIDATE" "$BACKUPS"
rsync -a --exclude=.git --exclude=vendor --exclude=dist --exclude=tests --exclude=.phpunit.cache "$ROOT/" "$CANDIDATE/"
"$ROOT/tests/upgrade/prepare-legacy-fixture.sh" "$FIXTURES"

export WPAVATAR_CANDIDATE_DIR="$CANDIDATE"
export WPAVATAR_LEGACY_OFFICIAL_DIR="$FIXTURES/legacy-official/wpavatar"
export WPAVATAR_LEGACY_FIXED_DIR="$FIXTURES/legacy-syntax-fixed/wpavatar"
export WPAVATAR_UPGRADE_DIR="$ROOT/tests/upgrade"
export WPAVATAR_UPGRADE_BACKUP_DIR="$BACKUPS"

projects=()
cli_containers=()
cleanup() {
  local rc=$?
  trap - EXIT TERM INT
  for container in "${cli_containers[@]}"; do
    docker stop "$container" >/dev/null 2>&1 || true
  done
  if [[ "$KEEP_ENV" != 1 ]]; then
    for project in "${projects[@]}"; do
      docker compose -p "$project" -f "$COMPOSE_FILE" down --volumes --remove-orphans >/dev/null 2>&1 || true
    done
  fi
  echo "UPGRADE_ARTIFACT_DIR=$TMP"
  echo "UPGRADE_EXIT=$rc"
  exit "$rc"
}
on_term() {
  echo "UPGRADE_SIGNAL=TERM" >&2
  exit 124
}
trap cleanup EXIT
trap on_term TERM INT

start_cli() {
  local -n dc_ref=$1
  local container=$2
  "${dc_ref[@]}" run -d --no-deps --name "$container" --entrypoint tail cli -f /dev/null >/dev/null
  cli_containers+=("$container")
}

wait_for_core() {
  local container=$1
  for _ in $(seq 1 60); do
    if docker exec "$container" wp core version >/dev/null 2>&1; then return 0; fi
    sleep 2
  done
  echo "WordPress files were not ready" >&2
  return 1
}

install_legacy() {
  local container=$1
  docker exec "$container" sh -c     'mkdir -p /var/www/html/wp-content/plugins && cp -a /fixtures/legacy-fixed /var/www/html/wp-content/plugins/wpavatar'
}

inject_official_fatal() {
  local container=$1
  docker exec "$container" sh -c     'cp /fixtures/legacy-official/includes/multisite.php /var/www/html/wp-content/plugins/wpavatar/includes/multisite.php'
  set +e
  docker exec "$container" wp plugin status wpavatar >/dev/null 2>&1
  local rc=$?
  set -e
  [[ $rc -ne 0 ]] || { echo "Expected unmodified v1.9.5 runtime to fail" >&2; return 1; }
  echo "OFFICIAL_V1_9_5_RUNTIME_EXIT=$rc"
}

safe_replace() {
  local container=$1
  local mode=$2
  docker exec "$container" sh /fixtures/upgrade/safe-replace.sh     /var/www/html /fixtures/candidate /upgrade-backups "$mode"
}

assert_single() {
  local container=$1
  local json
  json=$(docker exec "$container" wp eval 'echo wp_json_encode(array("candidate_version"=>WPAVATAR_VERSION,"option"=>get_option("wpavatar_custom_cdn"),"cache_path"=>get_option("wpavatar_cache_path"),"cache_exists"=>file_exists(WP_CONTENT_DIR."/uploads/cravatar/legacy-single.jpg"),"sentinel"=>get_option("wpavatar_upgrade_sentinel"),"active"=>is_plugin_active("wpavatar/wpavatar.php")));')
  python3 - "$json" <<'PYASSERT'
import json,sys
v=json.loads(sys.argv[1])
assert v == {
  'candidate_version':'1.9.6',
  'option':'legacy.example.test',
  'cache_path':'/var/www/html/wp-content/uploads/cravatar/',
  'cache_exists':True,
  'sentinel':{'keep':'single'},
  'active':True,
}, v
print('SINGLE_SITE_UPGRADE_ASSERTIONS=PASS')
PYASSERT
}

assert_network() {
  local container=$1
  local json
  json=$(docker exec "$container" wp eval 'switch_to_blog(2);$site=get_option("wpavatar_custom_cdn");$site_sentinel=get_option("wpavatar_upgrade_site_sentinel");restore_current_blog();echo wp_json_encode(array("candidate_version"=>WPAVATAR_VERSION,"network"=>get_site_option("wpavatar_custom_cdn"),"controlled"=>get_site_option("wpavatar_network_controlled_options"),"site"=>$site,"site_sentinel"=>$site_sentinel,"cache_main"=>file_exists(WP_CONTENT_DIR."/uploads/cravatar/legacy-network.jpg"),"cache_site"=>file_exists(WP_CONTENT_DIR."/uploads/cravatar/site-2/legacy-network.jpg"),"network_active"=>is_plugin_active_for_network("wpavatar/wpavatar.php")));')
  python3 - "$json" <<'PYASSERT'
import json,sys
v=json.loads(sys.argv[1])
assert v == {
  'candidate_version':'1.9.6',
  'network':'network.example.test',
  'controlled':['wpavatar_custom_cdn','wpavatar_enable_cache'],
  'site':'site-two.example.test',
  'site_sentinel':'keep-network-site',
  'cache_main':True,
  'cache_site':True,
  'network_active':True,
}, v
print('MULTISITE_UPGRADE_ASSERTIONS=PASS')
PYASSERT
}

run_single() {
  local project="wpavatar-upgrade-single-$RUN_ID"
  local cli="${project}-cli-shell"
  projects+=("$project")
  local dc=(docker compose -p "$project" -f "$COMPOSE_FILE")
  "${dc[@]}" up -d db wordpress
  start_cli dc "$cli"
  wait_for_core "$cli"
  docker exec "$cli" wp core install --url=http://wordpress --title=Upgrade-Single --admin_user=admin --admin_password=admin-pass --admin_email=admin@example.test --skip-email
  install_legacy "$cli"
  docker exec "$cli" wp plugin activate wpavatar
  docker exec "$cli" wp eval 'update_option("wpavatar_custom_cdn","legacy.example.test");update_option("wpavatar_cache_path",WP_CONTENT_DIR."/uploads/cravatar/");update_option("wpavatar_upgrade_sentinel",array("keep"=>"single"));wp_mkdir_p(WP_CONTENT_DIR."/uploads/cravatar");file_put_contents(WP_CONTENT_DIR."/uploads/cravatar/legacy-single.jpg","legacy");'
  inject_official_fatal "$cli"
  safe_replace "$cli" single
  assert_single "$cli"
}

run_network() {
  local project="wpavatar-upgrade-network-$RUN_ID"
  local cli="${project}-cli-shell"
  projects+=("$project")
  local dc=(docker compose -p "$project" -f "$COMPOSE_FILE")
  "${dc[@]}" up -d db wordpress
  start_cli dc "$cli"
  wait_for_core "$cli"
  docker exec "$cli" wp core install --url=http://wordpress --title=Upgrade-Network --admin_user=admin --admin_password=admin-pass --admin_email=admin@example.test --skip-email
  docker exec "$cli" wp core multisite-convert --title=Upgrade-Network --subdomains=false
  docker exec "$cli" wp site create --slug=site-two --title=Site-Two --email=admin@example.test
  install_legacy "$cli"
  docker exec "$cli" wp plugin activate wpavatar --network
  docker exec "$cli" wp eval 'update_site_option("wpavatar_custom_cdn","network.example.test");update_site_option("wpavatar_network_controlled_options",array("wpavatar_custom_cdn","wpavatar_enable_cache"));switch_to_blog(2);update_option("wpavatar_custom_cdn","site-two.example.test");update_option("wpavatar_upgrade_site_sentinel","keep-network-site");restore_current_blog();wp_mkdir_p(WP_CONTENT_DIR."/uploads/cravatar/site-2");file_put_contents(WP_CONTENT_DIR."/uploads/cravatar/legacy-network.jpg","legacy");file_put_contents(WP_CONTENT_DIR."/uploads/cravatar/site-2/legacy-network.jpg","legacy-site");'
  inject_official_fatal "$cli"
  safe_replace "$cli" network
  assert_network "$cli"
}

run_single
run_network
echo "LEGACY_1_9_5_TO_1_9_6_SAFE_REPLACE_UPGRADE=PASS"
echo "NO_USER_DATA_DELETION_ASSERTIONS=PASS"
