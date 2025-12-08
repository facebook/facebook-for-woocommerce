/**
 * EventValidator - Load and validate captured events
 */

const fs = require('fs').promises;
const path = require('path');
const EVENT_SCHEMAS = require('./event-schemas');

class EventValidator {
    constructor(testId, fbc=false) {
        this.testId = testId;
        this.filePath = path.join(__dirname, '../captured-events', `${testId}.json`);
        this.events = null;
        this.fbc = fbc;
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

        const pixel = this.events.pixel.filter(e => e.event_name === eventName);
        const capi = this.events.capi.filter(e => e.event_name === eventName);

        console.log(`   Pixel events found: ${pixel.length}`);
        console.log(`   CAPI events found: ${capi.length}`);

        const errors = [];
        const countCheckResult = this.validateEventCounts(pixel, capi, eventName, errors);
        if (!countCheckResult.passed) {
            return countCheckResult;
        }
        // If we do not do this check, the rest of the validations will fail cos of mismatched counts

        const p = pixel[0] || null;
        const c = capi[0] || null;
        const hasPixel = schema.channels.includes('pixel');
        const hasCapi = schema.channels.includes('capi');

        // Validate fields for each channel in schema
        if (hasPixel && p) {
            this.validateFieldsExistence(eventName, 'pixel', 'user_data', p, errors);
            this.validateFieldsExistence(eventName, 'pixel', 'custom_data', p, errors);
        }
        if (hasCapi && c) {
            this.validateFieldsExistence(eventName, 'capi', 'user_data', c, errors);
            this.validateFieldsExistence(eventName, 'capi', 'custom_data', c, errors);
        }

        console.log(`  ✓ Running data validators...`);

        // Cross-channel validations (only when both channels exist)
        if (hasPixel && hasCapi && p && c) {
            this.validateDeduplication(p, c, errors);
            this.validateTimestamp(p, c, errors);
            this.validateFbp(p, c, errors);
            this.validateCookies(p, c, errors);
            this.validateDataMatch(p, c, eventName, 'custom_data', errors);
            this.validateDataMatch(p, c, eventName, 'user_data', errors);
            this.validateUserData(p, c, errors);
        }

        await this.validatePhpErrors(page, errors);

        // Channel-specific validations
        if (hasPixel && p) this.validatePixelResponse(p, errors);

        return {
            passed: errors.length === 0,
            errors,
            pixel: p,
            capi: c
        };
    }

    validateEventCounts(pixel, capi, eventName, errors) {
        const schema = EVENT_SCHEMAS[eventName];
        
        // Check positive path: expect exactly 1 event for each channel in schema
        if (schema.channels.includes('pixel') && pixel.length !== 1) {
            errors.push(`Expected 1 Pixel event, found ${pixel.length}`);
        }
        if (schema.channels.includes('capi') && capi.length !== 1) {
            errors.push(`Expected 1 CAPI event, found ${capi.length}`);
        }

        if (errors.length === 0) {
            console.log(`  ✓ Event counts match`);
        }

        return {
            passed: errors.length === 0,
            errors,
            pixel,
            capi
        };
    }

    validateFieldsExistence(eventName, dataSource, dataType, eventData, errors) {
        const eventSchema = EVENT_SCHEMAS[eventName];
        if (!eventSchema || !eventSchema[dataSource] || !eventSchema[dataSource][dataType]) {
            return;
        }

        const expectedFields = eventSchema[dataSource][dataType];
        if (expectedFields.length === 0) {
            return;
        }

        const actualData = eventData[dataType];
        if (!actualData) {
            errors.push(`${dataSource} ${dataType} missing`);
            return;
        }

        let missing = 0;
        expectedFields.forEach(field => {
            if (!(field in actualData) || actualData[field] == null) {
                errors.push(`${dataSource} ${dataType}.${field} missing`);
                missing++;
            }
        });

        if (missing === 0) {
            console.log(`  ✓ ${dataSource} ${dataType}: All ${expectedFields.length} fields present`);
        }
    }

    validateDeduplication(p, c, errors) {
        console.log(`  ✓ Checking event deduplication...`);
        if (!p.event_id) errors.push('Pixel missing event_id');
        if (!c.event_id) errors.push('CAPI missing event_id');

        if (p.event_id && c.event_id) {
            if (p.event_id === c.event_id) {
                console.log(`    ✓ Event IDs match: ${p.event_id}`);
            } else {
                errors.push(`Event IDs mismatch: ${p.event_id} vs ${c.event_id}`);
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
        } else {
            console.log(`  ✓ Timestamp match (${diff}ms)`);
        }
    }

    validateFbp(pixel, capi, errors) {
        const pixelFbp = pixel.user_data?.fbp;
        const capiFbp = capi.user_data?.fbp;

        if (!pixelFbp) {
            errors.push(`Pixel missing fbp`);
        }
        if (!capiFbp) {
            errors.push(`CAPI missing browser_id (fbp)`);
        }

        if (pixelFbp && capiFbp && pixelFbp !== capiFbp) {
            errors.push(`FBP mismatch: ${pixelFbp} vs ${capiFbp}`);
        } else if (pixelFbp && capiFbp) {
            console.log(`  ✓ FBP match: ${pixelFbp}`);
        }
    }

    validateCookies(pixel, capi, errors) {
        if (!pixel.cookies) {
            errors.push('Pixel event missing cookies field');
            return;
        }

        if (!pixel.cookies._fbp) {
            errors.push('Cookie _fbp not present');
        }
        // TODO needs some fixing for non fbc cases i think
        // Check _fbc (only when expected)
        if (!this.fbc) return;

        if (!pixel.cookies._fbc) {
            errors.push('Cookie _fbc not present in Pixel event');
        }
        if (!capi.user_data?.fbc) {
            errors.push('fbc not present in CAPI event user data');
        }
        if (pixel.cookies._fbc && capi.user_data?.fbc && pixel.cookies._fbc !== capi.user_data.fbc) {
            errors.push(`Cookie _fbc mismatch: ${pixel.cookies._fbc} vs ${capi.user_data.fbc}`);
        }

        // Success message if all checks passed
        if (pixel.cookies._fbc && capi.user_data?.fbc && pixel.cookies._fbc === capi.user_data.fbc) {
            console.log(`  ✓ Cookie _fbc present and matches: ${pixel.cookies._fbc}`);
        }
    }

    validateDataMatch(pixel, capi, eventName, dataType, errors) {
        const eventSchema = EVENT_SCHEMAS[eventName];
        if (!eventSchema || !eventSchema.channels.includes('pixel') || !eventSchema.channels.includes('capi')) {
            return;
        }

        const pixelData = pixel[dataType];
        const capiData = capi[dataType];

        if (!pixelData || !capiData) {
            return;
        }

        const commonFields = eventSchema.pixel[dataType].filter(f => eventSchema.capi[dataType].includes(f));
        if (commonFields.length === 0) {
            return;
        }

        let mismatches = 0;
        commonFields.forEach(field => {
            const pVal = pixelData[field];
            const cVal = capiData[field];

            if (pVal === undefined || cVal === undefined) return;

            const normalize = (val) => {
                let v = typeof val === 'string' ? (() => { try { return JSON.parse(val); } catch { return val; } })() : val;

                if (v && typeof v === 'object' && !Array.isArray(v)) {
                    const keys = Object.keys(v);
                    if (keys.every((k, i) => k === String(i))) v = keys.map(k => v[k]);
                }

                return Array.isArray(v) ? [...v].sort((a, b) => JSON.stringify(a).localeCompare(JSON.stringify(b))) : v;
            };

            const pStr = JSON.stringify(normalize(pVal));
            const cStr = JSON.stringify(normalize(cVal));

            if (pStr !== cStr) {
                errors.push(`${dataType}.${field} mismatch: Pixel=${pStr} vs CAPI=${cStr}`);
                mismatches++;
            }
        });

        if (mismatches === 0) {
            console.log(`  ✓ ${dataType}: ${commonFields.length} common fields match`);
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

            if (pixelValue && capiValue && pixelValue === capiValue && /^[a-f0-9]{64}$/.test(pixelValue)) {
                console.log(`  ✓ ${field_name} hashed correctly and matches`);
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
