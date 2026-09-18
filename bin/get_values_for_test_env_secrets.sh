#!/bin/bash
#
# Read Facebook for WooCommerce options from a Local (by Flywheel/WP Engine)
# site and output them as JSON.
#
# Usage: ./get_values_for_test_env_secrets.sh <site-name|domain|site-id>
# Example: ./get_values_for_test_env_secrets.sh my-site
#
# Exits non-zero if any option is empty; the JSON is still written to stdout so
# callers can inspect what was found. Warnings go to stderr.
#
set -euo pipefail

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib/local-wp.sh"

OPTION_KEYS=(
	wc_facebook_access_token
	wc_facebook_merchant_access_token
	wc_facebook_business_manager_id
	wc_facebook_external_business_id
	wc_facebook_product_catalog_id
	wc_facebook_pixel_id
	wc_facebook_page_id
)

local_wp_resolve "${1:-}"

# One WordPress bootstrap for all keys, rather than one per option. json_encode
# handles escaping, so a value containing a quote can't produce invalid JSON.
read -r -d '' READ_OPTIONS_PHP <<'PHP' || true
$out = [];
foreach ( explode( ',', getenv( 'WP_OPTION_KEYS' ) ) as $key ) {
	$value       = get_option( $key, '' );
	$out[ $key ] = is_scalar( $value ) ? (string) $value : $value;
}
echo json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
PHP

keys_csv=$(IFS=,; echo "${OPTION_KEYS[*]}")
JSON=$(WP_OPTION_KEYS="$keys_csv" wp_local eval "$READ_OPTIONS_PHP") \
	|| local_wp_die "wp-cli failed against site '$SITE_NAME'"

echo "$JSON"

# Report empty options instead of silently emitting a file full of "".
EMPTY=$(printf '%s' "$JSON" | python3 -c '
import json, sys
data = json.load(sys.stdin)
print(" ".join(k for k, v in data.items() if v == ""))
')

if [[ -n "$EMPTY" ]]; then
	echo "${0##*/}: no value for:" >&2
	for key in $EMPTY; do echo "  $key" >&2; done
	echo "Is the Meta plugin connected on '$SITE_NAME'?" >&2
	exit 1
fi
