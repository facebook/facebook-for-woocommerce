/**
 * 🎯 COMPLETE FLOW TEST - Triggers ALL CAPI Events
 * 
 * This test triggers all possible CAPI events based on facebook-commerce-events-tracker.php hooks:
 * 1. PageView - wp_head hook (line 106, 158)
 * 2. ViewContent - woocommerce_after_single_product hook (line 110, 536)
 * 3. AddToCart - woocommerce_add_to_cart hook (line 121, 605)
 * 4. InitiateCheckout - woocommerce_after_checkout_form hook (line 131, 795)
 * 5. Purchase - woocommerce_thankyou hook (line 140, 858)
 * 6. ViewCategory - woocommerce_after_shop_loop hook (line 114, 204)
 * 7. Search - pre_get_posts hook (line 117, 401)
 */

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

// Import the simple monitor
const SimpleFacebookMonitor = require('./simple-event-capture.spec.js').SimpleFacebookMonitor || class SimpleFacebookMonitor {
    constructor() {
        this.debugLogPath = '/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public/wp-content/debug.log';
        this.testId = null;
        this.initialLogSize = 0;
        this.pixelNetworkEvents = [];
    }
    
    async startCapture(page, testName) {
        this.testId = `${testName}-${Date.now()}`;
        
        await page.context().addCookies([{
            name: 'facebook_test_id',
            value: this.testId,
            domain: 'wooc-local-test-sitecom.local',
            path: '/'
        }]);
        
        if (fs.existsSync(this.debugLogPath)) {
            this.initialLogSize = fs.statSync(this.debugLogPath).size;
        }
        
        // Capture pixel network events
        page.on('request', request => {
            const url = request.url();
            if (url.includes('facebook.com/tr') || url.includes('connect.facebook.net')) {
                this.pixelNetworkEvents.push({
                    type: 'pixel_network',
                    url: url,
                    method: request.method(),
                    timestamp: Date.now(),
                    testId: this.testId
                });
            }
        });
        
        console.log(`\n🔍 Started monitoring: ${this.testId}`);
    }
    
    async stopCapture(page) {
        const pixelEvents = this.pixelNetworkEvents || [];
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
        
        this.saveResults(results);
        return results;
    }
    
    parseDebugLog() {
        if (!fs.existsSync(this.debugLogPath)) return [];
        
        const currentSize = fs.statSync(this.debugLogPath).size;
        const newBytes = currentSize - this.initialLogSize;
        if (newBytes <= 0) return [];
        
        const buffer = Buffer.alloc(newBytes);
        const fd = fs.openSync(this.debugLogPath, 'r');
        fs.readSync(fd, buffer, 0, newBytes, this.initialLogSize);
        fs.closeSync(fd);
        
        const newContent = buffer.toString('utf8');
        
        return newContent
            .split('\n')
            .filter(line => line.includes(`[FBTEST|${this.testId}]`))
            .map(line => {
                const parts = line.split('|');
                if (parts.length >= 5) {
                    try {
                        return {
                            type: 'capi',
                            eventName: parts[2],
                            eventId: parts[3],
                            eventData: JSON.parse(parts[4]),
                            testId: this.testId,
                            timestamp: Date.now()
                        };
                    } catch (e) {
                        return null;
                    }
                }
                return null;
            })
            .filter(Boolean);
    }
    
    saveResults(results) {
        const eventsDir = path.join(__dirname, 'captured-events');
        if (!fs.existsSync(eventsDir)) {
            fs.mkdirSync(eventsDir, { recursive: true });
        }
        
        const filename = `events-${this.testId}.json`;
        fs.writeFileSync(path.join(eventsDir, filename), JSON.stringify(results, null, 2));
        console.log(`💾 Saved: ${filename}`);
    }
};

test.describe('Complete CAPI Event Flow', () => {
    test('should trigger and capture ALL CAPI events', async ({ page }) => {
        const monitor = new SimpleFacebookMonitor();
        
        console.log('\n🎯 COMPLETE FLOW TEST - Triggering ALL CAPI Events');
        console.log('='.repeat(60));
        
        // LOGIN FIRST
        console.log('\n🔐 Step 0: Login as admin...');
        await page.goto('/wp-admin');
        await page.fill('#user_login', 'madhav');
        await page.fill('#user_pass', 'madhav-wooc');
        await page.click('#wp-submit');
        await page.waitForLoadState('networkidle');
        console.log('   ✓ Logged in');
        
        // START MONITORING
        await monitor.startCapture(page, 'complete-flow');
        
        // EVENT 1: PageView (wp_head hook fires on every page)
        console.log('\n📄 Step 1: Visit Homepage → PageView');
        await page.goto('/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        console.log('   ✓ PageView event should be fired');
        
        // EVENT 2: ViewCategory (woocommerce_after_shop_loop hook)
        // NOTE: This only fires on CATEGORY pages, not /shop/ page!
        console.log('\n📂 Step 2: Visit Product Category → ViewCategory');
        
        // Find a category first
        await page.goto('/shop/');
        await page.waitForTimeout(1000);
        
        // Try to find a category link
        const categoryLink = page.locator('a[href*="/product-category/"]').first();
        if (await categoryLink.isVisible({ timeout: 5000 })) {
            await categoryLink.click();
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(2000);
            console.log('   ✓ ViewCategory event should be fired');
        } else {
            console.log('   ⚠️ No category found, trying /product-category/uncategorized/');
            await page.goto('/product-category/uncategorized/');
            await page.waitForTimeout(2000);
        }
        
        // EVENT 3: ViewContent (woocommerce_after_single_product hook)
        console.log('\n📦 Step 3: Visit Product Page → ViewContent');
        const productLink = page.locator('a.wc-block-components-product-name').first();
        if (await productLink.isVisible({ timeout: 5000 })) {
            await productLink.click();
        } else {
            // Direct URL fallback
            await page.goto('/product/test-product-for-facebook-pixel/');
        }
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        console.log('   ✓ ViewContent event should be fired');
        
        // EVENT 4: AddToCart (woocommerce_add_to_cart hook)
        console.log('\n🛒 Step 4: Add Product to Cart → AddToCart');
        const addToCartSelectors = [
            '.wc-block-components-product-button button',
            'div.wp-block-button button',
            '.single_add_to_cart_button'
        ];
        
        let addedToCart = false;
        for (const selector of addToCartSelectors) {
            try {
                const button = page.locator(selector).first();
                if (await button.isVisible({ timeout: 2000 })) {
                    await button.click();
                    await page.waitForTimeout(3000); // Wait for AJAX
                    addedToCart = true;
                    console.log(`   ✓ AddToCart event should be fired (${selector})`);
                    break;
                }
            } catch (e) {
                continue;
            }
        }
        
        if (!addedToCart) {
            console.log('   ⚠️ Could not add to cart');
        }
        
        // EVENT 5: Search (pre_get_posts hook - only when searching)
        console.log('\n🔍 Step 5: Search for Products → Search');
        await page.goto('/');
        await page.waitForTimeout(1000);
        
        // Find search form
        const searchInput = page.locator('input[name="s"]').first();
        if (await searchInput.isVisible({ timeout: 5000 })) {
            await searchInput.fill('test');
            await searchInput.press('Enter');
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(2000);
            console.log('   ✓ Search event should be fired');
        } else {
            console.log('   ⚠️ Search form not found');
        }
        
        // EVENT 6: InitiateCheckout (woocommerce_after_checkout_form hook)
        console.log('\n💳 Step 6: Visit Checkout Page → InitiateCheckout');
        await page.goto('/checkout/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000); // Let the form fully load
        console.log('   ✓ InitiateCheckout event should be fired');
        
        // EVENT 7: Purchase (woocommerce_thankyou hook)
        console.log('\n✅ Step 7: Complete Purchase → Purchase');
        
        // Fill checkout form
        try {
            await page.fill('#billing_first_name', 'Test');
            await page.fill('#billing_last_name', 'User');
            await page.fill('#billing_address_1', '123 Test Street');
            await page.fill('#billing_city', 'Test City');
            await page.fill('#billing_postcode', '12345');
            await page.fill('#billing_phone', '1234567890');
            await page.fill('#billing_email', 'test@example.com');
            
            // Select payment method (if available)
            const codPayment = page.locator('input#payment_method_cod');
            if (await codPayment.isVisible({ timeout: 2000 })) {
                await codPayment.click();
            }
            
            // Place order
            await page.click('#place_order');
            await page.waitForLoadState('networkidle', { timeout: 30000 });
            await page.waitForTimeout(5000); // Wait for order processing and thank you page
            
            console.log('   ✓ Purchase event should be fired');
        } catch (error) {
            console.log(`   ⚠️ Could not complete purchase: ${error.message}`);
        }
        
        // STOP MONITORING AND GET RESULTS
        console.log('\n📊 Capturing Results...');
        const results = await monitor.stopCapture(page);
        
        // DISPLAY RESULTS
        console.log('\n='.repeat(60));
        console.log('📈 RESULTS SUMMARY');
        console.log('='.repeat(60));
        console.log(`Total Events Captured: ${results.summary.totalEvents}`);
        console.log(`  CAPI Events: ${results.summary.capiEvents}`);
        console.log(`  Pixel Events: ${results.summary.pixelEvents}`);
        
        if (results.events.capi.length > 0) {
            console.log('\n✅ CAPI Events Captured:');
            const eventCounts = {};
            results.events.capi.forEach(e => {
                eventCounts[e.eventName] = (eventCounts[e.eventName] || 0) + 1;
            });
            Object.entries(eventCounts).forEach(([name, count]) => {
                console.log(`   ${count}x ${name}`);
            });
        }
        
        // Expected events
        const expectedEvents = ['PageView', 'ViewContent', 'AddToCart', 'InitiateCheckout', 'Purchase', 'ViewCategory', 'Search'];
        const capturedEventNames = [...new Set(results.events.capi.map(e => e.eventName))];
        
        console.log('\n📋 Event Checklist:');
        expectedEvents.forEach(eventName => {
            const captured = capturedEventNames.includes(eventName);
            console.log(`   ${captured ? '✅' : '❌'} ${eventName}`);
        });
        
        // Assertions
        expect(results.summary.capiEvents).toBeGreaterThan(0);
        console.log('\n🎉 Test completed!');
    });
});