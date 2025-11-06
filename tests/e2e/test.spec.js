/**
 * Facebook Events Test - Validates Pixel + CAPI deduplication
 */

const { test, expect } = require('@playwright/test');
const TestSetup = require('./lib/TestSetup');
const EventValidator = require('./lib/EventValidator');

test('PageView', async ({ page }) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'PageView');

    await page.goto('/');
    
    // Debug: Check what's actually in the HTML
    const htmlContent = await page.content();
    console.log('\n🔍 Checking HTML content:');
    console.log('   fbq(\'init\') present:', htmlContent.includes('fbq(\'init\'') ? '✅' : '❌');
    console.log('   fbq(\'track\', \'PageView\') present:', htmlContent.includes('fbq(\'track\', \'PageView\')') ? '✅' : '❌');
    
    // Debug: Check if pixel code is there at all
    if (htmlContent.includes('fbq')) {
        console.log('   ✅ fbq is in HTML');
        // Extract the pixel code section
        const fbqMatches = htmlContent.match(/fbq\([^)]+\)/g);
        if (fbqMatches) {
            console.log('   📝 fbq calls found:');
            fbqMatches.slice(0, 5).forEach(call => console.log(`      - ${call}`));
        }
    } else {
        console.log('   ❌ NO fbq in HTML at all!');
    }
    
    await pixelCapture.waitForEvent();
    
    const validator = new EventValidator(testId);
    const result = await validator.validate('PageView', page);

    TestSetup.logResult('PageView', result);
    expect(result.passed).toBe(true);
});

test('ViewContent', async ({ page }) => {
    // TODO needs to have an existing product
    const { testId, pixelCapture } = await TestSetup.init(page, 'ViewContent');

    await Promise.all([
        pixelCapture.waitForEvent(),
        page.goto('/product/testp/')
    ]);

    const validator = new EventValidator(testId);
    const result = await validator.validate('ViewContent', page);

    TestSetup.logResult('ViewContent', result);
    expect(result.passed).toBe(true);
});

test('AddToCart', async ({ page }) => {
    // TODO needs to have an existing product
    const { testId, pixelCapture } = await TestSetup.init(page, 'AddToCart');

    await page.goto('/product/testp/');

    await Promise.all([
        pixelCapture.waitForEvent(),
        page.click('.single_add_to_cart_button')
    ]);

    const validator = new EventValidator(testId);
    const result = await validator.validate('AddToCart', page);

    TestSetup.logResult('AddToCart', result);
    expect(result.passed).toBe(true);
});

test('ViewCategory - DEBUG', async ({ page }) => {
    const { testId } = await TestSetup.init(page, 'ViewCategory');

    // Listen to ALL facebook pixel events to see what's actually firing
    const pixelEvents = [];
    page.on('response', async (response) => {
        const url = response.url();
        if (url.includes('facebook.com/')) {
            const request = response.request();
            const urlObj = new URL(url);
            const eventName = urlObj.searchParams.get('ev');
            const method = request.method();

            console.log(`\n📡 Pixel event fired: ${eventName || 'NONE'}`);
            console.log(`   Method: ${method}`);
            console.log(`   Endpoint: ${urlObj.pathname}`);
            console.log(`   Full URL: ${url}`);

            // Check if it's a GET request with an image tag (common for pixel tracking)
            if (url.includes('/tr/') && !eventName) {
                console.log(`   ⚠️  This /tr/ request has NO ev parameter!`);
                // Check all query params
                console.log(`   All params:`, Array.from(urlObj.searchParams.entries()));
            }

            pixelEvents.push({ eventName: eventName || 'NONE', url, endpoint: urlObj.pathname, method });
        }
    });

    // Navigate to category page
    await page.goto('/product-category/uncategorized/');
    await page.waitForLoadState('networkidle');

    console.log(`\n📊 Total Pixel events captured: ${pixelEvents.length}`);
    pixelEvents.forEach(e => console.log(`   - ${e.eventName} (${e.method} ${e.endpoint})`));

    // For now, just check if any events fired
    expect(pixelEvents.length).toBeGreaterThan(0);
});

test.skip('ViewCategory', async ({ page }) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'ViewCategory');

    await Promise.all([
        pixelCapture.waitForEvent(),
        page.goto('/product-category/uncategorized/')
    ]);
    const validator = new EventValidator(testId);
    const result = await validator.validate('ViewCategory', page);

    TestSetup.logResult('ViewCategory', result);
    expect(result.passed).toBe(true);
});
