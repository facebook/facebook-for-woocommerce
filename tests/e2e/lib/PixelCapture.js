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
     * Wait for the specific Pixel event to be sent and captured
     * Returns a promise that resolves when the event is captured
     */
    async waitForEvent() {
        console.log(`  🎯 Waiting for Pixel event: ${this.eventName}`);

        // Set up request/response listeners BEFORE waiting
        const requests = [];
        const responses = [];
        
        const requestListener = (request) => {
            if (request.url().includes('facebook.com/tr')) {
                requests.push(request.url());
                console.log(`DEBUG_E2E: 🔵 Pixel REQUEST: ${request.url().substring(0, 100)}...`);
            }
        };
        
        const responseListener = (response) => {
            if (response.url().includes('facebook.com/tr')) {
                responses.push({ url: response.url(), status: response.status() });
                console.log(`DEBUG_E2E: 🟢 Pixel RESPONSE: ${response.status()}`);
            }
        };
        
        this.page.on('request', requestListener);
        this.page.on('response', responseListener);

        try {
            // Wait for the facebook.com/tr response with our specific event
            const response = await this.page.waitForResponse(
                response => {
                    const url = response.url();
                    const matches = url.includes('facebook.com/tr') &&
                                  url.includes(`ev=${this.eventName}`) &&
                                  response.status() === 200;
                    
                    if (url.includes('facebook.com/tr')) {
                        console.log(`DEBUG_E2E: Checking response - event: ${url.includes(`ev=${this.eventName}`)}, status: ${response.status()}`);
                    }
                    
                    return matches;
                },
                { timeout: 15000 } // 15 second timeout
            );

            console.log(`✅ Pixel event captured: ${this.eventName}`);

            // Parse and log the event
            const request = response.request();
            const eventData = this.parsePixelEvent(request.url());

            // Add response status to event data
            eventData.api_status = response.status();
            eventData.api_ok = response.ok();

            await this.logToServer(eventData);
            console.log(`   Event ID: ${eventData.eventId || 'none'}, API: ${response.status()}`);

            return response;
        } catch (err) {
            console.error(`❌ Timeout waiting for Pixel event: ${this.eventName}`);
            console.error(`   Total facebook.com/tr requests: ${requests.length}`);
            console.error(`   Total facebook.com/tr responses: ${responses.length}`);
            
            // Check if fbq was called but requests were blocked
            const fbqCalls = await this.page.evaluate(() => {
                return window._fbq_test_calls || [];
            });
            console.error(`   fbq() calls detected: ${fbqCalls.length}`);
            
            throw err;
        } finally {
            this.page.off('request', requestListener);
            this.page.off('response', responseListener);
        }
    }

    /**
     * Start capturing Pixel events (for debugging)
     */
    async start() {
        if (this.isCapturing) {
            return;
        }

        this.isCapturing = true;

        // Log request failures for debugging
        this.page.on('requestfailed', (request) => {
            const url = request.url();
            if (url.includes('facebook.com')) {
                console.log(`DEBUG_E2E: ❌ Facebook REQUEST FAILED: ${url.substring(0, 100)}... Error: ${request.failure().errorText}`);
            }
        });

        // Also check for console errors
        this.page.on('console', msg => {
            if (msg.type() === 'error') {
                console.log(`DEBUG_E2E: 🔴 Console Error: ${msg.text()}`);
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
