const { test, expect } = require('@playwright/test');

test('simplest test - capture pixel and capi events', async ({ page }) => {
    console.log('\n🧪 SIMPLEST TEST - Visit homepage');

    const testId = `test-${Date.now()}`;
    const pixelEvents = [];

    // Set cookie so PHP can log CAPI events with this test ID
    await page.context().addCookies([{
        name: 'facebook_test_id',
        value: testId,
        domain: 'wooc-local-test-sitecom.local',
        path: '/'
    }]);

    console.log(`🔍 Test ID: ${testId}`);

    // Capture pixel network requests and log them via PHP
    page.on('request', async (request) => {
        const url = request.url();
        if (url.includes('facebook.com/tr')) {
            console.log('✅ Pixel event captured:', url.substring(0, 150) + '...');

            // Parse event details from URL
            const urlObj = new URL(url);
            const eventName = urlObj.searchParams.get('ev') || 'Unknown';
            const eventId = urlObj.searchParams.get('eid') || 'unknown';

            pixelEvents.push({
                url: url,
                eventName: eventName,
                eventId: eventId,
                timestamp: Date.now(),
                testId: testId
            });

            // Send to PHP to log in debug.log
            try {
                await page.evaluate(async (data) => {
                    await fetch('/wp-content/plugins/facebook-for-woocommerce/tests/e2e/log-pixel-event.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                }, { testId, eventName, eventId, url });
            } catch (e) {
                console.log('Failed to log pixel event:', e.message);
            }
        }
    });

    // Visit homepage
    await page.goto('/');
    await page.waitForTimeout(2000);

    // Check results
    console.log(`\n📊 Pixel events captured: ${pixelEvents.length}`);
    console.log(`💡 Check debug.log for CAPI events with ID: ${testId}`);

    expect(pixelEvents.length).toBeGreaterThan(0);
});
