import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import { spawn, execFileSync } from 'node:child_process';
import { createHmac, randomUUID } from 'node:crypto';
import { createServer } from 'vite';
import react from '@vitejs/plugin-react';
import { chromium } from 'playwright';

const dir = path.resolve('storage/tmp/dynamic-layout-browser-' + randomUUID()); await fs.mkdir(dir, { recursive: true });
const phpPort = Number(process.env.DYNAMIC_LAYOUT_BROWSER_PORT || 5398);
const child = spawn('php', ['-d', `upload_tmp_dir=${dir}`, '-S', `127.0.0.1:${phpPort}`, 'tests/fixtures/DynamicLayoutRouter.php'], { windowsHide: true, env: { ...process.env, DYNAMIC_LAYOUT_HTTP: '1', DYNAMIC_LAYOUT_FIXTURE_FILE: path.join(dir, 'fixture.sqlite') }, stdio: ['ignore', 'pipe', 'pipe'] });
let logs = ''; child.stderr.on('data', b => { logs += b; });
const plugin = { name: 'layout-browser-fixture', enforce: 'pre', load(id) { if (path.relative(process.cwd(), id).replaceAll('\\', '/') === 'src/context/AuthContext.jsx') return 'export const useAuth=()=>({can:()=>true,user:{role:"super_admin"},isLoggedIn:true});'; }, configureServer(vite) { vite.middlewares.use('/__layout', async (req, res) => { res.setHeader('Content-Type', 'text/html'); res.end(await vite.transformIndexHtml('/__layout', '<html lang="th"><meta name="viewport" content="width=device-width,initial-scale=1"><div id="root"></div><script type="module" src="/tests/fixtures/DynamicLayoutUI.jsx"></script></html>')); }); } };
const target = `http://127.0.0.1:${phpPort}`;
const server = await createServer({ configFile: false, plugins: [plugin, react()], cacheDir: 'storage/tmp/vite-dynamic-layout', server: { host: '127.0.0.1', port: 0, proxy: { '/api': target, '/csrf-token': target, '/__fixture': target }, watch: { ignored: ['**/storage/tmp/**', '**/public/app/**'] } } });
let browser; const images = [];
function totp(slice) { let bits = '', bytes = []; for (const c of 'JBSWY3DPEHPK3PXP') bits += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'.indexOf(c).toString(2).padStart(5, '0'); for (let i = 0; i + 8 <= bits.length; i += 8) bytes.push(parseInt(bits.slice(i, i + 8), 2)); const count = Buffer.alloc(8); count.writeBigUInt64BE(BigInt(slice)); const h = createHmac('sha1', Buffer.from(bytes)).update(count).digest(); return String((h.readUInt32BE(h[19] & 15) & 0x7fffffff) % 1000000).padStart(6, '0'); }
try {
  await server.listen(); const base = server.resolvedUrls.local[0];
  browser = await chromium.launch({ executablePath: process.env.PLAYWRIGHT_EXECUTABLE }); const context = await browser.newContext(); const page = await context.newPage(); page.setDefaultTimeout(15000); const errors = []; page.on('pageerror', e => errors.push(e.message));
  let ready = false; for (let i = 0; i < 60; i++) { try { if ((await context.request.get(base + 'csrf-token')).ok()) { ready = true; break; } } catch {} await new Promise(r => setTimeout(r, 100)); } assert(ready, logs);
  await context.request.post(base + '__fixture/login', { data: { id: 1 } }); await page.setViewportSize({ width: 1440, height: 1000 }); await page.goto(base + '__layout');
  await page.getByRole('heading', { name: 'โครงสร้างเว็บไซต์และกิจกรรม', exact: true }).waitFor();
  await page.getByRole('button', { name: '+ Add Section', exact: true }).click();
  await page.getByLabel('ชื่อ section', { exact: true }).fill('รูปกิจกรรมของสหกรณ์'); await page.getByLabel('URL slug', { exact: true }).fill('activity-gallery'); await page.getByLabel('ประเภท', { exact: true }).selectOption('gallery'); await page.getByRole('button', { name: 'เพิ่มเป็น Draft', exact: true }).click();
  await page.getByRole('status').filter({ hasText: 'บันทึกร่างแล้ว' }).waitFor();
  assert.equal((await (await context.request.get(base + 'api/public/v1/layout')).json()).data, null);
  await page.getByRole('tab', { name: 'หมวดกิจกรรม', exact: true }).click(); await page.getByLabel('ชื่อหมวด', { exact: true }).fill('กิจกรรมสมาชิก'); await page.getByRole('button', { name: 'บันทึกหมวด', exact: true }).click(); await page.getByRole('button', { name: 'เพิ่มหมวดใหม่', exact: true }).click();
  await page.getByLabel('ชื่อหมวด', { exact: true }).fill('อบรม'); await page.getByLabel('หมวดแม่', { exact: true }).selectOption({ label: 'กิจกรรมสมาชิก' }); await page.getByRole('button', { name: 'บันทึกหมวด', exact: true }).click(); await page.getByText('อบรม ← กิจกรรมสมาชิก', { exact: false }).waitFor();
  await page.getByRole('tab', { name: 'อัลบั้มกิจกรรม', exact: true }).click(); await page.getByLabel('ชื่ออัลบั้ม', { exact: true }).fill('กิจกรรมอบรมร่วมใจ'); await page.getByLabel('URL slug', { exact: true }).fill('browser-album'); await page.getByLabel('หมวดกิจกรรม', { exact: true }).selectOption({ label: 'อบรม' }); await page.getByLabel('รูปแบบแสดงภาพ', { exact: true }).selectOption('masonry'); await page.getByRole('button', { name: 'บันทึกอัลบั้ม Draft', exact: true }).click(); await page.getByRole('button', { name: 'กิจกรรมอบรมร่วมใจ (Draft)', exact: true }).click();
  const png = execFileSync('php', ['-r', '$i=imagecreatetruecolor(1000,600);imagepng($i);imagedestroy($i);']);
  await page.getByLabel('อัปโหลดหลายภาพ', { exact: false }).setInputFiles([{ name: 'activity.png', mimeType: 'image/png', buffer: png }, { name: 'activity-two.png', mimeType: 'image/png', buffer: png }]);
  await page.getByLabel('Alt text', { exact: true }).first().waitFor(); await page.getByLabel('Alt text', { exact: true }).first().fill('ภาพอบรมของสมาชิก'); await page.getByLabel('คำบรรยาย', { exact: true }).first().fill('ร่วมกิจกรรมสหกรณ์'); await page.getByRole('button', { name: 'บันทึกคำบรรยาย', exact: true }).first().click();
  await page.getByRole('status').filter({ hasText: 'บันทึกร่างแล้ว' }).waitFor();
  await page.getByRole('tab', { name: 'Sections', exact: true }).click(); await page.getByRole('button', { name: 'รูปกิจกรรมของสหกรณ์', exact: true }).click(); await page.getByLabel('ขนาดหน้าจอตัวอย่าง', { exact: true }).selectOption('mobile'); await page.getByLabel('เปิดแสดงเนื้อหา', { exact: true }).check(); await page.getByRole('button', { name: 'บันทึก Draft', exact: true }).click();
  await page.getByRole('status').filter({ hasText: 'บันทึกร่างแล้ว' }).waitFor();
  await page.getByRole('button', { name: '+ Add Subsection', exact: true }).click(); await page.getByLabel('ชื่อ section', { exact: true }).fill('คำอธิบายกิจกรรม'); await page.getByLabel('URL slug', { exact: true }).fill('gallery-description'); await page.locator('.ql-editor').fill('คำอธิบายร่างกิจกรรม'); await page.getByRole('button', { name: 'เพิ่มเป็น Draft', exact: true }).click(); await page.getByRole('button', { name: 'คำอธิบายกิจกรรม', exact: true }).click();
  await page.getByRole('button', { name: 'ลบ section', exact: true }).click(); await page.getByRole('button', { name: 'ยืนยันการลบ', exact: true }).click(); await page.getByRole('button', { name: 'คำอธิบายกิจกรรม', exact: true }).waitFor({ state: 'hidden' }); await page.getByRole('button', { name: 'รูปกิจกรรมของสหกรณ์', exact: true }).click();
  await page.getByRole('button', { name: 'เลื่อน รูปกิจกรรมของสหกรณ์ ขึ้น', exact: true }).click(); await page.getByRole('button', { name: 'Save Draft', exact: true }).click(); await page.getByRole('status').filter({ hasText: 'บันทึกร่างแล้ว' }).waitFor();
  const dragged = page.getByRole('button', { name: 'รูปกิจกรรมของสหกรณ์', exact: true }).locator('xpath=../..'); const dropTarget = page.getByRole('button', { name: 'บริการด่วน', exact: true }).locator('xpath=../..'); await dragged.dragTo(dropTarget); await page.getByRole('button', { name: 'Save Draft', exact: true }).click(); await page.getByRole('status').filter({ hasText: 'บันทึกร่างแล้ว' }).waitFor();
  const reordered = (await (await context.request.get(base + 'api/admin/v1/layout/draft')).json()).data.state.sections; assert.equal(reordered[1].slug, 'activity-gallery');
  for (const width of [1440, 768, 375]) { await page.setViewportSize({ width, height: 1000 }); assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `layout overflow at ${width}`); await page.screenshot({ path: path.join(dir, `editor-${width}.png`), fullPage: true }); }
  await page.getByRole('button', { name: 'Publish / เผยแพร่', exact: true }).click(); await page.getByRole('button', { name: 'ยืนยัน', exact: true }).click(); await page.getByLabel('รหัสยืนยัน 6 หลัก', { exact: false }).fill(totp(Math.floor(Date.now() / 30000))); await page.getByRole('button', { name: 'ยืนยันและทำคำสั่งนี้', exact: true }).click(); await page.getByRole('status').filter({ hasText: 'เผยแพร่สำเร็จแล้ว' }).waitFor();
  await page.getByRole('tab', { name: 'ประวัติเผยแพร่', exact: true }).click(); await page.getByRole('button', { name: 'ย้อนกลับเวอร์ชันนี้', exact: true }).first().click(); await page.getByRole('button', { name: 'ยืนยัน', exact: true }).click(); await page.getByLabel('รหัสยืนยัน 6 หลัก', { exact: false }).fill(totp(Math.floor(Date.now() / 30000) + 1)); await page.getByRole('button', { name: 'ยืนยันและทำคำสั่งนี้', exact: true }).click(); await page.getByRole('status').filter({ hasText: 'เผยแพร่สำเร็จแล้ว' }).waitFor();
  assert.equal((await (await context.request.get(base + 'api/public/v1/layout')).json()).data.revision_id, 2);
  const album = (await (await context.request.get(base + 'api/public/v1/layout/albums/browser-album')).json()).data; assert.equal(album.images.length, 2); images.push(...album.images.map(i => i.id));
  await page.goto(base + '__layout?view=public'); await page.getByRole('heading', { name: 'กิจกรรมอบรมร่วมใจ', exact: true }).waitFor(); assert((await page.title()).includes('กิจกรรมอบรมร่วมใจ')); await page.getByRole('button', { name: 'ขยายภาพ ภาพอบรมของสมาชิก', exact: true }).click(); await page.getByRole('dialog', { name: 'ภาพกิจกรรมขนาดใหญ่' }).waitFor(); await page.keyboard.press('ArrowRight'); await page.keyboard.press('Escape'); await page.getByRole('dialog').waitFor({ state: 'hidden' });
  assert.deepEqual(errors, []); console.log('PASS: real PHP-backed browser section/category nesting/album/upload/alt/order/draft/TOTP publication, 1440/768/375 layouts, public SEO and keyboard lightbox; screenshots in ' + dir);
} catch (error) { const page = browser?.contexts()[0]?.pages()[0]; if (page) { await page.screenshot({ path: path.join(dir, 'failure.png'), fullPage: true }); await fs.writeFile(path.join(dir, 'failure.html'), await page.content()); } throw error;
} finally {
  await browser?.close(); await server.close(); child.kill(); await new Promise(r => { child.once('exit', r); setTimeout(r, 2000); });
  for (const id of images) for (const size of ['full', 'thumb']) await fs.rm(path.resolve('storage/private/layout-gallery', `${id}-${size}.webp`), { force: true });
  await fs.writeFile(path.join(dir, 'server.log'), logs);
}
