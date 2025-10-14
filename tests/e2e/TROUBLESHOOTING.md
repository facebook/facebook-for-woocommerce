# 🔧 Troubleshooting: Why No Events Were Captured

## What Just Happened

You ran the test and got:
```
✅ API.php has monitoring code
Total Events: 0
  Pixel Events: 0
  CAPI Events: 0
⚠️ No CAPI events captured
⚠️ No Pixel events captured
```

## Why This Happened

### 1. WP_DEBUG_LOG Was Disabled
✅ **FIXED** - I just enabled it in `wp-config.php`

### 2. No Events Are Being Fired
The Facebook plugin might not be configured or CAPI might be disabled.

## Next Steps to Get Events

### Step 1: Verify Facebook Plugin is Active & Configured

```bash
# Check plugin is active
wp plugin list --path="/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public"

# Check Facebook settings
wp option get wc_facebook_pixel_id --path="/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public"
wp option get wc_facebook_access_token --path="/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public"
```

### Step 2: Enable CAPI in Plugin Settings

Go to: `WooCommerce → Settings → Facebook → Advanced Features`

Make sure:
- ✅ Facebook Pixel ID is set
- ✅ Access Token is configured
- ✅ "Use Conversions API" is **ENABLED**
- ✅ "Test Event Code" can be left empty (not needed for this)

### Step 3: Manually Test Event Firing

```bash
# Visit homepage to trigger PageView
curl "http://wooc-local-test-sitecom.local/"

# Check if debug.log was created and has content
ls -lh "/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public/wp-content/debug.log"
cat "/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public/wp-content/debug.log"
```

You should see log entries. If you see our modified code working, it will look like:
```
[FBTEST|test-123] CAPI|PageView|event-id-abc|{...}
```

### Step 4: Check Browser Console

When you visit the site in a browser, open DevTools console and check:
```javascript
// Check if fbq is loaded
console.log(typeof fbq); // Should be 'function'

// Check if events are firing
fbq('track', 'PageView');
```

### Step 5: Re-run the Test

```bash
npx playwright test tests/e2e/simple-event-capture.spec.js
```

## Common Issues

### Issue 1: "Add to cart button not found"
This means the product page selector is wrong. Check the actual HTML:
```bash
curl "http://wooc-local-test-sitecom.local/product/test-product-for-facebook-pixel/" | grep -i "add.*cart"
```

Update the selector in the test if needed.

### Issue 2: No Pixel Events in Browser
- Facebook Pixel might not be installed/configured
- Ad blockers might be blocking it
- Check browser console for JavaScript errors

### Issue 3: No CAPI Events in Log
- CAPI not enabled in plugin settings
- Access token invalid/expired
- Our code modification didn't work (check API.php)

## Quick Verification Script

Run this to check everything:

```bash
#!/bin/bash
echo "🔍 Facebook Event Monitoring - System Check"
echo "=========================================="

# Check WP_DEBUG
echo "\n1. Checking WP_DEBUG..."
grep "WP_DEBUG" "/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public/wp-config.php"

# Check debug.log exists
echo "\n2. Checking debug.log..."
if [ -f "/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public/wp-content/debug.log" ]; then
    echo "✅ debug.log exists"
    ls -lh "/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public/wp-content/debug.log"
else
    echo "❌ debug.log does not exist"
fi

# Check API.php modification
echo "\n3. Checking API.php modification..."
if grep -q "EVENT MONITORING FOR E2E TESTS" "/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public/wp-content/plugins/facebook-for-woocommerce/includes/API.php"; then
    echo "✅ API.php has been modified"
else
    echo "❌ API.php has NOT been modified"
fi

# Check Facebook plugin active
echo "\n4. Checking Facebook plugin..."
if [ -d "/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public/wp-content/plugins/facebook-for-woocommerce" ]; then
    echo "✅ Facebook for WooCommerce plugin directory exists"
else
    echo "❌ Plugin directory not found"
fi

echo "\n=========================================="
echo "Check complete!"
```

Save this as `check-setup.sh`, make it executable (`chmod +x check-setup.sh`), and run it.

## Expected Output After Everything is Fixed

```
🧪 TEST: Facebook Event Capture
================================

🔍 Started monitoring with Test ID: test-run-1696636800000
   Cookie set: facebook_test_id=test-run-1696636800000
   Initial log size: 12345 bytes

📄 Step 1: Visit homepage...
   ✓ Homepage loaded

📦 Step 2: Visit product page...
   ✓ Product page loaded

🛒 Step 3: Add to cart...
   ✓ Product added to cart

✅ Stopping capture for: test-run-1696636800000
   Browser captured: 3 pixel events
   Log grew by: 1543 bytes

📋 New log entries:
[FBTEST|test-run-1696636800000] CAPI|PageView|ev-123-abc|{...}
[FBTEST|test-run-1696636800000] CAPI|ViewContent|ev-123-def|{...}
[FBTEST|test-run-1696636800000] CAPI|AddToCart|ev-123-ghi|{...}

   Parsed 3 CAPI events for test ID: test-run-1696636800000

💾 Saved results to: events-test-run-1696636800000.json

📊 RESULTS:
================================
Total Events: 6
  Pixel Events: 3
  CAPI Events: 3

✅ CAPI Events Captured:
   1. PageView (ID: ev-123-abc)
   2. ViewContent (ID: ev-123-def)
   3. AddToCart (ID: ev-123-ghi)

✅ Pixel Events Captured:
   1. track PageView
   2. track ViewContent
   3. track AddToCart

✅ Test completed!
```

## Next: Once Events Are Captured

After you get events captured successfully, you can move on to:
1. **Business Manager Validation** - Verify events appear in Facebook
2. **Parallel Testing** - Run multiple tests simultaneously
3. **CI/CD Integration** - Add to your GitHub Actions workflow