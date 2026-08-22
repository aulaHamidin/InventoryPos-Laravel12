import fs from 'node:fs';
import path from 'node:path';

export const ownerStatePath = path.resolve('storage/framework/testing/f9a-playwright-owner-state.json');
export const staffStatePath = path.resolve('storage/framework/testing/f9a-playwright-staff-state.json');

export function hardeningManifest() {
  const manifestPath = process.env.F9A_MANIFEST
    || path.resolve('storage/framework/testing/f9a-hardening-manifest.json');

  return JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
}

export async function login(page, actor) {
  await page.goto('/app/login');
  await page.locator('input[type="email"]').fill(actor.email);
  await page.locator('input[type="password"]').fill(actor.password);
  const startedAt = Date.now();
  await page.locator('button[type="submit"]').click();
  await page.getByText(/Status Stok|Peringatan Stok/).first().waitFor({ timeout: 60_000 });
  if (!/\/app(?:\/)?$/.test(page.url())) {
    throw new Error('Login tidak mencapai dashboard aplikasi.');
  }

  return Date.now() - startedAt;
}

export async function addScannedItem(page, barcode) {
  const input = page.locator('#barcode-input');
  await input.focus();
  await input.pressSequentially(barcode, { delay: 5 });
  await input.press('Enter');
  await page.getByText(/Keranjang \(1 baris\)/).waitFor();
}

export function forbiddenFinancialText(content) {
  return [
    'harga_beli',
    'average_cost',
    'harga_beli_terakhir',
    'inventory value',
    'valuation',
    'profit',
    'margin',
  ].filter((needle) => content.toLowerCase().includes(needle));
}
