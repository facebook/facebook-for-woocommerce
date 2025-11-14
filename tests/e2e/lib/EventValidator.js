/**
 * EventValidator - Load and validate captured events
 */

const fs = require('fs').promises;
const path = require('path');
const EVENT_SCHEMAS = require('./event-schemas');

class EventValidator {
    constructor(testId) {
        this.testId = testId;
        this.filePath = path.join(__dirname, '../captured-events', `${testId}.json`);
        this.events = null;
    }

    async load() {
        // Load from separate pixel and capi files
        const pixelFilePath = path.join(__dirname, '../captured-events', `pixel-${this.testId}.json`);
        const capiFilePath = path.join(__dirname, '../captured-events', `capi-${this.testId}.json`);

        let pixelEvents = [];
        let capiEvents = [];

        // Load pixel events
        try {
            const pixelData = await fs.readFile(pixelFilePath, 'utf8');
            pixelEvents = JSON.parse(pixelData);
            console.log(`✅ Loaded pixel events from: ${pixelFilePath}`);
        } catch (err) {
            if (err.code === 'ENOENT') {
                console.log(`⚠️  Pixel events file not found: ${pixelFilePath}`);
            } else {
                console.error(`❌ Error reading pixel events: ${err.message}`);
            }
        }

        // Load capi events
        try {
            const capiData = await fs.readFile(capiFilePath, 'utf8');
            capiEvents = JSON.parse(capiData);
            console.log(`✅ Loaded CAPI events from: ${capiFilePath}`);
        } catch (err) {
            if (err.code === 'ENOENT') {
                console.log(`⚠️  CAPI events file not found: ${capiFilePath}`);
            } else {
                console.error(`❌ Error reading CAPI events: ${err.message}`);
            }
        }

        this.events = {
            testId: this.testId,
            pixel: pixelEvents,
            capi: capiEvents
        };

        return this.events;
    }

    async validate(eventName, page = null) {
        if (!this.events) await this.load();
        await this.checkDebugLog();

        console.log(`\n  🔍 Validating ${eventName}...`);

        const schema = EVENT_SCHEMAS[eventName];
        if (!schema) throw new Error(`No schema for: ${eventName}`);

        const pixel = this.events.pixel.filter(e => e.eventName === eventName);
        const capi = this.events.capi.filter(e => e.event_name === eventName);

        console.log(`   Pixel events found: ${pixel.length}`);
        console.log(`   CAPI events found: ${capi.length}`);

        const errors = [];

        if (pixel.length === 0) errors.push(`No Pixel event found - ${eventName}`);
        if (capi.length === 0) errors.push(`No CAPI event found - ${eventName}`);
        if (pixel.length === 0 || capi.length === 0) {
            return { passed: false, errors };
        }

        if (pixel.length != capi.length) {
            errors.push(`Event count mismatch: Pixel=${pixel.length}, CAPI=${capi.length}`);
            return { passed: false, errors };
        }

        const p = pixel[0];
        const c = capi[0];

        // Check required top-level fields
        console.log(`  ✓ Checking required fields...`);
        let pixelFieldsMissing = 0;
        let capiFieldsMissing = 0;

        schema.required.pixel.forEach(field => {
            if (!(field in p) || p[field] == null) {
                errors.push(`Pixel field missing: ${field}`);
                pixelFieldsMissing++;
            }
        });

        schema.required.capi.forEach(field => {
            if (!(field in c) || c[field] == null) {
                errors.push(`CAPI field missing: ${field}`);
                capiFieldsMissing++;
            }
        });

        if (pixelFieldsMissing === 0 && capiFieldsMissing === 0) {
            console.log(`    ✓ All required fields present`);
        }

        // Check custom_data fields
        if (schema.custom_data && schema.custom_data.length > 0) {
            console.log(`  ✓ Checking custom_data fields...`);
            let customFieldsMissing = 0;

            schema.custom_data.forEach(field => {
                const pixelHas = p.custom_data && field in p.custom_data && p.custom_data[field] != null;
                const capiHas = c.custom_data && field in c.custom_data && c.custom_data[field] != null;

                if (!pixelHas) {
                    errors.push(`Pixel custom_data missing: ${field}`);
                    customFieldsMissing++;
                }
                if (!capiHas) {
                    errors.push(`CAPI custom_data missing: ${field}`);
                    customFieldsMissing++;
                }
            });

            if (customFieldsMissing === 0) {
                console.log(`    ✓ All custom_data fields present`);
            }
        }

        // Check dedup (event_id matching)
        console.log(`  ✓ Checking event deduplication...`);
        if (!p.eventId) errors.push('Pixel missing event_id');
        if (!c.event_id) errors.push('CAPI missing event_id');

        if (p.eventId && c.event_id) {
            if (p.eventId === c.event_id) {
                console.log(`    ✓ Event IDs match: ${p.eventId}`);
            } else {
                errors.push(`Event IDs mismatch: ${p.eventId} vs ${c.event_id}`);
            }
        }
          // // Run custom validators
        // if (schema.validators) {
        //     Object.entries(schema.validators).forEach(([name, fn]) => {
        //         try {
        //             if (!fn(p, c)) errors.push(`Validator failed: ${name}`);
        //         } catch (err) {
        //             errors.push(`Validator error: ${name} - ${err.message}`);
        //         }
        //     });
        // }

        // Run common validators
        console.log(`  ✓ Running data validators...`);
        const validatorErrors = errors.length;

        this.validateTimestamp(p, c, errors);
        this.validateFbp(p, c, errors);

        if (schema.custom_data && schema.custom_data.length > 0) {
            if (schema.custom_data.includes('value')) {
                this.validateValue(p, c, errors);
            }
            if (schema.custom_data.includes('content_ids')) {
                this.validateContentIds(p, c, errors);
            }
        }

        if (errors.length === validatorErrors) {
            console.log(`    ✓ All data validators passed`);
        }

        // Check for PHP errors if page is provided
        if (page) {
            console.log(`  ✓ Checking for PHP errors...`);
            const phpErrors = await this.checkPhpErrors(page);
            if (phpErrors.length > 0) {
                console.log(`    ✗ PHP errors found: ${phpErrors.length}`);
                phpErrors.forEach(err => errors.push(err));
            } else {
                console.log(`    ✓ No PHP errors`);
            }
        }

        // Check Pixel API response
        console.log(`  ✓ Checking Pixel response...`);
        if (p.api_status) {
            if (p.api_status === 200 && p.api_ok) {
                console.log(`    ✓ Pixel API: 200 OK`);
            } else {
                errors.push(`Pixel API failed: HTTP ${p.api_status}`);
                console.log(`    ✗ Pixel API: ${p.api_status}`);
            }
        }

        return {
            passed: errors.length === 0,
            errors,
            pixel: p,
            capi: c
        };
    }

    /**
     * Check for PHP errors on the page
     */
    async checkPhpErrors(page) {
        const pageContent = await page.content();
        const phpErrors = [];

        if (pageContent.includes('Fatal error')) {
            phpErrors.push('PHP Fatal error detected on page');
        }
        if (pageContent.includes('Parse error')) {
            phpErrors.push('PHP Parse error detected on page');
        }
        // if (pageContent.includes('Warning:') && pageContent.includes('.php')) {
        //     phpErrors.push('PHP Warning detected on page');
        // }

        return phpErrors;
    }

    // Common validation methods
    validateTimestamp(pixel, capi, errors) {
        const pixelTime = pixel.timestamp || Date.now();
        const capiTime = (capi.event_time || 0) * 1000;
        const diff = Math.abs(pixelTime - capiTime);

        if (diff >= 30000) {
            errors.push(`Timestamp mismatch: ${diff}ms (max 30s)`);
        }
    }

    validateFbp(pixel, capi, errors) {
        const pixelFbp = pixel.user_data?.fbp;
        const capiFbp = capi.user_data?.browser_id;

        if (!pixelFbp) {
            errors.push(`Pixel missing fbp`);
        }
        if (!capiFbp) {
            errors.push(`CAPI missing browser_id (fbp)`);
        }

        if (pixelFbp && capiFbp && pixelFbp !== capiFbp) {
            errors.push(`FBP mismatch: ${pixelFbp} vs ${capiFbp}`);
        }
    }

    validateValue(pixel, capi, errors) {
        const pVal = pixel.custom_data?.value;
        const cVal = capi.custom_data?.value;

        if (pVal !== undefined && cVal !== undefined) {
            const diff = Math.abs(parseFloat(pVal) - parseFloat(cVal));
            if (diff >= 0.01) {
                errors.push(`Value mismatch: ${pVal} vs ${cVal}`);
            }
        }
    }

    validateContentIds(pixel, capi, errors) {
        let pIds = pixel.custom_data?.content_ids;
        let cIds = capi.custom_data?.content_ids;

        if (!pIds || !cIds) return;

        // Normalize both to arrays for comparison
        // CAPI sends as JSON string (e.g., '["45"]'), Pixel sends as array (e.g., ['45'])
        if (typeof cIds === 'string') {
            try {
                cIds = JSON.parse(cIds);
            } catch (e) {
                errors.push(`CAPI content_ids invalid JSON: ${cIds}`);
                return;
            }
        }

        if (typeof pIds === 'string') {
            try {
                pIds = JSON.parse(pIds);
            } catch (e) {
                errors.push(`Pixel content_ids invalid JSON: ${pIds}`);
                return;
            }
        }

        // Now both should be arrays, compare them
        const pIdsStr = JSON.stringify(pIds);
        const cIdsStr = JSON.stringify(cIds);

        if (pIdsStr !== cIdsStr) {
            errors.push(`Content IDs mismatch: Pixel=${pIdsStr} vs CAPI=${cIdsStr}`);
        }
    }

    async checkDebugLog() {
        const debugLogPath = '/tmp/wordpress/wp-content/debug.log';
        try {
            const data = await fs.readFile(debugLogPath, 'utf8');
            
            // Ignore benign warnings like constant redefinitions
            const lines = data.split('\n');
            const criticalErrors = lines.filter(line => {
                // Skip constant redefinition warnings (benign)
                if (line.includes('Constant') && line.includes('already defined')) {
                    return false;
                }
                // Only care about fatal/error, not warnings
                return /fatal|error/i.test(line) && !/warning/i.test(line);
            });
            
            if (criticalErrors.length > 0) {
                console.log('❌ Critical errors in debug.log:');
                criticalErrors.forEach(err => console.log('  ', err));
                throw new Error('❌ Debug log errors detected');
            }
        } catch (err) {
            if (err.code !== 'ENOENT') throw err;
        }
    }
}

module.exports = EventValidator;
