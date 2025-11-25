/**
 * Facebook Events Test - Validates Pixel + CAPI deduplication
 */

const { test, expect } = require('@playwright/test');
const TestSetup = require('./lib/TestSetup');
const EventValidator = require('./lib/EventValidator');
const config = require('./config/test-config');

// DIAGNOSTIC TEST - Check if pixel code exists in HTML
test('DIAGNOSTIC: Pixel code in HTML', async ({ page }) => {
    await TestSetup.login(page);
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    const html = await page.content();

    console.log('\n🔍 DIAGNOSTIC: Checking HTML for pixel code...');
    const hasInit = html.includes("fbq('init'") || html.includes('fbq("init"');
    const hasTrackPageView = /fbq\s*\(\s*['"](track|pageview)['"]\s*,\s*['"]PageView['"]/i.test(html);
    const hasFbScript = html.includes('connect.facebook.net');
    const hasPageView = html.includes('PageView');

    console.log(`   Pixel script (connect.facebook.net): ${hasFbScript ? '✅ YES' : '❌ NO'}`);
    console.log(`   fbq('init'): ${hasInit ? '✅ YES' : '❌ NO'}`);
    console.log(`   fbq('track', 'PageView'): ${hasTrackPageView ? '✅ YES' : '❌ NO'}`);
    console.log(`   PageView: ${hasPageView ? '✅ YES' : '❌ NO'}`);

    if (!hasInit || !hasTrackPageView) {
        console.log('\n❌ PIXEL CODE NOT FOUND IN HTML');
        console.log('   This means the plugin is not rendering the tracking code at all.');
        console.log('   Check: Plugin active? Settings saved? Theme has wp_head()?');
    }

    expect(hasInit).toBe(true);
    expect(hasTrackPageView).toBe(true);
});

test('PageView', async ({ page }) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'PageView');

    await Promise.all([
        pixelCapture.waitForEvent(),
        page.goto('/').then(() => TestSetup.waitForPageReady(page))
    ]);

    const validator = new EventValidator(testId);
    const result = await validator.validate('PageView', page);

    TestSetup.logResult('PageView', result);
    expect(result.passed).toBe(true);
});

test('ViewContent', async ({ page }) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'ViewContent');

    console.log(`   🌐 Navigating to: ${config.TEST_PRODUCT_URL}`);
    
    await Promise.all([
        pixelCapture.waitForEvent(),
        page.goto(config.TEST_PRODUCT_URL).then(async (response) => {
            console.log(`   📄 Page loaded: ${response.status()} ${response.url()}`);
            
            // Check if pixel code exists on this page
            const hasPixelCode = await page.evaluate(() => {
                return {
                    fbqExists: typeof window.fbq !== 'undefined',
                    hasFbScript: document.documentElement.innerHTML.includes('connect.facebook.net'),
                    hasInit: document.documentElement.innerHTML.includes("fbq('init'"),
                };
            });
            
            console.log(`   🔍 Pixel on product page:`, hasPixelCode);
            
            await TestSetup.waitForPageReady(page);
        })
    ]);

    const validator = new EventValidator(testId);
    const result = await validator.validate('ViewContent', page);

    TestSetup.logResult('ViewContent', result);
    expect(result.passed).toBe(true);
});

test('AddToCart', async ({ page }) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'AddToCart');

    await page.goto(config.TEST_PRODUCT_URL);
    await TestSetup.waitForPageReady(page, 500);

    await Promise.all([
        pixelCapture.waitForEvent(),
        page.click('.single_add_to_cart_button').then(async () => {
            await page.waitForTimeout(1000);
        })
    ]);

    const validator = new EventValidator(testId);
    const result = await validator.validate('AddToCart', page);

    TestSetup.logResult('AddToCart', result);
    expect(result.passed).toBe(true);
});

test('ViewCategory', async ({ page }) => {
    const { testId, pixelCapture } = await TestSetup.init(page, 'ViewCategory');

    await Promise.all([
        pixelCapture.waitForEvent(),
        page.goto(config.TEST_CATEGORY_URL).then(() => TestSetup.waitForPageReady(page))
    ]);

    const validator = new EventValidator(testId);
    const result = await validator.validate('ViewCategory', page);

    TestSetup.logResult('ViewCategory', result);
    expect(result.passed).toBe(true);
});
