# ✅ Simple Approach: NO External Plugin Needed

## The Problem with MU-Plugin Approach
- ❌ Requires installing external code
- ❌ Extra files to maintain
- ❌ Requires filesystem access
- ❌ Complicated REST API setup

## ✅ MUCH BETTER: Modify the Plugin Directly

### Where to Add Event Capture

**File**: `/includes/API.php`  
**Method**: `send_pixel_events()` (Line 617)

This method is called EVERY TIME the plugin sends events to Facebook.

### Simple Modification

```php
// In /includes/API.php around line 617

public function send_pixel_events( $pixel_id, array $events ) {
    // 🎯 ADD THIS: Log events if we're testing
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG_LOG ) {
        $test_mode = get_transient( 'facebook_event_test_mode' );
        if ( $test_mode ) {
            // Log each event with full details
            foreach ( $events as $event ) {
                error_log( sprintf(
                    '[FB_EVENT_CAPTURE] %s | %s | %s',
                    $test_mode,  // test name
                    $event->get_event_name(),
                    json_encode( $event->as_array() )
                ) );
            }
        }
    }
    
    // Original code continues
    $request = new API\Pixel\Events\Request( $pixel_id, $events );
    $this->set_response_handler( Response::class );
    return $this->perform_request( $request );
}
```

### That's It! Now Your Tests Can:

```javascript
// In your test
const axios = require('axios');

// 1. Start capturing
await axios.post('http://wooc-local-test-sitecom.local/wp-json/wp/v2/options', {
    transient: 'facebook_event_test_mode',
    value: 'my-test-name'
});

// 2. Do your test actions
await page.goto('/product/123');
await page.click('.add_to_cart');

// 3. Read debug.log and parse events
const fs = require('fs');
const debugLog = fs.readFileSync('/path/to/wp-content/debug.log', 'utf8');

// Parse lines like: [FB_EVENT_CAPTURE] my-test-name | ViewContent | {...}
const myEvents = debugLog
    .split('\n')
    .filter(line => line.includes('[FB_EVENT_CAPTURE]') && line.includes('my-test-name'))
    .map(line => {
        const parts = line.split(' | ');
        return {
            testName: parts[0].split('] ')[1],
            eventName: parts[1],
            eventData: JSON.parse(parts[2])
        };
    });

console.log(`Captured ${myEvents.length} events`);
```

## Even Simpler: Just Check the Log File!

The plugin ALREADY logs to:  
`/wp-content/uploads/wc-logs/facebook_for_woocommerce-*.log`

**You found it yourself!** That file has CAPI events.

### Just Parse That File

```javascript
class SimpleEventMonitor {
    constructor(logPath) {
        this.logPath = logPath;
        this.initialSize = fs.statSync(logPath).size;
    }
    
    getNewEvents() {
        const currentSize = fs.statSync(this.logPath).size;
        const buffer = Buffer.alloc(currentSize - this.initialSize);
        const fd = fs.openSync(this.logPath, 'r');
        fs.readSync(fd, buffer, 0, buffer.length, this.initialSize);
        fs.closeSync(fd);
        
        const newContent = buffer.toString('utf8');
        return this.parseEvents(newContent);
    }
    
    parseEvents(content) {
        // Parse the Facebook log format
        return content
            .split('\n')
            .filter(line => line.includes('send_pixel_events'))
            .map(line => this.parseLogLine(line));
    }
}
```

## Comparison

| Approach | Complexity | Changes Needed | Maintenance |
|----------|-----------|----------------|-------------|
| MU-Plugin | High | Create new plugin, REST API, etc | High |
| **Modify API.php** | **Low** | **Add 10 lines of code** | **Low** |
| **Parse Existing Log** | **Lowest** | **Zero code changes** | **Lowest** |

## Recommended: Just Parse the Existing Log

**The Facebook plugin ALREADY logs everything you need!**

You found it: `/wp-content/uploads/wc-logs/facebook_for_woocommerce-2025-10-07*`

### Simple Test Code

```javascript
const { test } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

test('capture Facebook events', async ({ page }) => {
    // Find today's log file
    const logsDir = '/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public/wp-content/uploads/wc-logs';
    const today = new Date().toISOString().split('T')[0];
    const logFile = path.join(logsDir, `facebook_for_woocommerce-${today}.log`);
    
    // Get initial file size
    const initialSize = fs.existsSync(logFile) ? fs.statSync(logFile).size : 0;
    
    // Do test actions
    await page.goto('http://wooc-local-test-sitecom.local/product/123/');
    await page.click('.add_to_cart_button');
    await page.waitForTimeout(3000);
    
    // Read new log entries
    if (fs.existsSync(logFile)) {
        const currentSize = fs.statSync(logFile).size;
        const newBytes = currentSize - initialSize;
        
        const buffer = Buffer.alloc(newBytes);
        const fd = fs.openSync(logFile, 'r');
        fs.readSync(fd, buffer, 0, newBytes, initialSize);
        fs.closeSync(fd);
        
        const newContent = buffer.toString('utf8');
        console.log('New log entries:');
        console.log(newContent);
        
        // Count events
        const eventLines = newContent.split('\n').filter(line => 
            line.includes('send_pixel_events') || 
            line.includes('ViewContent') ||
            line.includes('AddToCart')
        );
        
        console.log(`\n✅ Captured ${eventLines.length} events`);
    }
});
```

## Winner: Parse Existing Log

**Why?**
1. ✅ Zero code changes to the plugin
2. ✅ Plugin already logs everything
3. ✅ Just read the file - simple!
4. ✅ Works immediately
5. ✅ No maintenance overhead

**That's what I should have done from the start!** 🎉