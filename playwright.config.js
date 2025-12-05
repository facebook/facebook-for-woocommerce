import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: 'html',
  // Global test timeout - increased to 5 minutes for complex WordPress operations
  timeout: 300000,
  use: {
    baseURL: process.env.WORDPRESS_URL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    // Ignore SSL errors for local development
    ignoreHTTPSErrors: true,
    // Global timeouts for all actions - increased to 3 minutes
    actionTimeout: 180000,
    navigationTimeout: 180000,
  },

  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        // Increased timeouts for WordPress admin operations
        actionTimeout: 180000,
        navigationTimeout: 180000,
      },
    },
  ],

  // Only look for E2E test files, ignore Jest tests
  testMatch: '**/tests/e2e/**/*.spec.js',

  // Only start webServer in CI, not when using external WordPress URL
  webServer: (process.env.CI && !process.env.WORDPRESS_URL) ? {
    command: 'php -S localhost:8080 -t /tmp/wordpress-e2e',
    port: 8080,
    reuseExistingServer: false,
  } : undefined,
});
