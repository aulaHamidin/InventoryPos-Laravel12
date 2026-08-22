import { expect, test } from '@playwright/test';
import {
  addScannedItem,
  forbiddenFinancialText,
  hardeningManifest,
  login,
  ownerStatePath,
  staffStatePath,
} from './support.js';

const manifest = hardeningManifest();
const tenant = manifest.tenants[0];

test.beforeEach(({ }, testInfo) => {
  test.skip(!['desktop-chromium', 'desktop-firefox'].includes(testInfo.project.name));
});

for (const [role, actor] of [['Owner', tenant.owner], ['Staff', tenant.cashiers[0]]]) {
  test(`${role} can sign in through the Firefox public login form`, async ({ browser }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-firefox');

    const context = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    const page = await context.newPage();
    await login(page, actor);
    await context.close();
  });
}

test('Owner and Staff receive their contractual navigation and projection', async ({ browser }) => {
  const ownerContext = await browser.newContext({ storageState: ownerStatePath });
  const ownerPage = await ownerContext.newPage();
  await ownerPage.goto('/app');
  await ownerPage.getByText(/Status Stok|Peringatan Stok/).first().waitFor();
  await expect(ownerPage.getByText('Staff', { exact: true }).first()).toBeVisible();
  await expect(ownerPage.getByText('Pengaturan Analytics', { exact: true }).first()).toBeVisible();
  await ownerContext.close();

  const staffContext = await browser.newContext({ storageState: staffStatePath });
  const staffPage = await staffContext.newPage();
  await staffPage.goto('/app');
  await staffPage.getByText(/Status Stok|Peringatan Stok/).first().waitFor();
  await expect(staffPage.getByText('Barang', { exact: true }).first()).toBeVisible();
  await expect(staffPage.getByText('Supplier', { exact: true }).first()).toBeVisible();
  await expect(staffPage.getByText('Staff', { exact: true })).toHaveCount(0);
  await expect(staffPage.getByText('Pengaturan Analytics', { exact: true })).toHaveCount(0);

  await staffPage.goto('/app/items');
  expect(forbiddenFinancialText(await staffPage.content())).toEqual([]);
  await staffContext.close();
});

for (const method of ['cash', 'qris', 'transfer']) {
  test(`Staff completes ${method} payment with scanner and discount`, async ({ page }) => {
    await page.goto('/app/pos');
    await addScannedItem(page, tenant.items[0].barcode);

    const discount = page.getByLabel('Diskon baris');
    await discount.fill('5');
    await discount.blur();
    await expect(page.getByText('Rp95', { exact: false }).last()).toBeVisible();
    await page.getByRole('button', { name: 'Pilih Pembayaran' }).click();

    if (method === 'cash') {
      await page.locator('input[value="cash"]').check({ force: true });
      await page.locator('#cash-input').fill('200');
    } else {
      await page.locator(`input[value="${method}"]`).check({ force: true });
      await page.getByLabel('Referensi manual (opsional)').fill(`F9A-${method}`);
      await page.getByLabel('Saya telah memastikan dana diterima.').check();
    }

    await page.getByRole('button', { name: 'Konfirmasi Pembayaran' }).click();
    await expect(page.getByText('Transaksi selesai')).toBeVisible();
    await expect(page.locator('#transaction-receipt')).not.toContainText('average_cost');
    await expect(page.locator('#transaction-receipt')).not.toContainText('harga_beli');
    const receipt = await page.locator('#transaction-receipt').innerText();
    const invoice = receipt.match(/POS-\d+-\d{8}-[A-Z0-9]+/)?.[0];
    expect(invoice).toBeTruthy();

    if (method === 'cash') {
      await page.evaluate(() => {
        window.__f9aPrinted = false;
        window.print = () => { window.__f9aPrinted = true; };
      });
      await page.getByRole('button', { name: 'Cetak Struk / PDF' }).click();
      await expect.poll(() => page.evaluate(() => window.__f9aPrinted)).toBe(true);
      await page.emulateMedia({ media: 'print' });
      const printState = await page.evaluate(() => ({
        bodyClass: document.body.classList.contains('printing-receipt'),
        receiptVisibility: getComputedStyle(document.querySelector('#transaction-receipt')).visibility,
        controlsDisplay: getComputedStyle(document.querySelector('#transaction-receipt .iq-no-print')).display,
      }));
      expect(printState).toEqual({
        bodyClass: true,
        receiptVisibility: 'visible',
        controlsDisplay: 'none',
      });
      await page.emulateMedia({ media: 'screen' });
    }

    await page.goto('/app/pos-transactions');
    await expect(page.getByText(invoice, { exact: true }).first()).toBeVisible();
  });
}

test('Staff foreign cashier transaction is disguised as not found', async ({ page, request }) => {
  const other = tenant.cashiers[1];
  const checkout = await request.post('/api/v1/pos/checkout', {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${other.token}`,
      'Idempotency-Key': crypto.randomUUID(),
    },
    data: { items: [{ item_id: tenant.items[1].id, qty: 1, discount_amount: '0.00' }] },
  });
  expect(checkout.status()).toBe(201);
  const transaction = await checkout.json();

  const response = await page.goto(`/app/pos-transactions/${transaction.data.id}`);
  expect(response.status()).toBe(404);
  expect(await page.content()).not.toContain(transaction.data.invoice_number);
});
