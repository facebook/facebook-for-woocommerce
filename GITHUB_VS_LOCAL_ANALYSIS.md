# GitHub CI vs Local Environment - Event Tracking Issues

## 🎯 CRITICAL FINDINGS

After analyzing your GitHub workflow and comparing it to local setup, here are the **key differences** that could prevent events from firing on GitHub but not locally:

---

## ✅ What Your GitHub Workflow DOES Correctly

### 1. **Theme Installation** ✅
```yaml
# Line 179-181
wp theme install twentytwentyfour --activate --allow-root
echo "✅ Theme activated (required for wp_head hook)"
```
**CRITICAL:** Without a theme, `wp_head()` doesn't fire → no pixel code injected!
**Status:** ✅ You have this!

### 2. **Non-Admin User** ✅
```yaml
# Line 242-245
wp user create customer customer@test.com --role=customer --user_pass=Password@54321 --allow-root
```
**CRITICAL:** Pixel tracking is blocked for admin users (line 953 in events-tracker.php)
**Status:** ✅ You create customer user AND your tests log in as customer (TestSetup.js line 58)

### 3. **Pixel Settings** ✅
```yaml
# Lines 213-226
wp option update wc_facebook_pixel_id "${{ secrets.FB_PIXEL_ID }}" --allow-root
wp option update wc_facebook_enable_pixel "yes" --allow-root
wp option update wc_facebook_enable_server_to_server "yes" --allow-root
wp option update wc_facebook_enable_advanced_matching "yes" --allow-root
```
**Status:** ✅ All set correctly

### 4. **Integration Settings** ✅
```yaml
# Lines 230-237
wp eval "update_option('woocommerce_woocommerce_facebook_for_woocommerce_settings', \$settings);"
```
**Status:** ✅ Double-setting via both methods

### 5. **Browser Configuration** ✅
```yaml
# playwright.config.js lines 36-41
'--disable-blink-features=AutomationControlled',
'--disable-dev-shm-usage',
'--disable-web-security',
'--disable-features=BlockThirdPartyCookies',
```
**Status:** ✅ Allows third-party cookies and requests

---

## ❌ POTENTIAL ISSUES - Differences Between GitHub & Local

### 🚨 ISSUE #1: Facebook Config NOT Stored in Database
**Location:** Workflow lines 230-237

```yaml
wp option update wc_facebook_pixel_id "${{ secrets.FB_PIXEL_ID }}" --allow-root
```

BUT - the plugin uses a DIFFERENT key! Looking at `facebook-commerce-pixel-event.php`:

```php
// Line 21
const SETTINGS_KEY = 'facebook_config';  // ❌ NOT 'wc_facebook_pixel_id'!

// Line 540-546
public static function get_pixel_id() {
    $fb_options = self::get_options();
    if ( ! $fb_options ) {
        return '';  // ❌ Returns EMPTY!
    }
    return isset( $fb_options[ self::PIXEL_ID_KEY ] ) ?
        $fb_options[ self::PIXEL_ID_KEY ] : '';
}

// Line 677-699
public static function get_options() {
    $fb_options = get_option( self::SETTINGS_KEY );  // Gets 'facebook_config'
    // ...
}
```

**The Problem:**
- Your workflow sets: `wc_facebook_pixel_id` 
- Plugin reads from: `facebook_config['pixel_id']`
- **These are DIFFERENT options!**

**Why it works locally:**
- Your local WordPress probably has the plugin settings saved via the admin UI
- The admin UI saves to `facebook_config` correctly
- GitHub never goes through admin UI, so `facebook_config` is NEVER set!

**The Fix:**
Replace lines 224-226 with:

```yaml
# OLD (doesn't work):
wp option update wc_facebook_pixel_id "${{ secrets.FB_PIXEL_ID }}" --allow-root

# NEW (correct):
wp eval "
  \$config = array(
    'pixel_id' => '${{ secrets.FB_PIXEL_ID }}',
    'use_pii' => 1,
    'use_s2s' => true,
    'access_token' => '${{ secrets.FB_ACCESS_TOKEN }}'
  );
  update_option('facebook_config', \$config);
  echo '✅ facebook_config updated' . PHP_EOL;
" --allow-root
```

---

### 🚨 ISSUE #2: Facebook Connection Check
**Location:** `facebook-commerce-events-tracker.php` line 250-252

```php
public function param_builder_client_setup() {
    // ⚠️ CHECK: Must be connected
    if ( ! facebook_for_woocommerce()->get_connection_handler()->is_connected() ) {
        return;  // Script not loaded!
    }
    // ...
}
```

This check might fail on GitHub if connection isn't properly initialized.

**Verification in workflow** (lines 267-304):
You DO check this! But look at the output - if it says "Connected: NO", the CAPI script won't load.

**Why it might fail:**
Looking at how connection is determined, it checks for:
- Access token
- External business ID  
- Business manager ID

**The Fix:**
Ensure ALL connection fields are set:

```yaml
# Add after line 226
wp option update wc_facebook_connected "yes" --allow-root
wp option update wc_facebook_is_connected "yes" --allow-root
```

---

### 🚨 ISSUE #3: Session Not Started
**Location:** Multiple places that use `WC()->session`

Events like AddToCart rely on session:
```php
// facebook-commerce-events-tracker.php line 750
WC()->session->set( 'facebook_for_woocommerce_add_to_cart_event_id', $event->get_id() );
```

**The Problem:**
- Sessions require cookies
- In headless/CI environments, sessions might not initialize
- WooCommerce session requires customer to have cart

**Your mitigation:**
```js
// playwright.config.js line 40
'--disable-features=BlockThirdPartyCookies',
```
✅ This helps!

**Additional fix needed:**
Ensure session is started in tests:

```yaml
# Add to workflow after plugin activation
wp eval "
  // Force start WooCommerce session
  if (class_exists('WC')) {
    WC()->session = new WC_Session_Handler();
    WC()->session->init();
    WC()->cart = new WC_Cart();
    WC()->cart->get_cart();
    echo '✅ WooCommerce session initialized' . PHP_EOL;
  }
" --allow-root
```

---

### 🚨 ISSUE #4: Headers Already Sent
**Location:** `facebook-commerce-events-tracker.php` lines 112-138

```php
public function param_builder_server_setup() {
    try {
        $cookie_to_set = self::get_param_builder()->getCookiesToSet();
        
        if ( ! headers_sent() ) {  // ⚠️ Might be true in tests!
            foreach ( $cookie_to_set as $cookie ) {
                setcookie( ... );
            }
        }
    } catch ( \Exception $exception ) {
        // Silently fails!
    }
}
```

**The Problem:**
- If ANY output happens before this (debug logs, warnings, etc.), headers are sent
- Cookies can't be set
- _fbp and _fbc cookies missing
- Events might still fire but with incomplete data

**Check in workflow** (line 116):
```yaml
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);  // ✅ Good - doesn't display
```

**But:** Even with `WP_DEBUG_DISPLAY` false, some plugins output to browser

**The Fix:**
Add output buffering:

```yaml
# Add to wp-config.php in workflow
cat >> wp-config.php << 'EOF'

// Start output buffering to prevent "headers already sent"
ob_start();

EOF
```

---

### 🚨 ISSUE #5: Base Pixel Code Rendered Check
**Location:** `facebook-commerce-pixel-event.php` lines 143-152

```php
public function pixel_base_code() {
    $pixel_id = self::get_pixel_id();
    
    // ⚠️ CHECK 1: Empty pixel ID
    if ( empty( $pixel_id ) ) {
        return '';  // Nothing rendered!
    }
    
    // ⚠️ CHECK 2: Already rendered
    if ( ! empty( self::$render_cache[ self::PIXEL_RENDER ] ) ) {
        return '';  // Already rendered!
    }
    
    self::$render_cache[ self::PIXEL_RENDER ] = true;
    // ...
}
```

**The Problem:**
- `$render_cache` is static
- If plugin is loaded multiple times in one request, it won't render again
- In tests, if page is reloaded/refreshed, cache persists
- **Most importantly:** If `get_pixel_id()` returns empty (due to Issue #1), NOTHING renders!

**This is probably your MAIN issue!**

---

### 🚨 ISSUE #6: Event Tracker Not Initialized
**Location:** `facebook-commerce.php` lines 390-394

```php
if ( $this->get_facebook_pixel_id() ) {  // ⚠️ Must return value!
    $aam_settings         = $this->load_aam_settings_of_pixel();
    $user_info            = WC_Facebookcommerce_Utils::get_user_info( $aam_settings );
    $this->events_tracker = new WC_Facebookcommerce_EventsTracker( $user_info, $aam_settings );
}
```

**The Problem:**
If `get_facebook_pixel_id()` returns empty/null, EventsTracker is NEVER created!

**Your verification** (lines 296-301):
```yaml
echo 'EventsTracker instantiated: ' . (is_object(\$tracker) ? 'YES' : 'NO') . PHP_EOL;
```

**If this prints "NO"**, that's your issue!

---

### 🚨 ISSUE #7: AAM Settings Fetch Failure
**Location:** `includes/Events/AAMSettings.php` lines 68-84

```php
public static function build_from_pixel_id( $pixel_id ) {
    $url      = self::get_url( $pixel_id );
    $response = wp_remote_get( $url );  // ⚠️ Network request to Facebook!
    
    if ( is_wp_error( $response ) ) {
        return null;  // ❌ Fails silently!
    }
    // ...
}
```

**The Problem:**
- Makes network request to `https://connect.facebook.net/signals/config/json/{pixel_id}`
- In CI environment, this might:
  - Be blocked by firewall
  - Timeout
  - Return error
- Returns `null` on failure
- BUT - code continues anyway!

**Your test** (lines 249-251):
```yaml
curl -I https://connect.facebook.net 2>&1 | head -n 5
```
✅ Good check!

**But** - this only tests if domain is reachable, not if the specific API endpoint works

**The Fix:**
Test the actual endpoint:

```yaml
# Replace lines 249-251
echo "=== Testing Facebook AAM Endpoint ==="
AAM_URL="https://connect.facebook.net/signals/config/json/${{ secrets.FB_PIXEL_ID }}"
curl -v "$AAM_URL" 2>&1 | head -n 20
if curl -f "$AAM_URL" > /tmp/aam_response.json 2>&1; then
  echo "✅ AAM endpoint reachable"
  cat /tmp/aam_response.json
else
  echo "❌ AAM endpoint NOT reachable - Advanced Matching may fail"
fi
```

---

### 🚨 ISSUE #8: Test Product Doesn't Exist
**Location:** Your tests

```js
// test.spec.js line 57
await page.goto('/product/testp/')
```

**The Problem:**
- Tests assume product `/product/testp/` exists
- Workflow doesn't create this product!
- 404 error → no product page → no ViewContent event

**Your TODO comments:**
```js
// Line 52
// TODO needs to have an existing product
```

**The Fix:**
Add product creation to workflow:

```yaml
# Add after line 245 (after customer user creation)
- name: Create test product
  run: |
    cd /tmp/wordpress
    
    # Create a simple product
    wp eval "
      \$product = new WC_Product_Simple();
      \$product->set_name('TestP');
      \$product->set_slug('testp');
      \$product->set_regular_price('19.99');
      \$product->set_description('Test product for E2E tests');
      \$product->set_short_description('Test product');
      \$product->set_status('publish');
      \$product->set_catalog_visibility('visible');
      \$product->set_stock_status('instock');
      \$product_id = \$product->save();
      
      echo 'Test product created: ID=' . \$product_id . PHP_EOL;
      echo 'Product URL: ' . get_permalink(\$product_id) . PHP_EOL;
    " --allow-root
```

---

### 🚨 ISSUE #9: WordPress Not Fully Initialized
**Location:** General timing issue

**The Problem:**
When you activate the plugin (line 240), WordPress might not be fully initialized:
- Hooks might not be registered
- Database might not be fully ready
- Transients might not work

**The Fix:**
Add a delay and force reinitialization:

```yaml
# Add after line 240 (after plugin activation)
- name: Force plugin initialization
  run: |
    cd /tmp/wordpress
    
    # Sleep to let everything settle
    sleep 5
    
    # Force a full page load to trigger all init hooks
    curl -s http://localhost:8080 > /dev/null
    
    # Verify pixel init via WP-CLI
    wp eval "
      do_action('init');
      do_action('wp');
      do_action('wp_loaded');
      echo '✅ WordPress hooks triggered' . PHP_EOL;
    " --allow-root
```

---

### 🚨 ISSUE #10: Different Cookie Domain
**Location:** Playwright browser context

**The Problem:**
- Local: `localhost` or `wooc-local-test-sitecom.local`
- GitHub: `localhost:8080`
- Cookie domains might be treated differently
- Facebook cookies might not set properly

**Your config:**
```js
// test-config.js
const WORDPRESS_URL = process.env.WORDPRESS_URL || 'http://localhost:8080';
const WP_CUSTOMER_USERNAME = process.env.WP_CUSTOMER_USERNAME || 'customer';
const WP_CUSTOMER_PASSWORD = process.env.WP_CUSTOMER_PASSWORD || 'Password@54321';
```

✅ Good - uses environment variables and matches workflow defaults!

---

## 🎯 THE MOST LIKELY CULPRIT

### **ISSUE #1 is your PRIMARY problem:**

The workflow sets pixel ID like this:
```yaml
wp option update wc_facebook_pixel_id "${{ secrets.FB_PIXEL_ID }}" --allow-root
```

But the plugin reads it from a DIFFERENT location:
```php
// facebook-commerce-pixel-event.php
public static function get_pixel_id() {
    $fb_options = get_option( 'facebook_config' );  // ❌ Different key!
    return isset( $fb_options['pixel_id'] ) ? $fb_options['pixel_id'] : '';
}
```

**Result:**
- `get_pixel_id()` returns `""` (empty string)
- `pixel_base_code()` returns early (line 148)
- NO pixel code is rendered
- NO `fbq()` function exists in browser
- ALL events fail

**How to verify this is the issue:**
Check your GitHub Actions logs for the "Verify Facebook for WooCommerce setup" step (line 267-304).
Look for this line:
```
Pixel ID: 
```
If it's blank, that confirms Issue #1!

---

## 🔧 COMPLETE FIX - Apply ALL These Changes

### Fix #1: Set facebook_config Correctly
**Location:** Workflow after line 223

```yaml
# Add this BEFORE activating the plugin
- name: Configure Facebook Pixel (CRITICAL FIX)
  run: |
    cd /tmp/wordpress
    
    # Set the correct option that the plugin actually reads from
    wp eval "
      \$config = array(
        'pixel_id' => '${{ secrets.FB_PIXEL_ID }}',
        'use_pii' => 1,
        'use_s2s' => true,
        'access_token' => '${{ secrets.FB_ACCESS_TOKEN }}'
      );
      update_option('facebook_config', \$config);
      echo '✅ facebook_config option updated' . PHP_EOL;
      
      // Verify it was saved
      \$saved = get_option('facebook_config');
      echo 'Saved pixel_id: ' . (\$saved['pixel_id'] ?? 'NONE') . PHP_EOL;
    " --allow-root
```

### Fix #2: Create Test Product
**Location:** After line 245

```yaml
- name: Create test products
  run: |
    cd /tmp/wordpress
    
    wp eval "
      // Create main test product
      \$product = new WC_Product_Simple();
      \$product->set_name('TestP');
      \$product->set_slug('testp');
      \$product->set_regular_price('19.99');
      \$product->set_description('Test product for E2E tests');
      \$product->set_short_description('A test product');
      \$product->set_status('publish');
      \$product->set_catalog_visibility('visible');
      \$product->set_stock_status('instock');
      \$product->set_manage_stock(false);
      \$product_id = \$product->save();
      
      echo 'Product created: ' . \$product_id . PHP_EOL;
      echo 'Product URL: ' . get_permalink(\$product_id) . PHP_EOL;
      
      // Assign to 'uncategorized' category for ViewCategory tests
      wp_set_object_terms(\$product_id, 'uncategorized', 'product_cat');
      
      echo '✅ Test product ready for tests' . PHP_EOL;
    " --allow-root
```

### Fix #3: Add Output Buffering
**Location:** In wp-config.php section (after line 129)

```yaml
# Add after security keys section
cat >> wp-config.php << 'EOF'

// Start output buffering to prevent "headers already sent" errors
ob_start();

EOF
```

### Fix #4: Force WordPress Initialization
**Location:** After plugin activation (after line 240)

```yaml
- name: Force WordPress initialization
  run: |
    cd /tmp/wordpress
    
    # Sleep to let WordPress settle
    sleep 3
    
    # Trigger a full page load to initialize all hooks
    echo "Triggering WordPress initialization..."
    curl -s http://localhost:8080 > /tmp/init_response.html
    
    # Force WordPress hooks
    wp eval "
      do_action('init');
      do_action('wp_loaded');
      do_action('wp');
      echo '✅ WordPress hooks triggered' . PHP_EOL;
    " --allow-root
```

### Fix #5: Better Verification
**Location:** Replace lines 267-354

```yaml
- name: Verify Facebook setup (ENHANCED)
  run: |
    cd /tmp/wordpress
    
    echo "=== Checking facebook_config Option ==="
    wp option get facebook_config --allow-root --format=json | jq . || echo "facebook_config not found!"
    
    echo ""
    echo "=== Checking Integration Settings ==="
    wp option get woocommerce_woocommerce_facebook_for_woocommerce_settings --allow-root --format=json | jq . || echo "Integration settings not found!"
    
    echo ""
    echo "=== Checking Plugin Status ==="
    wp eval "
      if (function_exists('facebook_for_woocommerce')) {
        \$integration = facebook_for_woocommerce()->get_integration();
        
        echo 'Integration loaded: YES' . PHP_EOL;
        echo 'get_facebook_pixel_id(): [' . \$integration->get_facebook_pixel_id() . ']' . PHP_EOL;
        
        // Check WC_Facebookcommerce_Pixel::get_pixel_id()
        echo 'WC_Facebookcommerce_Pixel::get_pixel_id(): [' . WC_Facebookcommerce_Pixel::get_pixel_id() . ']' . PHP_EOL;
        
        // Check EventsTracker
        \$reflection = new ReflectionClass(\$integration);
        \$property = \$reflection->getProperty('events_tracker');
        \$property->setAccessible(true);
        \$tracker = \$property->getValue(\$integration);
        echo 'EventsTracker exists: ' . (is_object(\$tracker) ? 'YES' : 'NO') . PHP_EOL;
        
        if (!is_object(\$tracker)) {
          echo '❌ CRITICAL: EventsTracker not created! No events will fire!' . PHP_EOL;
          exit(1);
        }
      } else {
        echo '❌ Plugin not loaded' . PHP_EOL;
        exit(1);
      }
    " --allow-root
    
    echo ""
    echo "=== Checking Pixel Code in HTML ==="
    curl -s http://localhost:8080 > /tmp/homepage.html
    
    if grep -q "fbq('init'" /tmp/homepage.html; then
      echo "✅ fbq('init') FOUND in HTML"
      grep "fbq('init'" /tmp/homepage.html | head -n 1
    else
      echo "❌ fbq('init') NOT FOUND - PIXEL CODE MISSING!"
      echo "This means pixel_base_code() returned empty!"
      exit 1
    fi
    
    if grep -q "fbq('track', 'PageView')" /tmp/homepage.html; then
      echo "✅ PageView event FOUND"
    else
      echo "❌ PageView event NOT FOUND"
      exit 1
    fi
```

---

## 📋 COMPLETE UPDATED WORKFLOW SECTION

Here's what your workflow should look like after applying all fixes:

```yaml
- name: Configure Facebook Pixel (CRITICAL FIX)
  run: |
    cd /tmp/wordpress
    
    # CRITICAL: Set facebook_config option (what the plugin actually reads)
    wp eval "
      \$config = array(
        'pixel_id' => '${{ secrets.FB_PIXEL_ID }}',
        'use_pii' => 1,
        'use_s2s' => true,
        'access_token' => '${{ secrets.FB_ACCESS_TOKEN }}'
      );
      update_option('facebook_config', \$config);
      
      \$saved = get_option('facebook_config');
      echo '✅ facebook_config saved. Pixel ID: ' . (\$saved['pixel_id'] ?? 'NONE') . PHP_EOL;
    " --allow-root
    
    # Also set integration settings
    wp eval "
      \$settings = get_option('woocommerce_woocommerce_facebook_for_woocommerce_settings', array());
      \$settings['facebook_pixel_id'] = '${{ secrets.FB_PIXEL_ID }}';
      \$settings['enable_advanced_matching'] = 'yes';
      \$settings['is_messenger_chat_plugin_enabled'] = 'no';
      update_option('woocommerce_woocommerce_facebook_for_woocommerce_settings', \$settings);
      echo '✅ Integration settings updated' . PHP_EOL;
    " --allow-root
    
    # Set legacy options for backward compatibility
    wp option update wc_facebook_pixel_id "${{ secrets.FB_PIXEL_ID }}" --allow-root
    wp option update wc_facebook_enable_pixel "yes" --allow-root
    wp option update wc_facebook_enable_server_to_server "yes" --allow-root
    wp option update wc_facebook_enable_advanced_matching "yes" --allow-root

# ... (existing plugin install/copy code) ...

- name: Activate plugin and initialize
  run: |
    cd /tmp/wordpress
    
    # Activate plugin
    wp plugin activate facebook-for-woocommerce --allow-root
    
    # Wait for activation to complete
    sleep 3
    
    # Force WordPress initialization
    curl -s http://localhost:8080 > /dev/null
    
    # Trigger WordPress hooks
    wp eval "
      do_action('init');
      do_action('wp_loaded');
      echo '✅ WordPress initialized' . PHP_EOL;
    " --allow-root

- name: Create customer user and test products
  run: |
    cd /tmp/wordpress
    
    # Create customer user
    wp user create customer customer@test.com \
      --role=customer \
      --user_pass=Password@54321 \
      --allow-root || echo "Customer already exists"
    
    # Create test product
    wp eval "
      \$product = new WC_Product_Simple();
      \$product->set_name('TestP');
      \$product->set_slug('testp');
      \$product->set_regular_price('19.99');
      \$product->set_description('Test product');
      \$product->set_status('publish');
      \$product->set_catalog_visibility('visible');
      \$product->set_stock_status('instock');
      \$product_id = \$product->save();
      wp_set_object_terms(\$product_id, 'uncategorized', 'product_cat');
      echo '✅ Test product created: ' . get_permalink(\$product_id) . PHP_EOL;
    " --allow-root

- name: Verify Facebook setup (ENHANCED)
  run: |
    cd /tmp/wordpress
    
    echo "=== facebook_config Option ==="
    wp option get facebook_config --allow-root
    
    echo ""
    echo "=== Checking Pixel ID via Plugin ==="
    wp eval "
      if (function_exists('facebook_for_woocommerce')) {
        echo 'Pixel ID from get_facebook_pixel_id(): [' . facebook_for_woocommerce()->get_integration()->get_facebook_pixel_id() . ']' . PHP_EOL;
        echo 'Pixel ID from WC_Facebookcommerce_Pixel: [' . WC_Facebookcommerce_Pixel::get_pixel_id() . ']' . PHP_EOL;
        
        if (empty(WC_Facebookcommerce_Pixel::get_pixel_id())) {
          echo '❌ CRITICAL: Pixel ID is empty!' . PHP_EOL;
          exit(1);
        }
        echo '✅ Pixel ID configured correctly' . PHP_EOL;
      }
    " --allow-root
    
    echo ""
    echo "=== Checking HTML Output ==="
    curl -s http://localhost:8080 > /tmp/homepage.html
    
    if ! grep -q "fbq('init'" /tmp/homepage.html; then
      echo "❌ CRITICAL: No pixel code in HTML!"
      exit 1
    fi
    echo "✅ Pixel code found in HTML"
```

---

## 🎯 Summary

**Primary Issue:** `facebook_config` option not set
**Impact:** Pixel ID returns empty → No pixel code rendered → No events fire
**Fix:** Set `facebook_config` option before activating plugin

**Secondary Issues:**
- Test product missing → ViewContent/AddToCart tests fail
- Timing issue → WordPress not fully initialized
- Output buffering → Prevents "headers already sent" errors

Apply all fixes above and your tests should work on GitHub!

<function_calls>
<invoke name="read_file">
<parameter name="path">/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public/wp-content/plugins/facebook-for-woocommerce/tests/e2e/config/test-config.js