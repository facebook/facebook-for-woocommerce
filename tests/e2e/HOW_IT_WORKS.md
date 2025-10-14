# 🎯 How the Event Monitoring System Works

## Complete Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│ STEP 1: SETUP (One-Time)                                           │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  Your Test (event-monitor-improved.js)                             │
│       │                                                             │
│       │  monitor.installMonitorPlugin()                            │
│       │                                                             │
│       ↓                                                             │
│  Creates File: wp-content/mu-plugins/facebook-event-monitor.php    │
│                                                                     │
│  This PHP file contains WordPress hooks that will intercept        │
│  ALL Facebook events automatically.                                │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│ STEP 2: TEST EXECUTION                                             │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  [Playwright Test Starts]                                          │
│       │                                                             │
│       │  monitor.startCapture('test-name')                         │
│       │                                                             │
│       ↓                                                             │
│  HTTP POST to WordPress REST API:                                  │
│  /wp-json/facebook-monitor/v1/start                               │
│       │                                                             │
│       ↓                                                             │
│  WordPress sets: set_transient('facebook_event_monitor_test')      │
│  WordPress clears: delete_option('facebook_event_monitor_captured')│
│       │                                                             │
│       │  [Monitoring is now ACTIVE]                                │
│       │                                                             │
│       ↓                                                             │
│  Test performs actions:                                            │
│    - Visit homepage                                                │
│    - Visit product page                                            │
│    - Add to cart                                                   │
│       │                                                             │
│       ↓                                                             │
│  [WordPress Facebook plugin fires events]                          │
│       │                                                             │
│       ↓                                                             │
│  ┌─────────────────────────────────────────────────────┐          │
│  │ WORDPRESS HOOK INTERCEPTS (mu-plugin)               │          │
│  │                                                     │          │
│  │ add_filter('wc_facebook_pixel_event_sent', ...)    │          │
│  │                                                     │          │
│  │ When Facebook plugin tries to send CAPI event:     │          │
│  │   1. Hook captures the event data                  │          │
│  │   2. Checks if test is active (transient exists)   │          │
│  │   3. Stores event in WordPress option              │          │
│  │   4. Lets the event continue to Facebook API       │          │
│  │                                                     │          │
│  │ Event stored as:                                   │          │
│  │ {                                                  │          │
│  │   type: 'capi',                                   │          │
│  │   event_name: 'ViewContent',                      │          │
│  │   event_data: {...full payload...},               │          │
│  │   timestamp: 1696636805000,                       │          │
│  │   test_name: 'test-name',                         │          │
│  │   source: 'wordpress_hook'                        │          │
│  │ }                                                  │          │
│  └─────────────────────────────────────────────────────┘          │
│       │                                                             │
│       │  [Events accumulate in WordPress option]                   │
│       │                                                             │
│       ↓                                                             │
│  Test calls: monitor.stopCapture()                                 │
│       │                                                             │
│       ↓                                                             │
│  HTTP POST to WordPress REST API:                                  │
│  /wp-json/facebook-monitor/v1/stop                                │
│       │                                                             │
│       ↓                                                             │
│  WordPress returns ALL captured events                             │
│       │                                                             │
│       ↓                                                             │
│  Test saves events to JSON file                                    │
│  captured-events/events-test-name-timestamp.json                   │
│       │                                                             │
│       ↓                                                             │
│  [Test validates events and completes]                             │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

## Core Components Explained

### 1. The WordPress MU-Plugin (Heart of the System)

**Location**: `wp-content/mu-plugins/facebook-event-monitor.php`

**What it does**: Automatically loads in WordPress and hooks into Facebook events

**Key Code**:
```php
// This filter runs EVERY TIME the Facebook plugin sends a CAPI event
add_filter('wc_facebook_pixel_event_sent', function($event_data, $event_name) {
    // Check if we're currently monitoring a test
    $current_test = get_transient('facebook_event_monitor_test');

    if ($current_test) {
        // Capture the event!
        $captured_event = [
            'type' => 'capi',
            'event_name' => $event_name,
            'event_data' => $event_data,  // Complete payload
            'timestamp' => microtime(true) * 1000,
            'test_name' => $current_test,
            'source' => 'wordpress_hook'
        ];

        // Store in WordPress options table
        $captured = get_option('facebook_event_monitor_captured', []);
        $captured[] = $captured_event;
        update_option('facebook_event_monitor_captured', $captured, false);
    }

    // Return unchanged - event still goes to Facebook
    return $event_data;
}, 10, 2);
```

**Why this works**:
- WordPress hooks fire at the EXACT moment events are sent
- We get the COMPLETE event payload (not parsed from logs)
- Zero performance impact (hook is lightweight)
- Non-intrusive (doesn't break Facebook integration)

### 2. REST API Endpoints (Control Interface)

**Endpoint 1: Start Monitoring**
```
POST /wp-json/facebook-monitor/v1/start
Body: { "test_name": "my-test" }
```

**What it does**:
```php
set_transient('facebook_event_monitor_test', 'my-test', 3600);
delete_option('facebook_event_monitor_captured');
```
- Sets a flag: "We're monitoring test 'my-test'"
- Clears any old captured events
- Now the mu-plugin hook knows to capture events

**Endpoint 2: Stop Monitoring**
```
POST /wp-json/facebook-monitor/v1/stop
```

**What it does**:
```php
$captured = get_option('facebook_event_monitor_captured', []);
delete_transient('facebook_event_monitor_test');
delete_option('facebook_event_monitor_captured');

return ['events' => $captured];
```
- Retrieves ALL captured events
- Clears the monitoring flag
- Returns events to the test

### 3. ImprovedEventMonitor (Node.js Controller)

**Location**: `tests/e2e/event-monitor-improved.js`

**Purpose**: Orchestrates the monitoring from your Playwright tests

**Key Methods**:

#### `installMonitorPlugin()`
```javascript
async installMonitorPlugin() {
    // Creates wp-content/mu-plugins/facebook-event-monitor.php
    const muPluginsDir = path.join(this.config.wordpressPath, 'wp-content', 'mu-plugins');
    fs.mkdirSync(muPluginsDir, { recursive: true });

    const pluginCode = this.getWordPressHookCode(); // PHP code from above
    fs.writeFileSync(pluginPath, pluginCode);
}
```
**What it does**: One-time setup - installs the PHP hook code into WordPress

#### `startCapture(testName)`
```javascript
async startCapture(testName, wordpressUrl = 'http://...') {
    // Tells WordPress to start monitoring
    await axios.post(`${wordpressUrl}/wp-json/facebook-monitor/v1/start`, {
        test_name: testName
    });
}
```
**What it does**: Makes HTTP request to WordPress REST API to activate monitoring

#### `stopCapture()`
```javascript
async stopCapture(wordpressUrl = 'http://...') {
    // Retrieves captured events from WordPress
    const response = await axios.post(`${wordpressUrl}/wp-json/facebook-monitor/v1/stop`);
    const events = response.data.events;

    // Save to file
    fs.writeFileSync('captured-events/...json', JSON.stringify(events));

    return events;
}
```
**What it does**: Makes HTTP request to get all captured events and saves them locally

## Data Flow (Detailed)

### When a User Adds Product to Cart

```
1. User clicks "Add to Cart" button
   ↓
2. WooCommerce processes the AJAX request
   ↓
3. Facebook for WooCommerce plugin triggers
   ↓
4. Plugin prepares AddToCart event payload:
   {
     event_name: 'AddToCart',
     event_time: 1696636805,
     user_data: {
       em: 'hashed_email',
       fn: 'hashed_firstname'
     },
     custom_data: {
       content_ids: ['212'],
       content_type: 'product',
       value: 19.99,
       currency: 'USD'
     }
   }
   ↓
5. Plugin calls: apply_filters('wc_facebook_pixel_event_sent', $event_data, 'AddToCart')
   ↓
6. MU-PLUGIN HOOK CATCHES THIS! ⚡
   │
   ├─→ Checks: get_transient('facebook_event_monitor_test')
   │   Is a test active? YES!
   │
   ├─→ Creates captured event:
   │   {
   │     type: 'capi',
   │     event_name: 'AddToCart',
   │     event_data: {...complete payload...},
   │     timestamp: 1696636805000,
   │     test_name: 'wordpress-hook-test',
   │     source: 'wordpress_hook'
   │   }
   │
   ├─→ Stores in WordPress:
   │   update_option('facebook_event_monitor_captured', [...events])
   │
   └─→ Returns $event_data unchanged
   ↓
7. Event continues to Facebook Graph API as normal
   ↓
8. Facebook receives and processes the event
```

**Key Insight**: We intercept at step 6 but DON'T block step 7. The event still goes to Facebook!

## Storage Mechanism

### WordPress Options Table

Events are stored temporarily in WordPress options:

```sql
-- WordPress automatically creates this
INSERT INTO wp_options (option_name, option_value) VALUES
('facebook_event_monitor_captured',
 'a:3:{i:0;a:6:{s:4:"type";s:4:"capi";s:10:"event_name";s:8:"PageView";...}}'
);
```

**Why WordPress options?**
- Fast read/write
- No database schema changes needed
- Automatically cleaned up after test
- No performance impact on normal operations

### JSON File Output

After test completes, events are saved to:
```
tests/e2e/captured-events/
└── events-wordpress-hook-test-1696636805000.json
```

**Format**:
```json
{
  "testName": "wordpress-hook-test",
  "startTime": 1696636800000,
  "endTime": 1696636830000,
  "duration": 30000,
  "events": [
    {
      "type": "capi",
      "event_name": "PageView",
      "event_data": {
        "event_time": 1696636805,
        "user_data": {...},
        "custom_data": {...}
      },
      "timestamp": 1696636805000,
      "test_name": "wordpress-hook-test",
      "source": "wordpress_hook"
    },
    {
      "type": "capi",
      "event_name": "ViewContent",
      "event_data": {...},
      "timestamp": 1696636810000,
      "test_name": "wordpress-hook-test",
      "source": "wordpress_hook"
    }
  ],
  "summary": {
    "totalEvents": 2,
    "capiEvents": 2,
    "pixelEvents": 0
  }
}
```

## Why This Approach is Better Than Log Parsing

### Old Approach (Log Parsing)
```
WordPress → Facebook Plugin → API Call → Log Entry
                                              ↓
                                        [Log File]
                                              ↓
                                        Parse logs
                                              ↓
                                        Extract events (incomplete)
```

**Problems**:
- ❌ Log parsing is slow
- ❌ Incomplete event data (logs don't have full payload)
- ❌ Timing issues (logs written after event sent)
- ❌ Log rotation can lose events
- ❌ Format changes break parser

### New Approach (WordPress Hooks)
```
WordPress → Facebook Plugin → **OUR HOOK** → Capture complete event
                                    ↓              ↓
                              API Call        Store in DB
                                    ↓              ↓
                              Facebook      Retrieved by test
```

**Advantages**:
- ✅ Real-time capture
- ✅ Complete event payload
- ✅ Zero parsing overhead
- ✅ Reliable timing
- ✅ Format independent

## Execution Example

Let's trace a real test execution:

```javascript
// Test code
test('capture events', async ({ page }) => {
    // 1. Setup
    await monitor.installMonitorPlugin();
    // → Creates: wp-content/mu-plugins/facebook-event-monitor.php
    // → WordPress automatically loads this file on every request

    // 2. Start monitoring
    await monitor.startCapture('my-test');
    // → HTTP POST to /wp-json/facebook-monitor/v1/start
    // → WordPress sets: transient('facebook_event_monitor_test', 'my-test')
    // → Now the hook is ACTIVE and will capture events

    // 3. Perform actions
    await page.goto('http://site.local/product/123/');
    // → WooCommerce page loads
    // → Facebook plugin fires ViewContent event
    // → MU-plugin hook catches it
    // → Event stored in options table

    await page.click('.add_to_cart_button');
    // → WooCommerce AJAX processes
    // → Facebook plugin fires AddToCart event
    // → MU-plugin hook catches it
    // → Event stored in options table

    // 4. Stop and retrieve
    const results = await monitor.stopCapture();
    // → HTTP POST to /wp-json/facebook-monitor/v1/stop
    // → WordPress returns: { events: [...2 events...] }
    // → Saves to: captured-events/events-my-test-123.json

    // 5. Validate
    expect(results.events.length).toBe(2);
    // → Test passes! ✅
});
```

## Key Takeaways

1. **MU-Plugin is the core** - It hooks into WordPress at the right moment
2. **REST API for control** - Test tells WordPress when to start/stop
3. **WordPress options for storage** - Fast, temporary, no schema changes
4. **Complete event data** - We get the full payload, not parsed fragments
5. **Non-intrusive** - Events still go to Facebook normally

## What Happens Without a Test Running?

When NO test is active:

```php
$current_test = get_transient('facebook_event_monitor_test');
// Returns FALSE (no transient set)

if ($current_test) {
    // This block NEVER executes
    // Hook does nothing!
}
```

**Result**: Zero performance impact when not testing. The hook exists but does nothing.

## Summary

**The system works by:**
1. Installing a WordPress mu-plugin with hooks
2. Using REST API to control when to capture
3. Storing events in WordPress options during test
4. Retrieving complete events after test completes
5. Saving to JSON for analysis

**It's better because:**
- Real-time capture (not log parsing)
- Complete event data (full payloads)
- Zero performance impact (conditional hook)
- Platform extensible (can adapt to Shopify, etc.)
- Test-friendly (simple API for tests)
