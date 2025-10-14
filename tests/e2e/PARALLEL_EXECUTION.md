# 🚀 Parallel Execution & Business Manager Validation

## Will This Work in CI/CD with Multiple PRs?

### ✅ YES! Here's Why:

## Scenario 1: Same Machine, Multiple Test Workers

```bash
# Running 10 tests in parallel on your local machine
npx playwright test --workers=10
```

**How it handles collisions:**

Each test gets its own browser context with its own cookie:
- Test 1: `facebook_test_id=checkout-1696636800000`
- Test 2: `facebook_test_id=product-1696636801000`
- Test 3: `facebook_test_id=search-1696636802000`

Events are tagged in debug.log:
```
[FBTEST|checkout-1696636800000] CAPI|InitiateCheckout|event-id-abc|{...}
[FBTEST|product-1696636801000] CAPI|ViewContent|event-id-def|{...}
[FBTEST|checkout-1696636800000] CAPI|Purchase|event-id-ghi|{...}
```

Each test only reads lines with ITS test_id → **NO COLLISION**

## Scenario 2: Multiple PRs in CI/CD

```
PR #123 → CI Runner 1 → WordPress instance 1 → Events tagged with test-1-*
PR #456 → CI Runner 2 → WordPress instance 2 → Events tagged with test-2-*
PR #789 → CI Runner 3 → WordPress instance 3 → Events tagged with test-3-*
```

**Each CI runner has:**
- ✅ Separate VM/container
- ✅ Separate WordPress installation
- ✅ Separate debug.log file
- ✅ Separate database

**Result**: Complete isolation. **NO COLLISION POSSIBLE**

## Scenario 3: Business Manager Validation (The Tricky Part)

**Problem**: All tests send events to the SAME Facebook Pixel and Business Manager!

```
PR #123 → Sends events to Pixel ID 123456789
PR #456 → Sends events to Pixel ID 123456789 (SAME!)
PR #789 → Sends events to Pixel ID 123456789 (SAME!)
```

When we query Business Manager to verify events, we might get events from other PRs!

**Solution**: Use `event_id` for matching

### How Event IDs Work

Every Facebook event has a unique `event_id`:

```json
{
  "event_name": "ViewContent",
  "event_id": "test-1-1696636800000-abc123",  // ← UNIQUE identifier
  "event_time": 1696636800,
  "user_data": {...}
}
```

The Facebook plugin ALREADY generates unique event IDs. Our modification captures them:

```php
error_log( sprintf(
    '[FBTEST|%s] CAPI|%s|%s|%s',
    $test_id,
    $event->get_event_name(),
    $event->get_event_id(),  // ← We log this!
    json_encode( $event->as_array() )
) );
```

### Business Manager Validation Strategy

```javascript
class BusinessManagerValidator {
    async validateEvents(testId, capturedEvents) {
        // 1. Get the event IDs from our captured events
        const ourEventIds = capturedEvents.map(e => e.eventId);
        
        // 2. Query Business Manager for events in our time window
        const bmEvents = await this.fetchFromBusinessManager({
            pixel_id: PIXEL_ID,
            start_time: testStartTime,
            end_time: testEndTime
        });
        
        // 3. Filter BM events to ONLY our event IDs
        const ourBMEvents = bmEvents.filter(bme => 
            ourEventIds.includes(bme.event_id)
        );
        
        // 4. Match captured events with BM events
        const matches = this.matchEvents(capturedEvents, ourBMEvents);
        
        return {
            captured: capturedEvents.length,
            foundInBM: ourBMEvents.length,
            matched: matches.length,
            success: matches.length === capturedEvents.length
        };
    }
}
```

## Complete Test Flow with BM Validation

```javascript
const { test, expect } = require('@playwright/test');

test('validate events end-to-end including Business Manager', async ({ page }) => {
    const monitor = new SimpleFacebookMonitor();
    const bmValidator = new BusinessManagerValidator();
    
    // 1. Start monitoring
    await monitor.startCapture(page, 'bm-validation-test');
    
    // 2. Perform actions
    await page.goto('http://site.local/product/123');
    await page.click('.add_to_cart');
    await page.waitForTimeout(3000);
    
    // 3. Stop and get captured events (with event IDs!)
    const results = await monitor.stopCapture(page);
    
    console.log('Captured Events:');
    results.events.capi.forEach(e => {
        console.log(`  - ${e.eventName} (ID: ${e.eventId})`);
    });
    
    // 4. Wait for events to appear in Business Manager
    console.log('Waiting for Business Manager to process events...');
    await new Promise(resolve => setTimeout(resolve, 120000)); // 2 minutes
    
    // 5. Validate against Business Manager
    const bmValidation = await bmValidator.validateEvents(
        results.testId,
        results.events.capi
    );
    
    console.log('\n📊 Business Manager Validation:');
    console.log(`   Captured: ${bmValidation.captured}`);
    console.log(`   Found in BM: ${bmValidation.foundInBM}`);
    console.log(`   Matched: ${bmValidation.matched}`);
    console.log(`   Success: ${bmValidation.success ? '✅' : '❌'}`);
    
    // Assert validation passed
    expect(bmValidation.success).toBe(true);
});
```

## Modified Monitor to Capture Event IDs

```javascript
class SimpleFacebookMonitor {
    parseDebugLog() {
        // Parse lines like: [FBTEST|test-1-123] CAPI|ViewContent|event-id-abc|{...}
        return newContent
            .split('\n')
            .filter(line => line.includes(`[FBTEST|${this.testId}]`))
            .map(line => {
                const parts = line.split('|');
                if (parts.length >= 5) {
                    return {
                        type: 'capi',
                        eventName: parts[2],
                        eventId: parts[3],        // ← CAPTURED!
                        eventData: JSON.parse(parts[4]),
                        testId: this.testId,
                        timestamp: Date.now()
                    };
                }
                return null;
            })
            .filter(Boolean);
    }
}
```

## Business Manager API Query

```javascript
class BusinessManagerValidator {
    async fetchFromBusinessManager(params) {
        const url = `https://graph.facebook.com/v18.0/${params.pixel_id}/events`;
        
        const response = await axios.get(url, {
            params: {
                access_token: this.accessToken,
                start_time: Math.floor(params.start_time / 1000),
                end_time: Math.floor(params.end_time / 1000),
                limit: 100
            }
        });
        
        return response.data.data || [];
    }
    
    matchEvents(capturedEvents, bmEvents) {
        return capturedEvents.map(captured => {
            const bmMatch = bmEvents.find(bm => 
                bm.event_id === captured.eventId &&
                bm.event_name === captured.eventName
            );
            
            return {
                captured,
                bmEvent: bmMatch,
                matched: !!bmMatch,
                confidence: bmMatch ? 1.0 : 0.0
            };
        });
    }
}
```

## Collision Matrix

| Scenario | Test Isolation | Event Capture | BM Validation | Collision Risk |
|----------|---------------|---------------|---------------|----------------|
| **Local parallel tests** | Cookie-based | Unique test_id in log | event_id matching | ✅ None |
| **CI/CD same PR** | Browser contexts | Per-worker isolation | event_id matching | ✅ None |
| **CI/CD multiple PRs** | Separate runners | Separate environments | event_id matching | ✅ None |
| **BM without event_id** | N/A | N/A | All events mixed | ❌ HIGH COLLISION |
| **BM with event_id** | N/A | N/A | Unique IDs filter | ✅ No collision |

## Why This Works

### 1. Local/CI Isolation
- Each test has unique `test_id` cookie
- Events tagged in debug.log
- Each test only reads its own lines

### 2. Cross-PR Isolation
- Different CI runners = different environments
- Can't access each other's logs
- Complete physical separation

### 3. Business Manager Isolation
- Each event has unique `event_id`
- We track our event IDs
- Query BM and filter by our event IDs only
- Other PRs' events are ignored

## Configuration for CI/CD

```yaml
# .github/workflows/e2e-tests.yml
name: E2E Tests with Facebook Event Validation

on: [pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        shard: [1, 2, 3, 4]  # Run 4 parallel shards
    
    steps:
      - name: Checkout
        uses: actions/checkout@v3
      
      - name: Setup WordPress
        run: |
          # Each job gets its own WordPress instance
          docker-compose up -d
      
      - name: Run E2E Tests
        env:
          FACEBOOK_PIXEL_ID: ${{ secrets.FACEBOOK_PIXEL_ID }}
          FACEBOOK_ACCESS_TOKEN: ${{ secrets.FACEBOOK_ACCESS_TOKEN }}
        run: |
          npx playwright test --shard=${{ matrix.shard }}/4
      
      - name: Upload Results
        uses: actions/upload-artifact@v3
        with:
          name: test-results-${{ matrix.shard }}
          path: captured-events/
```

## Final Answer

✅ **YES**, this works with:
- ✅ Multiple local test workers
- ✅ Multiple parallel PRs in CI/CD
- ✅ Business Manager validation across PRs

**Key**: 
1. Cookie-based test isolation for local capture
2. Event ID tracking for Business Manager matching
3. Each test only validates its own events

**No collisions possible** when using event_id matching!