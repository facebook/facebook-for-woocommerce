/**
 * SimpleFacebookMonitor - Utility class for capturing Facebook events
 *
 * This is a shared utility used by multiple test files
 */

const fs = require('fs');
const path = require('path');
const config = require('../config/test-config');

class SimpleFacebookMonitor {
    constructor() {
        this.debugLogPath = config.DEBUG_LOG_PATH;
        this.testId = null;
        this.initialLogSize = 0;
        this.pixelNetworkEvents = [];
    }

    async startCapture(page, testName) {
        this.testId = `${testName}-${Date.now()}`;
        
        await page.context().addCookies([{
            name: 'facebook_test_id',
            value: this.testId,
            domain: config.SITE_DOMAIN,
            path: '/'
        }]);

        if (fs.existsSync(this.debugLogPath)) {
            this.initialLogSize = fs.statSync(this.debugLogPath).size;
        }

        // Capture pixel network events
        page.on('request', request => {
            const url = request.url();
            if (url.includes('facebook.com/tr') || url.includes('connect.facebook.net')) {
                this.pixelNetworkEvents.push({
                    type: 'pixel_network',
                    url: url,
                    method: request.method(),
                    timestamp: Date.now(),
                    testId: this.testId
                });
            }
        });

        console.log(`\n🔍 Started monitoring: ${this.testId}`);
    }

    async stopCapture(page) {
        const pixelEvents = this.pixelNetworkEvents || [];
        const capiEvents = this.parseDebugLog();

        const results = {
            testId: this.testId,
            timestamp: new Date().toISOString(),
            events: {
                pixel: pixelEvents,
                capi: capiEvents
            },
            summary: {
                totalEvents: pixelEvents.length + capiEvents.length,
                pixelEvents: pixelEvents.length,
                capiEvents: capiEvents.length
            }
        };

        this.saveResults(results);
        return results;
    }

    parseDebugLog() {
        if (!fs.existsSync(this.debugLogPath)) return [];

        const currentSize = fs.statSync(this.debugLogPath).size;
        const newBytes = currentSize - this.initialLogSize;
        if (newBytes <= 0) return [];

        const buffer = Buffer.alloc(newBytes);
        const fd = fs.openSync(this.debugLogPath, 'r');
        fs.readSync(fd, buffer, 0, newBytes, this.initialLogSize);
        fs.closeSync(fd);

        const newContent = buffer.toString('utf8');

        return newContent
            .split('\n')
            .filter(line => line.includes(`[FBTEST|${this.testId}]`))
            .map(line => {
                const parts = line.split('|');
                if (parts.length >= 5) {
                    try {
                        return {
                            type: 'capi',
                            eventName: parts[2],
                            eventId: parts[3],
                            eventData: JSON.parse(parts[4]),
                            testId: this.testId,
                            timestamp: Date.now()
                        };
                    } catch (e) {
                        return null;
                    }
                }
                return null;
            })
            .filter(Boolean);
    }

    saveResults(results) {
        const eventsDir = path.join(__dirname, '..', 'captured-events');
        if (!fs.existsSync(eventsDir)) {
            fs.mkdirSync(eventsDir, { recursive: true });
        }

        const filename = `events-${this.testId}.json`;
        fs.writeFileSync(path.join(eventsDir, filename), JSON.stringify(results, null, 2));
        console.log(`💾 Saved: ${filename}`);
    }
}

module.exports = SimpleFacebookMonitor;
