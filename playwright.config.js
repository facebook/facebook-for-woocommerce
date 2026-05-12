import { defineConfig, devices } from '@playwright/test';

const CUSTOMER_EVENTS_SPEC = '**/events-test.spec.js';
const STOREFRONT_SPECS = [
  CUSTOMER_EVENTS_SPEC,
];
const ADMIN_SPECS = [
  '**/product-creation.spec.js',
  '**/product-deletion.spec.js',
  '**/product-batch.spec.js',
  '**/product-category.spec.js',
  '**/product-modification.spec.js',
  '**/plugin-level-tests.spec.js',
  '**/performance-sync.spec.js',
  '**/sync-in-progress.spec.js',
  '**/variable-product-depth.spec.js',
];

const commonTimeouts = {
  actionTimeout: 180000,
  navigationTimeout: 180000,
};

const privacySandboxOrigin = process.env.WORDPRESS_URL
  ? (() => {
      try {
        return new URL(process.env.WORDPRESS_URL).origin;
      } catch {
        return null;
      }
    })()
  : null;

const privacySandboxArgs = [
  '--enable-features=BrowsingTopics,InterestGroupStorage,AdInterestGroupAPI,Fledge,RunAdAuction,PrivacySandboxAdsAPIsOverride',
  '--enable-blink-features=BrowsingTopics,InterestGroupStorage,AdInterestGroupAPI,RunAdAuction',
  '--test-third-party-cookie-phaseout',
  ...(privacySandboxOrigin ? [`--unsafely-treat-insecure-origin-as-secure=${privacySandboxOrigin}`] : []),
];

const adminUse = {
  ...devices['Desktop Chrome'],
  ...commonTimeouts,
  storageState: './tests/e2e/.auth/admin.json',
};

const customerUse = {
  ...devices['Desktop Chrome'],
  ...commonTimeouts,
  storageState: './tests/e2e/.auth/customer.json',
};

const edgeExecutablePath = process.env.EDGE_EXECUTABLE_PATH;
const firefoxExecutablePath = process.env.FIREFOX_EXECUTABLE_PATH;
const braveExecutablePath = process.env.BRAVE_EXECUTABLE_PATH;
const operaExecutablePath = process.env.OPERA_EXECUTABLE_PATH;
const requireRealEdge = process.env.REQUIRE_REAL_EDGE === '1';
const requireRealFirefox = process.env.REQUIRE_REAL_FIREFOX === '1';
const requireRealBrave = process.env.REQUIRE_REAL_BRAVE === '1';
const requireRealOpera = process.env.REQUIRE_REAL_OPERA === '1';

if (requireRealEdge && !edgeExecutablePath) {
  throw new Error('REQUIRE_REAL_EDGE=1 but EDGE_EXECUTABLE_PATH is not set. Refusing channel fallback.');
}

if (requireRealFirefox && firefoxExecutablePath) {
  throw new Error('REQUIRE_REAL_FIREFOX=1 uses Playwright Firefox channel. Do not set FIREFOX_EXECUTABLE_PATH.');
}

if (requireRealBrave && !braveExecutablePath) {
  throw new Error('REQUIRE_REAL_BRAVE=1 but BRAVE_EXECUTABLE_PATH is not set. Refusing Chromium fallback.');
}

if (requireRealOpera && !operaExecutablePath) {
  throw new Error('REQUIRE_REAL_OPERA=1 but OPERA_EXECUTABLE_PATH is not set. Refusing Chromium fallback.');
}

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : 1,
  reporter: 'html',
  timeout: 1000000,
  globalSetup: './tests/e2e/global-setup.js',
  use: {
    baseURL: process.env.WORDPRESS_URL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    ignoreHTTPSErrors: true,
    ...commonTimeouts,
  },

  projects: [
    // -------------------------
    // Admin desktop coverage
    // -------------------------
    {
      name: 'chromium-wp-admin',
      testMatch: ADMIN_SPECS,
      testIgnore: STOREFRONT_SPECS,
      use: adminUse,
    },

    // -------------------------
    // Customer/browser + mobile events coverage
    // -------------------------
    {
      name: 'chromium-wp-customer',
      testMatch: [CUSTOMER_EVENTS_SPEC],
      use: customerUse,
    },
    {
      name: 'chromium-wp-customer-classic-theme',
      testMatch: [CUSTOMER_EVENTS_SPEC],
      use: customerUse,
    },
    {
      name: 'chromium-wp-customer-block-theme',
      testMatch: [CUSTOMER_EVENTS_SPEC],
      use: customerUse,
    },
    {
      name: 'chromium-privacy-sandbox-wp-customer',
      testMatch: [CUSTOMER_EVENTS_SPEC],
      use: {
        ...customerUse,
        channel: 'chrome',
        launchOptions: {
          args: privacySandboxArgs,
        },
      },
    },
    {
      name: 'edge-wp-customer',
      testMatch: [CUSTOMER_EVENTS_SPEC],
      use: {
        ...customerUse,
        ...(edgeExecutablePath
          ? { launchOptions: { executablePath: edgeExecutablePath } }
          : { channel: 'msedge' }),
      },
    },
    {
      name: 'firefox-wp-customer',
      testMatch: [CUSTOMER_EVENTS_SPEC],
      use: {
        ...devices['Desktop Firefox'],
        ...commonTimeouts,
        ...(requireRealFirefox
          ? { channel: 'firefox' }
          : (firefoxExecutablePath ? { launchOptions: { executablePath: firefoxExecutablePath } } : {})),
        storageState: './tests/e2e/.auth/customer.json',
      },
    },
    {
      name: 'brave-wp-customer',
      testMatch: [CUSTOMER_EVENTS_SPEC],
      use: {
        ...customerUse,
        ...(requireRealBrave
          ? { launchOptions: { executablePath: braveExecutablePath } }
          : { userAgent: `${devices['Desktop Chrome'].userAgent} Brave/1.0.0.0` }),
      },
    },
    {
      name: 'opera-wp-customer',
      testMatch: [CUSTOMER_EVENTS_SPEC],
      use: {
        ...customerUse,
        ...(requireRealOpera
          ? { launchOptions: { executablePath: operaExecutablePath } }
          : { userAgent: `${devices['Desktop Chrome'].userAgent} OPR/100.0.0.0` }),
      },
    },
    {
      name: 'android-pixel-wp-customer',
      testMatch: [CUSTOMER_EVENTS_SPEC],
      use: {
        ...devices['Pixel 5'],
        ...commonTimeouts,
        storageState: './tests/e2e/.auth/customer.json',
      },
    },
    {
      name: 'safari-ios-wp-customer',
      testMatch: [CUSTOMER_EVENTS_SPEC],
      use: {
        ...devices['iPhone 13'],
        ...commonTimeouts,
        browserName: 'webkit',
        // Real WebKit engine for Safari-like rendering/runtime behavior.
        storageState: './tests/e2e/.auth/customer.json',
      },
    },
  ],

  testMatch: '**/tests/e2e/**/*.spec.js',

  webServer: (process.env.CI && !process.env.WORDPRESS_URL)
    ? {
        command: 'php -S localhost:8080 -t /tmp/wordpress-e2e',
        port: 8080,
        reuseExistingServer: false,
      }
    : undefined,
});
