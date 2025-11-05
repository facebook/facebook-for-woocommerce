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
    constructor(page, testId) {
        this.page = page;
        this.testId = testId;
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

        // Capture Pixel events and responses
        this.page.on('response', async (response) => {
            if (!this.isCapturing) return;

            const url = response.url();
            if (url.includes('facebook.com/tr')) {
                console.log('✅ Pixel event captured');

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
     * Log event using Logger.php (direct PHP call from Node.js)
     */
    async logToServer(eventData) {
        const { exec } = require('child_process');
        const { promisify } = require('util');
        const execAsync = promisify(exec);
        const path = require('path');

        const loggerPath = path.join(__dirname, 'Logger.php');
        const jsonData = JSON.stringify({
            testId: this.testId,
            eventType: 'pixel',
            eventData: eventData
        }).replace(/"/g, '\\"'); // Escape quotes for shell

        try {
            await execAsync(`php -r "require_once('${loggerPath}'); E2E_Event_Logger::log_event('${this.testId}', 'pixel', json_decode('${jsonData}', true)['eventData']);"`);
        } catch (err) {
            console.error('Failed to log event via Logger.php:', err.message);
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
