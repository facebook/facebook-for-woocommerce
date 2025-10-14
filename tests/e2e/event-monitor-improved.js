/**
 * 🎯 IMPROVED Event Monitor - Uses WordPress Hooks Instead of File Parsing
 *
 * This approach is MUCH better because:
 * 1. Uses WordPress action hooks - real-time capture
 * 2. Intercepts at the Facebook plugin level - before API call
 * 3. No file parsing overhead
 * 4. Captures complete event payload with all data
 * 5. Works for both Pixel and CAPI events
 */

const fs = require('fs');
const path = require('path');

class ImprovedEventMonitor {
    constructor(config = {}) {
        this.config = {
            eventsDir: config.eventsDir || path.join(__dirname, 'captured-events'),
            wordpressPath: config.wordpressPath || '/Users/nmadhav/Local Sites/wooc-local-test-sitecom/app/public',
            debug: config.debug || false
        };

        this.capturedEvents = [];
        this.testName = null;
        this.testStartTime = null;
    }

    /**
     * 🎯 KEY IMPROVEMENT: Hook into Facebook plugin at WordPress level
     * This is done via a custom WordPress plugin/mu-plugin
     */
    getWordPressHookCode() {
        return `<?php
/**
 * Plugin Name: Facebook Event Monitor
 * Description: Captures Facebook Pixel and CAPI events for e2e testing
 * Version: 1.0.0
 */

// Capture CAPI events BEFORE they're sent to Facebook
add_filter('wc_facebook_pixel_event_sent', function($event_data, $event_name) {
    // Get the current test name from a transient
    $current_test = get_transient('facebook_event_monitor_test');

    if ($current_test) {
        $captured_event = [
            'type' => 'capi',
            'event_name' => $event_name,
            'event_data' => $event_data,
            'timestamp' => microtime(true) * 1000,
            'test_name' => $current_test,
            'source' => 'wordpress_hook'
        ];

        // Store in a custom option (cleared after test)
        $captured = get_option('facebook_event_monitor_captured', []);
        $captured[] = $captured_event;
        update_option('facebook_event_monitor_captured', $captured, false);
    }

    return $event_data;
}, 10, 2);

// Capture Pixel events from the integration
add_action('wc_facebook_pixel_render', function($pixel_code, $event_name) {
    $current_test = get_transient('facebook_event_monitor_test');

    if ($current_test) {
        $captured_event = [
            'type' => 'pixel',
            'event_name' => $event_name,
            'pixel_code' => $pixel_code,
            'timestamp' => microtime(true) * 1000,
            'test_name' => $current_test,
            'source' => 'wordpress_hook'
        ];

        $captured = get_option('facebook_event_monitor_captured', []);
        $captured[] = $captured_event;
        update_option('facebook_event_monitor_captured', $captured, false);
    }
}, 10, 2);

// REST API endpoint to manage monitoring
add_action('rest_api_init', function() {
    register_rest_route('facebook-monitor/v1', '/start', [
        'methods' => 'POST',
        'callback' => function($request) {
            $test_name = $request->get_param('test_name');
            set_transient('facebook_event_monitor_test', $test_name, 3600);
            delete_option('facebook_event_monitor_captured');

            return ['success' => true, 'test_name' => $test_name];
        },
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('facebook-monitor/v1', '/stop', [
        'methods' => 'POST',
        'callback' => function($request) {
            $captured = get_option('facebook_event_monitor_captured', []);
            delete_transient('facebook_event_monitor_test');
            delete_option('facebook_event_monitor_captured');

            return [
                'success' => true,
                'events' => $captured,
                'count' => count($captured)
            ];
        },
        'permission_callback' => '__return_true'
    ]);

    register_rest_route('facebook-monitor/v1', '/events', [
        'methods' => 'GET',
        'callback' => function($request) {
            $captured = get_option('facebook_event_monitor_captured', []);
            return [
                'success' => true,
                'events' => $captured,
                'count' => count($captured)
            ];
        },
        'permission_callback' => '__return_true'
    ]);
});
`;
    }

    /**
     * Install the WordPress monitoring plugin
     */
    async installMonitorPlugin() {
        const muPluginsDir = path.join(this.config.wordpressPath, 'wp-content', 'mu-plugins');

        // Create mu-plugins directory if it doesn't exist
        if (!fs.existsSync(muPluginsDir)) {
            fs.mkdirSync(muPluginsDir, { recursive: true });
        }

        const pluginPath = path.join(muPluginsDir, 'facebook-event-monitor.php');
        const pluginCode = this.getWordPressHookCode();

        fs.writeFileSync(pluginPath, pluginCode);

        console.log('✅ Installed WordPress monitoring plugin at:', pluginPath);
        console.log('   This mu-plugin will automatically capture Facebook events');
    }

    /**
     * Start monitoring via WordPress REST API
     */
    async startCapture(testName, wordpressUrl = 'http://wooc-local-test-sitecom.local') {
        this.testName = testName;
        this.testStartTime = Date.now();

        try {
            const axios = require('axios');
            const response = await axios.post(`${wordpressUrl}/wp-json/facebook-monitor/v1/start`, {
                test_name: testName
            });

            console.log(`🔍 Started monitoring via WordPress hooks: ${testName}`);
            return response.data;
        } catch (error) {
            console.error('❌ Failed to start monitoring:', error.message);
            console.log('   Make sure the mu-plugin is installed');
            return null;
        }
    }

    /**
     * Stop monitoring and retrieve captured events from WordPress
     */
    async stopCapture(wordpressUrl = 'http://wooc-local-test-sitecom.local') {
        try {
            const axios = require('axios');
            const response = await axios.post(`${wordpressUrl}/wp-json/facebook-monitor/v1/stop`);

            const events = response.data.events || [];

            console.log(`✅ Stopped monitoring. Captured ${events.length} events`);

            // Save to file
            const results = {
                testName: this.testName,
                startTime: this.testStartTime,
                endTime: Date.now(),
                duration: Date.now() - this.testStartTime,
                events: events,
                summary: {
                    totalEvents: events.length,
                    capiEvents: events.filter(e => e.type === 'capi').length,
                    pixelEvents: events.filter(e => e.type === 'pixel').length
                }
            };

            // Ensure events directory exists
            if (!fs.existsSync(this.config.eventsDir)) {
                fs.mkdirSync(this.config.eventsDir, { recursive: true });
            }

            const filename = `events-${this.testName.replace(/[^a-zA-Z0-9]/g, '-')}-${Date.now()}.json`;
            const filepath = path.join(this.config.eventsDir, filename);

            fs.writeFileSync(filepath, JSON.stringify(results, null, 2));
            console.log(`💾 Saved events to: ${filename}`);

            return results;
        } catch (error) {
            console.error('❌ Failed to stop monitoring:', error.message);
            return null;
        }
    }

    /**
     * Capture Pixel events from browser (still useful for validation)
     */
    async capturePixelEvents(page) {
        // Inject script to capture fbq() calls
        await page.addInitScript(() => {
            if (typeof window !== 'undefined') {
                window.__fbEventCapture = [];

                const originalFbq = window.fbq || function() {};
                window.fbq = function(...args) {
                    window.__fbEventCapture.push({
                        type: 'pixel_browser',
                        args: args,
                        timestamp: Date.now(),
                        url: window.location.href,
                        source: 'browser_capture'
                    });

                    return originalFbq.apply(this, args);
                };

                Object.keys(originalFbq).forEach(key => {
                    window.fbq[key] = originalFbq[key];
                });
            }
        });

        console.log('📡 Browser pixel capture enabled');
    }

    /**
     * Get fbq calls from browser
     */
    async getFbqCalls(page) {
        try {
            const fbqCalls = await page.evaluate(() => {
                return window.__fbEventCapture || [];
            });

            return fbqCalls;
        } catch (error) {
            return [];
        }
    }
}

module.exports = ImprovedEventMonitor;
