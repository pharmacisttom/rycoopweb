import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const read=p=>readFileSync(p,'utf8');
const icons=read('src/components/admin/AdminSidebarItemIcon.jsx');
const shell=read('src/components/admin/AdminControlCenterShell.jsx');
const css=read('src/components/admin/AdminIconPolishRC20.css');
assert.match(shell,/import \{ AdminSidebarItemIcon \} from '\.\/AdminSidebarItemIcon'/);
assert.match(shell,/<AdminSidebarItemIcon path=\{item\.to\} \/>/);
assert.match(shell,/className="acc-nav-item-text"/);
assert.match(css,/\.acc-sidebar-nav \.acc-nav-items \.acc-nav-item-icon/);
assert.match(icons,/aria-hidden="true"/);
for(const route of ['/admin/manual','/admin/mega-menu','/admin/content','/admin/members',
 '/admin/security','/admin/access','/admin/backups','/admin/settings','/admin/audit-logs']) {
 assert.ok(icons.includes("'"+route+"'"),'missing icon mapping for '+route);
}
assert.ok(!icons.includes('localStorage')&&!icons.includes('fetch('),'icon mapping must remain presentation-only');
console.log('ADMIN_SIDEBAR_ICON_CONTRACT=PASS');
