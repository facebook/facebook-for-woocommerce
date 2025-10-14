/**
 * 🧪 Test using the Improved Event Monitor
 *
 * HOW IT WORKS:
 * 1. Monitor installs a WordPress mu-plugin that hooks into Facebook events
 * 2. startCapture() tells WordPress "start recording events"
 * 3. You do your test actions (visit pages, add to cart, etc.)
 * 4. WordPress hooks capture EVERY Facebook event (CAPI + Pixel) as they happen
 * 5. stopCapture() says "stop recording and give me all the events"
 */

const { test, expect } = require('@playwright/test');
const ImprovedEventMonitor = require('./event-monitor-improved');

test.describe('Facebook Event Monitoring - Improved Version', () => {
    let monitor;

    test.beforeAll(async () => {
        // ONE-TIME SETUP: Install the WordPress monitoring plugin
        monitor = new ImprovedEventMonitor({
            wordpressPath: '/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public',
            debug: true
        });

        console.log('📦 Installing WordPress monitoring plugin...');
        await monitor.installMonitorPlugin();
        console.log('✅ Plugin installed at: wp-content/mu-plugins/facebook-event-monitor.php');
        console.log('   This plugin will automatically capture all Facebook events');
    });

    test('should capture CAPI and Pixel events using WordPress hooks', async ({ page }) => {
        console.log('\n🧪 TEST FLOW:');
        console.log('============================================');

        // STEP 1: Tell WordPress to start monitoring
        console.log('1️⃣ Starting event monitoring...');
        await monitor.startCapture('wordpress-hook-test');
        console.log('   ✅ WordPress is now recording all Facebook events');

        // STEP 2: Enable browser pixel capture (optional, for validation)
        await monitor.capturePixelEvents(page);

        // STEP 3: Do your test actions - WordPress hooks capture everything automatically
        console.log('\n2️⃣ Performing test actions...');

        // Visit homepage
        console.log('   📄 Visiting homepage...');
        await page.goto('http://wooc-local-test-sitecom.local/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000); // Let events fire
        console.log('      → PageView event should be captured by WordPress hook');

        // Visit product page
        console.log('   📦 Visiting product page...');
        await page.goto('http://wooc-local-test-sitecom.local/product/test-product-for-facebook-pixel/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        console.log('      → ViewContent event should be captured by WordPress hook');

        // Add to cart
        console.log('   🛒 Adding product to cart...');
        const addToCartButton = page.locator('.single_add_to_cart_button').first();
        if (await addToCartButton.isVisible()) {
            await addToCartButton.click();
            await page.waitForTimeout(3000);
            console.log('      → AddToCart event should be captured by WordPress hook');
        }

        // Get browser fbq calls (for comparison)
        const browserEvents = await monitor.getFbqCalls(page);
        console.log(`   📊 Browser captured ${browserEvents.length} fbq() calls`);

        // STEP 4: Stop monitoring and get ALL events from WordPress
        console.log('\n3️⃣ Stopping monitoring and retrieving events...');
        const results = await monitor.stopCapture();

        // STEP 5: Analyze results
        console.log('\n📊 RESULTS:');
        console.log('============================================');
        console.log(`Total Events Captured: ${results.summary.totalEvents}`);
        console.log(`  CAPI Events: ${results.summary.capiEvents}`);
        console.log(`  Pixel Events: ${results.summary.pixelEvents}`);
        console.log(`  Browser fbq() calls: ${browserEvents.length}`);

        // Show event details
        if (results.events.length > 0) {
            console.log('\n📋 Event Details:');
            results.events.forEach((event, index) => {
                console.log(`  ${index + 1}. ${event.type.toUpperCase()} - ${event.event_name}`);
                console.log(`     Source: ${event.source}`);
                console.log(`     Time: ${new Date(event.timestamp).toLocaleTimeString()}`);
            });
        }

        // STEP 6: Validate
        console.log('\n✅ VALIDATION:');
        console.log('============================================');

        // Check we captured events
        expect(results.summary.totalEvents).toBeGreaterThan(0);
        console.log(`✓ Captured ${results.summary.totalEvents} total events`);

        // Check for specific events
        const eventNames = results.events.map(e => e.event_name);
        const hasPageView = eventNames.includes('PageView');
        const hasViewContent = eventNames.includes('ViewContent');

        if (hasPageView) console.log('✓ PageView event captured');
        if (hasViewContent) console.log('✓ ViewContent event captured');

        console.log('\n🎉 Test completed successfully!');
        console.log(`📁 Events saved to: captured-events/events-wordpress-hook-test-*.json`);
    });
});

test.describe('Monitor Setup Verification', () => {
    test('should verify the mu-plugin is installed correctly', async () => {
        const fs = require('fs');
        const path = require('path');

        const muPluginPath = '/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public/wp-content/mu-plugins/facebook-event-monitor.php';

        console.log('\n🔍 Checking WordPress monitoring plugin...');
        console.log('============================================');

        const exists = fs.existsSync(muPluginPath);

        if (exists) {
            console.log('✅ Plugin installed at:', muPluginPath);
            console.log('   This plugin automatically captures Facebook events');
            console.log('   No configuration needed - it just works!');
        } else {
            console.log('❌ Plugin NOT installed');
            console.log('   Run the main test first to install it');
        }

        expect(exists).toBe(true);
    });
});
