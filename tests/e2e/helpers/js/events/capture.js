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
    try {
      const request = await this.page.waitForRequest(
        request => {
          const url = request.url();
          const parsedUrl = new URL(url);

          if (!parsedUrl.hostname.includes('facebook.com')) return false;
          if (!parsedUrl.pathname.includes('/tr/') && !parsedUrl.pathname.includes('/privacy_sandbox/')) return false;
          return parsedUrl.search.includes(`ev=${this.eventName}`);
        },
        { timeout: parseInt(process.env.PIXEL_EVENT_TIMEOUT || TIMEOUTS.EXTRA_LONG.toString(), 10) }
      );

      console.log(`✅ Pixel event captured: ${this.eventName}`);

      const response = await request.response();
      const eventData = await this.parsePixelEvent(request.url());

      eventData.api_status = response ? response.status() : 'N/A';
      eventData.api_ok = response ? response.ok() : false;

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
  async parsePixelEvent(url) {
    const urlObj = new URL(url);

    const event_name = urlObj.searchParams.get('ev') || 'Unknown';
    const event_id = urlObj.searchParams.get('eid') || null;
    const pixel_id = urlObj.searchParams.get('id') || 'Unknown';

    const customData = {};
    const userData = {};

    urlObj.searchParams.forEach((value, key) => {
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
      } else if (key.startsWith('ud[')) {
        const udKey = key.replace('ud[', '').replace(']', '');
        userData[udKey] = decodeURIComponent(value);
      }
    });

    const fbp = urlObj.searchParams.get('fbp');
    if (fbp) {
      userData.fbp = fbp;
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
