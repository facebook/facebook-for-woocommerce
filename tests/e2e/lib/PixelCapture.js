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

        // Track ALL facebook.com requests for debugging
        const allFBRequests = [];
        this.page.on('request', request => {
            const url = request.url();
            if (url.includes('facebook.com') || url.includes('facebook.net')) {
                allFBRequests.push({
                    url: url.substring(0, 200),
                    type: request.resourceType(),
                    method: request.method()
                });
                console.log(`   [Request] FB: ${request.resourceType()} ${request.method()} ${url.substring(0, 150)}...`);
            }
        });

        try {
            // Wait for the facebook.com/tr response with our specific event
            // Modern pixel uses POST requests, so check both URL params and POST body
            const response = await this.page.waitForResponse(
                async response => {
                    const url = response.url();

                    // Must be a facebook.com/tr request
                    if (!url.includes('facebook.com/tr')) {
                        return false;
                    }

                    // Check GET request (legacy/noscript)
                    if (url.includes(`ev=${this.eventName}`)) {
                        console.log(`   [Response] FB GET: ${url.substring(0, 150)}... (✅ matches)`);
                        return true;
                    }

                    // Check POST request body (modern pixel)
                    const request = response.request();
                    if (request.method() === 'POST') {
                        try {
                            const postData = request.postData();
                            if (postData && postData.includes(this.eventName)) {
                                console.log(`   [Response] FB POST to /tr/ (✅ contains ${this.eventName})`);
                                return true;
                            }
                        } catch (err) {
                            // Sometimes postData() isn't available
                        }
                    }

                    return false;
                },
                { timeout: 15000 } // 15 second timeout
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
                console.log(`   [Debug] Total FB requests captured: ${allFBRequests.length}`);
                if (allFBRequests.length > 0) {
                    console.log(`   [Debug] FB requests made:`);
                    allFBRequests.forEach(req => console.log(`     - ${req.type} ${req.method}: ${req.url}`));
                } else {
                    console.error(`   ⚠️  NO facebook.com requests were made at all!`);
                }

                // Debug: Check what happened
                await this.debugTimeoutIssue();

                throw new Error(`Pixel event ${this.eventName} did not fire within timeout`);
            }
            console.error(`❌ Error capturing Pixel event: ${err.message}`);
            throw err;
        }
    }

    /**
     * Debug Pixel setup - Check if Pixel script is loaded
     */
    async debugPixelSetup() {
        try {
            const pixelInfo = await this.page.evaluate(() => {
                return {
                    fbqExists: typeof window.fbq !== 'undefined',
                    fbqVersion: window.fbq ? window.fbq.version : null,
                    pixelScriptInPage: document.documentElement.innerHTML.includes('facebook.com/tr'),
                    pixelScriptInHead: !!document.querySelector('script[src*="connect.facebook.net"]'),
                    hasPixelId: document.documentElement.innerHTML.includes('fbq(\'init\''),
                };
            });

            console.log(`   [Debug] fbq function exists: ${pixelInfo.fbqExists ? '✅' : '❌'}`);
            console.log(`   [Debug] Pixel script in page: ${pixelInfo.pixelScriptInPage ? '✅' : '❌'}`);
            console.log(`   [Debug] Pixel script loaded: ${pixelInfo.pixelScriptInHead ? '✅' : '❌'}`);
            console.log(`   [Debug] Pixel initialized: ${pixelInfo.hasPixelId ? '✅' : '❌'}`);

            if (!pixelInfo.fbqExists) {
                console.error(`   ⚠️  WARNING: fbq() function not found! Pixel won't fire.`);
            }
        } catch (err) {
            console.error(`   [Debug] Error checking pixel setup: ${err.message}`);
        }
    }

    /**
     * Debug timeout issue - Check what went wrong
     */
    async debugTimeoutIssue() {
        try {
            console.log(`\n   🔍 Debugging timeout issue...`);

            // Check console errors
            const consoleErrors = await this.page.evaluate(() => {
                // Can't access console history, but we can check for errors
                return window._pixelErrors || [];
            });

            // Check if any facebook requests were made at all
            console.log(`   [Debug] Checking if ANY facebook.com requests were made...`);

            // Re-check pixel setup
            const pixelInfo = await this.page.evaluate(() => {
                return {
                    fbqExists: typeof window.fbq !== 'undefined',
                    pageUrl: window.location.href,
                };
            });

            console.log(`   [Debug] Current URL: ${pixelInfo.pageUrl}`);
            console.log(`   [Debug] fbq still exists: ${pixelInfo.fbqExists ? 'Yes' : 'No'}`);

        } catch (err) {
            console.error(`   [Debug] Error during timeout debugging: ${err.message}`);
        }
    }

    /**
     * Dump cookies from the page context
     */
    async dumpPageCookies(context = 'Current') {
        try {
            const cookies = await this.page.context().cookies();
            const fbCookies = cookies.filter(c =>
                c.name.includes('_fb') ||
                c.name.includes('fb') ||
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
