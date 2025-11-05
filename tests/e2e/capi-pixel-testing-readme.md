# E2E Testing Framework - Facebook Pixel & CAPI Validation

## 📖 Overview

This is a comprehensive end-to-end testing framework that validates Facebook Pixel and Conversion API (CAPI) events for the WooCommerce Facebook plugin. It ensures both client-side (Pixel) and server-side (CAPI) events are fired correctly, contain the right data, and match for proper event deduplication.

---

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                          E2E Test Flow                                    │
└─────────────────────────────────────────────────────────────────────────┘

1. Test Setup (TestSetup.js)
   ├─ Generate unique test ID (e.g., "pageview-1762240201866")
   ├─ Login user to WordPress
   ├─ Set test cookies:
   │  ├─ facebook_test_id (for event capture)
   │  └─ facebook_test_error_capture (for PHP error tracking)
   └─ Start PixelCapture (intercept browser events)

2. User Action Simulation (test.spec.js)
   ├─ Navigate to page (e.g., product page, category page)
   ├─ Interact with elements (e.g., add to cart, checkout)
   └─ Wait for events to fire

3. Event Capture (Parallel)
   ├─ CLIENT SIDE (PixelCapture.js)
   │  ├─ Intercept fbq() calls in browser
   │  ├─ Extract event data from Pixel requests
   │  └─ Log to Logger.php → JSON file
   │
   └─ SERVER SIDE (API.php)
      ├─ Plugin sends CAPI event to Facebook
      ├─ Before sending, log event data
      └─ Log to Logger.php → JSON file

4. Validation (EventValidator.js)
   ├─ Load JSON file for test ID
   ├─ Filter events by type (PageView, ViewContent, etc.)
   ├─ Run schema validations:
   │  ├─ Required fields present
   │  ├─ Custom data fields present
   │  ├─ Event IDs match (deduplication)
   │  └─ Custom validators (values match, timestamps, etc.)
   └─ Return pass/fail + detailed errors

5. Test Result
   └─ Playwright assertion: expect(result.passed).toBe(true)
```

---

## 📂 File Structure

```
tests/e2e/
├── test.spec.js                  # Main test file
├── config/
│   └── test-config.js            # Configuration (URLs, credentials)
├── lib/
│   ├── TestSetup.js             # Test initialization & utilities
│   ├── PixelCapture.js          # Client-side event capture
│   ├── Logger.php               # Event logging to JSON
│   ├── ErrorCapture.php         # PHP error capture
│   ├── EventValidator.js        # Event validation engine
│   └── event-schemas.js         # Event schema definitions
└── captured-events/
    ├── pageview-123.json        # Captured events for each test
    ├── viewcontent-456.json
    └── ...
```

---

## 🎯 Event Schemas & Validation

### Schema Structure

Each event type has a schema that defines:

1. **Required Fields** - Top-level fields that MUST exist
2. **Custom Data Fields** - Fields that MUST exist in `custom_data`
3. **Validators** - Custom validation functions for advanced checks

### Example: ViewContent Schema

```javascript
ViewContent: {
    required: {
        pixel: ['eventName', 'eventId', 'pixelId', 'timestamp', 'custom_data'],
        capi: ['event_name', 'event_id', 'event_time', 'action_source', 'user_data', 'custom_data']
    },
    custom_data: ['content_ids', 'content_type', 'content_name', 'value', 'currency'],
    validators: {
        // Timestamps within 30 seconds
        timestamp: (pixel, capi) => {
            const diff = Math.abs(pixel.timestamp - capi.event_time * 1000);
            return diff < 30000;
        },

        // Content IDs match exactly
        contentIds: (pixel, capi) => {
            return JSON.stringify(pixel.custom_data?.content_ids) ===
                   JSON.stringify(capi.custom_data?.content_ids);
        },

        // Values match (with floating point tolerance)
        value: (pixel, capi) => {
            return Math.abs(pixel.custom_data?.value - capi.custom_data?.value) < 0.01;
        },

        // Currency codes match
        currency: (pixel, capi) => {
            return pixel.custom_data?.currency === capi.custom_data?.currency;
        },

        // FBP cookie matches browser_id
        fbp: (pixel, capi) => {
            return pixel.user_data?.fbp === capi.user_data?.browser_id;
        }
    }
}
```

---

## ✅ Validation Checklist

Our framework validates ALL of these requirements:

| Requirement | Status | Description |
|------------|--------|-------------|
| ✅ **Event Triggered** | **DONE** | Correct event fires for user action |
| ✅ **Pixel Event Exists** | **DONE** | Client-side event captured |
| ✅ **CAPI Event Exists** | **DONE** | Server-side event sent |
| ✅ **Event Count Match** | **DONE** | Same number of Pixel & CAPI events |
| ✅ **Required Fields** | **DONE** | All top-level fields present |
| ✅ **Custom Data Fields** | **DONE** | All custom_data fields present (per schema) |
| ✅ **Event ID Matching** | **DONE** | `pixel.eventId === capi.event_id` (deduplication) |
| ✅ **Timestamp Sync** | **DONE** | Events within 30 seconds |
| ✅ **Value Matching** | **DONE** | Numeric values match (±0.01) |
| ✅ **Currency Matching** | **DONE** | Currency codes match |
| ✅ **Content IDs Matching** | **DONE** | Arrays contain same product IDs |
| ✅ **FBP/Browser ID** | **DONE** | Cookie matches user_data.browser_id |
| ✅ **Currency Format** | **DONE** | Purchase: must be 3-letter code (USD, EUR) |
| ✅ **Value > 0** | **DONE** | Purchase: value must be positive |
| ✅ **PHP Errors** | **DONE** | No errors/warnings during execution |

---

## 🧪 Supported Events

| Event Type | Schema | Custom Data Fields |
|-----------|--------|-------------------|
| **PageView** | ✅ | None |
| **ViewContent** | ✅ | content_ids, content_type, content_name, value, currency |
| **AddToCart** | ✅ | content_ids, content_type, content_name, value, currency |
| **InitiateCheckout** | ✅ | content_ids, content_type, num_items, value, currency |
| **Purchase** | ✅ | content_ids, content_type, value, currency |
| **ViewCategory** | ✅ | content_name, content_category |

*Based on: [Google Sheets - Event Parameters](https://docs.google.com/spreadsheets/d/1fQvDwgHgq2jz1M_zfvKzW4PR8c_OgmsIJbGrRk1zMjY)*

---

## 🔧 How It Works

### 1. Event Capture (Logger.php)

Logger.php provides a centralized logging mechanism that both Pixel and CAPI events use:

```php
// Can be called directly (not via HTTP)
require_once 'Logger.php';
E2E_Event_Logger::log_event($test_id, 'pixel', $event_data);
E2E_Event_Logger::log_event($test_id, 'capi', $event_data);
```

**Thread-Safe:** Uses `flock()` for file locking to prevent race conditions.

**Output Format:**
```json
{
  "testId": "pageview-123",
  "timestamp": 1762240201866,
  "pixel": [
    {
      "eventName": "PageView",
      "eventId": "event123",
      "pixelId": "1234567890",
      "timestamp": 1762240201866,
      "custom_data": {},
      "user_data": { "fbp": "fb.1.123.456" }
    }
  ],
  "capi": [
    {
      "event_name": "PageView",
      "event_id": "event123",
      "event_time": 1762240202,
      "action_source": "website",
      "custom_data": {},
      "user_data": { "browser_id": "fb.1.123.456" }
    }
  ],
  "errors": []
}
```

### 2. Client-Side Capture (PixelCapture.js)

Intercepts `fbq()` calls in the browser:

```javascript
// Inject capture script into page
await page.addInitScript(() => {
    window.capturedEvents = [];
    const original = window.fbq;
    window.fbq = function() {
        if (arguments[0] === 'track') {
            window.capturedEvents.push({
                eventName: arguments[1],
                customData: arguments[2]
            });
        }
        original.apply(this, arguments);
    };
});

// After action, extract events and log
const events = await page.evaluate(() => window.capturedEvents);
```

### 3. Server-Side Capture (API.php)

Before sending to Facebook, log the event:

```php
public function send_pixel_events( $pixel_id, array $events ) {
    $request = new API\Pixel\Events\Request( $pixel_id, $events );
    $response = $this->perform_request( $request );

    // Log to E2E test framework if successful
    if ( $response && ! $response->has_api_error() ) {
        foreach ( $events as $event ) {
            $this->log_event_for_tests( $event );
        }
    }

    return $response;
}
```

### 4. Error Capture (ErrorCapture.php)

Captures PHP errors during test execution:

```php
class E2E_Error_Capture {
    public static function start($test_id) {
        // Set custom error handler
        set_error_handler(array(__CLASS__, 'error_handler'));
        register_shutdown_function(array(__CLASS__, 'shutdown_handler'));
    }

    public static function error_handler($errno, $errstr, $errfile, $errline) {
        // Only capture Facebook plugin errors
        if (strpos($errfile, 'facebook-for-woocommerce') !== false) {
            self::$errors[] = array(
                'type' => $type,
                'message' => $errstr,
                'file' => $errfile,
                'line' => $errline
            );
        }
    }
}
```

### 5. Validation (EventValidator.js)

```javascript
const result = await validator.validate('ViewContent');

// result = {
//   passed: true/false,
//   errors: [...],
//   pixel: {...},
//   capi: {...},
//   phpErrors: [...]
// }
```

**Validation Steps:**

1. ✅ Check for PHP errors
2. ✅ Check event exists (Pixel & CAPI)
3. ✅ Check event count matches
4. ✅ Check required top-level fields
5. ✅ Check custom_data fields
6. ✅ Check event ID matching
7. ✅ Run custom validators:
   - Timestamp sync
   - Value matching
   - Currency matching
   - Content IDs matching
   - FBP/browser_id matching

---

## 🚀 Running Tests

```bash
# Install dependencies
npm install

# Run all E2E tests
npm run test:e2e

# Run specific test
npx playwright test tests/e2e/test.spec.js

# Run with UI (debugging)
npx playwright test --ui

# Run headed (see browser)
npx playwright test --headed
```

---

## 📝 Writing New Tests

### Step 1: Add Event Schema

```javascript
// lib/event-schemas.js
module.exports = {
    MyNewEvent: {
        required: {
            pixel: ['eventName', 'eventId', 'pixelId', 'timestamp', 'custom_data'],
            capi: ['event_name', 'event_id', 'event_time', 'action_source', 'custom_data']
        },
        custom_data: ['field1', 'field2'],
        validators: {
            field1: (pixel, capi) => {
                return pixel.custom_data?.field1 === capi.custom_data?.field1;
            }
        }
    }
};
```

### Step 2: Write Test

```javascript
// test.spec.js
test('MyNewEvent', async ({ page }) => {
    const { testId } = await TestSetup.init(page, 'mynewevent');

    // Simulate user action
    await page.goto('/some-page/');
    await page.click('.some-button');
    await TestSetup.wait();

    // Validate
    const validator = new EventValidator(testId);
    const result = await validator.validate('MyNewEvent');

    console.log('Result:', JSON.stringify(result, null, 2));
    expect(result.passed).toBe(true);
});
```

---

## 🐛 Debugging

### View Captured Events

```bash
# View raw JSON
cat tests/e2e/captured-events/pageview-123.json | jq
```

### Enable Debug Logging

```javascript
// In test.spec.js
const result = await validator.validate('PageView');
console.log('Full Result:', JSON.stringify(result, null, 2));
```

### Check for PHP Errors

```bash
# WordPress debug log
tail -f /path/to/wp-content/debug.log | grep "FB-"
```

### Common Issues

**No CAPI events captured:**
- Check if test cookie is set: `facebook_test_id`
- Check if Logger.php file exists and is writable
- Check API.php logs CAPI events after successful send

**No Pixel events captured:**
- Check if PixelCapture is started before navigation
- Check browser console for fbq errors
- Verify Pixel ID is configured

**Validation fails:**
- Check `result.errors` array for specific failures
- Compare `result.pixel` vs `result.capi` data
- Verify schema matches actual plugin implementation

---

## 🎓 Key Concepts

### Event Deduplication

Facebook uses `event_id` to deduplicate Pixel and CAPI events. If both have the same `event_id`, Facebook counts it as ONE event (not two). This prevents double-counting.

```javascript
// Same event_id = deduplicated
pixel:  { eventId: "event123" }
capi:   { event_id: "event123" }  ✅ Deduplicated!

// Different event_id = counted twice
pixel:  { eventId: "event123" }
capi:   { event_id: "event456" }  ❌ Double-counted!
```

### FBP Cookie & Browser ID

The `_fbp` cookie (first-party Facebook Pixel cookie) should match `user_data.browser_id` in CAPI events. This helps Facebook match users across Pixel and CAPI.

```javascript
pixel:  { user_data: { fbp: "fb.1.123.456" } }
capi:   { user_data: { browser_id: "fb.1.123.456" } }  ✅ Match!
```

### Timestamp Tolerance

Pixel and CAPI events won't have EXACT same timestamps (network latency, processing time). We allow 30 seconds tolerance.

```javascript
pixel:  { timestamp: 1762240201000 }  // 1:00:01
capi:   { event_time: 1762240202 }    // 1:00:02  ✅ Within 30s
```

---

## 📊 Test Output

```
Running 4 tests using 1 worker

  ✓ PageView (3.2s)
  ✓ ViewContent (2.8s)
  ✓ AddToCart (3.5s)
  ✓ ViewCategory (2.9s)

  4 passed (12.4s)
```

**With Failures:**

```
  ✗ ViewContent (3.1s)

  Errors:
  - Pixel custom_data missing: content_name
  - CAPI custom_data missing: content_name
  - Validator failed: value
```

---

## 🔮 Future Enhancements

### TODO:
- [ ] Add more events: Search, AddPaymentInfo, Lead
- [ ] Test error scenarios (API failures, missing fields)
- [ ] Performance testing (measure event timing)
- [ ] Visual regression testing (Pixel loading)
- [ ] CI/CD integration (GitHub Actions)
- [ ] Parallel test execution (multiple browsers)
- [ ] Test data factory (create products on-demand)
- [ ] Advanced Matching validation (email, phone hashing)

---

## 📚 References

- [Facebook Pixel Documentation](https://developers.facebook.com/docs/meta-pixel)
- [Conversion API Documentation](https://developers.facebook.com/docs/marketing-api/conversions-api)
- [Event Deduplication Guide](https://developers.facebook.com/docs/marketing-api/conversions-api/deduplicate-pixel-and-server-events)
- [Playwright Documentation](https://playwright.dev)
- [Event Parameters Spreadsheet](https://docs.google.com/spreadsheets/d/1fQvDwgHgq2jz1M_zfvKzW4PR8c_OgmsIJbGrRk1zMjY)

---

## 🤝 Contributing

When adding new validations:

1. Update the schema in `/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public/wp-content/plugins/facebook-for-woocommerce/tests/e2e/lib/event-schemas.js`
2. Add validator function if needed
3. Update this README
4. Run tests to ensure they pass

---

## 📄 License

This framework is part of the Facebook for WooCommerce plugin.

---

**Last Updated:** November 4, 2025
**Version:** 1.0.0
**Author:** Madhav N
