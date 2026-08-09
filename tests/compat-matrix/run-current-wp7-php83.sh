#!/usr/bin/env bash
set -uo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
COMPOSE_FILE="$ROOT/tests/compat-matrix/compose.yml"
RUN_ID="${WPAVATAR_MATRIX_RUN_ID:-$$}"
RESULT_DIR="$ROOT/tests/compat-matrix/results/current-wp7-php83-${RUN_ID}"
mkdir -p "$RESULT_DIR"
SUMMARY="$RESULT_DIR/summary.txt"
: > "$SUMMARY"

export WP_IMAGE="${WP_IMAGE:-wordpress:7.0.2-php8.3-apache}"
export WP_CLI_IMAGE="${WP_CLI_IMAGE:-wordpress:cli-php8.3}"
EXPECTED_WP_VERSION="${EXPECTED_WP_VERSION:-7.0.3}"
export WPAVATAR_PLUGIN_DIR="$ROOT"

record() {
  local label=$1
  shift
  printf '+ %q ' "$@" | tee -a "$SUMMARY"
  printf '\n' | tee -a "$SUMMARY"
  "$@" >>"$SUMMARY" 2>&1
  local rc=$?
  echo "$label exit=$rc" | tee -a "$SUMMARY"
  return "$rc"
}

run_case() {
  local mode=$1
  local port=$2
  local project="wpavatar-matrix-wp7-php83-${mode}-${RUN_ID}"
  export WP_PORT=$port
  local -a dc=(docker compose -p "$project" -f "$COMPOSE_FILE")
  local -a wp=("${dc[@]}" exec -T wordpress wp --allow-root)
  local rc=0

  cleanup_case() {
    "${dc[@]}" down --volumes --remove-orphans >>"$SUMMARY" 2>&1 || true
  }
  trap cleanup_case RETURN

  record "${mode}.up" "${dc[@]}" up -d db wordpress || rc=1
  local ready=0
  for _ in $(seq 1 90); do
    if curl --max-time 3 -fsS "http://127.0.0.1:${port}/wp-admin/install.php" >/dev/null 2>&1; then
      ready=1
      break
    fi
    sleep 2
  done
  echo "${mode}.http_ready=$ready" | tee -a "$SUMMARY"
  [[ $ready -eq 1 ]] || rc=1
  local config_ready=0
  for _ in $(seq 1 30); do
    if "${dc[@]}" exec -T wordpress test -s /var/www/html/wp-config.php >/dev/null 2>&1; then
      config_ready=1
      break
    fi
    sleep 2
  done
  echo "${mode}.wp_config_ready=$config_ready" | tee -a "$SUMMARY"
  if [[ $config_ready -ne 1 ]]; then
    echo "${mode}=FAIL(wp-config not ready)" | tee -a "$SUMMARY"
    return 1
  fi
  local core_ready=0
  local attempt
  for attempt in 1 2 3; do
    if record "${mode}.core_download_attempt_${attempt}" "${wp[@]}" core download --version="$EXPECTED_WP_VERSION" --force --skip-content; then
      core_ready=1
      break
    fi
    sleep $(( attempt * 2 ))
  done
  if [[ $core_ready -ne 1 ]]; then
    echo "${mode}=FAIL(core download retries exhausted)" | tee -a "$SUMMARY"
    return 1
  fi

  if [[ $mode == single ]]; then
    record "single.install" "${wp[@]}" core install --url="http://127.0.0.1:${port}" --title=WPAvatar-Matrix --admin_user=admin --admin_password=matrix-pass --admin_email=admin@example.test --skip-email || rc=1
  else
    record "multisite.install" "${wp[@]}" core multisite-install --url="matrix.test" --title=WPAvatar-Matrix-MS --admin_user=admin --admin_password=matrix-pass --admin_email=admin@example.test --skip-email --subdomains || rc=1
  fi

  # Core installation may log upstream translation-network warnings. Establish a
  # clean baseline before loading WPAvatar so the final assertion is plugin-scoped.
  record "${mode}.debug_baseline_reset" "${dc[@]}" exec -T wordpress sh -lc 'rm -f /var/www/html/wp-content/debug.log; : > /var/www/html/wp-content/debug.log; chown www-data:www-data /var/www/html/wp-content/debug.log' || rc=1

  if [[ $mode == single ]]; then
    record "single.activate" "${wp[@]}" plugin activate wpavatar || rc=1
  else
    record "multisite.activate" "${wp[@]}" plugin activate wpavatar --network || rc=1
    record "multisite.create_subsite" "${wp[@]}" site create --slug=two --title=Two --email=admin@example.test || rc=1
  fi

  record "${mode}.runtime_version" "${wp[@]}" eval 'global $wp_version; if ( "7.0.3" !== $wp_version || 0 !== strpos( PHP_VERSION, "8.3." ) ) { fwrite( STDERR, "unexpected WP/PHP: " . $wp_version . "/" . PHP_VERSION ); exit( 1 ); } echo "WP=$wp_version PHP=" . PHP_VERSION . "\n";' || rc=1
  record "${mode}.candidate_version" "${wp[@]}" eval 'if ( "1.9.6" !== WPAVATAR_VERSION ) { fwrite( STDERR, "unexpected candidate version=" . WPAVATAR_VERSION ); exit( 1 ); } echo "WPAVATAR_VERSION=" . WPAVATAR_VERSION . "\n";' || rc=1
  record "${mode}.candidate_metadata" "${wp[@]}" plugin get wpavatar --field=version || rc=1
  candidate_metadata=$("${wp[@]}" plugin get wpavatar --field=version 2>>"$SUMMARY")
  if [[ "$candidate_metadata" != 1.9.6 ]]; then echo "${mode}.candidate_metadata_assert=FAIL($candidate_metadata)" | tee -a "$SUMMARY"; rc=1; else echo "${mode}.candidate_metadata_assert=PASS(1.9.6)" | tee -a "$SUMMARY"; fi
  if [[ $mode == single ]]; then
    record "single.active_assert" "${wp[@]}" plugin is-active wpavatar || rc=1
    record "single.route_cache_assert" "${wp[@]}" eval '
      $email="matrix@example.test";
      $url=get_avatar_url($email,array("size"=>96));
      $host=wp_parse_url($url,PHP_URL_HOST);
      $hash=hash("sha256",$email);
      $path=\WPAvatar\Cache::get_cache_path($hash,96);
      if("cravatar.com"!==$host || false===strpos($url,"/avatar/".$hash) || false!==strpos($path,"/site-") || false===strpos(wp_normalize_path($path),wp_normalize_path(WP_CONTENT_DIR."/uploads/cravatar/"))){fwrite(STDERR,wp_json_encode(compact("url","host","path")));exit(1);} echo wp_json_encode(compact("url","host","path"))."\n";' || rc=1
  else
    record "multisite.active_assert" "${wp[@]}" plugin is-active wpavatar --network || rc=1
    record "multisite.route_cache_assert" "${wp[@]}" eval '
      $email="matrix@example.test";$hash=hash("sha256",$email);
      $url1=get_avatar_url($email,array("size"=>96));$path1=\WPAvatar\Cache::get_cache_path($hash,96);
      $sites=get_sites(array("number"=>0,"fields"=>"ids"));$other=0;foreach($sites as $id){if((int)$id!==get_main_site_id()){ $other=(int)$id; break; }}
      if(!$other){fwrite(STDERR,"no subsite");exit(1);}switch_to_blog($other);$url2=get_avatar_url($email,array("size"=>96));$path2=\WPAvatar\Cache::get_cache_path($hash,96);restore_current_blog();
      $host1=wp_parse_url($url1,PHP_URL_HOST);$host2=wp_parse_url($url2,PHP_URL_HOST);
      if("cravatar.com"!==$host1 || "cravatar.com"!==$host2 || false===strpos($path1,"/site-1/") || false===strpos($path2,"/site-".$other."/") || $path1===$path2){fwrite(STDERR,wp_json_encode(compact("url1","url2","path1","path2","other")));exit(1);}echo wp_json_encode(compact("url1","url2","path1","path2","other"))."\n";' || rc=1
  fi

  record "${mode}.front_http" curl --max-time 10 -fsS -o /dev/null "http://127.0.0.1:${port}/" || rc=1
  record "${mode}.debug_assert" "${wp[@]}" eval '$p=WP_CONTENT_DIR."/debug.log";$lines=file_exists($p)?array_values(array_filter(file($p),"strlen")):array();if($lines){fwrite(STDERR,implode("",$lines));exit(1);}echo "DEBUG_NONEMPTY_LINES=0\n";' || rc=1
  if [[ $rc -eq 0 ]]; then
    echo "${mode}=PASS" | tee -a "$SUMMARY"
  else
    echo "${mode}=FAIL" | tee -a "$SUMMARY"
  fi
  return "$rc"
}

overall=0
run_case single "${WPAVATAR_SINGLE_PORT:-19071}" || overall=1
run_case multisite "${WPAVATAR_MULTISITE_PORT:-19072}" || overall=1
echo "WP_IMAGE=$WP_IMAGE" | tee -a "$SUMMARY"
echo "WP_CLI_IMAGE=$WP_CLI_IMAGE" | tee -a "$SUMMARY"
echo "MATRIX_EXIT=$overall" | tee -a "$SUMMARY"
echo "MATRIX_RESULT_DIR=$RESULT_DIR"
exit "$overall"
