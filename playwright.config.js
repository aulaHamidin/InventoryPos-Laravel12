import { defineConfig } from '@playwright/test';

const viewport = (width, height) => ({ viewport: { width, height } });

export default defineConfig({
  testDir: './tests/Browser',
  globalSetup: './tests/Browser/global-setup.js',
  fullyParallel: false,
  workers: process.env.CI ? 1 : undefined,
  retries: process.env.CI ? 1 : 0,
  timeout: 90_000,
  expect: { timeout: 10_000 },
  outputDir: 'storage/framework/testing/playwright-results',
  reporter: process.env.CI
    ? [['line'], ['html', { outputFolder: 'storage/framework/testing/playwright-report', open: 'never' }]]
    : [['list']],
  use: {
    baseURL: process.env.F9A_BASE_URL || 'http://127.0.0.1:8001',
    screenshot: 'only-on-failure',
    trace: 'off',
    video: 'off',
    locale: 'id-ID',
    timezoneId: 'Asia/Jakarta',
    storageState: 'storage/framework/testing/f9a-playwright-staff-state.json',
  },
  projects: [
    { name: 'desktop-chromium', use: { browserName: 'chromium', ...viewport(1440, 900) } },
    { name: 'desktop-firefox', use: { browserName: 'firefox', ...viewport(1440, 900) } },
    { name: 'mobile-chromium', use: { browserName: 'chromium', ...viewport(390, 844), isMobile: true, hasTouch: true } },
    { name: 'mobile-firefox', use: { browserName: 'firefox', ...viewport(390, 844), hasTouch: true } },
    { name: 'tablet-chromium', use: { browserName: 'chromium', ...viewport(768, 1024), isMobile: true, hasTouch: true } },
    { name: 'tablet-firefox', use: { browserName: 'firefox', ...viewport(768, 1024), hasTouch: true } },
  ],
});
