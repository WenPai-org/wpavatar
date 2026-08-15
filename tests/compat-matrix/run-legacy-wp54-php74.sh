#!/usr/bin/env bash
set -euo pipefail
ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
export WP_IMAGE="${WP_IMAGE:-wordpress:php7.4-apache}"
export WP_CLI_IMAGE="${WP_CLI_IMAGE:-wordpress:cli-php7.4}"
export EXPECTED_WP_VERSION="${EXPECTED_WP_VERSION:-5.4}"
export EXPECTED_PHP_PREFIX="${EXPECTED_PHP_PREFIX:-7.4}"
export MATRIX_LABEL="${MATRIX_LABEL:-legacy-wp54-php74}"
exec "$ROOT/tests/compat-matrix/run-current-wp7-php83.sh"
