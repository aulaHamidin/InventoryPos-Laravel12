import { expect, test } from '@playwright/test';
import { adminStatePath, ownerStatePath } from './support.js';

test('Super Admin sees MRR and the F10 platform resources after mandatory MFA', async ({ browser }) => {
  const context = await browser.newContext({ storageState: adminStatePath });
  const page = await context.newPage();
  await page.goto('/admin');
  await expect(page.getByText('MRR Aktif')).toBeVisible();
  for (const route of ['/admin/tenants', '/admin/plans', '/admin/subscriptions', '/admin/invoices', '/admin/billing-payments']) {
    await page.goto(route);
    await expect(page).toHaveURL(new RegExp(`${route.replaceAll('/', '\\/')}(?:\\?.*)?$`, 'u'));
    await expect(page.locator('main')).toBeVisible();
  }
  await expect(page.locator('body')).not.toContainText('totp_secret');
  await context.close();
});

test('Owner can inspect billing capability and cancel an unapproved deletion request', async ({ browser }) => {
  const context = await browser.newContext({ storageState: ownerStatePath });
  const page = await context.newPage();
  await page.goto('/app/billing');
  await expect(page.getByText('Langganan saat ini')).toBeVisible();
  await expect(page.getByText('Legacy F0-F9')).toBeVisible();
  await page.locator('textarea').fill('Data sintetis F10 tidak lagi diperlukan untuk pengujian.');
  await page.getByRole('button', { name: 'Ajukan penghapusan' }).click();
  await expect(page.getByText(/Status:.*requested/u)).toBeVisible();
  page.once('dialog', (dialog) => dialog.accept());
  await page.getByRole('button', { name: 'Batalkan permintaan' }).click();
  await expect(page.getByRole('button', { name: 'Ajukan penghapusan' })).toBeVisible();
  await context.close();
});
