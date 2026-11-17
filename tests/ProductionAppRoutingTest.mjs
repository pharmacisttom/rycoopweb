import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { setTimeout as sleep } from 'node:timers/promises';
import { chromium } from 'playwright';

// Run: NODE_ENV=production npm run build
// Then: node tests/ProductionAppRoutingTest.mjs
// No production API access or real credentials. Uses loopback preview and synthetic API responses.
const port = 5199;
const origin = `http://127.0.0.1:${port}`;
const server = spawn(process.execPath, ['node_modules/vite/bin/vite.js', 'preview', '--host', '127.0.0.1', '--port', String(port), '--strictPort'], {
  env: { ...process.env, NODE_ENV: 'production' },
  stdio: ['ignore', 'pipe', 'pipe']
});
let output = '';
server.stdout.on('data', data => { output += String(data); });
server.stderr.on('data', data => { output += String(data); });
let browser;
try {
  let ready = false;
  for (let i = 0; i < 40; i++) {
    if (server.exitCode !== null) throw new Error(`Preview exited: ${output}`);
    try {
      const response = await fetch(`${origin}/app/`);
      if (response.ok) { ready = true; break; }
    } catch {}
    await sleep(250);
  }
  assert.ok(ready, `Vite preview unavailable: ${output}`);
  browser = await chromium.launch(process.env.PLAYWRIGHT_EXECUTABLE
    ? { executablePath: process.env.PLAYWRIGHT_EXECUTABLE }
    : {});
  const page = await browser.newPage();
  await page.route('**/api/**', route => route.fulfill({
    status: 401, contentType: 'application/json',
    body: JSON.stringify({ success: false, message: 'Unauthenticated test fixture' })
  }));
  await page.route('**/csrf-token', route => route.fulfill({
    status: 200, contentType: 'application/json',
    body: JSON.stringify({ token: 'fixture-only' })
  }));
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  for (const pathname of ['/app/', '/app/about', '/app/admin/login', '/app/member/login']) {
    const response = await page.goto(`${origin}${pathname}`, { waitUntil: 'domcontentloaded' });
    assert.equal(response.status(), 200, `HTTP failure for ${pathname}`);
    await page.locator('#root').waitFor();
    await page.waitForTimeout(700);
    const body = await page.locator('body').innerText();
    assert.ok(!body.includes('ไม่พบหน้าที่คุณต้องการ'), `React 404 rendered for ${pathname}`);
    assert.ok(!body.includes('404 —'), `React 404 rendered for ${pathname}`);
    console.log(`PASS: ${pathname}`);
  }
  await page.goto(`${origin}/app/this-route-does-not-exist-rc02`);
  await page.getByText('ไม่พบหน้าที่คุณต้องการ').waitFor({ timeout: 10000 });
  assert.deepEqual(errors, [], `Browser JS errors: ${errors.join('; ')}`);
  console.log('PASS: unknown route displays 404, no page errors');
} finally {
  if (browser) await browser.close();
  server.kill('SIGTERM');
}
