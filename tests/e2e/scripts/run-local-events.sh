#!/usr/bin/env bash
set -euo pipefail

# Reliable local runner for E2E events tests.
# - Exports required env vars
# - Cleans leftover mu-plugins/locks/captures
# - Forces CAPI test logging switch ON
# - Sets rollout transient guard
# - Runs playwright with deterministic workers

ROOT_DIR="$(cd "$(dirname "$0")/../../.." && pwd)"

# Read runtime configuration directly from current process environment.
WORDPRESS_PATH="${WORDPRESS_PATH:-}"
WORDPRESS_URL="${WORDPRESS_URL:-}"
WP_USERNAME="${WP_USERNAME:-}"
WP_PASSWORD="${WP_PASSWORD:-}"
WP_CUSTOMER_USERNAME="${WP_CUSTOMER_USERNAME:-}"
WP_CUSTOMER_PASSWORD="${WP_CUSTOMER_PASSWORD:-}"

TEST_PRODUCT_URL="${TEST_PRODUCT_URL:-}"
TEST_CATEGORY_URL="${TEST_CATEGORY_URL:-}"
TEST_FBCLID="${TEST_FBCLID:-}"

FB_E2E_TEST_COOKIE_NAME="${FB_E2E_TEST_COOKIE_NAME:-}"
FB_E2E_LOGGER_PATH="${FB_E2E_LOGGER_PATH:-}"

WP_DEBUG_LOG="${WP_DEBUG_LOG:-}"
WC_LOG_PATH="${WC_LOG_PATH:-}"

WORKERS="${WORKERS:-1}"
PROJECT="${PROJECT:-chromium-wp-customer}"
GREP="${GREP:-}"
SPEC="${SPEC:-tests/e2e/events-test.spec.js}"
PIXEL_DEBUG_LOGGER="${PIXEL_DEBUG_LOGGER:-true}"
FRESH_AUTH="${FRESH_AUTH:-1}"

# Tiny CLI parser for convenience.
while [[ $# -gt 0 ]]; do
  case "$1" in
    --workers) WORKERS="$2"; shift 2 ;;
    --project) PROJECT="$2"; shift 2 ;;
    --grep) GREP="$2"; shift 2 ;;
    --spec) SPEC="$2"; shift 2 ;;
    --wp-path) WORDPRESS_PATH="$2"; shift 2 ;;
    --wp-url) WORDPRESS_URL="$2"; shift 2 ;;
    --quiet-pixel) PIXEL_DEBUG_LOGGER="false"; shift ;;
    --fresh-auth) FRESH_AUTH="1"; shift ;;
    --keep-auth) FRESH_AUTH="0"; shift ;;
    -h|--help)
      cat <<'USAGE'
Usage: tests/e2e/scripts/run-local-events.sh [options]
  --workers <n>      Playwright workers (default: 1)
  --project <name>   Playwright project (default: chromium-wp-customer)
  --grep <pattern>   Playwright --grep pattern
  --spec <path>      Spec path (default: tests/e2e/events-test.spec.js)
  --wp-path <path>   WordPress path override
  --wp-url <url>     WordPress URL override
  --quiet-pixel      Disable PIXEL_DEBUG_LOGGER
  --fresh-auth       Rebuild Playwright auth state before run (default)
  --keep-auth        Keep existing auth state

Required environment variables:
  WORDPRESS_PATH, WORDPRESS_URL
  WP_USERNAME, WP_PASSWORD
  WP_CUSTOMER_USERNAME, WP_CUSTOMER_PASSWORD
  TEST_PRODUCT_URL, TEST_CATEGORY_URL, TEST_FBCLID
  FB_E2E_TEST_COOKIE_NAME, FB_E2E_LOGGER_PATH
  WP_DEBUG_LOG, WC_LOG_PATH
USAGE
      exit 0
      ;;
    *)
      echo "Unknown arg: $1" >&2
      exit 1
      ;;
  esac
done

require_env() {
  local name="$1"
  if [[ -z "${!name:-}" ]]; then
    echo "❌ Missing required environment variable: $name" >&2
    exit 1
  fi
}

require_env WORDPRESS_PATH
require_env WORDPRESS_URL
require_env WP_USERNAME
require_env WP_PASSWORD
require_env WP_CUSTOMER_USERNAME
require_env WP_CUSTOMER_PASSWORD
require_env TEST_PRODUCT_URL
require_env TEST_CATEGORY_URL
require_env TEST_FBCLID
require_env FB_E2E_TEST_COOKIE_NAME
require_env FB_E2E_LOGGER_PATH
require_env WP_DEBUG_LOG
require_env WC_LOG_PATH

if [[ ! -d "$WORDPRESS_PATH" ]]; then
  echo "❌ WORDPRESS_PATH does not exist: $WORDPRESS_PATH"
  exit 1
fi

cd "$ROOT_DIR"

# Use wp-cli with php -n to avoid broken local php.ini extension mismatches.
WP_CLI=(php -n "$(which wp)" --path="$WORDPRESS_PATH" --allow-root)

echo "🔧 Local E2E setup"
echo "   ROOT_DIR=$ROOT_DIR"
echo "   WORDPRESS_PATH=$WORDPRESS_PATH"
echo "   WORDPRESS_URL=$WORDPRESS_URL"

# Clean sticky artifacts from aborted previous runs.
rm -f "$WORDPRESS_PATH/wp-content/mu-plugins/e2e-pixel-blocker.php" || true
rm -f "$WORDPRESS_PATH/wp-content/mu-plugins/e2e-js-error-simulator.php" || true
rm -f "$ROOT_DIR/tests/e2e/.theme-compat.lock" || true
rm -f "$ROOT_DIR/tests/e2e/helpers/captured-events"/*.json || true

if [[ "$FRESH_AUTH" == "1" ]]; then
  echo "🔐 Rebuilding Playwright auth state (.auth)"
  rm -f "$ROOT_DIR/tests/e2e/.auth/admin.json" || true
  rm -f "$ROOT_DIR/tests/e2e/.auth/customer.json" || true
fi

echo "🧪 Forcing CAPI test logger switch ON"
"${WP_CLI[@]}" eval '$s=get_option("wc_facebook_for_woocommerce_rollout_switches", []); if(!is_array($s)) $s=[]; $s["enable_woocommerce_capi_event_logging"]="yes"; update_option("wc_facebook_for_woocommerce_rollout_switches", $s); echo "ok\n";' --skip-plugins --skip-themes

PLUGIN_VERSION="$(node -p "require('./package.json').version" 2>/dev/null || echo '3.6.2')"
TRANSIENT_KEY="_wc_facebook_for_woocommerce_rollout_switch_flag_${PLUGIN_VERSION}"
"${WP_CLI[@]}" eval "set_transient('${TRANSIENT_KEY}', 'yes', 3600); echo 'transient=ok\n';" --skip-plugins --skip-themes

"${WP_CLI[@]}" option get wc_facebook_for_woocommerce_rollout_switches --format=json --skip-plugins --skip-themes || true

# Force AAM (advanced matching) settings so hashed PII fields are present consistently in local runs.
# This avoids dependence on remote connect.facebook.net in flaky local environments.
"${WP_CLI[@]}" eval '$aam=array("enableAutomaticMatching"=>true,"enabledAutomaticMatchingFields"=>array("em","fn","ln","external_id","ct","zp","country","st","ph"),"pixelId"=>get_option("wc_facebook_pixel_id","")); set_transient("wc_facebook_aam_settings", wp_json_encode($aam), 10 * MINUTE_IN_SECONDS); echo "aam=ok\n";' --skip-plugins --skip-themes

# Ensure test users/passwords are as expected.
"${WP_CLI[@]}" user update "$WP_USERNAME" --user_pass="$WP_PASSWORD" || true
"${WP_CLI[@]}" user update "$WP_CUSTOMER_USERNAME" --user_pass="$WP_CUSTOMER_PASSWORD" || true

export WORDPRESS_PATH WORDPRESS_URL
export WP_USERNAME WP_PASSWORD WP_CUSTOMER_USERNAME WP_CUSTOMER_PASSWORD
export TEST_PRODUCT_URL TEST_CATEGORY_URL TEST_FBCLID
export FB_E2E_TEST_COOKIE_NAME FB_E2E_LOGGER_PATH
export WP_DEBUG_LOG WC_LOG_PATH
export PIXEL_DEBUG_LOGGER

echo "🚀 Running Playwright"
CMD=(npx playwright test "$SPEC" --project="$PROJECT" --workers="$WORKERS")
if [[ -n "$GREP" ]]; then
  CMD+=(--grep "$GREP")
fi

printf '   %q ' "${CMD[@]}"
echo
"${CMD[@]}"

# Quick sanity: list latest captures.
echo "\n📦 Latest captured event files:"
ls -lt "$ROOT_DIR/tests/e2e/helpers/captured-events" 2>/dev/null | head -n 20 || true
