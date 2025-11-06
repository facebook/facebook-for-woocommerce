/**
 * PixelCapture - Captures Pixel events from browser
 */

class PixelCapture {
    constructor(page, testId, eventName) {
        this.page = page;
        this.testId = testId;
        this.eventName = eventName;
        this.isCapturing = false;
    }

    /**
     * Wait for the specific Pixel event to be sent, capture it, and log it
     * Returns a promise that resolves when the event is captured and logged
     */
    async waitForEvent() {
        console.log(`🎯 Pixel capture started for: ${this.eventName}`);
        console.log(`⏳ Waiting for Pixel event: ${this.eventName}...`);

        // Dump cookies before waiting for event
        await this.dumpPageCookies('Before Event');

        try {
            // Wait for the facebook.com/tr response with our specific event
            const response = await this.page.waitForResponse(
                response => {
                    const url = response.url();
                    return url.includes('facebook.com/') &&
                            // url.includes('trigger') &&
                           url.includes(`ev=${this.eventName}`);
                },
                { timeout: 15000 } // 10 second timeout
            );

            console.log(`✅ Pixel event captured: ${this.eventName}`);

            try {
                // Parse and log the event
                const request = response.request();
                const eventData = this.parsePixelEvent(request.url());

                // Add response status to event data
                eventData.api_status = response.status();
                eventData.api_ok = response.ok();

                await this.logToServer(eventData);
                console.log(`   Event ID: ${eventData.eventId || 'none'}, API: ${response.status()}`);
            } catch (err) {
                console.error(`❌ Failed to log Pixel event: ${err.message}`);
                throw err;
            }

          } catch (err) {
            if (err.message && err.message.includes('Timeout')) {
                console.error(`❌ Timeout: Pixel event ${this.eventName} not captured within 15 seconds`);
                throw new Error(`Pixel event ${this.eventName} did not fire within timeout`);
            }
            console.error(`❌ Error capturing Pixel event: ${err.message}`);
            throw err;
        }
    }

    /**
     * Dump cookies from the page context
     */
    async dumpPageCookies(context = 'Current') {
        try {
            const cookies = await this.page.context().cookies();
            const fbCookies = cookies.filter(c =>
                c.name.startsWith('_fb') ||
                c.name.startsWith('fb') ||
                c.name === 'facebook_test_id'
            );

            if (fbCookies.length > 0) {
                console.log(`   [${context}] FB cookies: ${fbCookies.map(c => c.name).join(', ')}`);
            } else {
                console.log(`   [${context}] ⚠️ No FB cookies`);
            }
        } catch (err) {
            console.error(`   ❌ Error dumping cookies: ${err.message}`);
        }
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

        return {
            eventName: eventName,
            eventId: eventId,
            pixelId: pixelId,
            custom_data: customData,
            user_data: userData,
            timestamp: Date.now()
        };
    }

    /**
     * Log event to file - writes to pixel-{testId}.json
     */
    async logToServer(eventData) {
        const fs = require('fs').promises;
        const path = require('path');

        const capturedDir = path.join(__dirname, '../captured-events');
        const filePath = path.join(capturedDir, `pixel-${this.testId}.json`); // Match EventValidator path

        try {
            // Ensure directory exists
            await fs.mkdir(capturedDir, { recursive: true });

            // Load existing events
            let events = [];
            try {
                const contents = await fs.readFile(filePath, 'utf8');
                events = JSON.parse(contents);
            } catch (err) {
                if (err.code !== 'ENOENT') {
                    console.error(`⚠️  Warning: Could not read existing events: ${err.message}`);
                }
                // File doesn't exist yet, that's ok - will be created
            }

            // Append new pixel event
            events.push(eventData);

            // Write back
            await fs.writeFile(filePath, JSON.stringify(events, null, 2));
            console.log(`💾 Event logged to: ${filePath}`);
        } catch (err) {
            console.error(`❌ Failed to log Pixel event to file: ${err.message}`);
            throw err; // Re-throw so caller knows logging failed
        }
    }
}

module.exports = PixelCapture;
