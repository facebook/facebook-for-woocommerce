#!/bin/bash
#
# Shared helpers for driving WP-CLI against a Local (by Flywheel/WP Engine)
# site from a plain terminal.
#
# Source this, call local_wp_resolve <site>, then use wp_local as you would wp:
#
#     source "$(dirname "$0")/lib/local-wp.sh"
#     local_wp_resolve "$1"
#     wp_local plugin list
#
# After local_wp_resolve, these are set: SITE_ID, SITE_NAME, SITE_PATH,
# DOCROOT, PHP_BIN, PHP_INI, MYSQL_SOCK.
#

LOCAL_SUPPORT="$HOME/Library/Application Support/Local"
LOCAL_SITES_JSON="$LOCAL_SUPPORT/sites.json"
LOCAL_WP_CLI_PHAR="/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/wp-cli.phar"

local_wp_die() { echo "${0##*/}: $*" >&2; exit 1; }

# Resolves a site from Local's sites.json. Everything site-specific (id, path,
# PHP version) is looked up rather than hardcoded: the id is random per site and
# the PHP build suffix changes when Local updates its bundled services.
local_wp_resolve() {
	local want="${1:-}"

	[[ -f "$LOCAL_SITES_JSON" ]]  || local_wp_die "no sites.json found — is Local installed?"
	[[ -f "$LOCAL_WP_CLI_PHAR" ]] || local_wp_die "wp-cli.phar not found at $LOCAL_WP_CLI_PHAR"

	local resolve_py
	read -r -d '' resolve_py <<-'PY' || true
	import json, os, sys, shlex

	sites = json.load(open(sys.argv[1]))
	want = sys.argv[2].lower()


	def listing():
	    return '\n'.join(
	        f"  {s.get('name', '?')}  ({s.get('domain', '?')})" for s in sites.values()
	    )


	if not want:
	    sys.exit(f"missing site argument. Available sites:\n{listing()}")


	def aliases(sid, s):
	    """A site is addressable by its id, name, domain, bare domain label, or
	    the folder name under ~/Local Sites — these are often all different."""
	    domain = s.get('domain', '')
	    return {
	        a.lower() for a in (
	            sid,
	            s.get('name', ''),
	            domain,
	            domain.split('.')[0],
	            os.path.basename(os.path.expanduser(s.get('path', '')).rstrip('/')),
	        ) if a
	    }


	matches = [s for sid, s in sites.items() if want in aliases(sid, s)]
	if not matches:
	    # Fall back to a substring match, but only when it is unambiguous.
	    matches = [s for sid, s in sites.items() if any(want in a for a in aliases(sid, s))]
	if not matches:
	    sys.exit(f"no site matching {sys.argv[2]!r}. Available sites:\n{listing()}")
	if len(matches) > 1:
	    names = ', '.join(s.get('name', '?') for s in matches)
	    sys.exit(f"{sys.argv[2]!r} is ambiguous ({names}). Use the exact name or domain.")

	site = matches[0]
	print(f"SITE_ID={shlex.quote(site['id'])}")
	print(f"SITE_NAME={shlex.quote(site.get('name', ''))}")
	print(f"SITE_PATH={shlex.quote(os.path.expanduser(site['path']))}")
	print(f"PHP_VERSION={shlex.quote(site.get('services', {}).get('php', {}).get('version', ''))}")
	PY

	local resolved
	resolved=$(python3 -c "$resolve_py" "$LOCAL_SITES_JSON" "$want") || exit 1
	eval "$resolved"

	DOCROOT="$SITE_PATH/app/public"
	PHP_INI="$LOCAL_SUPPORT/run/$SITE_ID/conf/php/php.ini"
	MYSQL_SOCK="$LOCAL_SUPPORT/run/$SITE_ID/mysql/mysqld.sock"

	local arch
	arch="darwin-$( [[ "$(uname -m)" == "arm64" ]] && echo arm64 || echo x64 )"

	# Lightning service dirs carry a build suffix (e.g. php-8.2.29+0), so glob
	# for it. nullglob keeps a miss from leaving the pattern behind.
	local php_candidates
	shopt -s nullglob
	php_candidates=("$LOCAL_SUPPORT/lightning-services/php-$PHP_VERSION"*/bin/"$arch"/bin/php)
	PHP_BIN="${php_candidates[0]:-}"
	shopt -u nullglob

	[[ -d "$DOCROOT" ]] || local_wp_die "site '$SITE_NAME' has no WordPress at $DOCROOT"
	[[ -x "$PHP_BIN" ]] || local_wp_die "no PHP $PHP_VERSION binary bundled for site '$SITE_NAME'"

	# php.ini carries mysqli.default_socket, which is the only thing pointing
	# DB_HOST=localhost at this site's mysqld. It exists only while the site runs.
	[[ -f "$PHP_INI" ]] || local_wp_die "site '$SITE_NAME' looks stopped — start it in Local first"
}

# Runs WP-CLI against the resolved site.
wp_local() {
	# MYSQL_UNIX_PORT matters for `wp db *`: the mysql client shells out and
	# does not read php.ini, so it would otherwise hit the system socket.
	( cd "$DOCROOT" &&
		MYSQL_UNIX_PORT="$MYSQL_SOCK" \
		"$PHP_BIN" -c "$PHP_INI" "$LOCAL_WP_CLI_PHAR" "$@" )
}
