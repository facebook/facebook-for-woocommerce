/**
 * Simple Event Validator
 *
 * Fetches events from Facebook Events Manager Test Events
 * and compares with locally captured CAPI events
 */

const https = require('https');

// Configuration
const PIXEL_ID = "21602405281339";
const ACCESS_TOKEN = "ACCESS_TOKEN" ;
const TEST_EVENT_CODE = "TEST27057"; // Pass test ID as argument

if (!PIXEL_ID || !ACCESS_TOKEN || !TEST_EVENT_CODE) {
    console.log('Usage: node validate-events.js <test-event-code>');
    console.log('Required env vars: FB_PIXEL_ID, FB_ACCESS_TOKEN');
    process.exit(1);
}

// Fetch test events from Facebook
function fetchFacebookEvents(testCode) {
    return new Promise((resolve, reject) => {
        const url = `https://graph.facebook.com/v21.0/${PIXEL_ID}/events?test_event_code=${testCode}&access_token=${ACCESS_TOKEN}`;

        https.get(url, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try {
                    resolve(JSON.parse(data));
                } catch (e) {
                    reject(e);
                }
            });
        }).on('error', reject);
    });
}

// Main validation
async function validate() {
    console.log(`\n🔍 Validating Test ID: ${TEST_EVENT_CODE}`);
    console.log('='.repeat(60));

    try {
        const response = await fetchFacebookEvents(TEST_EVENT_CODE);

        if (response.error) {
            console.error('❌ Facebook API Error:', response.error.message);
            process.exit(1);
        }

        const fbEvents = response.data || [];
        console.log(`\n✅ Facebook Events Received: ${fbEvents.length}`);

        if (fbEvents.length > 0) {
            console.log('\nEvent Types:');
            fbEvents.forEach((event, i) => {
                console.log(`  ${i+1}. ${event.event_name} (${new Date(event.event_time * 1000).toISOString()})`);
            });
        } else {
            console.log('\n⚠️  No events found in Facebook Events Manager');
            console.log('    Make sure:');
            console.log('    1. Cookie facebook_test_id was set correctly');
            console.log('    2. Events were triggered on the site');
            console.log('    3. Wait a few seconds for events to sync');
        }

    } catch (error) {
        console.error('❌ Validation failed:', error.message);
        process.exit(1);
    }
}

validate();
