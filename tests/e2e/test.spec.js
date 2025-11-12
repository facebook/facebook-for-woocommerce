/**
 * Facebook Events Test - Validates Pixel + CAPI deduplication
 */

const { test, expect } = require('@playwright/test');
const TestSetup = require('./lib/TestSetup');
const EventValidator = require('./lib/EventValidator');

// DIAGNOSTIC TEST - Check if pixel code exists in HTML
test('DIAGNOSTIC: Pixel code in HTML', async ({ page }) => {
    await TestSetup.login(page);
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    const html = await page.content();

    console.log('\n🔍 DIAGNOSTIC: Checking HTML for pixel code...');
    const hasInit = html.includes("fbq('init'") || html.includes('fbq("init"');
    // Match PageView with flexible whitespace (multiline formatting)
    const hasTrackPageView = /fbq\s*\(\s*['"](track|pageview)['"]\s*,\s*['"]PageView['"]/i.test(html);
    const hasFbScript = html.includes('connect.facebook.net');
    const hasPageView = html.includes('PageView');
    // const haspageview = html.includes('pageview');


    console.log(`   Pixel script (connect.facebook.net): ${hasFbScript ? '✅ YES' : '❌ NO'}`);
    console.log(`   fbq('init'): ${hasInit ? '✅ YES' : '❌ NO'}`);
    console.log(`   fbq('track', 'PageView'): ${hasTrackPageView ? '✅ YES' : '❌ NO'}`);
    console.log(`PageView: ${hasPageView ? '✅ YES' : '❌ NO'}`);
    // console.log(`pageview: ${haspageview ? '✅ YES' : '❌ NO'}`);


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

    // Capture console logs and errors (filter out the traffic permission warning)
    page.on('console', msg => {
        const text = msg.text();
        if (!text.includes('traffic permission settings')) {
            console.log(`   [Browser ${msg.type()}] ${text}`);
        }
    });
    page.on('pageerror', err => console.error(`   [Browser Error] ${err.message}`));

    // BYPASS: Intercept Facebook's config request and replace domain=localhost
    await page.route('**/connect.facebook.net/**', async (route) => {
        const request = route.request();
        let url = request.url();

        // Replace domain=localhost with whitelisted domain
        if (url.includes('domain=localhost')) {
            url = url.replace(/domain=localhost/g, 'domain=wooc-local-test-sitecom.local');
            console.log(`   [BYPASS] Modified FB config URL to use whitelisted domain`);
        }

        await route.continue({ url });
    });

    await Promise.all([
        pixelCapture.waitForEvent(),
        page.goto('/').then(async () => {
            await page.waitForLoadState('networkidle');
            await page.waitForFunction(() => typeof jQuery !== 'undefined' && jQuery.isReady);
            await page.waitForTimeout(1000);

            // Debug: Check what fbq actually did
            const fbqDebug = await page.evaluate(() => {
                return {
                    exists: typeof window.fbq !== 'undefined',
                    loaded: window.fbq?.loaded,
                    queue: window.fbq?.queue?.length || 0
                };
            });
            console.log(`   [fbq status]`, fbqDebug);
        })
    ]);

    const validator = new EventValidator(testId);
    const result = await validator.validate('PageView', page);

    TestSetup.logResult('PageView', result);
    expect(result.passed).toBe(true);
});

// test('ViewContent', async ({ page }) => {
//     // TODO needs to have an existing product
//     const { testId, pixelCapture } = await TestSetup.init(page, 'ViewContent');

//     await Promise.all([
//         pixelCapture.waitForEvent(),
//         page.goto('/product/testp/')
//     ]);

//     const validator = new EventValidator(testId);
//     const result = await validator.validate('ViewContent', page);

//     TestSetup.logResult('ViewContent', result);
//     expect(result.passed).toBe(true);
// });

// test('AddToCart', async ({ page }) => {
//     // TODO needs to have an existing product
//     const { testId, pixelCapture } = await TestSetup.init(page, 'AddToCart');

//     await page.goto('/product/testp/');

//     await Promise.all([
//         pixelCapture.waitForEvent(),
//         page.click('.single_add_to_cart_button')
//     ]);

//     const validator = new EventValidator(testId);
//     const result = await validator.validate('AddToCart', page);

//     TestSetup.logResult('AddToCart', result);
//     expect(result.passed).toBe(true);
// });

// test('ViewCategory - DEBUG', async ({ page }) => {
//     const { testId } = await TestSetup.init(page, 'ViewCategory');

//     // Listen to ALL facebook pixel events to see what's actually firing
//     const pixelEvents = [];
//     page.on('response', async (response) => {
//         const url = response.url();
//         if (url.includes('facebook.com/')) {
//             const request = response.request();
//             const urlObj = new URL(url);
//             const eventName = urlObj.searchParams.get('ev');
//             const method = request.method();

//             console.log(`\n📡 Pixel event fired: ${eventName || 'NONE'}`);
//             console.log(`   Method: ${method}`);
//             console.log(`   Endpoint: ${urlObj.pathname}`);
//             console.log(`   Full URL: ${url}`);

//             // Check if it's a GET request with an image tag (common for pixel tracking)
//             if (url.includes('/tr/') && !eventName) {
//                 console.log(`   ⚠️  This /tr/ request has NO ev parameter!`);
//                 // Check all query params
//                 console.log(`   All params:`, Array.from(urlObj.searchParams.entries()));
//             }

//             pixelEvents.push({ eventName: eventName || 'NONE', url, endpoint: urlObj.pathname, method });
//         }
//     });

//     // Navigate to category page
//     await page.goto('/product-category/uncategorized/');
//     await page.waitForLoadState('networkidle');

//     console.log(`\n📊 Total Pixel events captured: ${pixelEvents.length}`);
//     pixelEvents.forEach(e => console.log(`   - ${e.eventName} (${e.method} ${e.endpoint})`));

//     // For now, just check if any events fired
//     expect(pixelEvents.length).toBeGreaterThan(0);
// });

// test.skip('ViewCategory', async ({ page }) => {
//     const { testId, pixelCapture } = await TestSetup.init(page, 'ViewCategory');

//     await Promise.all([
//         pixelCapture.waitForEvent(),
//         page.goto('/product-category/uncategorized/')
//     ]);
//     const validator = new EventValidator(testId);
//     const result = await validator.validate('ViewCategory', page);

//     TestSetup.logResult('ViewCategory', result);
//     expect(result.passed).toBe(true);
// });
