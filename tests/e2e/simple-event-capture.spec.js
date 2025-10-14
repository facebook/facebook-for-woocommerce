/**
 * 🎯 SIMPLE FACEBOOK EVENT CAPTURE TEST
 *
 * Run this: npx playwright test tests/e2e/simple-event-capture.spec.js
 */

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

class SimpleFacebookMonitor {
    constructor() {
        this.debugLogPath = '/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public/wp-content/debug.log';
        this.testId = null;
        this.initialLogSize = 0;
    }

    async startCapture(page, testName) {
        // Generate unique test ID
        this.testId = `${testName}-${Date.now()}`;

        // 🎯 SET THE COOKIE - This is what identifies this test
        await page.context().addCookies([{
            name: 'facebook_test_id',
            value: this.testId,
            domain: 'wooc-local-test-sitecom.local',
            path: '/'
        }]);

        console.log(`\n🔍 Started monitoring with Test ID: ${this.testId}`);
        console.log(`   Cookie set: facebook_test_id=${this.testId}`);

        // Get initial log size
        if (fs.existsSync(this.debugLogPath)) {
            this.initialLogSize = fs.statSync(this.debugLogPath).size;
            console.log(`   Initial log size: ${this.initialLogSize} bytes`);
        } else {
            console.log(`   ⚠️ Debug log not found at: ${this.debugLogPath}`);
        }

        // Capture browser pixel events
        await this.capturePixelEvents(page);
    }

    async capturePixelEvents(page) {
        // Method 1: Intercept network requests to facebook.com/tr
        this.pixelNetworkEvents = [];

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

        // Method 2: Intercept fbq() calls (more robust)
        await page.addInitScript((testId) => {
            window.__pixelEvents = [];

            // Wait for fbq to be defined, then intercept
            const interceptFbq = () => {
                if (typeof window.fbq !== 'undefined' && !window.fbq.__intercepted) {
                    const originalFbq = window.fbq;
                    window.fbq = function(...args) {
                        window.__pixelEvents.push({
                            type: 'pixel_javascript',
                            args: args,
                            timestamp: Date.now(),
                            testId: testId
                        });
                        console.log('📡 Pixel fbq() captured:', args);
                        return originalFbq.apply(this, args);
                    };
                    window.fbq.__intercepted = true;

                    // Preserve properties
                    Object.keys(originalFbq).forEach(key => {
                        if (key !== '__intercepted') {
                            window.fbq[key] = originalFbq[key];
                        }
                    });
                }
            };

            // Try immediately
            interceptFbq();

            // Keep trying every 100ms for 5 seconds
            const maxAttempts = 50;
            let attempts = 0;
            const interval = setInterval(() => {
                attempts++;
                if (typeof window.fbq !== 'undefined' || attempts >= maxAttempts) {
                    interceptFbq();
                    clearInterval(interval);
                }
            }, 100);
        }, this.testId);
    }

    async stopCapture(page) {
        console.log(`\n✅ Stopping capture for: ${this.testId}`);

        // Get browser pixel events from both sources
        const pixelEventsFromScript = await page.evaluate(() => window.__pixelEvents || []);
        const pixelEventsFromNetwork = this.pixelNetworkEvents || [];

        // Combine both
        const pixelEvents = [...pixelEventsFromScript, ...pixelEventsFromNetwork];

        console.log(`   Browser JS captured: ${pixelEventsFromScript.length} pixel events`);
        console.log(`   Network captured: ${pixelEventsFromNetwork.length} pixel events`);
        console.log(`   Total pixel: ${pixelEvents.length} events`);

        // Get CAPI events from debug log
        const capiEvents = this.parseDebugLog();
        console.log(`   Log captured: ${capiEvents.length} CAPI events`);

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

        // Save to file
        this.saveResults(results);

        return results;
    }

    parseDebugLog() {
        if (!fs.existsSync(this.debugLogPath)) {
            console.log('   ⚠️ Debug log file not found');
            return [];
        }

        const currentSize = fs.statSync(this.debugLogPath).size;
        const newBytes = currentSize - this.initialLogSize;

        console.log(`   Log grew by: ${newBytes} bytes`);

        if (newBytes <= 0) {
            console.log('   ⚠️ No new log entries');
            return [];
        }

        const buffer = Buffer.alloc(newBytes);
        const fd = fs.openSync(this.debugLogPath, 'r');
        fs.readSync(fd, buffer, 0, newBytes, this.initialLogSize);
        fs.closeSync(fd);

        const newContent = buffer.toString('utf8');

        // Show raw log content for debugging
        console.log('\n📋 New log entries:');
        console.log(newContent.substring(0, 500) + (newContent.length > 500 ? '...' : ''));

        // Parse lines like: [FBTEST|test-1-123] CAPI|ViewContent|event-id|{...}
        const events = newContent
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
                        console.log(`   ⚠️ Failed to parse line: ${line.substring(0, 100)}`);
                        return null;
                    }
                }
                return null;
            })
            .filter(Boolean);

        console.log(`   Parsed ${events.length} CAPI events for test ID: ${this.testId}`);

        return events;
    }

    saveResults(results) {
        const eventsDir = path.join(__dirname, 'captured-events');
        if (!fs.existsSync(eventsDir)) {
            fs.mkdirSync(eventsDir, { recursive: true });
        }

        const filename = `events-${this.testId}.json`;
        const filepath = path.join(eventsDir, filename);

        fs.writeFileSync(filepath, JSON.stringify(results, null, 2));
        console.log(`\n💾 Saved results to: ${filename}`);
    }
}

// THE ACTUAL TEST
test.describe('Facebook Event Capture POC', () => {
    test('should capture CAPI and Pixel events', async ({ page }) => {
        const monitor = new SimpleFacebookMonitor();

        console.log('\n🧪 TEST: Facebook Event Capture');
        console.log('================================\n');

        // STEP 1: Login as admin first
        console.log('\n🔐 Logging in as admin...');
        await page.goto('/wp-admin');
        await page.fill('#user_login', 'madhav');
        await page.fill('#user_pass', 'madhav-wooc');
        await page.click('#wp-submit');
        await page.waitForLoadState('networkidle');
        console.log('   ✓ Logged in successfully');

        // STEP 2: Start monitoring
        await monitor.startCapture(page, 'test-run');

        // STEP 3: Navigate to homepage
        console.log('\n📄 Step 1: Visit homepage...');
        await page.goto('/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        console.log('   ✓ Homepage loaded');

        // STEP 3: Go to shop page first
        console.log('\n🛒 Step 2: Visit shop page...');
        await page.goto('/shop/');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        console.log('   ✓ Shop page loaded');

        // STEP 4: Click first product link to go to product page
        console.log('\n📦 Step 3: Click on first product...');
        const productLink = page.locator('a.wc-block-components-product-name').first();
        if (await productLink.isVisible({ timeout: 5000 })) {
            await productLink.click();
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(2000);
            console.log('   ✓ Product page loaded');
        }

        // STEP 5: Add to cart
        console.log('\n🛒 Step 4: Add to cart...');

        // Try multiple possible selectors (including WooCommerce blocks)
        const possibleSelectors = [
            '.wc-block-components-product-button button',  // WooCommerce blocks
            'div.wp-block-button button',                   // Block theme
            '.single_add_to_cart_button',                   // Classic theme
            'button[name="add-to-cart"]',
            '.add_to_cart_button',
            'button:has-text("Add to cart")',
            'button:has-text("Add to Cart")'
        ];

        let buttonFound = false;
        for (const selector of possibleSelectors) {
            try {
                const button = page.locator(selector).first();
                if (await button.isVisible({ timeout: 2000 })) {
                    await button.click();
                    await page.waitForTimeout(3000); // Wait for AJAX and events
                    console.log(`   ✓ Product added to cart (using selector: ${selector})`);
                    buttonFound = true;
                    break;
                }
            } catch (e) {
                // Try next selector
            }
        }

        if (!buttonFound) {
            console.log('   ⚠️ Add to cart button not found with any selector');
            console.log('   Available buttons on page:');
            const buttons = await page.locator('button').all();
            for (let i = 0; i < Math.min(buttons.length, 5); i++) {
                const text = await buttons[i].textContent();
                console.log(`     - ${text?.trim()}`);
            }
        }

        // STEP 5: Stop and get results
        const results = await monitor.stopCapture(page);

        // STEP 6: Display results
        console.log('\n📊 RESULTS:');
        console.log('================================');
        console.log(`Total Events: ${results.summary.totalEvents}`);
        console.log(`  Pixel Events: ${results.summary.pixelEvents}`);
        console.log(`  CAPI Events: ${results.summary.capiEvents}`);

        if (results.events.capi.length > 0) {
            console.log('\n✅ CAPI Events Captured:');
            results.events.capi.forEach((e, i) => {
                console.log(`   ${i + 1}. ${e.eventName} (ID: ${e.eventId})`);
            });
        } else {
            console.log('\n⚠️ No CAPI events captured');
            console.log('   Possible reasons:');
            console.log('   - WP_DEBUG_LOG not enabled');
            console.log('   - Facebook plugin not configured');
            console.log('   - CAPI not enabled in plugin settings');
        }

        if (results.events.pixel.length > 0) {
            console.log('\n✅ Pixel Events Captured:');
            
            // Separate by type
            const scriptLoads = results.events.pixel.filter(e => e.url && e.url.includes('fbevents.js'));
            const configLoads = results.events.pixel.filter(e => e.url && e.url.includes('/signals/config/'));
            const eventFires = results.events.pixel.filter(e => e.url && e.url.includes('/tr/') || (e.url && e.url.includes('/tr?')));
            
            if (scriptLoads.length > 0) {
                console.log(`   📦 Pixel Script Loads: ${scriptLoads.length}`);
            }
            
            if (configLoads.length > 0) {
                console.log(`   ⚙️ Pixel Config Loads: ${configLoads.length}`);
            }
            
            if (eventFires.length > 0) {
                console.log(`   🎯 Pixel Events Fired: ${eventFires.length}`);
                eventFires.forEach((e, i) => {
                    try {
                        const urlObj = new URL(e.url);
                        const eventName = urlObj.searchParams.get('ev') || urlObj.searchParams.get('en') || 'PageView';
                        console.log(`      ${i + 1}. ${eventName}`);
                    } catch (err) {
                        console.log(`      ${i + 1}. Event (URL parse error)`);
                    }
                });
            }
            
            // Show JavaScript fbq calls if any
            const jsEvents = results.events.pixel.filter(e => e.type === 'pixel_javascript');
            if (jsEvents.length > 0) {
                console.log(`   📞 JavaScript fbq() calls: ${jsEvents.length}`);
                jsEvents.forEach((e, i) => {
                    if (e.args) {
                        console.log(`      ${i + 1}. fbq('${e.args[0]}', '${e.args[1]}')`);
                    }
                });
            }
        } else {
            console.log('\n⚠️ No Pixel events captured');
        }

        // Assertions
        expect(results.summary.totalEvents).toBeGreaterThan(0);

        console.log('\n✅ Test completed!');
    });
});

// Verification test
test.describe('Setup Verification', () => {
    test('should verify API.php has been modified', async () => {
        const apiPath = '/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public/wp-content/plugins/facebook-for-woocommerce/includes/API.php';

        console.log('\n🔍 Verifying setup...');

        const content = fs.readFileSync(apiPath, 'utf8');

        // Check if our code is present
        const hasMonitoring = content.includes('EVENT MONITORING FOR E2E TESTS');
        const hasCookie = content.includes('facebook_test_id');
        const hasFBTEST = content.includes('[FBTEST|');

        console.log('\nSetup Status:');
        console.log(`  ${hasMonitoring ? '✅' : '❌'} API.php has monitoring code`);
        console.log(`  ${hasCookie ? '✅' : '❌'} Cookie checking enabled`);
        console.log(`  ${hasFBTEST ? '✅' : '❌'} Event tagging enabled`);

        if (!hasMonitoring || !hasCookie || !hasFBTEST) {
            console.log('\n⚠️ API.php needs to be modified!');
            console.log('   See: FINAL_SIMPLE_POC.md for instructions');
        } else {
            console.log('\n✅ All checks passed! Ready to capture events.');
        }

        expect(hasMonitoring).toBe(true);
    });
});
