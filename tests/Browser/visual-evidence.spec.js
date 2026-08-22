import path from 'node:path';
import { expect, test } from '@playwright/test';
import { addScannedItem, hardeningManifest } from './support.js';

const manifest = hardeningManifest();
const tenant = manifest.tenants[0];

test('captures sanitized Staff dashboard and POS evidence at contractual viewports', async ({ page }, testInfo) => {
  test.skip(!['desktop-chromium', 'mobile-chromium'].includes(testInfo.project.name));

  const evidenceDir = process.env.F9A_EVIDENCE_DIR || testInfo.outputDir;
  const suffix = testInfo.project.name === 'desktop-chromium' ? 'desktop-1440x900' : 'mobile-390x844';

  await page.goto('/app');
  await page.getByText(/Status Stok|Peringatan Stok/).first().waitFor();
  await page.screenshot({ path: path.join(evidenceDir, `staff-dashboard-${suffix}.png`), fullPage: true });

  await page.goto('/app/pos');
  await addScannedItem(page, tenant.items[3].barcode);
  await page.getByRole('button', { name: 'Pilih Pembayaran' }).click();
  await expect(page.getByRole('radiogroup', { name: 'Metode pembayaran' })).toBeVisible();
  await page.screenshot({ path: path.join(evidenceDir, `staff-pos-payment-${suffix}.png`), fullPage: true });

  const forbidden = await page.goto('/app/staff');
  expect([403, 404]).toContain(forbidden.status());
  await page.screenshot({ path: path.join(evidenceDir, `staff-unauthorized-${suffix}.png`), fullPage: true });
});
