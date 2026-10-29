import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import { createRequire } from 'node:module';
import { createServer } from 'vite';
import react from '@vitejs/plugin-react';
// Optional tooling may be supplied externally without changing application dependencies.
const require = createRequire(path.resolve(process.env.PLAYWRIGHT_PACKAGE_ROOT || '.', 'package.json'));
const { chromium } = require('playwright');
const out = 'storage/tmp/activity-slideshow-qa'; await fs.mkdir(out, { recursive: true });
const server = await createServer({ configFile: false, plugins: [react(), { name: 'gallery-fixture', configureServer(vite) {
  vite.middlewares.use('/__gallery_fixture', async (request, response) => {
    const html = await vite.transformIndexHtml('/__gallery_fixture', '<html lang="th"><meta name="viewport" content="width=device-width, initial-scale=1"><body><div id="root"></div><script type="module" src="/tests/fixtures/ActivityGallery.jsx"></script></body></html>');
    response.setHeader('Content-Type', 'text/html'); response.end(html);
  });
} }], server: { host: '127.0.0.1', port: 5193, strictPort: true }, optimizeDeps: { include: ['react', 'react-dom/client'] } });
let browser;
try {
  await server.listen();
  browser = await chromium.launch(process.env.PLAYWRIGHT_EXECUTABLE ? { executablePath: process.env.PLAYWRIGHT_EXECUTABLE } : {});
  const page = await browser.newPage({ reducedMotion: 'no-preference' }); const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.goto('http://127.0.0.1:5193/__gallery_fixture');
  await page.getByText('1 / 3', { exact: true }).first().waitFor();
  await page.getByRole('button', { name: 'ภาพถัดไป', exact: true }).first().click();
  await page.getByText('2 / 3', { exact: true }).first().waitFor();
  await page.locator('.activity-stage').focus(); await page.keyboard.press('ArrowRight');
  await page.getByText('3 / 3', { exact: true }).first().waitFor();
  await page.keyboard.press('ArrowRight'); await page.getByText('1 / 3', { exact: true }).first().waitFor();
  await page.getByRole('button', { name: 'ขยายภาพ', exact: true }).click();
  assert.equal(await page.locator('dialog').evaluate(element => element.open), true);
  await page.keyboard.press('Escape'); assert.equal(await page.locator('dialog').evaluate(element => element.open), false);
  assert.equal(await page.evaluate(() => document.activeElement.textContent), 'ขยายภาพ');
  await page.getByRole('button', { name: 'เล่นสไลด์', exact: true }).click();
  await page.getByRole('button', { name: 'หยุดสไลด์', exact: true }).waitFor();
  await page.getByRole('button', { name: 'หยุดสไลด์', exact: true }).click();
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.waitForFunction(() => [...document.querySelectorAll('button')].some(button => button.textContent === 'เล่นสไลด์' && button.disabled));
  const stage = await page.locator('.activity-stage').boundingBox();
  await page.mouse.move(stage.x + 280, stage.y + 100);await page.mouse.down();await page.mouse.move(stage.x + 100, stage.y + 105);await page.mouse.up();
  await page.getByText('2 / 3', { exact: true }).first().waitFor();
  for (const width of [360, 390, 768, 1024, 1440, 1920]) {
    await page.setViewportSize({ width, height: 900 });
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), `no page overflow at ${width}`);
    assert.ok(await page.locator('.activity-stage img').evaluate(element => element.complete && element.naturalWidth > 0));
    await page.screenshot({ path: `${out}/gallery-${width}.png`, fullPage: true });
  }
  assert.deepEqual(errors, []); console.log('PASS: slideshow navigation, keyboard, lightbox focus/Escape, play/pause, reduced motion, swipe and 6 responsive screenshots');
} finally { await browser?.close(); await server.close(); }
