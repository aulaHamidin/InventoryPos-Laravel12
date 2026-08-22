import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

export const ownerStatePath = path.resolve('storage/framework/testing/f9a-playwright-owner-state.json');
export const staffStatePath = path.resolve('storage/framework/testing/f9a-playwright-staff-state.json');
export const adminStatePath = path.resolve('storage/framework/testing/f10-playwright-admin-state.json');

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

function decodeBase32(value) {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  let bits = '';
  for (const character of value.replace(/=+$/u, '').toUpperCase()) {
    bits += alphabet.indexOf(character).toString(2).padStart(5, '0');
  }
  const bytes = [];
  for (let index = 0; index + 8 <= bits.length; index += 8) {
    bytes.push(Number.parseInt(bits.slice(index, index + 8), 2));
  }
  return Buffer.from(bytes);
}

export function totp(secret, timestamp = Date.now()) {
  const counter = Math.floor(timestamp / 1000 / 30);
  const buffer = Buffer.alloc(8);
  buffer.writeBigUInt64BE(BigInt(counter));
  const digest = crypto.createHmac('sha1', decodeBase32(secret)).update(buffer).digest();
  const offset = digest[digest.length - 1] & 0x0f;
  const binary = ((digest[offset] & 0x7f) << 24)
    | ((digest[offset + 1] & 0xff) << 16)
    | ((digest[offset + 2] & 0xff) << 8)
    | (digest[offset + 3] & 0xff);
  return String(binary % 1_000_000).padStart(6, '0');
}

export async function enrollAdmin(page, actor) {
  await page.goto('/admin/login');
  await page.locator('input[type="email"]').fill(actor.email);
  await page.locator('input[type="password"]').fill(actor.password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/\/admin\/mfa\/setup/u);
  await page.locator('#code').fill(totp(actor.totp_secret));
  await page.getByRole('button', { name: 'Konfirmasi' }).click();
  await page.getByText('Simpan recovery code').waitFor();
  await page.getByRole('link', { name: 'Saya sudah menyimpan' }).click();
  await page.waitForURL(/\/admin(?:\/)?$/u);
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
