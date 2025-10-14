# 📊 Expected vs Captured Events - Analysis

## Based on Testing Flow Document

### All CAPI Events That Should Be Captured

| Event | Trigger | WooCommerce Hook | What We Captured |
|-------|---------|------------------|------------------|
| **PageView** | Visit any page | `wp_head`, `wp_footer` | ✅ YES (1 event) |
| **ViewCategory** | Visit product category page | `woocommerce_after_shop_loop` | ❓ NOT TESTED |
| **ViewContent** | Visit product page | `woocommerce_after_single_product` | ✅ YES (1 event) |
| **AddToCart** | Add product to cart | `woocommerce_add_to_cart`, `woocommerce_ajax_added_to_cart` | ✅ YES (1 event) |
| **InitiateCheckout** | Go to checkout page | `woocommerce_after_checkout_form` | ❓ NOT TESTED |
| **Purchase** | Place order | `woocommerce_new_order`, `woocommerce_thankyou`, etc. | ❓ NOT TESTED |
| **Search** | Search for products | Custom hook | ❓ NOT TESTED |
| **Subscribe** | Purchase subscription | Subscription hooks | ❓ NOT TESTED |
| **Lead** | Submit Contact Form 7 | `wpcf7submit` | ❓ NOT TESTED |

## Your Current Test Flow

```
1. Visit homepage        → PageView ✅
2. Visit shop page       → ViewCategory ❌ (shop != category)
3. Click product link    → ViewContent ✅
4. Add to cart          → AddToCart ✅
```

## What You're Missing

### 1. ViewCategory Event
**Current**: You visit `/shop/` which is the main shop page  
**Expected**: Visit a category page like `/product-category/uncategorized/`

**Why it's missing**: Shop page ≠ Category page. The hook `woocommerce_after_shop_loop` fires on category pages, not the main shop page.

### 2. InitiateCheckout Event
**Not tested**: You didn't go to the checkout page

### 3. Purchase Event
**Not tested**: You didn't complete a purchase

## Your Actual Capture is CORRECT! ✅

Based on your test flow, you **should** capture exactly 3 CAPI events:
- ✅ PageView (homepage)
- ✅ ViewContent (product page)
- ✅ AddToCart (clicked add to cart)

You captured exactly what you should have! 🎉

## To Capture More Events

### Test 1: Full Purchase Flow

```javascript
test('complete purchase flow', async ({ page }) => {
    const monitor = new SimpleFacebookMonitor();
    
    // Login
    await page.goto('/wp-admin');
    await page.fill('#user_login', 'madhav');
    await page.fill('#user_pass', 'madhav-wooc');
    await page.click('#wp-submit');
    
    await monitor.startCapture(page, 'full-purchase');
    
    // 1. Homepage → PageView
    await page.goto('/');
    await page.waitForTimeout(2000);
    
    // 2. Category page → ViewCategory
    await page.goto('/product-category/uncategorized/');
    await page.waitForTimeout(2000);
    
    // 3. Product page → ViewContent
    await page.goto('/product/test-product-for-facebook-pixel/');
    await page.waitForTimeout(2000);
    
    // 4. Add to cart → AddToCart
    await page.click('button:has-text("Add to cart")');
    await page.waitForTimeout(3000);
    
    // 5. Go to checkout → InitiateCheckout
    await page.goto('/checkout/');
    await page.waitForTimeout(2000);
    
    // 6. Fill checkout form and purchase → Purchase
    await page.fill('#billing_first_name', 'Test');
    await page.fill('#billing_last_name', 'User');
    await page.fill('#billing_address_1', '123 Test St');
    await page.fill('#billing_city', 'Test City');
    await page.fill('#billing_postcode', '12345');
    await page.fill('#billing_phone', '1234567890');
    await page.fill('#billing_email', 'test@example.com');
    
    await page.click('#place_order');
    await page.waitForTimeout(5000); // Wait for order processing
    
    const results = await monitor.stopCapture(page);
    
    // Should capture 6 events:
    // PageView, ViewCategory, ViewContent, AddToCart, InitiateCheckout, Purchase
    expect(results.summary.capiEvents).toBe(6);
});
```

### Test 2: Search Flow

```javascript
test('search flow', async ({ page }) => {
    const monitor = new SimpleFacebookMonitor();
    
    await monitor.startCapture(page, 'search-flow');
    
    // Search for a product
    await page.goto('/');
    await page.fill('form.woocommerce-product-search input', 'test');
    await page.click('form.woocommerce-product-search button[type="submit"]');
    await page.waitForTimeout(3000);
    
    const results = await monitor.stopCapture(page);
    
    // Should capture: PageView + Search
    expect(results.summary.capiEvents).toBeGreaterThanOrEqual(2);
});
```

## Expected Event Counts Per Flow

| Test Flow | Expected CAPI Events |
|-----------|---------------------|
| **Your current test** | 3 (PageView, ViewContent, AddToCart) ✅ |
| **Full purchase flow** | 6 (PageView, ViewCategory, ViewContent, AddToCart, InitiateCheckout, Purchase) |
| **Search flow** | 2 (PageView, Search) |
| **Category browse** | 2 (PageView, ViewCategory) |

## Summary

🎉 **Your test is working perfectly!**

You captured:
- ✅ 3 CAPI events (correct for your test flow)
- ✅ 7 Pixel events (script loads + config + actual events)

The reason you only got 3 CAPI events is because you only performed 3 actions that trigger CAPI events. This is **expected and correct**!

## Next Steps

1. ✅ **Current test is good** - validates basic event capture
2. 🔄 **Add full purchase test** - captures all 6 main events
3. 🔄 **Add search test** - validates search event
4. 🔄 **Add category test** - validates ViewCategory
5. 🎯 **Business Manager validation** - verify events appear in Facebook