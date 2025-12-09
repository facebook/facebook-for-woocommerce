# Expected Data Validation - Smart & Simple

## 🎯 Philosophy

**Fetch real data. Exclude truly dynamic fields. Simple comparison.**

## 📁 Single File Solution

```
lib/ExpectedDataBuilder.js   # ~400 lines - everything you need
```

## 🔧 Core Features

### ✅ Fetches REAL Data
- **Product info** (name, price, category) via WP-CLI
- **Versions** (WooCommerce, plugin) from actual sources
- **Customer data** (known test customer, pre-hashed)

### ✅ Smart Exclusions
- Only excludes **truly dynamic** fields (fbp, event_id, timestamps, IP addresses)
- Everything else is **validated against real values**

### ✅ Simple Comparison
- Built-in `compareObjects()` - just exclude dynamic fields
- No complex null handling, no "validate existence" logic
- **Direct equality check** on all other fields

## 💡 Usage

```javascript
const ExpectedDataBuilder = require('./lib/ExpectedDataBuilder');

// Create builder
const builder = new ExpectedDataBuilder();

// Get expected event data (fetches real product info, versions)
const expected = await builder.getExpectedEvent('ViewContent', 'pixel', {
    productId: 13
});

// Get fields to exclude (dynamic fields only)
const excludeFields = ExpectedDataBuilder.getExcludedFields('ViewContent', 'pixel');

// Simple comparison
const result = ExpectedDataBuilder.compareObjects(
    expected,
    capturedEvent,
    excludeFields
);

if (!result.matches) {
    console.log('❌ Validation failed:');
    result.differences.forEach(diff => console.log(`   ${diff}`));
} else {
    console.log('✅ Event matches expected!');
}
```

## 🏗️ What Gets Validated

### ✅ Always Validated (Real Values)
- **User Data**: All hashed fields (em, external_id, ct, zp, st, ph, country/cn)
- **Product Data**: content_name, content_ids, value, currency
- **Metadata**: source, version, pluginVersion
- **Event Structure**: All field names, types, array lengths

### ⏭️ Excluded (Dynamic, Can't Know)
- `fbp` - Browser-generated, dynamic
- `fbc` - Click ID from ads, dynamic
- `event_id` - UUID, generated per event
- `event_time` - Timestamp, changes each run
- `capturedAt` - Test capture time
- `client_ip_address` - CI environment IP
- `client_user_agent` - Browser UA string
- `action_source` - CAPI only
- `event_source_url` - Full URL with query params
- `api_status`, `api_ok` - HTTP response (N/A for sendBeacon)
- `order_id` - Purchase event only, dynamic

## 📊 How It Works

### 1. Fetch Real Product Data
```javascript
async getProduct(productId) {
    // WP-CLI: Get product name
    const name = execSync(`wp post get ${productId} --field=post_title`);

    // WP-CLI: Get product price
    const price = execSync(`wp post meta get ${productId} _price`);

    return { id: productId, name, price, category: 'Uncategorized' };
}
```

### 2. Extract Real Versions
```javascript
async getVersions() {
    // WP-CLI: Get WooCommerce version
    const wcVersion = execSync(`wp plugin get woocommerce --field=version`);

    // File read: Get plugin version from main PHP file
    const pluginContent = fs.readFileSync('facebook-for-woocommerce.php');
    const pluginVersion = pluginContent.match(/Version:\s*(.+)/)[1];

    return {
        source: 'woocommerce_0',
        version: wcVersion,
        pluginVersion: pluginVersion
    };
}
```

### 3. Build Expected Structure
```javascript
async getCustomData(eventType, source, options) {
    const versions = await this.getVersions();

    switch (eventType) {
        case 'ViewContent':
            const product = await this.getProduct(options.productId);
            return {
                ...versions,  // Real versions
                content_name: product.name,  // Real name
                value: parseFloat(product.price),  // Real price
                content_ids: [`wc_post_id_${product.id}`],
                currency: 'USD',
                // ...
            };
    }
}
```

### 4. Simple Comparison
```javascript
static compareObjects(expected, actual, excludeFields) {
    // Skip excluded fields (fbp, event_id, etc.)
    // Compare everything else directly
    // Return { matches: boolean, differences: Array }
}
```

## 🎨 Event-Specific Behavior

### PageView
```javascript
// Pixel: Versions + unhashed user_data copy
// CAPI: Empty custom_data
```

### ViewContent / AddToCart
```javascript
// Fetches: product name, price from productId
// Validates: Exact product name, exact price
```

### ViewCategory / Search
```javascript
// Fetches: ALL products in catalog
// Validates: All product names, all prices, total value
```

### InitiateCheckout
```javascript
// Fetches: Product data + cart info
// Validates: Product details, num_items
```

### Purchase
```javascript
// CAPI only
// Fetches: Product data
// Excludes: order_id (dynamic)
```

### Lead
```javascript
// Pixel only
// User Data: Only em
// Custom Data: Only versions
```

## 🔑 Key Advantages

### 1. **Real Data Validation**
```javascript
// ❌ Old way: content_name: null  (skip validation)
// ✅ New way: content_name: "TestP"  (validate exact match)
```

### 2. **Minimal Exclusions**
```javascript
// Only exclude what truly can't be known:
['fbp', 'fbc', 'event_id', 'event_time', 'capturedAt',
 'client_ip_address', 'client_user_agent', 'action_source',
 'event_source_url', 'api_status', 'api_ok']

// Purchase adds: ['order_id']
```

### 3. **Simple Comparison**
```javascript
// Just compare objects, skip excluded fields
// No complex null handling, no "validate existence" logic
if (expected !== actual && !isExcluded(field)) {
    differences.push(`${field}: expected ${expected} but got ${actual}`);
}
```

### 4. **Comprehensive Coverage**
- ✅ Validates ALL product data against real values
- ✅ Validates versions from actual source files
- ✅ Validates all user data (hashed correctly)
- ✅ Validates structure, types, array lengths

## 📚 Complete Example

```javascript
const ExpectedDataBuilder = require('./lib/ExpectedDataBuilder');

// Test ViewContent event
const builder = new ExpectedDataBuilder();

// Fetch expected data (real product info, versions)
const expected = await builder.getExpectedEvent('ViewContent', 'pixel', {
    productId: 13
});

// Expected now has:
// {
//   user_data: { em: "hash...", external_id: "hash...", ct: "hash...", zp: "hash...", cn: "hash..." },
//   custom_data: {
//     source: "woocommerce_0",
//     version: "10.3.5",  // Real WC version
//     pluginVersion: "3.5.14",  // Real plugin version
//     content_name: "TestP",  // Real product name
//     content_ids: ["wc_post_id_13"],
//     value: 19.99,  // Real product price
//     currency: "USD",
//     // ...
//   }
// }

// Get fields to exclude
const excludeFields = ExpectedDataBuilder.getExcludedFields('ViewContent', 'pixel');
// ['fbp', 'fbc', 'event_id', 'event_time', 'capturedAt', 'api_status', 'api_ok']

// Compare
const result = ExpectedDataBuilder.compareObjects(expected, actualEvent, excludeFields);

if (!result.matches) {
    console.log('❌ Differences found:');
    result.differences.forEach(diff => console.log(`   - ${diff}`));
    // Example output:
    //   - custom_data.content_name: expected "TestP" but got "TestProduct"
    //   - custom_data.value: expected "19.99" but got "20.99"
} else {
    console.log('✅ Perfect match!');
}
```

## 🚀 Integration with EventValidator

```javascript
class EventValidator {
    async validate(eventType, page) {
        // ... load captured events ...

        const builder = new ExpectedDataBuilder();
        const productId = this.extractProductId(pixelEvent);

        // Get expected (fetches real data)
        const expected = await builder.getExpectedEvent(eventType, 'pixel', { productId });

        // Get exclusions
        const excludeFields = ExpectedDataBuilder.getExcludedFields(eventType, 'pixel');

        // Simple comparison
        const result = ExpectedDataBuilder.compareObjects(
            expected,
            pixelEvent,
            excludeFields
        );

        if (!result.matches) {
            errors.push(...result.differences);
        }

        return { passed: errors.length === 0, errors };
    }
}
```

## ✅ Benefits

1. **Real Data** - Validates against actual product names, prices, versions
2. **Smart** - Only excludes truly dynamic fields (timestamps, IPs, UUIDs)
3. **Simple** - One function, clear logic, no null handling
4. **Comprehensive** - Validates everything that can be validated
5. **Maintainable** - Clean switch/case, easy to extend
6. **Fast** - Caches product data, versions for performance
7. **Clean** - ~400 lines, single file, OOP principles

## 🎯 Summary

| Aspect | Old Approach | New Approach |
|--------|-------------|--------------|
| Product Data | ❌ Skipped (null) | ✅ Real from WP-CLI |
| Versions | ❌ Skipped (null) | ✅ Real from files |
| Comparison | ❌ Complex null handling | ✅ Simple exclude list |
| Coverage | ⚠️ Partial | ✅ Comprehensive |
| Code | ⚠️ 2 files, 450 lines | ✅ 1 file, 400 lines |
| Philosophy | "Skip what's dynamic" | "Validate everything real" |

**Smart. Simple. Comprehensive. Elegant.** ✨
