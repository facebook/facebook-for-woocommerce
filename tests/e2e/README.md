# E2E Test Suite Documentation

## Overview

This directory contains the End-to-End (E2E) test suite for Facebook for WooCommerce plugin. The tests are organized into 4 balanced groups that run in parallel to optimize test execution time.

**Current Test Organization:**
- **Total Tests:** 15 tests
- **Parallel Workers:** 4 (in CI)
- **Execution Time:** ~6-7 minutes (down from ~15 minutes with sequential execution)

---

## Test File Organization

### 1. Health Checks (`product-health.spec.js`)
**Expected Duration: ~2 minutes**

Quick health checks that validate basic WordPress and WooCommerce functionality:
- WordPress admin and Facebook plugin presence
- WooCommerce product list availability
- PHP error checks across key pages

**When to add tests here:**
- Basic validation tests that don't create/modify data
- Quick sanity checks for core functionality
- Tests that verify plugin installation and configuration

---

### 2. Product Creation (`product-creation.spec.js`)
**Expected Duration: ~6-7 minutes**

Tests focused on creating new products:
- Create simple product with WooCommerce
- Create variable product with WooCommerce

**When to add tests here:**
- New product creation scenarios
- Testing product creation with different types (simple, variable, grouped, etc.)
- Validation of product sync to Facebook after creation

---

### 3. Product Modification (`product-modification.spec.js`)
**Expected Duration: ~6-7 minutes**

Tests focused on editing existing products:
- Edit simple product and verify Facebook sync
- Quick Edit simple product price and verify Facebook sync
- Edit variable product and verify Facebook sync
- Edit Facebook-specific options for simple product

**When to add tests here:**
- Product update scenarios
- Price changes and sync validation
- Attribute/variation modifications
- Facebook field updates

---

### 4. Critical Operations (`product-critical.spec.js`)
**Expected Duration: ~3 minutes**

Tests that affect global state or perform critical operations:
- Delete products and validate Facebook sync
- Facebook plugin deactivation and reactivation

**Configuration:** This file uses `test.describe.configure({ mode: 'serial' })` to run tests sequentially within the file to prevent plugin state conflicts.

**When to add tests here:**
- Plugin activation/deactivation tests
- Tests that modify global settings
- Bulk operations that affect multiple products
- Tests that may interfere with other parallel tests

---

## Decision Tree: Where to Add New Tests

```
┌─ Does your test affect global state (plugin settings, activation, etc.)?
│  └─ YES → Add to product-critical.spec.js
│  └─ NO  → Continue ↓
│
├─ Is this a quick validation/sanity check?
│  └─ YES → Add to product-health.spec.js
│  └─ NO  → Continue ↓
│
├─ Does your test create new products?
│  └─ YES → Add to product-creation.spec.js
│  └─ NO  → Continue ↓
│
└─ Does your test modify/update existing products?
   └─ YES → Add to product-modification.spec.js
   └─ NO  → Consider if you need a new test group
```

---

## Best Practices for Test Independence

### ✅ DO:
1. **Create unique test data** for each test using helpers like:
   - `generateProductName()` - Creates unique product names with timestamps
   - `generateUniqueSKU()` - Creates unique SKUs to prevent conflicts
   - `createTestProduct()` - Creates isolated test products

2. **Clean up after yourself** using `finally` blocks:
   ```javascript
   try {
     // Test logic
   } finally {
     if (productId) {
       await cleanupProduct(productId);
     }
   }
   ```

3. **Use proper isolation** - Each test should be completely independent and not rely on data from other tests

4. **Wait appropriately** - Use `waitForTimeout()`, `waitForLoadState()`, and selector waits to ensure stability

### ❌ DON'T:
1. **Share data between tests** - Each test creates and destroys its own data
2. **Modify global state** without marking as serial - Use `test.describe.configure({ mode: 'serial' })` for tests that affect plugin state
3. **Hard-code product IDs or SKUs** - Always generate unique values
4. **Skip cleanup** - Even if a test fails, cleanup should run in `finally` block

---

## Running Tests

### Local Development
```bash
# Run all E2E tests (uses default worker count)
npm run test:e2e

# Run specific test file
npx playwright test product-creation.spec.js

# Run tests in headed mode (see browser)
npx playwright test --headed

# Run tests in debug mode
npx playwright test --debug
```

### Continuous Integration
```bash
# CI runs with 4 parallel workers automatically
# Configured in playwright.config.js:
# workers: process.env.CI ? 4 : undefined
```

---

## Test Execution Timeline (Parallel)

When running in CI with 4 workers:

```
Worker 1: product-health.spec.js          [====]           (~2 min)
Worker 2: product-creation.spec.js        [============]   (~6-7 min) ← Longest
Worker 3: product-modification.spec.js    [============]   (~6-7 min) ← Longest
Worker 4: product-critical.spec.js        [======]         (~3 min)
          ─────────────────────────────────────────────────
          Total Time: ~6-7 minutes
```

The total execution time is determined by the longest-running worker (Worker 2 or 3).

---

## Helper Functions Reference

All test helpers are located in `test-helpers.js`:

### Product Management
- `createTestProduct(options)` - Creates a test product via WordPress REST API
- `cleanupProduct(productId)` - Permanently deletes a product
- `generateProductName(type)` - Generates unique product name
- `generateUniqueSKU(type)` - Generates unique SKU

### Navigation & Authentication
- `loginToWordPress(page)` - Logs into WordPress admin
- `publishProduct(page)` - Publishes current product
- `extractProductIdFromUrl(url)` - Extracts product ID from URL

### Validation
- `validateFacebookSync(productId, productName, timeout)` - Validates product sync to Facebook
- `checkForPhpErrors(page)` - Checks page for PHP errors

### Utilities
- `safeScreenshot(page, filename)` - Takes screenshot for debugging
- `logTestStart(testInfo)` - Logs test start with timestamp
- `logTestEnd(testInfo, success)` - Logs test completion

---

## Adding Facebook Sync Validation

When testing product operations, validate sync to Facebook:

```javascript
// After creating/modifying a product
const result = await validateFacebookSync(productId, productName, 60);
expect(result['success']).toBe(true);

// For deletion validation
expect(result['success']).toBe(false);
expect(
  result['debug'].some(
    (msg) => msg === 'Compared fields for 0 products, found 0 total mismatches'
  )
).toBe(true);
```

---

## Troubleshooting

### Test Failures
1. Check `playwright-report/index.html` for detailed results
2. Review screenshots in `test-results/` directory
3. Check console logs for PHP errors or sync issues

### Flaky Tests
1. Increase timeouts if operations are slow
2. Add additional `waitForTimeout()` calls for stability
3. Verify selectors are unique and stable
4. Check if test is interfering with others (consider moving to `product-critical.spec.js`)

### Performance Issues
1. If tests exceed expected duration, consider:
   - Reducing wait times where safe
   - Optimizing product creation logic
   - Splitting complex tests into smaller units

---

## Future Optimization Opportunities

These are documented for future consideration but not currently implemented:

1. **Test Sharding** - Distribute tests across multiple CI runners for ~3-4 min total time
2. **Project-level Parallelization** - Use Playwright projects for browser-level isolation
3. **Database State Isolation** - Implement database snapshots for faster test setup
4. **Selective Test Execution** - Run only affected tests based on code changes

---

## Contact & Support

For questions or issues with the E2E test suite:
- Review this documentation first
- Check existing test files for examples
- Ensure tests follow the isolation best practices outlined above
