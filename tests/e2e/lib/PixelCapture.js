/**
 * PixelCapture - Captures Pixel events from browser
 */

const config = require('../config/test-config');

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
        console.log(`🎯 Waiting for Pixel event: ${this.eventName}...`);

        // Track ALL facebook requests for debugging
        const allFBRequests = [];
        const requestListener = request => {
            const url = request.url();
            if (url.includes('facebook.com') || url.includes('facebook.net')) {
                allFBRequests.push({
                    url: url.substring(0, 200),
                    type: request.resourceType(),
                    method: request.method()
                });
                console.log(`   📡 FB Request: ${request.resourceType()} ${request.method()} ${url.substring(0, 100)}...`);
            }
        };

        this.page.on('request', requestListener);

        try {
            // Wait for the request (more reliable than waitForResponse for custom events)
            const request = await this.page.waitForRequest(
                request => {
                    const url = request.url();

                    // Must be a Facebook pixel request
                    if (!url.includes('facebook.com')) {
                        return false;
                    }

                    // Must be a tracking endpoint (/tr/ or /privacy_sandbox/pixel)
                    if (!url.includes('/tr/') && !url.includes('/privacy_sandbox/pixel')) {
                        return false;
                    }

                    // Check if URL contains our event name
                    const matches = url.includes(`ev=${this.eventName}`);
                    if (matches) {
                        console.log(`   ✅ MATCHED: ${url.substring(0, 150)}...`);
                    }
                    return matches;
                },
                { timeout: config.PIXEL_EVENT_TIMEOUT }
            );

            console.log(`✅ Pixel event captured: ${this.eventName}`);

            // Get the response to validate status
            const response = await request.response();
            const eventData = this.parsePixelEvent(request.url());

            // Add response status
            if (response) {
                eventData.api_status = response.status();
                eventData.api_ok = response.ok();

                if (!response.ok()) {
                    console.warn(`   ⚠️  Response status: ${response.status()} - Not OK`);
                }
            } else {
                eventData.api_status = 'N/A';
                eventData.api_ok = true;
            }

            console.log(`   Event ID: ${eventData.eventId || 'none'}, API: ${eventData.api_status}`);
            await this.logToServer(eventData);

        } catch (err) {
            // Remove listener before throwing
            this.page.off('request', requestListener);

            if (err.message?.includes('Timeout')) {
                console.error(`❌ Timeout: Pixel event ${this.eventName} not captured within ${config.PIXEL_EVENT_TIMEOUT}ms`);
                console.log(`   📊 Total FB requests captured: ${allFBRequests.length}`);
                if (allFBRequests.length > 0) {
                    console.log(`   📋 FB requests made:`);
                    allFBRequests.forEach(req => console.log(`      - ${req.type} ${req.method}: ${req.url}`));
                } else {
                    console.error(`   ⚠️  NO facebook requests were made at all!`);
                }
                throw new Error(`Pixel event ${this.eventName} did not fire within timeout`);
            }
            throw err;
        } finally {
            // Clean up listener
            this.page.off('request', requestListener);
        }
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
