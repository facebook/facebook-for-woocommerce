/**
 * PixelCapture - Reusable helper to capture Pixel events from browser
 *
 * Usage in tests:
 *   const capture = new PixelCapture(page, testId);
 *   await capture.start();
 *   // ... test actions ...
 *   await capture.stop();
 */

class PixelCapture {
    constructor(page, testId, eventName) {
        this.page = page;
        this.testId = testId;
        this.eventName = eventName;
        this.isCapturing = false;
    }

    /**
     * Start capturing Pixel events
     */
    async start() {
        if (this.isCapturing) {
            console.warn('⚠️  Already capturing Pixel events');
            return;
        }

        this.isCapturing = true;
        console.log(`  🎯 Filtering for event: ${this.eventName}`);

        // Capture Pixel REQUESTS (before they're sent)
        this.page.on('request', async (request) => {
            if (!this.isCapturing) return;

            const url = request.url();

            // DEBUG: Log all facebook.com requests
            if (url.includes('facebook.com')) {
                console.log(`DEBUG_E2E: 🔵 Facebook REQUEST: ${url.substring(0, 150)}...`);
            }

            // Check if this is our pixel event
            if (url.includes('facebook.com/tr') && url.includes(`ev=${this.eventName}`)) {
                console.log('✅ Pixel event REQUEST captured (waiting for response...)');
            }
        });

        // Capture Pixel RESPONSES
        this.page.on('response', async (response) => {
            if (!this.isCapturing) return;

            const url = response.url();

            // DEBUG: Log all facebook.com responses
            if (url.includes('facebook.com')) {
                console.log(`DEBUG_E2E: 🟢 Facebook RESPONSE: ${url.substring(0, 150)}... [${response.status()}]`);
            }

            // Filter by facebook.com/tr AND ev parameter matching expected event
            if (url.includes('facebook.com/tr') && url.includes(`ev=${this.eventName}`)) {
                console.log('✅ Pixel event RESPONSE captured');

                try {
                    const request = response.request();
                    const eventData = this.parsePixelEvent(request.url());

                    // Add response status to event data
                    eventData.api_status = response.status();
                    eventData.api_ok = response.ok();

                    await this.logToServer(eventData);
                    console.log(`   Event: ${eventData.eventName}, ID: ${eventData.eventId || 'none'}, API: ${response.status()}`);
                } catch (err) {
                    console.error('❌ Failed to log Pixel event:', err.message);
                }
            }
        });

        console.log('🎬 Pixel capture started');
    }

    /**
     * Stop capturing Pixel events
     */
    async stop() {
        this.isCapturing = false;
        console.log('🛑 Pixel capture stopped');
    }

    /**
     * Parse Pixel event from URL
     */
    parsePixelEvent(url) {
        const urlObj = new URL(url);

        // Extract basic fields
        const eventName = urlObj.searchParams.get('ev') || 'Unknown';
        const eventId = urlObj.searchParams.get('eid') || null;
        const pixelId = urlObj.searchParams.get('id') || 'Unknown';

        // Extract custom_data (cd[...]) and user_data (ud[...])
        const customData = {};
        const userData = {};

        urlObj.searchParams.forEach((value, key) => {
            if (key.startsWith('cd[')) {
                const cdKey = key.replace('cd[', '').replace(']', '');
                const decodedValue = decodeURIComponent(value);

                // Try to parse as JSON, otherwise keep as string
                try {
                    customData[cdKey] = JSON.parse(decodedValue);
                } catch {
                    // Check if it's a number
                    if (!isNaN(decodedValue) && decodedValue !== '') {
                        customData[cdKey] = parseFloat(decodedValue);
                    } else {
                        customData[cdKey] = decodedValue;
                    }
                }
            } else if (key.startsWith('ud[')) {
                const udKey = key.replace('ud[', '').replace(']', '');
                userData[udKey] = decodeURIComponent(value);
            }
        });

        // Extract fbp (Facebook Browser ID) from top-level parameter
        const fbp = urlObj.searchParams.get('fbp');
        if (fbp) {
            userData.fbp = fbp;
        }

        // Extract user_data from custom_data if present (it's sent as cd[user_data])
        let finalUserData = userData;
        if (customData.user_data) {
            // Merge cd[user_data] with ud[] (Advanced Matching)
            finalUserData = { ...customData.user_data, ...userData };
            delete customData.user_data; // Remove from custom_data since we extracted it
        }

        return {
            eventName: eventName,
            eventId: eventId,
            pixelId: pixelId,
            custom_data: customData,
            user_data: finalUserData,
            timestamp: Date.now()
        };
    }

    /**
     * Log event to separate pixel file (no race condition with CAPI)
     */
    async logToServer(eventData) {
        const fs = require('fs').promises;
        const path = require('path');

        console.log(`DEBUG_E2E: Logging pixel event for testId: ${this.testId}`); // DEBUG_E2E

        const capturedDir = path.join(__dirname, '../captured-events');

        try {
            await fs.mkdir(capturedDir, { recursive: true });
            console.log(`DEBUG_E2E: Using directory: ${capturedDir}`); // DEBUG_E2E
        } catch (err) {
            console.error(`DEBUG_E2E: Cannot create dir: ${err.message}`); // DEBUG_E2E
            return;
        }

        const filePath = path.join(capturedDir, `pixel-${this.testId}.json`);
        console.log(`DEBUG_E2E: Writing to: ${filePath}`); // DEBUG_E2E

        try {
            let events = [];
            try {
                const contents = await fs.readFile(filePath, 'utf8');
                events = JSON.parse(contents);
            } catch {}

            events.push(eventData);
            await fs.writeFile(filePath, JSON.stringify(events, null, 2));
            console.log(`DEBUG_E2E: Event logged successfully`); // DEBUG_E2E
        } catch (err) {
            console.error(`DEBUG_E2E: Write failed: ${err.message}`); // DEBUG_E2E
        }
    }
    // HTTP call to logger
    // async logToServer(eventData) {
    //     await this.page.evaluate(async ({ testId, eventData }) => {
    //         await fetch('/wp-content/plugins/facebook-for-woocommerce/tests/e2e/lib/Logger.php', {
    //             method: 'POST',
    //             headers: { 'Content-Type': 'application/json' },
    //             body: JSON.stringify({
    //                 testId: testId,
    //                 eventType: 'pixel',
    //                 eventData: eventData
    //             })
    //         });
    //     }, { testId: this.testId, eventData });
    // }

}

module.exports = PixelCapture;
