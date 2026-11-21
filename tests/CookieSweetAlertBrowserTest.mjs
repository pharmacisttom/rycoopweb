import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { createServer } from 'vite';
import react from '@vitejs/plugin-react';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const port = 5198;
const plugin = {
  name: 'cookie-swal-browser-fixture',
  configureServer(vite) {
    vite.middlewares.use('/__cookie_swal_fixture', async (_, response) => {
      response.setHeader('Content-Type', 'text/html; charset=utf-8');
      response.end(await vite.transformIndexHtml('/__cookie_swal_fixture',
        '<html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><div id="root"></div><script type="module" src="/tests/fixtures/CookieSweetAlertBrowserFixture.jsx"></script></body></html>'));
    });
  },
};
const server = await createServer({
  configFile: false, plugins: [plugin, react()],
  server: { host: '127.0.0.1', port, strictPort: true },
});
let browser;
try {
  await server.listen();
  browser = await chromium.launch(process.env.PLAYWRIGHT_EXECUTABLE
    ? { executablePath: process.env.PLAYWRIGHT_EXECUTABLE } : {});
  const page = await browser.newPage({ viewport: { width: 1366, height: 850 } });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  let persisted = null;
  await page.route('**/csrf-token', route => route.fulfill({
    status: 200, contentType: 'application/json', body: JSON.stringify({ token: 'fixture-csrf' })
  }));
  await page.route('**/api/cookie-consent', async route => {
    persisted = route.request().postDataJSON();
    await route.fulfill({
      status: 200, contentType: 'application/json', body: JSON.stringify({ success: true })
    });
  });
  await page.goto('http://127.0.0.1:' + port + '/__cookie_swal_fixture');
  const dialog = page.getByRole('dialog', { name: 'เว็บไซต์นี้ใช้คุกกี้' });
  await dialog.waitFor({ state: 'visible' });
  assert.equal(await page.locator('body > .cookie-consent').count(), 1, 'cookie dialog must portal to body');
  const elevated = await dialog.evaluate(element => {
    const z = Number.parseInt(getComputedStyle(element).zIndex, 10);
    const bounds = element.getBoundingClientRect();
    return { z, centerX: bounds.left + bounds.width / 2, centerY: bounds.top + bounds.height / 2 };
  });
  assert.ok(elevated.z >= 10000);
  await dialog.getByRole('button', { name: 'ปฏิเสธ', exact: true }).click();
  await dialog.waitFor({ state: 'detached' });
  assert.equal(persisted?.necessary, 1);
  assert.equal(persisted?.analytics, 0);
  assert.equal(persisted?.marketing, 0);
  await page.getByRole('button', { name: 'เปลี่ยนการตั้งค่าคุกกี้' }).click();
  await dialog.waitFor({ state: 'visible' });
  await dialog.getByRole('button', { name: 'ยอมรับทั้งหมด' }).click();
  await dialog.waitFor({ state: 'detached' });
  assert.equal(persisted?.analytics, 1);
  await page.locator('#test-toast').click();
  await page.locator('.swal2-popup.swal2-toast').waitFor({ state: 'visible' });
  await page.locator('#test-confirm').click();
  await page.locator('.swal2-popup:not(.swal2-toast)').waitFor({ state: 'visible' });
  assert.equal(errors.length, 0, errors.join('; '));
  console.log('COOKIE_MODAL_BROWSER_E2E=PASS');
  console.log('SWEETALERT2_BROWSER_E2E=PASS');
} finally {
  if (browser) await browser.close();
  await server.close();
}
