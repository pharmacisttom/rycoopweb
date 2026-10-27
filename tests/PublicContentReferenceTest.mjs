import assert from 'node:assert/strict';
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';
import react from '@vitejs/plugin-react';

const reference = JSON.parse(fs.readFileSync(new URL('../src/data/legacyReference.json', import.meta.url)));
assert.deepEqual(reference.interestRates.deposits.map(p => [p.rate, p.minDeposit]), [['1.75%', '1,000 บาท'], ['1.50%', '100 บาท']]);
assert.equal(reference.boardMembers.length, 15);
assert.equal(new Set(reference.boardMembers.map(p => p.name)).size, 15);
assert.equal(reference.boardMembers[0].name, 'นางสาวอุษา อิศรางกูร ณ อยุธยา');
assert.equal(reference.interestRates.effectiveDate, null, 'Do not invent an effective date');
assert.equal(reference.staffMembers.length, 6);
assert.equal(reference.staffMembers.find(p => p.id === 4).name, 'นางสาวศุจิพัชร เหล็กเพชร');
const phpCoop = JSON.parse(execFileSync('php', ['-r', "function env($key,$default=null){return $default;} $app=require 'config/app.php';echo json_encode($app['coop'],JSON_UNESCAPED_UNICODE);"], { encoding: 'utf8' }));
for (const [phpKey, jsKey] of [['full_name_th', 'nameTh'], ['phone', 'phone'], ['fax', 'fax'], ['email', 'email'], ['address', 'address'], ['office_hours', 'workHours']]) {
  assert.equal(phpCoop[phpKey], reference.coopInfo[jsKey], `PHP config and public reference agree: ${phpKey}`);
}
for (const group of reference.boardGroups) {
  assert.equal(group.members.length, 5);
  for (const [id] of group.members) assert.ok(reference.boardMembers.some(p => p.id === id));
}
const expectedLimits = { emergency: [200000, 12], special: [3000000, 360], goods_installment: [300000, 84], education: [400000, 36], life_security: [3000000, 360] };
for (const [id, expected] of Object.entries(expectedLimits)) {
  const p = reference.loanProducts.find(p => p.id === id);
  assert.deepEqual([p.maxLimit, p.maxTerm], expected);
}

// Render the real pages to catch imports, JSON wiring, omitted groups and stale visible rates.
const server = await createServer({ configFile: false, plugins: [react()], resolve: { alias: { 'react-router-dom': 'react-router' } }, optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true }, appType: 'custom' });
try {
  const { StaticRouter } = await server.ssrLoadModule('react-router');
  for (const [path, strings] of [
    ['/src/pages/BoardPage.jsx', ['ชุดที่ 38', ...reference.boardMembers.map(p => p.name), ...reference.boardGroups.map(g => g.title), ...reference.advisors.map(p => p[0]), ...reference.auditors.map(p => p[0])]],
    ['/src/pages/BoardOfficerPage.jsx', reference.staffMembers.map(p => p.name)],
    ['/src/pages/ContactPage.jsx', [reference.coopInfo.address, reference.coopInfo.phone, reference.coopInfo.email]],
    ['/src/components/layout/Footer.jsx', [reference.coopInfo.address, reference.coopInfo.phone, reference.coopInfo.email, reference.coopInfo.slogan]],
    ['/src/pages/DepositsPage.jsx', ['1.75%', '1.50%', '1,000 บาท', '100 บาท', reference.sources.deposits.url]],
    ['/src/pages/LoansPage.jsx', ['200,000', '300,000', '400,000', 'ไม่มีเงินเฉลี่ยคืน', reference.sources.loans.url]],
  ]) {
    const { default: Page } = await server.ssrLoadModule(path);
    const html = renderToStaticMarkup(React.createElement(StaticRouter, { location: '/' }, React.createElement(Page)));
    for (const value of strings) assert.ok(html.includes(value), `${path}: missing ${value}`);
    if (path.includes('Deposits')) assert.ok(!html.includes('3.10%'));
    if (path.includes('Contact') || path.includes('Footer')) {
      assert.ok(!html.includes('facebook.com/rayongcoop'));
      assert.ok(!html.includes('038-611178'));
      assert.ok(!html.includes('contact@rayongcoop.com'));
    }
  }
  console.log('PASS: reference values, board groups and rendered public pages');
} finally {
  await server.close();
}
