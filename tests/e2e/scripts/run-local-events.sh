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
SYNC_TEST_USERS="${SYNC_TEST_USERS:-auto}"
EDGE_EXECUTABLE_PATH="${EDGE_EXECUTABLE_PATH:-}"
FIREFOX_EXECUTABLE_PATH="${FIREFOX_EXECUTABLE_PATH:-}"
BRAVE_EXECUTABLE_PATH="${BRAVE_EXECUTABLE_PATH:-}"
OPERA_EXECUTABLE_PATH="${OPERA_EXECUTABLE_PATH:-}"
REQUIRE_REAL_EDGE="${REQUIRE_REAL_EDGE:-0}"
REQUIRE_REAL_FIREFOX="${REQUIRE_REAL_FIREFOX:-0}"
REQUIRE_REAL_BRAVE="${REQUIRE_REAL_BRAVE:-0}"
REQUIRE_REAL_OPERA="${REQUIRE_REAL_OPERA:-0}"
AUTO_INSTALL="${AUTO_INSTALL:-0}"

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
    --sync-test-users) SYNC_TEST_USERS="1"; shift ;;
    --no-sync-test-users) SYNC_TEST_USERS="0"; shift ;;
    --edge-path) EDGE_EXECUTABLE_PATH="$2"; shift 2 ;;
    --firefox-path) FIREFOX_EXECUTABLE_PATH="$2"; shift 2 ;;
    --brave-path) BRAVE_EXECUTABLE_PATH="$2"; shift 2 ;;
    --opera-path) OPERA_EXECUTABLE_PATH="$2"; shift 2 ;;
    --real-edge) REQUIRE_REAL_EDGE="1"; shift ;;
    --real-firefox) REQUIRE_REAL_FIREFOX="1"; shift ;;
    --real-brave) REQUIRE_REAL_BRAVE="1"; shift ;;
    --real-opera) REQUIRE_REAL_OPERA="1"; shift ;;
    --auto-install) AUTO_INSTALL="1"; shift ;;
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
  --sync-test-users  Force wp user password sync before run
  --no-sync-test-users
                     Skip wp user password sync before run
  --real-edge        Require real Edge binary (auto-enabled for edge projects)
  --edge-path <p>    Explicit Edge executable path
  --real-firefox     Require real Firefox binary (auto-enabled for firefox projects)
  --firefox-path <p> Explicit Firefox executable path
  --real-brave       Require real Brave binary (auto-enabled for brave projects)
  --brave-path <p>   Explicit Brave executable path
  --real-opera       Require real Opera binary (auto-enabled for opera projects)
  --opera-path <p>   Explicit Opera executable path
  --auto-install     Allow this script to install missing browsers/packages (may run sudo,
                     add apt repositories/keyrings, and modify system package sources)

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

warn_auto_install_disabled() {
  local browser="$1"
  echo "⚠️  ${browser} executable not found and auto-install is disabled." >&2
  echo "   Re-run with --auto-install to allow package installation/repository setup." >&2
}

# If running a Brave project, force real Brave (no Chromium UA fallback).
if [[ "$PROJECT" == edge-* ]]; then
  REQUIRE_REAL_EDGE="1"
fi

if [[ "$PROJECT" == firefox-* ]]; then
  REQUIRE_REAL_FIREFOX="1"
fi

if [[ "$PROJECT" == brave-* ]]; then
  REQUIRE_REAL_BRAVE="1"
fi

if [[ "$PROJECT" == opera-* ]]; then
  REQUIRE_REAL_OPERA="1"
fi

if [[ "$REQUIRE_REAL_BRAVE" == "1" ]]; then
  if [[ -z "$BRAVE_EXECUTABLE_PATH" ]]; then
    if command -v brave-browser >/dev/null 2>&1; then
      BRAVE_EXECUTABLE_PATH="$(command -v brave-browser)"
    elif [[ -x "/Applications/Brave Browser.app/Contents/MacOS/Brave Browser" ]]; then
      BRAVE_EXECUTABLE_PATH="/Applications/Brave Browser.app/Contents/MacOS/Brave Browser"
    elif [[ -x "/Applications/Brave Browser Beta.app/Contents/MacOS/Brave Browser Beta" ]]; then
      BRAVE_EXECUTABLE_PATH="/Applications/Brave Browser Beta.app/Contents/MacOS/Brave Browser Beta"
    elif [[ -x "/usr/bin/brave-browser" ]]; then
      BRAVE_EXECUTABLE_PATH="/usr/bin/brave-browser"
    elif [[ -x "/snap/bin/brave" ]]; then
      BRAVE_EXECUTABLE_PATH="/snap/bin/brave"
    fi
  fi

  if [[ -z "$BRAVE_EXECUTABLE_PATH" || ! -x "$BRAVE_EXECUTABLE_PATH" ]]; then
    if [[ "$AUTO_INSTALL" != "1" ]]; then
      warn_auto_install_disabled "Brave"
    else
      echo "⚠️  Auto-install enabled: this may run sudo and modify system package sources."
      echo "ℹ️ Brave executable not found. Attempting local install..."
      if [[ "$(uname -s)" == "Darwin" ]]; then
        if command -v brew >/dev/null 2>&1; then
          brew install --cask brave-browser
        else
          echo "❌ Homebrew not found; cannot auto-install Brave on macOS." >&2
        fi
        if [[ -x "/Applications/Brave Browser.app/Contents/MacOS/Brave Browser" ]]; then
          BRAVE_EXECUTABLE_PATH="/Applications/Brave Browser.app/Contents/MacOS/Brave Browser"
        fi
      elif [[ "$(uname -s)" == "Linux" ]]; then
        if command -v apt-get >/dev/null 2>&1; then
          sudo apt-get update
          sudo apt-get install -y curl ca-certificates
          sudo curl -fsSLo /usr/share/keyrings/brave-browser-archive-keyring.gpg https://brave-browser-apt-release.s3.brave.com/brave-browser-archive-keyring.gpg
          echo "deb [signed-by=/usr/share/keyrings/brave-browser-archive-keyring.gpg] https://brave-browser-apt-release.s3.brave.com/ stable main" | sudo tee /etc/apt/sources.list.d/brave-browser-release.list >/dev/null
          sudo apt-get update
          sudo apt-get install -y brave-browser
          if command -v brave-browser >/dev/null 2>&1; then
            BRAVE_EXECUTABLE_PATH="$(command -v brave-browser)"
          fi
        fi
      fi
    fi
  fi

  if [[ -z "$BRAVE_EXECUTABLE_PATH" || ! -x "$BRAVE_EXECUTABLE_PATH" ]]; then
    echo "❌ REQUIRE_REAL_BRAVE=1 but Brave executable not found." >&2
    echo "   Set BRAVE_EXECUTABLE_PATH or pass --brave-path <path>." >&2
    echo "   macOS default: /Applications/Brave Browser.app/Contents/MacOS/Brave Browser" >&2
    echo "   Linux default: /usr/bin/brave-browser" >&2
    exit 1
  fi
fi

cd "$ROOT_DIR"

# Use wp-cli with php -n to avoid broken local php.ini extension mismatches.
WP_CLI=(php -n "$(which wp)" --path="$WORDPRESS_PATH" --allow-root)

echo "🔧 Local E2E setup"
echo "   ROOT_DIR=$ROOT_DIR"
echo "   WORDPRESS_PATH=$WORDPRESS_PATH"
echo "   WORDPRESS_URL=$WORDPRESS_URL"
if [[ "$REQUIRE_REAL_EDGE" == "1" ]]; then
  if [[ -z "$EDGE_EXECUTABLE_PATH" ]]; then
    if command -v microsoft-edge >/dev/null 2>&1; then
      EDGE_EXECUTABLE_PATH="$(command -v microsoft-edge)"
    elif command -v microsoft-edge-stable >/dev/null 2>&1; then
      EDGE_EXECUTABLE_PATH="$(command -v microsoft-edge-stable)"
    elif [[ -x "/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge" ]]; then
      EDGE_EXECUTABLE_PATH="/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge"
    elif [[ -x "/usr/bin/microsoft-edge" ]]; then
      EDGE_EXECUTABLE_PATH="/usr/bin/microsoft-edge"
    elif [[ -x "/usr/bin/microsoft-edge-stable" ]]; then
      EDGE_EXECUTABLE_PATH="/usr/bin/microsoft-edge-stable"
    fi
  fi

  if [[ -z "$EDGE_EXECUTABLE_PATH" || ! -x "$EDGE_EXECUTABLE_PATH" ]]; then
    if [[ "$AUTO_INSTALL" != "1" ]]; then
      warn_auto_install_disabled "Edge"
    else
      echo "⚠️  Auto-install enabled: this may run sudo and modify system package sources."
      echo "ℹ️ Edge executable not found. Attempting local install..."
      if [[ "$(uname -s)" == "Darwin" ]]; then
        if command -v brew >/dev/null 2>&1; then
          brew install --cask microsoft-edge
        else
          echo "❌ Homebrew not found; cannot auto-install Edge on macOS." >&2
        fi
        if [[ -x "/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge" ]]; then
          EDGE_EXECUTABLE_PATH="/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge"
        fi
      elif [[ "$(uname -s)" == "Linux" ]]; then
        if command -v apt-get >/dev/null 2>&1; then
          sudo apt-get update
          sudo apt-get install -y wget gpg
          wget -qO- https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor > microsoft.gpg
          sudo install -o root -g root -m 644 microsoft.gpg /usr/share/keyrings/microsoft.gpg
          rm -f microsoft.gpg
          echo "deb [arch=amd64,arm64 signed-by=/usr/share/keyrings/microsoft.gpg] https://packages.microsoft.com/repos/edge stable main" | sudo tee /etc/apt/sources.list.d/microsoft-edge.list >/dev/null
          sudo apt-get update
          sudo apt-get install -y microsoft-edge-stable
          if command -v microsoft-edge-stable >/dev/null 2>&1; then
            EDGE_EXECUTABLE_PATH="$(command -v microsoft-edge-stable)"
          elif command -v microsoft-edge >/dev/null 2>&1; then
            EDGE_EXECUTABLE_PATH="$(command -v microsoft-edge)"
          fi
        fi
      fi
    fi
  fi

  if [[ -z "$EDGE_EXECUTABLE_PATH" || ! -x "$EDGE_EXECUTABLE_PATH" ]]; then
    echo "❌ REQUIRE_REAL_EDGE=1 but Edge executable not found." >&2
    echo "   Set EDGE_EXECUTABLE_PATH or pass --edge-path <path>." >&2
    echo "   macOS default: /Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge" >&2
    echo "   Linux default: /usr/bin/microsoft-edge-stable" >&2
    exit 1
  fi

  echo "   REQUIRE_REAL_EDGE=1"
  echo "   EDGE_EXECUTABLE_PATH=$EDGE_EXECUTABLE_PATH"
  "$EDGE_EXECUTABLE_PATH" --version || true
fi

if [[ "$REQUIRE_REAL_FIREFOX" == "1" ]]; then
  # Use Playwright Firefox channel for protocol compatibility.
  # If user provided a path, validate it for visibility but do not export it.
  if [[ -n "$FIREFOX_EXECUTABLE_PATH" ]]; then
    if [[ ! -x "$FIREFOX_EXECUTABLE_PATH" ]]; then
      echo "❌ FIREFOX_EXECUTABLE_PATH is set but not executable: $FIREFOX_EXECUTABLE_PATH" >&2
      exit 1
    fi
  else
    if command -v firefox >/dev/null 2>&1; then
      FIREFOX_EXECUTABLE_PATH="$(command -v firefox)"
    elif [[ -x "/Applications/Firefox.app/Contents/MacOS/firefox" ]]; then
      FIREFOX_EXECUTABLE_PATH="/Applications/Firefox.app/Contents/MacOS/firefox"
    elif [[ -x "/usr/bin/firefox" ]]; then
      FIREFOX_EXECUTABLE_PATH="/usr/bin/firefox"
    fi

    if [[ -z "$FIREFOX_EXECUTABLE_PATH" ]]; then
      if [[ "$AUTO_INSTALL" != "1" ]]; then
        warn_auto_install_disabled "Firefox"
      else
        echo "⚠️  Auto-install enabled: this may run sudo and modify system package sources."
        echo "ℹ️ Firefox binary not found locally. Attempting local install..."
        if [[ "$(uname -s)" == "Darwin" ]]; then
          if command -v brew >/dev/null 2>&1; then
            brew install --cask firefox
          fi
          if [[ -x "/Applications/Firefox.app/Contents/MacOS/firefox" ]]; then
            FIREFOX_EXECUTABLE_PATH="/Applications/Firefox.app/Contents/MacOS/firefox"
          fi
        elif [[ "$(uname -s)" == "Linux" ]]; then
          if command -v apt-get >/dev/null 2>&1; then
            sudo apt-get update
            sudo apt-get install -y firefox
            if command -v firefox >/dev/null 2>&1; then
              FIREFOX_EXECUTABLE_PATH="$(command -v firefox)"
            fi
          fi
        fi
      fi
    fi
  fi

  if [[ -n "$FIREFOX_EXECUTABLE_PATH" ]]; then
    echo "   REQUIRE_REAL_FIREFOX=1"
    echo "   Local Firefox binary detected: $FIREFOX_EXECUTABLE_PATH"
    "$FIREFOX_EXECUTABLE_PATH" --version || true
  else
    echo "   REQUIRE_REAL_FIREFOX=1"
    echo "   Local Firefox binary not found; Playwright channel 'firefox' will be used"
  fi

  # IMPORTANT: do not pass executablePath for Firefox; use Playwright channel only.
  FIREFOX_EXECUTABLE_PATH=""
fi

if [[ "$REQUIRE_REAL_BRAVE" == "1" ]]; then
  echo "   REQUIRE_REAL_BRAVE=1"
  echo "   BRAVE_EXECUTABLE_PATH=$BRAVE_EXECUTABLE_PATH"
  "$BRAVE_EXECUTABLE_PATH" --version || true
fi

if [[ "$REQUIRE_REAL_OPERA" == "1" ]]; then
  if [[ -z "$OPERA_EXECUTABLE_PATH" ]]; then
    if command -v opera >/dev/null 2>&1; then
      OPERA_EXECUTABLE_PATH="$(command -v opera)"
    elif command -v opera-stable >/dev/null 2>&1; then
      OPERA_EXECUTABLE_PATH="$(command -v opera-stable)"
    elif [[ -x "/Applications/Opera.app/Contents/MacOS/Opera" ]]; then
      OPERA_EXECUTABLE_PATH="/Applications/Opera.app/Contents/MacOS/Opera"
    elif [[ -x "/usr/bin/opera" ]]; then
      OPERA_EXECUTABLE_PATH="/usr/bin/opera"
    elif [[ -x "/usr/bin/opera-stable" ]]; then
      OPERA_EXECUTABLE_PATH="/usr/bin/opera-stable"
    fi
  fi

  if [[ -z "$OPERA_EXECUTABLE_PATH" || ! -x "$OPERA_EXECUTABLE_PATH" ]]; then
    if [[ "$AUTO_INSTALL" != "1" ]]; then
      warn_auto_install_disabled "Opera"
    else
      echo "⚠️  Auto-install enabled: this may run sudo and modify system package sources."
      echo "ℹ️ Opera executable not found. Attempting local install..."
      if [[ "$(uname -s)" == "Darwin" ]]; then
        if command -v brew >/dev/null 2>&1; then
          brew install --cask opera
        else
          echo "❌ Homebrew not found; cannot auto-install Opera on macOS." >&2
        fi
        if [[ -x "/Applications/Opera.app/Contents/MacOS/Opera" ]]; then
          OPERA_EXECUTABLE_PATH="/Applications/Opera.app/Contents/MacOS/Opera"
        fi
      elif [[ "$(uname -s)" == "Linux" ]]; then
        if command -v apt-get >/dev/null 2>&1; then
          sudo apt-get update
          sudo apt-get install -y wget gnupg2
          wget -qO- https://deb.opera.com/archive.key | sudo gpg --dearmor -o /usr/share/keyrings/opera.gpg
          echo "deb [signed-by=/usr/share/keyrings/opera.gpg] https://deb.opera.com/opera-stable/ stable non-free" | sudo tee /etc/apt/sources.list.d/opera-stable.list >/dev/null
          sudo apt-get update
          sudo apt-get install -y opera-stable
          if command -v opera-stable >/dev/null 2>&1; then
            OPERA_EXECUTABLE_PATH="$(command -v opera-stable)"
          elif command -v opera >/dev/null 2>&1; then
            OPERA_EXECUTABLE_PATH="$(command -v opera)"
          fi
        fi
      fi
    fi
  fi

  if [[ -z "$OPERA_EXECUTABLE_PATH" || ! -x "$OPERA_EXECUTABLE_PATH" ]]; then
    echo "❌ REQUIRE_REAL_OPERA=1 but Opera executable not found." >&2
    echo "   Set OPERA_EXECUTABLE_PATH or pass --opera-path <path>." >&2
    echo "   macOS default: /Applications/Opera.app/Contents/MacOS/Opera" >&2
    echo "   Linux default: /usr/bin/opera-stable" >&2
    exit 1
  fi

  echo "   REQUIRE_REAL_OPERA=1"
  echo "   OPERA_EXECUTABLE_PATH=$OPERA_EXECUTABLE_PATH"
  "$OPERA_EXECUTABLE_PATH" --version || true
fi

# Clean sticky artifacts from aborted previous runs.
rm -f "$WORDPRESS_PATH/wp-content/mu-plugins/e2e-pixel-blocker.php" || true
rm -f "$WORDPRESS_PATH/wp-content/mu-plugins/e2e-js-error-simulator.php" || true
rm -f "$ROOT_DIR/tests/e2e/.theme-compat.lock" || true
rm -f "$ROOT_DIR/tests/e2e/helpers/captured-events"/*.json || true

# Keep event-suite debug checks deterministic by starting with a clean debug.log.
if [[ -n "$WP_DEBUG_LOG" ]]; then
  mkdir -p "$(dirname "$WP_DEBUG_LOG")"
  : > "$WP_DEBUG_LOG"
  echo "🧹 Cleared WP_DEBUG_LOG: $WP_DEBUG_LOG"
fi

if [[ "$FRESH_AUTH" == "1" ]]; then
  echo "🔐 Rebuilding Playwright auth state (.auth)"
  rm -f "$ROOT_DIR/tests/e2e/.auth/admin.json" || true
  rm -f "$ROOT_DIR/tests/e2e/.auth/customer.json" || true
fi

# Password updates invalidate WP auth cookies. Default behavior:
# - fresh-auth run  -> sync users (safe, auth will be rebuilt)
# - keep-auth run   -> skip sync users (preserve existing auth cookies)
if [[ "$SYNC_TEST_USERS" == "auto" ]]; then
  if [[ "$FRESH_AUTH" == "1" ]]; then
    SYNC_TEST_USERS="1"
  else
    SYNC_TEST_USERS="0"
  fi
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
if [[ "$SYNC_TEST_USERS" == "1" ]]; then
  echo "👤 Syncing WP test user passwords"
  "${WP_CLI[@]}" user update "$WP_USERNAME" --user_pass="$WP_PASSWORD" || true
  "${WP_CLI[@]}" user update "$WP_CUSTOMER_USERNAME" --user_pass="$WP_CUSTOMER_PASSWORD" || true
else
  echo "👤 Skipping WP user password sync to preserve existing auth cookies (--keep-auth safe)"
fi

export WORDPRESS_PATH WORDPRESS_URL
export WP_USERNAME WP_PASSWORD WP_CUSTOMER_USERNAME WP_CUSTOMER_PASSWORD
export TEST_PRODUCT_URL TEST_CATEGORY_URL TEST_FBCLID
export FB_E2E_TEST_COOKIE_NAME FB_E2E_LOGGER_PATH
export WP_DEBUG_LOG WC_LOG_PATH
export PIXEL_DEBUG_LOGGER
export EDGE_EXECUTABLE_PATH FIREFOX_EXECUTABLE_PATH BRAVE_EXECUTABLE_PATH OPERA_EXECUTABLE_PATH
export REQUIRE_REAL_EDGE REQUIRE_REAL_FIREFOX REQUIRE_REAL_BRAVE REQUIRE_REAL_OPERA

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
