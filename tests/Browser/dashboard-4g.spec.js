import { expect, test } from '@playwright/test';

test('mobile dashboard becomes operational-ready within the documented local 4G gate', async ({ context, page }, testInfo) => {
  test.skip(testInfo.project.name !== 'mobile-chromium');

  await page.goto('/app');
  await page.getByText(/Status Stok|Peringatan Stok/).first().waitFor();
  const cdp = await context.newCDPSession(page);
  await cdp.send('Network.enable');
  await cdp.send('Network.emulateNetworkConditions', {
    offline: false,
    latency: 40,
    downloadThroughput: 524_288,
    uploadThroughput: 196_608,
    connectionType: 'cellular4g',
  });

  const startedAt = Date.now();
  await page.goto('/app');
  await page.getByText(/Status Stok|Peringatan Stok/).first().waitFor();
  const operationalReadyMs = Date.now() - startedAt;

  expect(operationalReadyMs).toBeLessThanOrEqual(2_000);
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(1);
});
