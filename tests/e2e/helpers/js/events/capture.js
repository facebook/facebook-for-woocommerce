/**
 * PixelCapture - Captures Pixel events from browser
 */

const { TIMEOUTS } = require('../constants/timeouts');

class PixelCapture {
  constructor(page, testId, eventName, expectZeroEvents = false) {
    this.page = page;
    this.testId = testId;
    this.eventName = eventName;
    this.isCapturing = false;
    this.expectZeroEvents = expectZeroEvents;
  }

  /**
   * Wait for the specific Pixel event to be sent, capture it, and log it
   */
  async waitForEvent() {
    console.log(`🎯 Waiting for Pixel event: ${this.eventName}...`);

    const debugEnabled = ['1', 'true', 'yes'].includes((process.env.PIXEL_DEBUG_LOGGER || '').toLowerCase());
    const debugRequestLogger = (request) => {
      if (!debugEnabled) return;

      try {
        const parsedUrl = new URL(request.url());
        const host = parsedUrl.hostname;
        const isFacebookHost = host === 'facebook.com' || host.endsWith('.facebook.com');
        if (!isFacebookHost) return;


        const ev = parsedUrl.searchParams.get('ev') || '(none-in-query)';
        const eid = parsedUrl.searchParams.get('eid') || '(none)';
        const body = request.postData() || '';
        const bodyPreview = body ? body.slice(0, 180).replace(/\s+/g, ' ') : '';

        console.log(
          `📡 [PixelDebug] ${request.method()} ${host}${parsedUrl.pathname} ev=${ev} eid=${eid} body=${bodyPreview || '(empty)'}`
        );
      } catch (_) {
        // ignore debug parser failures
      }
    };

    const context = this.page.context();
    context.on('request', debugRequestLogger);

    try {
      const timeoutMs = parseInt(process.env.PIXEL_EVENT_TIMEOUT || TIMEOUTS.EXTRA_LONG.toString(), 10);
      const deadline = Date.now() + timeoutMs;

      const scoreEvent = (eventData) => {
        const userCount = Object.keys(eventData.user_data || {}).length;
        const customCount = Object.keys(eventData.custom_data || {}).length;
        let score = 0;
        if (eventData.event_id) score += 5;
        if (eventData.pixel_id && eventData.pixel_id !== 'SB') score += 3;
        score += Math.min(userCount, 10);
        score += Math.min(customCount, 10);
        return score;
      };

      const isRichEnough = (eventData) => {
        const userCount = Object.keys(eventData.user_data || {}).length;
        const customCount = Object.keys(eventData.custom_data || {}).length;
        return !!eventData.event_id && eventData.pixel_id !== 'SB' && (userCount >= 2 || customCount >= 2);
      };

      const queuedRequests = [];
      const queueListener = (request) => {
        try {
          const parsedUrl = new URL(request.url());
          const host = parsedUrl.hostname;
          const isFacebookHost = host === 'facebook.com' || host.endsWith('.facebook.com');
          if (!isFacebookHost) return;


          queuedRequests.push(request);
        } catch (_) {
          // ignore
        }
      };

      context.on('request', queueListener);

      let best = null;
      let bestForExpectedEvent = null;

      try {
        while (Date.now() < deadline) {
          if (queuedRequests.length === 0) {
            await new Promise((resolve) => setTimeout(resolve, 40));
            continue;
          }

          const request = queuedRequests.shift();
          const response = await request.response();
          const eventData = await this.parsePixelEvent(request.url(), request);
          eventData.api_status = response ? response.status() : 'N/A';
          eventData.api_ok = response ? response.ok() : false;
          eventData.request_failure = request.failure() ? request.failure().errorText : null;
          eventData.request_method = request.method();
          eventData.request_url = request.url();
          eventData.request_has_payload = Boolean(request.postData());

          const score = scoreEvent(eventData);
          if (!best || score > best.score) {
            best = { request, eventData, score };
          }

          if (eventData.event_name === this.eventName) {
            if (!bestForExpectedEvent || score > bestForExpectedEvent.score) {
              bestForExpectedEvent = { request, eventData, score };
            }

            if (isRichEnough(eventData)) {
              break;
            }
          }
        }
      } finally {
        context.off('request', queueListener);
      }

      if (this.expectZeroEvents) {
        if (bestForExpectedEvent) {
          throw new Error(`❌ Pixel event ${this.eventName} fired unexpectedly within ${timeoutMs}ms`);
        }

        console.log(`✅ No Pixel event fired (as expected for negative test)`);
        return;
      }

      const chosen = bestForExpectedEvent || best;
      if (!chosen) {
        throw new Error(`❌ Pixel event ${this.eventName} did not fire within ${timeoutMs}ms`);
      }

      const eventData = chosen.eventData;
      if (eventData.event_name !== this.eventName) {
        throw new Error(`❌ Pixel event ${this.eventName} not found within ${timeoutMs}ms (best match was ${eventData.event_name || 'unknown'})`);
      }

      const userCount = Object.keys(eventData.user_data || {}).length;
      const customCount = Object.keys(eventData.custom_data || {}).length;
      if (!eventData.pixel_id || eventData.pixel_id === 'SB') {
        throw new Error(`❌ Pixel event ${this.eventName} captured with invalid pixel_id=${eventData.pixel_id || 'unknown'}`);
      }
      if (!eventData.event_id) {
        throw new Error(`❌ Pixel event ${this.eventName} captured without event_id`);
      }
      if (userCount < 2 && customCount < 2) {
        throw new Error(`❌ Pixel event ${this.eventName} too sparse (user_data keys=${userCount}, custom_data keys=${customCount})`);
      }

      console.log(`✅ Pixel event captured: ${this.eventName} (pixel_id=${eventData.pixel_id || 'unknown'}, score=${chosen.score})`);
      console.log(`   Event ID: ${eventData.event_id || 'none'}, API: ${eventData.api_status}`);
      await this.logToServer(eventData);

    } catch (err) {
      if (err.message?.includes('Timeout')) {
        if (this.expectZeroEvents) {
          console.log(`✅ No Pixel event fired (as expected for negative test)`);
          return;
        }
        throw new Error(`❌ Pixel event ${this.eventName} did not fire within ${parseInt(process.env.PIXEL_EVENT_TIMEOUT || TIMEOUTS.EXTRA_LONG.toString(), 10)}ms`);
      }
      throw err;
    } finally {
      context.off('request', debugRequestLogger);
    }
  }

  /**
   * Get all cookies from the browser
   */
  async getAllCookies() {
    const cookies = await this.page.context().cookies();
    const cookieMap = {};

    cookies.forEach(cookie => {
      cookieMap[cookie.name] = cookie.value;
    });

    return cookieMap;
  }

  /**
   * Parse Pixel event from URL
   */
  async parsePixelEvent(url, request = null) {
    const urlObj = new URL(url);

    let event_name = urlObj.searchParams.get('ev') || 'Unknown';
    let event_id = urlObj.searchParams.get('eid') || null;
    let pixel_id = urlObj.searchParams.get('id') || 'Unknown';
    let documentLocation = urlObj.searchParams.get('dl') || '';

    const customData = {};
    const userData = {};
    let marketingAgentParam = null;

    const ingestParam = (key, value) => {
      if (key.startsWith('cd[')) {
        const cdKey = key.replace('cd[', '').replace(']', '');
        const decodedValue = decodeURIComponent(value);

        try {
          customData[cdKey] = JSON.parse(decodedValue);
        } catch {
          if (!isNaN(decodedValue) && decodedValue !== '') {
            customData[cdKey] = parseFloat(decodedValue);
          } else {
            customData[cdKey] = decodedValue;
          }
        }
      } else if (key.startsWith('ud[') || key.startsWith('aud[')) {
        const udMatch = key.match(/^(?:ud|aud)\[([^\]]+)\]$/);
        if (!udMatch) {
          return;
        }

        const udKey = udMatch[1];
        // Prefer explicit ud[*], but accept aud[*] as fallback when ud[*] isn't present.
        if (key.startsWith('ud[') || !userData[udKey]) {
          userData[udKey] = decodeURIComponent(value);
        }
      } else if (key === 'ev' && event_name === 'Unknown') {
        event_name = decodeURIComponent(value);
      } else if (key === 'eid' && !event_id) {
        event_id = decodeURIComponent(value);
      } else if (key === 'id' && pixel_id === 'Unknown') {
        pixel_id = decodeURIComponent(value);
      } else if (key === 'fbp' && !userData.fbp) {
        userData.fbp = decodeURIComponent(value);
      } else if (key === 'a' && !marketingAgentParam) {
        marketingAgentParam = decodeURIComponent(value);
      } else if (key === 'dl' && !documentLocation) {
        documentLocation = decodeURIComponent(value);
      }
    };

    urlObj.searchParams.forEach((value, key) => ingestParam(key, value));

    const body = request?.postData?.() || '';
    if (body) {
      // application/x-www-form-urlencoded payload
      if (body.includes('=')) {
        const form = new URLSearchParams(body);
        for (const key of form.keys()) {
          const values = form.getAll(key);
          values.forEach((value) => ingestParam(key, value));
        }
      }

      // multipart/form-data payload (robust boundary split)
      if (body.includes('name="')) {
        const firstLine = body.split(/\r?\n/, 1)[0] || '';
        const boundary = firstLine.startsWith('--') ? firstLine.trim() : '';

        if (boundary) {
          const parts = body.split(boundary);
          for (const rawPart of parts) {
            const part = rawPart.trim();
            if (!part || part === '--') continue;

            const nameMatch = part.match(/name="([^"]+)"/);
            if (!nameMatch) continue;

            const key = nameMatch[1];
            const valueBlock = part.split(/\r?\n\r?\n/)[1] || '';
            const value = valueBlock.replace(/\r?\n--$/, '').trim();
            if (!value) continue;

            ingestParam(key, value);
          }
        }

        // Linear fallback parser for odd multipart serializations (no complex regex backtracking).
        let cursor = 0;
        while (cursor < body.length) {
          const nameStart = body.indexOf('name="', cursor);
          if (nameStart === -1) break;

          const keyStart = nameStart + 6;
          const keyEnd = body.indexOf('"', keyStart);
          if (keyEnd === -1) break;

          const key = body.slice(keyStart, keyEnd).trim();
          if (!key) {
            cursor = keyEnd + 1;
            continue;
          }

          // Move to payload start: first blank line after headers.
          const headerEndCRLF = body.indexOf('\r\n\r\n', keyEnd);
          const headerEndLF = body.indexOf('\n\n', keyEnd);

          let valueStart = -1;
          if (headerEndCRLF !== -1 && (headerEndLF === -1 || headerEndCRLF < headerEndLF)) {
            valueStart = headerEndCRLF + 4;
          } else if (headerEndLF !== -1) {
            valueStart = headerEndLF + 2;
          }

          if (valueStart === -1) {
            cursor = keyEnd + 1;
            continue;
          }

          const nextBoundaryCRLF = body.indexOf('\r\n--', valueStart);
          const nextBoundaryLF = body.indexOf('\n--', valueStart);

          let valueEnd = -1;
          if (nextBoundaryCRLF !== -1 && (nextBoundaryLF === -1 || nextBoundaryCRLF < nextBoundaryLF)) {
            valueEnd = nextBoundaryCRLF;
          } else if (nextBoundaryLF !== -1) {
            valueEnd = nextBoundaryLF;
          }

          if (valueEnd === -1) {
            cursor = valueStart;
            continue;
          }

          const value = body.slice(valueStart, valueEnd).trim();
          if (value) ingestParam(key, value);

          cursor = valueEnd + 1;
        }
      }
    }

    // AJAX AddToCart compatibility: Store API path may encode plugin metadata in `a`
    // (e.g. a=woocommerce_0-10.6.2-3.6.3) instead of cd[source]/cd[version]/cd[pluginVersion].
    const isAjaxAtcOnShopLoop = event_name === 'AddToCart' && /\/shop\/?($|\?|#)/.test(documentLocation || '');

    if (
      isAjaxAtcOnShopLoop &&
      marketingAgentParam &&
      (!customData.source || !customData.version || !customData.pluginVersion)
    ) {
      const lastDash = marketingAgentParam.lastIndexOf('-');
      const secondLastDash = marketingAgentParam.lastIndexOf('-', lastDash - 1);

      if (secondLastDash > 0 && lastDash > secondLastDash) {
        const parsedSource = marketingAgentParam.slice(0, secondLastDash);
        const parsedVersion = marketingAgentParam.slice(secondLastDash + 1, lastDash);
        const parsedPluginVersion = marketingAgentParam.slice(lastDash + 1);

        if (!customData.source) customData.source = parsedSource;
        if (!customData.version) customData.version = parsedVersion;
        if (!customData.pluginVersion) customData.pluginVersion = parsedPluginVersion;

        console.log(
          `ℹ️ [PixelCapture] AddToCart metadata mapping detected: used 'a' param ` +
          `(${marketingAgentParam}) for custom_data.source/version/pluginVersion ` +
          `because cd[source|version|pluginVersion] were missing.`
        );
      }
    }

    const cookies = await this.getAllCookies();

    return {
      event_name: event_name,
      event_id: event_id,
      pixel_id: pixel_id,
      custom_data: customData,
      user_data: userData,
      cookies: cookies,
      timestamp: Date.now()
    };
  }

  /**
   * Log event to file
   */
  async logToServer(eventData) {
    const fs = require('fs').promises;
    const path = require('path');

    const capturedDir = path.join(__dirname, '../../captured-events');
    const filePath = path.join(capturedDir, `pixel-${this.testId}.json`);

    try {
      await fs.mkdir(capturedDir, { recursive: true });

      let events = [];
      try {
        const contents = await fs.readFile(filePath, 'utf8');
        events = JSON.parse(contents);
      } catch (err) {
        if (err.code !== 'ENOENT') {
          console.error(`⚠️  Warning: Could not read existing events: ${err.message}`);
        }
      }

      events.push(eventData);
      await fs.writeFile(filePath, JSON.stringify(events, null, 2));
      console.log(`💾 Event logged to: ${filePath}`);
    } catch (err) {
      console.error(`❌ Failed to log Pixel event to file: ${err.message}`);
      throw err;
    }
  }
}

module.exports = PixelCapture;
