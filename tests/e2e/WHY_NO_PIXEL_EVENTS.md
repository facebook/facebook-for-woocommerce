# ⚠️ Why No Pixel Events Are Captured

## What Just Happened

✅ **CAPI events captured**: 3 events (PageView, ViewContent, AddToCart)  
❌ **Pixel events captured**: 0 events

## Why Pixel Events Are Missing

### The Facebook Pixel Has Two Parts:

1. **Browser JavaScript** (`fbq()` function) - Runs in the browser
2. **Server-side CAPI** - Runs on WordPress server

### Why CAPI Works But Pixel Doesn't:

**CAPI is ENABLED by default** in the Facebook for WooCommerce plugin.  
**Pixel JavaScript is DISABLED** unless you explicitly configure it.

## How to Enable Facebook Pixel

### Option 1: Via WordPress Admin (Recommended)

1. Go to: **WooCommerce → Settings → Facebook**
2. Scroll to **"Facebook Pixel"** section
3. Make sure your **Pixel ID** is entered
4. Enable: **"Enable pixel on website"** or similar checkbox
5. Click **Save changes**

### Option 2: Via WP-CLI

```bash
cd "/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public"

# Check current pixel settings
wp option get wc_facebook_pixel_id

# Set pixel ID (if not set)
wp option update wc_facebook_pixel_id "YOUR_PIXEL_ID_HERE"

# Enable pixel (the setting name varies by plugin version)
wp option update wc_facebook_enable_pixel 1
```

### Option 3: Check Current Configuration

Run this to see what's configured:

```bash
cd "/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public"

php -r "
require_once('wp-load.php');
echo '🔍 Facebook Configuration:\n';
echo 'Pixel ID: ' . get_option('wc_facebook_pixel_id', 'Not set') . '\n';
echo 'Access Token: ' . (get_option('wc_facebook_access_token') ? 'Set' : 'Not set') . '\n';

\$fb_options = get_option('wc_facebook_options', []);
echo 'Enable Pixel: ' . (isset(\$fb_options['enable_pixel']) ? (\$fb_options['enable_pixel'] ? 'Yes' : 'No') : 'Unknown') . '\n';
echo 'CAPI Enabled: ' . (isset(\$fb_options['use_s2s']) ? (\$fb_options['use_s2s'] ? 'Yes' : 'No') : 'Unknown') . '\n';
"
```

## How to Verify Pixel is Working

### Method 1: Check Browser Console

Visit your site and open DevTools console:

```javascript
// Check if fbq function exists
typeof fbq
// Should return: "function"

// Check if pixel is loaded
fbq('track', 'PageView');
// Should fire an event
```

### Method 2: Check Network Tab

1. Open DevTools → Network tab
2. Visit a page on your site
3. Look for requests to `facebook.com/tr`
4. If you see these requests → Pixel is working! ✅
5. If no requests → Pixel is not loaded ❌

### Method 3: Use Facebook Pixel Helper Extension

1. Install: [Facebook Pixel Helper Chrome Extension](https://chrome.google.com/webstore/detail/facebook-pixel-helper/fdgfkebogiimcoedlicjlajpkdmockpc)
2. Visit your site
3. Click the extension icon
4. It will show you if the pixel is firing and what events

## After Enabling Pixel

Once you enable the Pixel JavaScript, run the test again:

```bash
npx playwright test tests/e2e/simple-event-capture.spec.js
```

You should see:

```
✅ CAPI Events Captured:
   1. PageView (ID: xxx)
   2. ViewContent (ID: xxx)
   3. AddToCart (ID: xxx)

✅ Pixel Events Captured:
   1. track PageView
   2. track ViewContent
   3. track AddToCart

Total Events: 6 (3 pixel, 3 CAPI)
```

## Why CAPI Works Without Pixel

The Facebook for WooCommerce plugin has a setting like:

```
☑ Use Conversions API (CAPI)         ← YOU HAVE THIS ENABLED ✅
☐ Enable Pixel on website            ← YOU DON'T HAVE THIS ENABLED ❌
```

**CAPI** sends events directly from WordPress server to Facebook (works without browser)  
**Pixel** sends events from browser JavaScript (needs `fbq()` code on page)

## Current Status

✅ **What's Working**:
- Cookie-based test isolation
- CAPI event capture
- Event logging with event IDs
- Parallel test support

⚠️ **What's Missing**:
- Pixel JavaScript not enabled in plugin
- No `fbq()` function on page
- No browser events being fired

## Next Steps

1. **Enable Pixel** in WooCommerce → Settings → Facebook
2. **Verify** by checking browser console for `fbq` function
3. **Re-run test** to see both CAPI and Pixel events
4. **Business Manager validation** - verify events appear in Facebook

## Summary

You have **half the system working** (CAPI ✅) but not the other half (Pixel ❌).

**To fix**: Just enable "Facebook Pixel on website" in the plugin settings!

The monitoring code is working perfectly - it's just waiting for Pixel events to capture! 🎯