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

# Configuration is read only from FB_TEST_* variables. Generic names such as
# DB_NAME, DB_HOST or WP_CORE_DIR are commonly set by other projects (.env files,
# direnv, other plugins' test setups), and inheriting them could point this script
# at a real database or WordPress install. The values passed to install-wp-tests.sh
# are baked into wp-tests-config.php, which is where the tests read them from.
#
# Note which generic names the environment set before this script assigns its own
# variables of the same name, so it can say it is ignoring them.
INHERITED_GENERIC_NAMES=""
for generic_name in DB_NAME DB_USER DB_PASS DB_HOST WP_VERSION WC_VERSION; do
	if [ -n "${!generic_name:-}" ]; then
		INHERITED_GENERIC_NAMES="${INHERITED_GENERIC_NAMES} ${generic_name}"
	fi
done

WP_ROOT="${FB_TEST_WP_DIR:-/tmp/wordpress}"
DB_NAME="${FB_TEST_DB_NAME:-wordpress_test}"
DB_USER="${FB_TEST_DB_USER:-root}"
DB_PASS="${FB_TEST_DB_PASS:-}"
DB_HOST="${FB_TEST_DB_HOST:-localhost}"
WP_VERSION="${FB_TEST_WP_VERSION:-latest}"
WC_VERSION="${FB_TEST_WC_VERSION:-latest}"

# The test bootstrap does read these two at run time, so they keep their standard
# names, but are derived only from WP_ROOT, never inherited. Remember what the
# shell had, to warn about it: it would also apply when running phpunit.
INHERITED_WP_CORE_DIR="${WP_CORE_DIR:-}"
INHERITED_WP_TESTS_DIR="${WP_TESTS_DIR:-}"
export WP_CORE_DIR="${WP_ROOT}/src"
export WP_TESTS_DIR="${WP_ROOT}/tests/phpunit"

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Records that this script started MySQL, so cleanup only stops a server it is
# responsible for. Stopping one that was already running would be rude.
STATE_DIR="/tmp/wc-facebook-local-setup"
MYSQL_STARTED_MARKER="${STATE_DIR}/mysql-started-by-setup"

# Written into the WordPress tree by setup, and only when setup created that tree.
# Setup refuses to install into, and cleanup refuses to delete, a tree without it.
CREATED_MARKER=".created-by-wc-facebook-local-setup"

# A table created inside the test database by setup, after setup created that
# database. Setup and cleanup refuse to drop a database without it. It lives in the
# database itself, so it survives a reboot clearing /tmp, and has no wptests_ prefix
# so the WordPress test suite's own table resets leave it alone.
DB_MARKER_TABLE="fb_local_setup_marker"

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
             stop MySQL if this script was the one that started it. See WHAT IT
             TOUCHES below.
  help       Show this message.

AFTER SETUP
  ./vendor/bin/phpunit --testsuite=unit
  ./vendor/bin/phpunit --testsuite=unit --filter OfflineEventsAjaxTest

REQUIREMENTS
  php, composer, mysql, svn
  On macOS:  brew install mysql svn

CONFIGURATION (environment variables, all optional)
  FB_TEST_WP_DIR      Where WordPress and the test library go
                      (default /tmp/wordpress; core in src/, tests in tests/phpunit/)
  FB_TEST_DB_NAME     Test database          (default wordpress_test)
  FB_TEST_DB_USER     Database user          (default root)
  FB_TEST_DB_PASS     Database password      (default empty)
  FB_TEST_DB_HOST     Database host          (default localhost)
  FB_TEST_WP_VERSION  WordPress version      (default latest)
  FB_TEST_WC_VERSION  WooCommerce version    (default latest)

  Generic names such as DB_NAME, DB_HOST or WP_CORE_DIR are deliberately ignored:
  other projects often set them, and they could point at real data.

WHAT IT TOUCHES
  setup    Creates FB_TEST_WP_DIR and FB_TEST_DB_NAME, and refuses to proceed if
           either already exists without having been created by this script —
           it will not install into, or drop, anything it did not create.
           Starts MySQL if it is not running.
  cleanup  Drops the test database and removes FB_TEST_WP_DIR, each only if this
           script created it, and never a path that is, contains or sits inside
           a git working tree, this plugin, / or your home directory. Stops MySQL
           only if setup started it. It never runs git, and never touches your
           checkout or uncommitted work.
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

	# Never install into a WordPress tree this script did not create:
	# install-wp-tests.sh replaces the WooCommerce and Polylang plugin directories
	# inside it with rm -rf, which would destroy a real install's copies.
	local wp_root_existed=false
	if [ -e "$WP_ROOT" ]; then
		if [ ! -f "${WP_ROOT}/${CREATED_MARKER}" ]; then
			fail "${WP_ROOT} already exists and was not created by this script.
  Setup will not install into it: install-wp-tests.sh replaces plugin directories inside it.
  Use another location, e.g.  FB_TEST_WP_DIR=/tmp/wc-facebook-wordpress ./bin/local-setup.sh setup
  If it is a leftover from an earlier version of this script, remove it yourself and re-run."
		fi
		wp_root_existed=true
	fi

	# Never drop a database this script did not create. install-wp-tests.sh would
	# prompt before replacing an existing one, which hangs a non-interactive run, so
	# an existing database that setup created is dropped first and recreated.
	if db_exists; then
		if ! db_created_by_setup; then
			fail "Database ${DB_NAME} already exists on ${DB_HOST} and was not created by this script.
  Setup will not drop it. Use another name, e.g.  FB_TEST_DB_NAME=wc_facebook_test ./bin/local-setup.sh setup
  If it is a leftover from an earlier version of this script and is disposable, drop it yourself and re-run."
		fi
		run_mysql -e "DROP DATABASE \`${DB_NAME}\`;"
	fi

	# </dev/null is a backstop: if a future prompt appears, fail rather than hang.
	( cd "$PLUGIN_DIR" && ./bin/install-wp-tests.sh \
		"$DB_NAME" "$DB_USER" "$DB_PASS" "$DB_HOST" "$WP_VERSION" "$WC_VERSION" \
		</dev/null )

	# Mark what this script created, so later runs and cleanup recognise it as ours.
	if [ "$wp_root_existed" = false ]; then
		touch "${WP_ROOT}/${CREATED_MARKER}"
	fi
	run_mysql -e "CREATE TABLE IF NOT EXISTS \`${DB_NAME}\`.\`${DB_MARKER_TABLE}\` (created_at DATETIME NOT NULL);"

	ok 'installed'
}

# Runs the mysql client against the configured server.
run_mysql() {
	mysql --user="$DB_USER" --password="$DB_PASS" --host="$DB_HOST" "$@" 2>/dev/null
}

# Whether the configured test database exists. Exact match; LIKE would treat the
# underscores in names such as wordpress_test as wildcards.
db_exists() {
	[ -n "$(run_mysql -N -e "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '${DB_NAME}';")" ]
}

# Whether the configured test database carries the marker setup leaves in it.
db_created_by_setup() {
	[ -n "$(run_mysql -N -e "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '${DB_NAME}' AND TABLE_NAME = '${DB_MARKER_TABLE}';")" ]
}

# Rejects database names that would not be safe to place in the SQL above.
validate_db_name() {
	case "$DB_NAME" in
		'' | *[!A-Za-z0-9_]*)
			fail "FB_TEST_DB_NAME must contain only letters, digits and underscores (got '${DB_NAME}')."
			;;
	esac
}

# Notes configuration in the environment that this script deliberately ignores.
warn_about_inherited_configuration() {
	local name
	for name in $INHERITED_GENERIC_NAMES; do
		info "ignoring ${name} from your environment; this script reads FB_TEST_${name} instead"
	done

	# These two matter beyond setup: the test bootstrap reads them when phpunit runs.
	if [ -n "$INHERITED_WP_CORE_DIR" ] && [ "$INHERITED_WP_CORE_DIR" != "$WP_CORE_DIR" ]; then
		warn "your shell sets WP_CORE_DIR=${INHERITED_WP_CORE_DIR}; setup ignores it, but phpunit would use it."
		warn "export the WP_CORE_DIR and WP_TESTS_DIR printed at the end before running the tests."
	elif [ -n "$INHERITED_WP_TESTS_DIR" ] && [ "$INHERITED_WP_TESTS_DIR" != "$WP_TESTS_DIR" ]; then
		warn "your shell sets WP_TESTS_DIR=${INHERITED_WP_TESTS_DIR}; setup ignores it, but phpunit would use it."
		warn "export the WP_CORE_DIR and WP_TESTS_DIR printed at the end before running the tests."
	fi
}

# The directory holding both WP_CORE_DIR and WP_TESTS_DIR, e.g. /tmp/wordpress.
wp_root_dir() {
	printf '%s\n' "$WP_ROOT"
}

# Prints a directory's physical path, or nothing if it does not exist.
physical_path() {
	( cd "$1" 2>/dev/null && pwd -P )
}

# Decides whether cleanup may delete a WordPress tree. Exits 0 and prints the reason
# when it is UNSAFE; exits 1 when deleting it cannot touch anything but the tree
# setup downloaded. Cleanup must never reach a git checkout or uncommitted work,
# whatever WP_CORE_DIR and WP_TESTS_DIR are set to.
unsafe_to_remove() {
	local dir resolved plugin home
	dir="$1"

	if [ "$WP_CORE_DIR" != "${dir}/src" ]; then
		echo "custom paths are in use"
		return 0
	fi

	resolved="$(physical_path "$dir")"
	if [ -z "$resolved" ]; then
		echo "${dir} does not exist"
		return 0
	fi

	home="$(physical_path "$HOME")"
	if [ "$resolved" = "/" ] || [ "$resolved" = "$home" ]; then
		echo "${resolved} is the filesystem root or your home directory"
		return 0
	fi

	plugin="$(physical_path "$PLUGIN_DIR")"
	case "${plugin}/" in
		"${resolved}/"*)
			echo "${resolved} contains this plugin's checkout"
			return 0
			;;
	esac
	case "${resolved}/" in
		"${plugin}/"*)
			echo "${resolved} is inside this plugin's checkout"
			return 0
			;;
	esac

	if git -C "$resolved" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
		echo "${resolved} is inside a git working tree"
		return 0
	fi

	if [ -n "$(find "$resolved" -name .git -print -quit 2>/dev/null)" ]; then
		echo "${resolved} contains a git repository"
		return 0
	fi

	if [ ! -f "${resolved}/${CREATED_MARKER}" ]; then
		echo "${resolved} was not created by ./bin/local-setup.sh setup"
		return 0
	fi

	return 1
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
	validate_db_name
	check_requirements
	warn_about_inherited_configuration
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
	validate_db_name

	step 'Dropping test database'
	if ! mysql_is_up; then
		info 'MySQL is not running; nothing to drop'
	elif ! db_exists; then
		info "${DB_NAME} does not exist; nothing to drop"
	elif ! db_created_by_setup; then
		info "left in place: ${DB_NAME} was not created by ./bin/local-setup.sh setup"
	elif run_mysql -e "DROP DATABASE \`${DB_NAME}\`;"; then
		ok "dropped ${DB_NAME}"
	else
		warn "could not drop ${DB_NAME}"
	fi

	step 'Removing downloaded WordPress tree'
	local wp_root reason
	wp_root="$(wp_root_dir)"
	if reason="$(unsafe_to_remove "$wp_root")"; then
		info "left in place: ${reason}"
		info "remove these yourself if you want them gone:"
		info "  ${WP_CORE_DIR}"
		info "  ${WP_TESTS_DIR}"
	else
		rm -rf "$wp_root"
		ok "removed ${wp_root}"
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
