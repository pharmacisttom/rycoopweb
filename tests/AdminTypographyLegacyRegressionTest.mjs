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
// RC26 welfare CRUD: only authorized staff may mutate; public view remains read-only.
const welfareApi = fs.readFileSync('app/Controllers/Admin/WelfareCmsController.php','utf8');
const routeConfig = fs.readFileSync('config/routes.php','utf8');
const publicWelfare = fs.readFileSync('src/pages/WelfarePage.jsx','utf8');
assert.match(routeConfig,/prefix'=>'api\/admin\/welfare'/);
assert.match(routeConfig,/RoleMiddleware\(\['super_admin'\]\)/);
assert.match(routeConfig,/WelfareCmsController@store/);
assert.match(routeConfig,/WelfareCmsController@update/);
assert.match(routeConfig,/WelfareCmsController@disable/);
assert.match(welfareApi,/STALE_VERSION/);
assert.match(welfareApi,/AuditService::log\('welfare'/);
assert.match(welfareApi,/\$data\['status'\]\s*=\s*'inactive'/);
assert.match(welfare,/changePublication\(item\)/);
assert.match(welfare,/confirmPublish/);
assert.match(welfare,/setStatusFilter/);
assert.match(welfare,/useMemo/);
assert.match(welfare,/setEditing\(\{\.\.\.blank,slug:'welfare-'/,'new Thai welfare entries receive a safe auto-generated slug');
assert.match(welfare,/\/api\/admin\/welfare/);
assert.match(publicWelfare,/fetch\('\/api\/public\/welfare'/);
assert.doesNotMatch(publicWelfare,/memberAdminApi\('/,'public page cannot mutate welfare records');
console.log('RC26_WELFARE_CMS_CRUD_CONTRACT=PASS');
console.log('PASS: staff-only 16px typography, responsive welfare admin, and original protected API routes');
