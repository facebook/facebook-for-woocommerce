#!/bin/bash
#
# Copyright (c) Meta, Inc. and its affiliates. All Rights Reserved
#
# This source code is licensed under the license found in the
# LICENSE file in the root directory of this source tree.
#

# Local PHPUnit environment
#
# Brings up everything ./vendor/bin/phpunit needs on a development machine, and
# tears it back down again, so the machine is left as it was found.
#
# Usage:
#   ./bin/local-setup.sh help
#   ./bin/local-setup.sh setup
#   ./bin/local-setup.sh cleanup

set -euo pipefail

# Paths are exported explicitly rather than left to $TMPDIR, which differs
# between shells and between a terminal and a tool running commands for you.
export WP_CORE_DIR="${WP_CORE_DIR:-/tmp/wordpress/src}"
export WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress/tests/phpunit}"

DB_NAME="${DB_NAME:-wordpress_test}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_HOST="${DB_HOST:-localhost}"
WP_VERSION="${WP_VERSION:-latest}"
WC_VERSION="${WC_VERSION:-latest}"

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Records that this script started MySQL, so cleanup only stops a server it is
# responsible for. Stopping one that was already running would be rude.
STATE_DIR="/tmp/wc-facebook-local-setup"
MYSQL_STARTED_MARKER="${STATE_DIR}/mysql-started-by-setup"

info() { printf '  %s\n' "$1"; }
step() { printf '\n▸ %s\n' "$1"; }
ok() { printf '  ✓ %s\n' "$1"; }
warn() { printf '  ! %s\n' "$1" >&2; }
fail() {
	printf '\n✗ %s\n' "$1" >&2
	exit 1
}

usage() {
	cat <<'EOF'
Local PHPUnit environment for Meta for WooCommerce.

USAGE
  ./bin/local-setup.sh <command>

COMMANDS
  setup      Start MySQL and install WordPress, the WP test library and
             WooCommerce. Safe to re-run; existing downloads are reused.
  cleanup    Drop the test database, remove the downloaded WordPress tree, and
             stop MySQL if this script was the one that started it.
  help       Show this message.

AFTER SETUP
  ./vendor/bin/phpunit --testsuite=unit
  ./vendor/bin/phpunit --testsuite=unit --filter OfflineEventsAjaxTest

REQUIREMENTS
  php, composer, mysql, svn
  On macOS:  brew install mysql svn

CONFIGURATION (environment variables, all optional)
  WP_CORE_DIR    WordPress core         (default /tmp/wordpress/src)
  WP_TESTS_DIR   WP test library        (default /tmp/wordpress/tests/phpunit)
  DB_NAME        Test database          (default wordpress_test)
  DB_USER        Database user          (default root)
  DB_PASS        Database password      (default empty)
  DB_HOST        Database host          (default localhost)
  WP_VERSION     WordPress version      (default latest)
  WC_VERSION     WooCommerce version    (default latest)

NOTES
  The test database is dropped and recreated by setup. Do not point DB_NAME at
  a database you care about.
EOF
}

require_command() {
	command -v "$1" >/dev/null 2>&1 || fail "$1 is not installed. $2"
}

check_requirements() {
	step 'Checking requirements'
	require_command php 'Install PHP 7.4 or newer.'
	require_command composer 'See https://getcomposer.org/download/'
	require_command mysql 'On macOS: brew install mysql'
	require_command svn 'Needed to fetch the WP test library. On macOS: brew install svn'
	ok "php $(php -r 'echo PHP_VERSION;')"

	if [ ! -x "${PLUGIN_DIR}/vendor/bin/phpunit" ]; then
		warn 'PHPUnit is missing; running composer install'
		( cd "$PLUGIN_DIR" && composer install --no-interaction )
	fi
	ok 'composer dev dependencies present'
}

mysql_is_up() {
	mysqladmin --user="$DB_USER" --password="$DB_PASS" --host="$DB_HOST" ping >/dev/null 2>&1
}

start_mysql() {
	step 'Starting MySQL'

	if mysql_is_up; then
		ok 'already running (left as-is; cleanup will not stop it)'
		return
	fi

	if command -v brew >/dev/null 2>&1 && brew list mysql >/dev/null 2>&1; then
		brew services start mysql >/dev/null 2>&1 || mysql.server start >/dev/null 2>&1 || true
	elif command -v mysql.server >/dev/null 2>&1; then
		mysql.server start >/dev/null 2>&1 || true
	fi

	# Give the server a moment to come up before deciding it failed.
	for _ in $(seq 1 20); do
		if mysql_is_up; then
			mkdir -p "$STATE_DIR"
			touch "$MYSQL_STARTED_MARKER"
			ok 'started (cleanup will stop it again)'
			return
		fi
		sleep 1
	done

	fail "Could not reach MySQL at ${DB_HOST} as ${DB_USER}.
  Start it yourself and re-run, e.g.:  brew services start mysql
  If the root password is not empty, pass it:  DB_PASS=yourpass ./bin/local-setup.sh setup"
}

install_test_suite() {
	step 'Installing WordPress, test library and WooCommerce'
	info "core:  ${WP_CORE_DIR}"
	info "tests: ${WP_TESTS_DIR}"

	chmod +x "${PLUGIN_DIR}/bin/install-wp-tests.sh"

	# install-wp-tests.sh prompts interactively when the database already exists,
	# which hangs any non-interactive run. Drop it first so there is nothing to
	# prompt about; setup recreating the test database from scratch is the
	# documented behaviour anyway.
	if mysql_is_up; then
		mysql --user="$DB_USER" --password="$DB_PASS" --host="$DB_HOST" \
			-e "DROP DATABASE IF EXISTS \`${DB_NAME}\`;" >/dev/null 2>&1 || true
	fi

	# </dev/null is a backstop: if a future prompt appears, fail rather than hang.
	( cd "$PLUGIN_DIR" && ./bin/install-wp-tests.sh \
		"$DB_NAME" "$DB_USER" "$DB_PASS" "$DB_HOST" "$WP_VERSION" "$WC_VERSION" \
		</dev/null )

	ok 'installed'
}

verify() {
	step 'Verifying'

	[ -f "${WP_TESTS_DIR}/includes/bootstrap.php" ] ||
		fail "WP test library missing at ${WP_TESTS_DIR}/includes/bootstrap.php"
	ok 'WP test library present'

	[ -d "${WP_CORE_DIR}/wp-content/plugins/woocommerce" ] ||
		fail "WooCommerce missing at ${WP_CORE_DIR}/wp-content/plugins/woocommerce"
	ok 'WooCommerce present'

	mysql --user="$DB_USER" --password="$DB_PASS" --host="$DB_HOST" \
		-e "USE ${DB_NAME};" >/dev/null 2>&1 ||
		fail "Test database ${DB_NAME} is not reachable"
	ok "database ${DB_NAME} reachable"
}

do_setup() {
	check_requirements
	start_mysql
	install_test_suite
	verify

	cat <<EOF

Ready. Run the suite with:

  export WP_CORE_DIR="${WP_CORE_DIR}"
  export WP_TESTS_DIR="${WP_TESTS_DIR}"
  ./vendor/bin/phpunit --testsuite=unit

Tear it down again with:  ./bin/local-setup.sh cleanup
EOF
}

do_cleanup() {
	step 'Dropping test database'
	if mysql_is_up; then
		mysql --user="$DB_USER" --password="$DB_PASS" --host="$DB_HOST" \
			-e "DROP DATABASE IF EXISTS \`${DB_NAME}\`;" >/dev/null 2>&1 &&
			ok "dropped ${DB_NAME}" ||
			warn "could not drop ${DB_NAME}"
	else
		info 'MySQL is not running; nothing to drop'
	fi

	step 'Removing downloaded WordPress tree'
	# Only remove the shared parent when both paths live under it, so a custom
	# WP_CORE_DIR pointing at a real checkout is never deleted.
	local wp_root
	wp_root="$(dirname "$(dirname "$WP_TESTS_DIR")")"
	if [ "$WP_CORE_DIR" = "${wp_root}/src" ] && [ -d "$wp_root" ]; then
		rm -rf "$wp_root"
		ok "removed ${wp_root}"
	else
		info "custom paths in use; remove these yourself if you want them gone:"
		info "  ${WP_CORE_DIR}"
		info "  ${WP_TESTS_DIR}"
	fi

	step 'Stopping MySQL'
	if [ -f "$MYSQL_STARTED_MARKER" ]; then
		if command -v brew >/dev/null 2>&1 && brew list mysql >/dev/null 2>&1; then
			brew services stop mysql >/dev/null 2>&1 || mysql.server stop >/dev/null 2>&1 || true
		elif command -v mysql.server >/dev/null 2>&1; then
			mysql.server stop >/dev/null 2>&1 || true
		fi
		rm -f "$MYSQL_STARTED_MARKER"
		rmdir "$STATE_DIR" 2>/dev/null || true
		ok 'stopped'
	else
		info 'MySQL was already running before setup; leaving it alone'
	fi

	printf '\nClean.\n'
}

case "${1:-help}" in
	setup) do_setup ;;
	cleanup) do_cleanup ;;
	help | -h | --help) usage ;;
	*)
		printf 'Unknown command: %s\n\n' "$1" >&2
		usage >&2
		exit 1
		;;
esac
