#!/usr/bin/env bash
#
# Download WordPress core and the WordPress PHPUnit library.
# Does not install system packages.

set -euo pipefail

if [[ $# -lt 4 ]]; then
	echo "Usage: bin/install-wp-tests.sh <db-name> <db-user> <db-pass> <db-host> [wp-version]" >&2
	exit 1
fi

DB_NAME="$1"
DB_USER="$2"
DB_PASS="$3"
DB_HOST="$4"
WP_VERSION="${5:-7.1.3}"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/formlyx-wordpress-tests}"
WP_CORE_DIR="${WP_CORE_DIR:-/tmp/formlyx-wordpress-core}"
POLYFILLS_PATH="${WP_TESTS_PHPUNIT_POLYFILLS_PATH:-$ROOT/vendor/yoast/phpunit-polyfills}"
WORK_DIR="$(mktemp -d)"

cleanup() {
	rm -rf "$WORK_DIR"
}
trap cleanup EXIT

download() {
	local url="$1"
	local destination="$2"
	curl --fail --location --silent --show-error "$url" --output "$destination"
}

echo "Downloading WordPress ${WP_VERSION} core..."
download "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" "$WORK_DIR/wordpress.tar.gz"
rm -rf "$WP_CORE_DIR"
mkdir -p "$(dirname "$WP_CORE_DIR")"
tar -xzf "$WORK_DIR/wordpress.tar.gz" -C "$WORK_DIR"
mv "$WORK_DIR/wordpress" "$WP_CORE_DIR"

echo "Downloading WordPress ${WP_VERSION} test library..."
download "https://github.com/WordPress/wordpress-develop/archive/refs/tags/${WP_VERSION}.tar.gz" "$WORK_DIR/wordpress-develop.tar.gz"
rm -rf "$WP_TESTS_DIR"
mkdir -p "$(dirname "$WP_TESTS_DIR")"
tar -xzf "$WORK_DIR/wordpress-develop.tar.gz" -C "$WORK_DIR"
mv "$WORK_DIR/wordpress-develop-${WP_VERSION}/tests/phpunit" "$WP_TESTS_DIR"

if [[ ! -d "$POLYFILLS_PATH" ]]; then
	echo "PHPUnit polyfills were not found at ${POLYFILLS_PATH}. Run composer install first." >&2
	exit 1
fi

cat >"$WP_TESTS_DIR/wp-tests-config.php" <<PHP
<?php
define( 'ABSPATH', '${WP_CORE_DIR}/' );
define( 'WP_DEBUG', true );
define( 'DB_NAME', '${DB_NAME}' );
define( 'DB_USER', '${DB_USER}' );
define( 'DB_PASSWORD', '${DB_PASS}' );
define( 'DB_HOST', '${DB_HOST}' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );
\$table_prefix = 'wptests_';
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', '${POLYFILLS_PATH}' );
PHP

if [[ "${SKIP_DB_CREATE:-0}" != "1" ]]; then
	echo "Creating database ${DB_NAME}..."
	mysql --user="$DB_USER" --password="$DB_PASS" --host="$DB_HOST" --execute="CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;"
fi

echo "WP_TESTS_DIR=${WP_TESTS_DIR}"
echo "WP_CORE_DIR=${WP_CORE_DIR}"
