/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * Tests for checkWooCommerceLogs() non-200 classification.
 *
 * This helper backs the `Check WooCommerce logs for fatal errors and non-200
 * responses` E2E test, which re-reads the same cumulative log file on every
 * Playwright retry -- so anything it rejects reds the shard with no way for a
 * retry to recover. The 4xx/5xx split is what keeps upstream Meta blips from
 * doing that while still failing on requests the plugin itself got wrong.
 */

const fs = require('fs');
const os = require('os');
const path = require('path');

const { checkWooCommerceLogs } = require('../e2e/helpers/js/wordpress/exec');

const today = new Date().toISOString().split('T')[0];

function entry(code, message, extra = '') {
  return [
    '2026-09-01T03:24:57+00:00 NOTICE Response',
    `code: ${code}`,
    `message: ${message}`,
    extra,
  ].filter(Boolean).join('\n');
}

const OK = entry(200, 'OK', 'body: {"events_received":1}');

/** Write a log file into a fresh temp dir and point WC_LOG_PATH at it. */
function withLog(body) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'wc-logs-'));
  fs.writeFileSync(
    path.join(dir, `facebook_for_woocommerce-${today}-deadbeef.log`),
    `${body}\n`
  );
  process.env.WC_LOG_PATH = dir;
  return dir;
}

describe('checkWooCommerceLogs non-200 classification', () => {
  const originalLogPath = process.env.WC_LOG_PATH;
  const dirs = [];

  beforeEach(() => {
    jest.spyOn(console, 'log').mockImplementation(() => {});
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  afterAll(() => {
    dirs.forEach(d => fs.rmSync(d, { recursive: true, force: true }));
    if (originalLogPath === undefined) {
      delete process.env.WC_LOG_PATH;
    } else {
      process.env.WC_LOG_PATH = originalLogPath;
    }
  });

  const scenario = (body) => {
    dirs.push(withLog(body));
    return checkWooCommerceLogs();
  };

  it('passes when every response is a 200', async () => {
    await expect(scenario([OK, OK].join('\n'))).resolves.toEqual({ success: true });
  });

  it('tolerates an upstream 5xx -- Meta server error, not a plugin defect', async () => {
    const body = [
      OK,
      entry(500, 'Internal Server Error', 'body: {"error":{"code":1,"message":"An unknown error occurred","error_subcode":99}}'),
      OK,
    ].join('\n');
    await expect(scenario(body)).resolves.toEqual({ success: true });
  });

  it('still fails on a 4xx -- the plugin sent a bad request', async () => {
    const body = [OK, entry(400, 'Bad Request', 'body: {"error":{"code":100}}')].join('\n');
    const result = await scenario(body);
    expect(result.success).toBe(false);
    expect(result.error).toBe('Non-200 response codes found');
  });

  it('fails on a 4xx even when an upstream 5xx is also present', async () => {
    const body = [
      entry(500, 'Internal Server Error'),
      entry(400, 'Bad Request', 'body: {"error":{"code":100}}'),
    ].join('\n');
    await expect(scenario(body)).resolves.toMatchObject({ success: false });
  });

  it('keeps ignoring the known transient 4xx via the context allowlist', async () => {
    const body = [
      OK,
      entry(400, 'Bad Request', 'body: {"error":{"message":"Another upload already in progress"}}'),
    ].join('\n');
    await expect(scenario(body)).resolves.toEqual({ success: true });
  });
});
