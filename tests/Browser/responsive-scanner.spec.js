import { expect, test } from '@playwright/test';
import {
  addScannedItem,
  forbiddenFinancialText,
  hardeningManifest,
  ownerStatePath,
} from './support.js';

const manifest = hardeningManifest();
const tenant = manifest.tenants[0];

test('POS remains operable without horizontal overflow across the device matrix', async ({ page }) => {
  await page.goto('/app/items');
  const search = page.locator('input[type="search"]').first();
  await search.fill(tenant.items[4].kode);
  await expect(page.getByText(tenant.items[4].kode, { exact: true }).first()).toBeVisible();

  await page.goto('/app/pos');

  await page.keyboard.press('F2');
  await expect(page.locator('#barcode-input')).toBeFocused();
  await addScannedItem(page, tenant.items[2].barcode);

  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(1);
  expect(forbiddenFinancialText(await page.content())).toEqual([]);

  await page.getByRole('button', { name: 'Pilih Pembayaran' }).click();
  await expect(page.getByRole('radiogroup', { name: 'Metode pembayaran' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Konfirmasi Pembayaran' })).toBeVisible();
});

test('Staff cannot open Owner-only direct URLs', async ({ page }) => {
  for (const path of ['/app/staff', '/app/analytics-settings', '/app/report-exports']) {
    const response = await page.goto(path);
    expect([403, 404]).toContain(response.status());
  }
});

test('Staff cannot replay an Owner-only Livewire component snapshot', async ({ browser, page }, testInfo) => {
  test.skip(!['desktop-chromium', 'desktop-firefox'].includes(testInfo.project.name));

  const ownerContext = await browser.newContext({ storageState: ownerStatePath });
  const ownerPage = await ownerContext.newPage();
  await ownerPage.goto('/app/staff');
  const ownerSnapshots = await ownerPage.locator('[wire\\:snapshot]').evaluateAll((nodes) => (
    nodes.map((node) => node.getAttribute('wire:snapshot')).filter(Boolean)
  ));
  const ownerSnapshot = ownerSnapshots.find((snapshot) => JSON.parse(snapshot).memo.name.includes('list-staff'));
  await ownerContext.close();
  expect(ownerSnapshot).toBeTruthy();

  await page.goto('/app');
  const csrfToken = await page.locator('meta[name="csrf-token"]').getAttribute('content');
  const forged = await page.evaluate(async ({ csrfToken, ownerSnapshot }) => {
    const response = await fetch('/livewire/update', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Livewire': 'true',
      },
      body: JSON.stringify({
        components: [{
          snapshot: ownerSnapshot,
          updates: {},
          calls: [{ path: '', method: 'resetTable', params: [] }],
        }],
      }),
    });

    return { status: response.status, body: await response.text() };
  }, { csrfToken, ownerSnapshot });

  expect([403, 404, 419, 422]).toContain(forged.status);
  const forgedBody = forged.body.toLowerCase();
  for (const forbidden of ['harga_beli', 'average_cost', 'harga_beli_terakhir']) {
    expect(forgedBody).not.toContain(forbidden);
  }
  for (const cashier of tenant.cashiers) {
    expect(forgedBody).not.toContain(cashier.email.toLowerCase());
  }
});
