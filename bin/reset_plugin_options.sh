#!/bin/bash
#
# Clear the Meta for WooCommerce connection options on a Local site so the
# onboarding flow can be run again from scratch, as a brand new install.
#
# The external business ID is cleared along with everything else, so the next
# onboarding mints a fresh one and MBE creates a *new* installation. Your
# existing MBE connection is left alone rather than being rebound and
# overwritten, so there is no need to disconnect it first.
#
# Usage: ./reset_plugin_options.sh <site-name|domain|site-id> [options]
#
#   --keep-business-id  Preserve the external business ID, so the next
#                       onboarding rebinds to — and overwrites — the existing
#                       MBE installation instead of creating a new one.
#   --backup <file>     Write the current values to <file> before deleting
#                       (defaults to a timestamped file in the system temp dir).
#   --no-backup         Skip the backup.
#   --dry-run           Show what would be deleted and exit.
#
# Example:
#   ./reset_plugin_options.sh my-site --dry-run
#   ./reset_plugin_options.sh my-site
#
set -euo pipefail

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/lib/local-wp.sh"

SITE=""
CLEAR_BUSINESS_ID="yes"
DRY_RUN="no"
BACKUP_FILE=""
DO_BACKUP="yes"

while [[ $# -gt 0 ]]; do
	case "$1" in
		--keep-business-id) CLEAR_BUSINESS_ID="no"; shift ;;
		--dry-run)          DRY_RUN="yes"; shift ;;
		--no-backup)        DO_BACKUP="no"; shift ;;
		--backup)           BACKUP_FILE="${2:-}"; shift 2 ;;
		-h|--help)          sed -n '2,24p' "$0"; exit 0 ;;
		-*)                 local_wp_die "unknown option: $1" ;;
		*)                  SITE="$1"; shift ;;
	esac
done

local_wp_resolve "$SITE"

# The option list is read from the plugin's own constants rather than
# hardcoded here, so it tracks includes/API/Plugin/Settings/Handler.php's
# clear_integration_options() as that list changes.
read -r -d '' COLLECT_PHP <<'PHP' || true
$integration_constants = [
	'OPTION_ACCESS_TOKEN',
	'OPTION_AD_ACCOUNT_ID',
	'OPTION_BUSINESS_MANAGER_ID',
	'OPTION_COMMERCE_MERCHANT_SETTINGS_ID',
	'OPTION_COMMERCE_PARTNER_INTEGRATION_ID',
	'OPTION_ENABLE_MESSENGER',
	'OPTION_FEED_ID',
	'OPTION_HAS_AUTHORIZED_PAGES_READ_ENGAGEMENT',
	'OPTION_HAS_CONNECTED_FBE_2',
	'OPTION_INSTALLED_FEATURES',
	'OPTION_MERCHANT_ACCESS_TOKEN',
	'OPTION_PAGE_ACCESS_TOKEN',
	'OPTION_PRODUCT_CATALOG_ID',
	'OPTION_PROFILES',
	'OPTION_SYSTEM_USER_ID',
	'SETTING_FACEBOOK_PAGE_ID',
	'SETTING_FACEBOOK_PIXEL_ID',
];

$names = [];
foreach ( $integration_constants as $constant ) {
	$qualified = 'WC_Facebookcommerce_Integration::' . $constant;
	if ( defined( $qualified ) ) {
		$names[] = constant( $qualified );
	}
}

// Not in the plugin's own clear list, but left behind it would be stale after
// a re-onboard, and the pixel config keeps firing with old settings.
$names[] = 'wc_facebook_instagram_business_id';
$names[] = 'wc_facebook_pixel_install_time';
if ( defined( 'WC_Facebookcommerce_Pixel::SETTINGS_KEY' ) ) {
	$names[] = constant( 'WC_Facebookcommerce_Pixel::SETTINGS_KEY' );
}

if ( 'yes' === getenv( 'RESET_CLEAR_BUSINESS_ID' ) && defined( 'WC_Facebookcommerce_Integration::OPTION_EXTERNAL_BUSINESS_ID' ) ) {
	$names[] = constant( 'WC_Facebookcommerce_Integration::OPTION_EXTERNAL_BUSINESS_ID' );
} elseif ( 'yes' === getenv( 'RESET_CLEAR_BUSINESS_ID' ) ) {
	$names[] = 'wc_facebook_external_business_id';
}

$names = array_values( array_unique( $names ) );
sort( $names );

$report = [];
foreach ( $names as $name ) {
	$value            = get_option( $name, null );
	$report[ $name ] = [
		'present' => ! is_null( $value ),
		'value'   => $value,
	];
}

echo json_encode( $report ), "\n";
PHP

REPORT=$(RESET_CLEAR_BUSINESS_ID="$CLEAR_BUSINESS_ID" wp_local eval "$COLLECT_PHP") \
	|| local_wp_die "wp-cli failed against site '$SITE_NAME'"

echo "Site: $SITE_NAME ($DOCROOT)"
printf '%s' "$REPORT" | python3 -c '
import json, sys
report = json.load(sys.stdin)
present = [k for k, v in report.items() if v["present"]]
absent  = [k for k, v in report.items() if not v["present"]]
print(f"Set:   {len(present)} option(s)")
for k in present:
    print(f"  - {k}")
if absent:
    print(f"Already clear: {len(absent)} option(s)")
'

if [[ "$DRY_RUN" == "yes" ]]; then
	echo
	echo "Dry run — nothing deleted."
	exit 0
fi

if [[ "$DO_BACKUP" == "yes" ]]; then
	if [[ -z "$BACKUP_FILE" ]]; then
		BACKUP_FILE="${TMPDIR:-/tmp}/fbwc-options-$SITE_ID-$(date +%Y%m%d-%H%M%S).json"
	fi
	printf '%s' "$REPORT" > "$BACKUP_FILE"
	chmod 600 "$BACKUP_FILE"
	echo
	echo "Backup (contains access tokens, mode 600): $BACKUP_FILE"
fi

read -r -d '' DELETE_PHP <<'PHP' || true
$names   = json_decode( getenv( 'RESET_OPTION_NAMES' ), true );
$deleted = 0;
foreach ( (array) $names as $name ) {
	if ( delete_option( $name ) ) {
		$deleted++;
	}
}

// Connection state that does not live in the options table.
delete_transient( 'wc_facebook_connection_invalid' );

echo $deleted, "\n";
PHP

NAMES=$(printf '%s' "$REPORT" | python3 -c 'import json,sys; print(json.dumps(list(json.load(sys.stdin).keys())))')
DELETED=$(RESET_OPTION_NAMES="$NAMES" wp_local eval "$DELETE_PHP") \
	|| local_wp_die "deletion failed — the backup above still holds the old values"

echo
echo "Deleted $DELETED option(s)."
if [[ "$CLEAR_BUSINESS_ID" == "yes" ]]; then
	echo "External business ID cleared — the next onboarding creates a new MBE installation,"
	echo "leaving your existing one untouched."
else
	echo "External business ID kept — the next onboarding rebinds to and overwrites the"
	echo "existing MBE installation."
fi
echo "Reload WP Admin → Marketing → Meta to start onboarding again."
