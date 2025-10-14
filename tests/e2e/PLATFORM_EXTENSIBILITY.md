# 🌍 Platform Extensibility Architecture

## Overview

The event monitoring system is designed to be **platform-agnostic**. The core monitoring logic works across any e-commerce platform - you just need the right adapter.

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Your E2E Tests                          │
│                  (Platform Independent)                     │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ↓
┌─────────────────────────────────────────────────────────────┐
│                  EventMonitor (Core)                        │
│  • Event validation logic                                   │
│  • Event storage                                            │
│  • Business Manager comparison                              │
│  • Test orchestration                                       │
└────────────────────────┬────────────────────────────────────┘
                         │
        ┌────────────────┼────────────────┐
        ↓                ↓                ↓
┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│  WooCommerce │  │   Shopify    │  │   Magento    │
│   Adapter    │  │   Adapter    │  │   Adapter    │
└──────────────┘  └──────────────┘  └──────────────┘
        │                │                │
        ↓                ↓                ↓
┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│  WordPress   │  │   Shopify    │  │   Magento    │
│    Hooks     │  │  App Proxy   │  │  Observers   │
└──────────────┘  └──────────────┘  └──────────────┘
```

## Platform Adapter Interface

Every platform adapter must implement:

```javascript
class PlatformAdapter {
    // Install monitoring hooks/plugins for the platform
    async install() {}

    // Start capturing events for a test
    async startCapture(testName) {}

    // Stop capturing and retrieve events
    async stopCapture() {}

    // Get platform-specific configuration
    getConfig() {}

    // Clean up monitoring setup
    async uninstall() {}
}
```

## Platform-Specific Implementations

### 1. WooCommerce (WordPress)

**Hook Method**: WordPress action/filter hooks

```php
// mu-plugin intercepts at WordPress level
add_filter('wc_facebook_pixel_event_sent', function($event) {
    // Capture CAPI event
    store_event($event);
    return $event;
}, 10, 2);
```

**Advantages**:
- Direct access to WordPress hooks
- Server-side event capture
- No performance impact on store

### 2. Shopify

**Hook Method**: App Proxy + Webhook Listeners

```javascript
// Shopify App Proxy endpoint
app.post('/apps/event-monitor/capture', async (req, res) => {
    const { event_name, event_data } = req.body;

    // Store event for active test
    await captureShopifyEvent({
        type: 'capi',
        event_name,
        event_data,
        timestamp: Date.now()
    });

    res.json({ success: true });
});

// Theme App Extension (for Pixel events)
// Injected into theme.liquid
<script>
if (window.fbq) {
    const originalFbq = window.fbq;
    window.fbq = function(...args) {
        // Send to our monitoring endpoint
        fetch('/apps/event-monitor/capture', {
            method: 'POST',
            body: JSON.stringify({
                type: 'pixel',
                args: args,
                timestamp: Date.now()
            })
        });

        return originalFbq.apply(this, args);
    };
}
</script>
```

**Advantages**:
- Works with Shopify's app ecosystem
- Can use webhooks for order events
- Theme app extensions for client-side capture

### 3. Magento

**Hook Method**: Event Observers + Plugins (Interceptors)

```php
// Observer for Facebook events
class FacebookEventObserver implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        $event = $observer->getEvent();
        $eventName = $event->getName();
        $eventData = $event->getData();

        // Store event if monitoring is active
        $this->eventMonitor->captureEvent([
            'type' => 'capi',
            'event_name' => $eventName,
            'event_data' => $eventData,
            'timestamp' => microtime(true) * 1000
        ]);
    }
}
```

## Unified Test Interface

**Same test code works across all platforms:**

```javascript
// tests/facebook-events.spec.js
const { test } = require('@playwright/test');
const EventMonitorFactory = require('./event-monitor-factory');

test.describe('Facebook Events - Platform Agnostic', () => {
    let monitor;

    test.beforeAll(async () => {
        // Automatically detects platform and creates appropriate adapter
        monitor = await EventMonitorFactory.create({
            platform: 'auto-detect', // or 'woocommerce', 'shopify', 'magento'
            siteUrl: process.env.SITE_URL
        });

        await monitor.install();
    });

    test('should capture events during product purchase', async ({ page }) => {
        await monitor.startCapture('product-purchase-test');

        // Same test code for all platforms!
        await page.goto(process.env.SITE_URL);
        await page.click('[data-test="product-link"]');
        await page.click('[data-test="add-to-cart"]');

        const results = await monitor.stopCapture();

        // Validation works the same regardless of platform
        expect(results.summary.totalEvents).toBeGreaterThan(0);
    });
});
```

## Implementation Roadmap

### ✅ Phase 1: WooCommerce (Current)
- WordPress mu-plugin hooks
- Direct CAPI capture
- Browser pixel monitoring
- Status: **COMPLETE**

### 📋 Phase 2: Shopify (Next)
**Timeline**: 2-3 weeks

**Components Needed**:
1. **Shopify App** for event capture
2. **App Proxy** endpoint for event storage
3. **Theme Extension** for pixel capture
4. **Webhook listeners** for order events

**Code Structure**:
```
adapters/
├── shopify-adapter.js
├── shopify-app/
│   ├── server.js          # Express app with App Proxy
│   ├── webhooks.js        # Order/cart webhook handlers
│   └── theme-extension/   # Liquid templates for pixel capture
```

### 📋 Phase 3: Magento (Future)
**Timeline**: 3-4 weeks

**Components Needed**:
1. **Magento Module** with event observers
2. **Plugin/Interceptor** for Facebook extension
3. **REST API** for event retrieval
4. **Admin panel** for test management

## Key Design Decisions

### 1. **Why Platform Adapters?**
Each e-commerce platform has unique architecture:
- **WordPress**: Hook-based, PHP-centric
- **Shopify**: API-first, Liquid templates
- **Magento**: Module-based, complex event system

Adapters isolate these differences.

### 2. **What Stays Platform-Agnostic?**
- Event validation logic
- Business Manager comparison
- Test orchestration
- Result reporting
- Storage format

### 3. **What's Platform-Specific?**
- Event capture mechanism (hooks vs APIs vs webhooks)
- Installation/setup process
- Configuration requirements
- Performance optimization

## Example: Adding a New Platform

Let's add **BigCommerce** support:

### Step 1: Create Adapter

```javascript
// adapters/bigcommerce-adapter.js
const PlatformAdapter = require('./platform-adapter');

class BigCommerceAdapter extends PlatformAdapter {
    async install() {
        // Install BigCommerce app via API
        // Set up webhook subscriptions
    }

    async startCapture(testName) {
        // Set active test in Redis/database
        await this.api.post('/event-monitor/start', { testName });
    }

    async stopCapture() {
        // Retrieve captured events
        const response = await this.api.get('/event-monitor/events');
        return response.data.events;
    }
}
```

### Step 2: Register in Factory

```javascript
// event-monitor-factory.js
class EventMonitorFactory {
    static async create(config) {
        const platform = config.platform || await this.detectPlatform(config.siteUrl);

        switch(platform) {
            case 'woocommerce':
                return new WooCommerceAdapter(config);
            case 'shopify':
                return new ShopifyAdapter(config);
            case 'magento':
                return new MagentoAdapter(config);
            case 'bigcommerce':
                return new BigCommerceAdapter(config); // NEW
            default:
                throw new Error(`Unsupported platform: ${platform}`);
        }
    }
}
```

### Step 3: Tests Work Automatically

No test code changes needed! Same tests work across all platforms.

## Configuration Examples

### WooCommerce
```javascript
{
    platform: 'woocommerce',
    siteUrl: 'http://wooc-local-test-sitecom.local',
    wordpressPath: '/path/to/wordpress',
    hookMethod: 'mu-plugin'
}
```

### Shopify
```javascript
{
    platform: 'shopify',
    siteUrl: 'https://mystore.myshopify.com',
    shopifyApiKey: 'xxx',
    shopifyPassword: 'xxx',
    hookMethod: 'app-proxy'
}
```

### Magento
```javascript
{
    platform: 'magento',
    siteUrl: 'https://magento.local',
    magentoPath: '/path/to/magento',
    hookMethod: 'observer'
}
```

## Benefits of This Architecture

✅ **Write Once, Test Everywhere**: Same test code for all platforms
✅ **Easy Extension**: Add new platforms without changing core
✅ **Maintainable**: Platform-specific code is isolated
✅ **Scalable**: Can support unlimited platforms
✅ **Testable**: Each adapter can be tested independently

## Current Status

| Platform | Status | Hook Method | Effort |
|----------|--------|-------------|--------|
| WooCommerce | ✅ Complete | WordPress mu-plugin | Done |
| Shopify | 📋 Planned | App Proxy + Theme Extension | 2-3 weeks |
| Magento | 📋 Planned | Event Observers | 3-4 weeks |
| BigCommerce | 💭 Future | Webhook Listeners | 3-4 weeks |
| Custom | 💭 Future | Adapter Interface | Varies |

## Getting Started with a New Platform

1. **Study the platform's event system**
   - How does it trigger Facebook events?
   - What hooks/APIs are available?
   - Can you intercept before/after event sending?

2. **Create an adapter class**
   - Implement the `PlatformAdapter` interface
   - Handle platform-specific installation
   - Capture events using platform mechanisms

3. **Test the adapter**
   - Run existing test suite
   - Verify event capture
   - Validate against Business Manager

4. **Submit PR**
   - Add adapter to `adapters/` directory
   - Update factory to support new platform
   - Document platform-specific setup

## Conclusion

This architecture allows your Facebook event monitoring to work across **any e-commerce platform**. The core logic is shared, only the "how we capture events" changes per platform.

**Current**: Works perfectly with WooCommerce
**Next**: Extend to Shopify, Magento, and beyond
**Future**: Support any platform with a Facebook integration
