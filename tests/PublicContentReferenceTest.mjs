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

// Public pages initially render without network data. They must not present the bundled
// historical seed/reference records as *current* official data. Staff-approved records
// arrive asynchronously from /api/public/content after a successful server response.
const server = await createServer({ configFile: false, plugins: [react()], resolve: { alias: { 'react-router-dom': 'react-router' } }, optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true }, appType: 'custom' });
try {
  const { StaticRouter } = await server.ssrLoadModule('react-router');
  for (const path of [
    '/src/pages/BoardPage.jsx',
    '/src/pages/BoardOfficerPage.jsx',
    '/src/pages/ContactPage.jsx',
    '/src/components/layout/Footer.jsx',
    '/src/pages/DepositsPage.jsx',
    '/src/pages/LoansPage.jsx',
  ]) {
    const { default: Page } = await server.ssrLoadModule(path);
    const html = renderToStaticMarkup(React.createElement(StaticRouter, { location: '/' }, React.createElement(Page)));
    for (const member of [...reference.boardMembers, ...reference.staffMembers]) {
      assert.ok(!html.includes(member.name), `${path}: must not display historical seed person ${member.id} before publication`);
    }
    for (const value of [reference.coopInfo.address, reference.coopInfo.phone, reference.coopInfo.email]) {
      assert.ok(!html.includes(value), `${path}: must not display unverified contact data before publication`);
    }
    if (path.includes('BoardPage')) assert.ok(!html.includes('ชุดที่ 38'), 'Never force a historical board term without published records');
    if (path.includes('Deposits')) assert.ok(!html.includes('1.75%') && !html.includes('1.50%'), 'Never display seeded rates before publication');
    if (path.includes('Loans')) assert.ok(!html.includes(reference.loanProducts[0].title), 'Never display seeded products before publication');
  }
  console.log('PASS: historical fixtures retained for audit, not shown as published public facts');
} finally {
  await server.close();
}
