import { chromium } from '@playwright/test';
import { adminStatePath, enrollAdmin, hardeningManifest, login, ownerStatePath, staffStatePath } from './support.js';

export default async function globalSetup() {
  const manifest = hardeningManifest();
  const baseURL = process.env.F9A_BASE_URL || 'http://127.0.0.1:8001';
  const browser = await chromium.launch();

  try {
    for (const [actor, statePath] of [
      [manifest.tenants[0].owner, ownerStatePath],
      [manifest.tenants[0].cashiers[0], staffStatePath],
    ]) {
      const context = await browser.newContext({ baseURL });
      const page = await context.newPage();
      await login(page, actor);
      await context.storageState({ path: statePath });
      await context.close();
    }

    const adminContext = await browser.newContext({ baseURL });
    const adminPage = await adminContext.newPage();
    await enrollAdmin(adminPage, manifest.platform_admin);
    await adminContext.storageState({ path: adminStatePath });
    await adminContext.close();
  } finally {
    await browser.close();
  }
}
