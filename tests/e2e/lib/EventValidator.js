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

        console.log(`\n  🔍 Validating ${eventName}...`);

        const schema = EVENT_SCHEMAS[eventName];
        if (!schema) throw new Error(`No schema for: ${eventName}`);

        const pixel = this.events.pixel.filter(e => e.eventName === eventName);
        const capi = this.events.capi.filter(e => e.event_name === eventName);

        console.log(`   Pixel events found: ${pixel.length}`);
        console.log(`   CAPI events found: ${capi.length}`);

        const errors = [];

        const countCheckResult = this.validateEventCounts(pixel, capi, eventName, errors);
        if (!countCheckResult.passed) {
            return countCheckResult;
        }
        // If we do not do this check, the rest of the validations will fail cos of mismatched counts

        const p = pixel[0];
        const c = capi[0];

        this.validateRequiredFields(p, c, schema, errors);
        this.validateCustomDataFields(p, c, schema, errors);
        this.validateDeduplication(p, c, errors);

        console.log(`  ✓ Running data validators...`);

        this.validateTimestamp(p, c, errors);
        this.validateFbp(p, c, errors);
        this.validateCookies(p, errors)
        this.validateValue(p, c, schema, errors);
        this.validateContentIds(p, c, schema, errors);
        this.validateUserData(p, c, errors);
        await this.validatePhpErrors(page, errors);

        this.validatePixelResponse(p, errors); // if response was 200 OK or not.
        // CAPI we do not need to check this response status, as we log it only if it is successful response. it will be caught in lengthcheck and details will be in debug.log

        return {
            passed: errors.length === 0,
            errors,
            pixel: p,
            capi: c
        };
    }

    validateEventCounts(pixel, capi, eventName, errors) {
        if (pixel.length === 0) errors.push(`No Pixel event found - ${eventName}`);
        if (capi.length === 0) errors.push(`No CAPI event found - ${eventName}`);
        if (pixel.length === 0 || capi.length === 0) {
            return { passed: false, errors, pixel: pixel, capi: capi };
        }

        if (pixel.length != capi.length) {
            errors.push(`Event count mismatch: Pixel=${pixel.length}, CAPI=${capi.length}`);
            return { passed: false, errors, pixel: pixel, capi: capi };
        }

        if (pixel.length===1 && capi.length===1) {
            console.log(`✅ Pixel and CAPI events match: ${pixel.length}`);
            return { passed: true, errors, pixel: pixel, capi: capi};
        }

    }

    validateRequiredFields(p, c, schema, errors) {
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
    }

    validateCustomDataFields(p, c, schema, errors) {
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
    }

    validateDeduplication(p, c, errors) {
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
    }

    async validatePhpErrors(page, errors) {
        if (page) {
            console.log(`  ✓ Checking for PHP errors...`);
            const pageContent = await page.content();
            const phpErrors = [];

            if (pageContent.includes('Fatal error')) {
                phpErrors.push('PHP Fatal error detected on page');
            }
            if (pageContent.includes('Parse error')) {
                phpErrors.push('PHP Parse error detected on page');
            }

            if (phpErrors.length > 0) {
                console.log(`    ✗ PHP errors found: ${phpErrors.length}`);
                phpErrors.forEach(err => errors.push(err));
            } else {
                console.log(`    ✓ No PHP errors`);
            }
        }
    }

    validatePixelResponse(p, errors) {
        console.log(`  ✓ Checking Pixel response...`);
        if (p.api_status) {
            if (p.api_status === 200 && p.api_ok) {
                console.log(`    ✓ Pixel API: 200 OK`);
            } else {
                errors.push(`Pixel API failed: HTTP ${p.api_status}`);
                console.log(`    ✗ Pixel API: ${p.api_status}`);
            }
        }
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

    validateCookies(pixel, errors) {
        if (!pixel.cookies) {
            errors.push('Pixel event missing cookies field');
            return;
        }

        if (!pixel.cookies._fbp) {
            errors.push('Cookie _fbp not present');
        }

        // if (!pixel.cookies._fbc) {
        //     errors.push('Cookie _fbc not present');
        // }
        // TODO: do we need to check _fbc?
    }

    validateValue(pixel, capi, schema, errors) {
        if (schema.custom_data && schema.custom_data.length > 0) {
            if (schema.custom_data.includes('value')) {
                const pVal = pixel.custom_data?.value;
                const cVal = capi.custom_data?.value;

                if (pVal !== undefined && cVal !== undefined) {
                    const diff = Math.abs(parseFloat(pVal) - parseFloat(cVal));
                    if (diff >= 0.01) {
                        errors.push(`Value mismatch: ${pVal} vs ${cVal}`);
                    }
                }
            }
        }
    }

    validateContentIds(pixel, capi, schema, errors) {
        if (schema.custom_data && schema.custom_data.length > 0) {
            if (schema.custom_data.includes('content_ids')) {
                let pIds = pixel.custom_data?.content_ids;
                let cIds = capi.custom_data?.content_ids;

                if (!pIds || !cIds) return;

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

                const pIdsStr = JSON.stringify(pIds);
                const cIdsStr = JSON.stringify(cIds);

                if (pIdsStr !== cIdsStr) {
                    errors.push(`Content IDs mismatch: Pixel=${pIdsStr} vs CAPI=${cIdsStr}`);
                }
            }
        }
    }

    validateUserData(pixel, capi, errors) {
        this.validatePII(pixel, capi, errors, 'em');
        this.validatePII(pixel, capi, errors, 'external_id');
    }

    validatePII(pixel, capi, errors, field_name) {
        const pixelValue = pixel.user_data?.[field_name];
        const capiValue = capi.user_data?.[field_name];

        if (pixelValue || capiValue) {
            // Check both exist
            if (!pixelValue) errors.push(`Pixel missing hashed ${field_name}`);
            if (!capiValue) errors.push(`CAPI missing hashed ${field_name}`);

            // Check they match
            if (pixelValue && capiValue && pixelValue !== capiValue) {
                errors.push(`Hashed ${field_name} mismatch: ${pixelValue} vs ${capiValue}`);
            }

            // Check proper SHA256 format (64 hex chars)
            if (pixelValue && !/^[a-f0-9]{64}$/.test(pixelValue)) {
                errors.push(`Pixel ${field_name} not properly SHA256 hashed`);
            }
        }
    }

    /**
     * Check WordPress debug log for critical errors
     */
    async checkDebugLog() {
        const debugLogPath = process.env.WP_DEBUG_LOG;
        try {
            const data = await fs.readFile(debugLogPath, 'utf8');
            const lines = data.split('\n');
            const criticalErrors = lines.filter(line => {
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
