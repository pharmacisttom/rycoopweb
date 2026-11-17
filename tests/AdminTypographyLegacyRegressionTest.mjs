import assert from 'node:assert/strict';
import fs from 'node:fs';
const css = fs.readFileSync('src/components/admin/AdminModernRefresh.css','utf8');
const welfare = fs.readFileSync('src/pages/AdminWelfarePage.jsx','utf8');
const app = fs.readFileSync('src/App.jsx','utf8');
assert.match(css,/\.acc-shell\s*\{\s*font-size:\s*16px/);
assert.match(css,/\.acc-shell\s+\.acc-page-title/);
assert.match(css,/\.acc-shell\s+\.acc-card-title/);
assert.match(css,/\.acc-shell\s+\.admin-module\s*\{/);
for (const component of ['AdminPageHeader','AdminCard','AdminErrorState','AdminEmptyState','AdminStatusBadge']) {
 assert.ok(welfare.includes(component),'Welfare page missing standardized component: '+component);
}
assert.ok(welfare.includes('admin-table-scroll'),'Welfare table must remain responsive');
assert.ok(welfare.includes("memberAdminApi('/api/admin/welfare'"),'Welfare API preserved');
assert.ok(app.includes('path="/admin/welfare"'),'Welfare staff route preserved');
assert.ok(!css.includes('html {font-size:16px}'),'Never change global member/public typography');
console.log('PASS: staff-only 16px typography, responsive welfare admin, and original protected API routes');
