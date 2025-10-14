# 🎯 FINAL SIMPLE POC - Event Monitoring

## What We Need to Modify

**Just ONE file**: `/includes/API.php` - Line 617

### The Modification

```php
// In /includes/API.php - Line 617
public function send_pixel_events( $pixel_id, array $events ) {
    
    // 🎯 ADD THIS CODE - Start logging for tests
    $test_id = isset( $_COOKIE['facebook_test_id'] ) ? $_COOKIE['facebook_test_id'] : null;
    
    if ( $test_id && defined( 'WP_DEBUG' ) && WP_DEBUG_LOG ) {
        foreach ( $events as $event ) {
            error_log( sprintf(
                '[FBTEST|%s] CAPI|%s|%s',
                $test_id,
                $event->get_event_name(),
                json_encode( $event->as_array() )
            ) );
        }
    }
    // 🎯 END OF ADDED CODE
    
    // Original code continues unchanged
    $request = new API\Pixel\Events\Request( $pixel_id, $events );
    $this->set_response_handler( Response::class );
    return $this->perform_request( $request );
}
```

## Why This Works for Parallel Tests

**Key**: We use a **COOKIE** to identify which test is running!

```javascript
// Test 1 sets: facebook_test_id=test-1-timestamp
// Test 2 sets: facebook_test_id=test-2-timestamp
// Test 3 sets: facebook_test_id=test-3-timestamp
```

Each browser session has its own cookie → Each test's events are tagged with its unique ID!

## Complete Test Code

```javascript
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

class SimpleFacebookMonitor {
    constructor(debugLogPath) {
        this.debugLogPath = debugLogPath || '/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public/wp-content/debug.log';
        this.testId = null;
        this.initialLogSize = 0;
    }
    
    async startCapture(page, testName) {
        this.testId = `${testName}-${Date.now()}`;
        
        // Set cookie to identify this test
        await page.context().addCookies([{
            name: 'facebook_test_id',
            value: this.testId,
            domain: 'wooc-local-test-sitecom.local',
            path: '/'
        }]);
        
        // Get initial log size
        if (fs.existsSync(this.debugLogPath)) {
            this.initialLogSize = fs.statSync(this.debugLogPath).size;
        }
        
        // Also capture Pixel events from browser
        await this.capturePixelEvents(page);
        
        console.log(`🔍 Started monitoring: ${this.testId}`);
    }
    
    async capturePixelEvents(page) {
        await page.addInitScript((testId) => {
            window.__pixelEvents = [];
            
            if (typeof window.fbq !== 'undefined') {
                const originalFbq = window.fbq;
                window.fbq = function(...args) {
                    window.__pixelEvents.push({
                        type: 'pixel',
                        args: args,
                        timestamp: Date.now(),
                        testId: testId
                    });
                    return originalFbq.apply(this, args);
                };
            }
        }, this.testId);
    }
    
    async stopCapture(page) {
        // Get browser pixel events
        const pixelEvents = await page.evaluate(() => window.__pixelEvents || []);
        
        // Get CAPI events from debug log
        const capiEvents = this.parseDebugLog();
        
        const results = {
            testId: this.testId,
            timestamp: new Date().toISOString(),
            events: {
                pixel: pixelEvents,
                capi: capiEvents
            },
            summary: {
                totalEvents: pixelEvents.length + capiEvents.length,
                pixelEvents: pixelEvents.length,
                capiEvents: capiEvents.length
            }
        };
        
        // Save to file
        const filename = `events-${this.testId}.json`;
        const eventsDir = path.join(__dirname, 'captured-events');
        if (!fs.existsSync(eventsDir)) {
            fs.mkdirSync(eventsDir, { recursive: true });
        }
        fs.writeFileSync(path.join(eventsDir, filename), JSON.stringify(results, null, 2));
        
        console.log(`✅ Captured ${results.summary.totalEvents} events (${results.summary.pixelEvents} pixel, ${results.summary.capiEvents} CAPI)`);
        
        return results;
    }
    
    parseDebugLog() {
        if (!fs.existsSync(this.debugLogPath)) {
            return [];
        }
        
        const currentSize = fs.statSync(this.debugLogPath).size;
        const newBytes = currentSize - this.initialLogSize;
        
        if (newBytes <= 0) return [];
        
        const buffer = Buffer.alloc(newBytes);
        const fd = fs.openSync(this.debugLogPath, 'r');
        fs.readSync(fd, buffer, 0, newBytes, this.initialLogSize);
        fs.closeSync(fd);
        
        const newContent = buffer.toString('utf8');
        
        // Parse lines like: [FBTEST|test-1-123] CAPI|ViewContent|{...}
        return newContent
            .split('\n')
            .filter(line => line.includes(`[FBTEST|${this.testId}]`))
            .map(line => {
                const parts = line.split('|');
                if (parts.length >= 4) {
                    return {
                        type: 'capi',
                        eventName: parts[2],
                        eventData: JSON.parse(parts[3]),
                        testId: this.testId,
                        timestamp: Date.now()
                    };
                }
                return null;
            })
            .filter(Boolean);
    }
}

// THE ACTUAL TEST
test.describe('Facebook Event Monitoring POC', () => {
    test('should capture both Pixel and CAPI events', async ({ page }) => {
        const monitor = new SimpleFacebookMonitor();
        
        // 1. Start monitoring
        await monitor.startCapture(page, 'product-flow');
        
        // 2. Do test actions
        await page.goto('http://wooc-local-test-sitecom.local/');
        await page.waitForTimeout(2000);
        
        await page.goto('http://wooc-local-test-sitecom.local/product/test-product-for-facebook-pixel/');
        await page.waitForTimeout(2000);
        
        const addToCartButton = page.locator('.single_add_to_cart_button').first();
        if (await addToCartButton.isVisible()) {
            await addToCartButton.click();
            await page.waitForTimeout(3000);
        }
        
        // 3. Stop and get results
        const results = await monitor.stopCapture(page);
        
        // 4. Validate
        expect(results.summary.totalEvents).toBeGreaterThan(0);
        
        console.log('\n📊 Test Results:');
        console.log(`   Total Events: ${results.summary.totalEvents}`);
        console.log(`   Pixel Events: ${results.summary.pixelEvents}`);
        console.log(`   CAPI Events: ${results.summary.capiEvents}`);
        
        // Show event names
        if (results.events.capi.length > 0) {
            console.log('\n   CAPI Events Captured:');
            results.events.capi.forEach(e => console.log(`     - ${e.eventName}`));
        }
        
        if (results.events.pixel.length > 0) {
            console.log('\n   Pixel Events Captured:');
            results.events.pixel.forEach(e => console.log(`     - ${e.args[0]} ${e.args[1]}`));
        }
    });
});
```

## How It Works

### 1. Test Sets Cookie
```javascript
await page.context().addCookies([{
    name: 'facebook_test_id',
    value: 'test-1-timestamp'
}]);
```

### 2. WordPress Sees Cookie
```php
$test_id = $_COOKIE['facebook_test_id']; // "test-1-timestamp"
```

### 3. Logs Tagged Events
```
[FBTEST|test-1-timestamp] CAPI|ViewContent|{"event_name":"ViewContent",...}
[FBTEST|test-1-timestamp] CAPI|AddToCart|{"event_name":"AddToCart",...}
```

### 4. Test Parses Its Own Events
```javascript
.filter(line => line.includes(`[FBTEST|${this.testId}]`))
```

## Parallel Tests Work!

```bash
# Run 3 tests in parallel
npx playwright test --workers=3
```

**What happens**:
- Test 1: Cookie = `product-flow-1633024800000`
- Test 2: Cookie = `checkout-flow-1633024801000`
- Test 3: Cookie = `search-flow-1633024802000`

**Debug log**:
```
[FBTEST|product-flow-1633024800000] CAPI|ViewContent|{...}
[FBTEST|checkout-flow-1633024801000] CAPI|InitiateCheckout|{...}
[FBTEST|product-flow-1633024800000] CAPI|AddToCart|{...}
[FBTEST|search-flow-1633024802000] CAPI|Search|{...}
```

Each test only reads lines with ITS cookie value!

## What You Get

### Captured Events File: `events-product-flow-1633024800000.json`
```json
{
  "testId": "product-flow-1633024800000",
  "timestamp": "2025-10-07T16:15:00.000Z",
  "events": {
    "pixel": [
      {
        "type": "pixel",
        "args": ["track", "PageView"],
        "timestamp": 1633024801000,
        "testId": "product-flow-1633024800000"
      },
      {
        "type": "pixel",
        "args": ["track", "ViewContent", {"content_ids": ["212"]}],
        "timestamp": 1633024803000,
        "testId": "product-flow-1633024800000"
      }
    ],
    "capi": [
      {
        "type": "capi",
        "eventName": "PageView",
        "eventData": {
          "event_name": "PageView",
          "event_time": 1633024801,
          "user_data": {...},
          "custom_data": {...}
        },
        "testId": "product-flow-1633024800000",
        "timestamp": 1633024801000
      },
      {
        "type": "capi",
        "eventName": "ViewContent",
        "eventData": {
          "event_name": "ViewContent",
          "event_time": 1633024803,
          "user_data": {...},
          "custom_data": {"content_ids": ["212"]}
        },
        "testId": "product-flow-1633024800000",
        "timestamp": 1633024803000
      }
    ]
  },
  "summary": {
    "totalEvents": 4,
    "pixelEvents": 2,
    "capiEvents": 2
  }
}
```

## Setup Steps

1. **Modify `/includes/API.php` line 617** - Add the 8 lines of code above
2. **Enable WordPress debug logging** in `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
3. **Copy the test code** above to your tests
4. **Run it**: `npx playwright test`

## Why This is Better

| Approach | Parallel Tests | Pixel Events | CAPI Events | Code Changes |
|----------|---------------|--------------|-------------|--------------|
| Log parsing (basic) | ❌ No | ❌ No | ✅ Yes | None |
| **Cookie-based POC** | **✅ Yes** | **✅ Yes** | **✅ Yes** | **8 lines** |
| MU-Plugin approach | ✅ Yes | ✅ Yes | ✅ Yes | 100+ lines |

## That's It!

Just modify ONE file (`API.php`), add 8 lines of code, and you're done! ✅