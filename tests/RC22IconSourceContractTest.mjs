import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const read=p=>readFileSync(p,'utf8');
const brand=read('src/components/branding/BrandIcon.jsx');
const login=read('src/pages/LoginPage.jsx');
const footer=read('src/components/layout/Footer.jsx');
const shell=read('src/components/admin/AdminControlCenterShell.jsx');
const source=read('src/components/admin/AdminSidebarItemIcon.jsx');
const css=read('src/components/branding/BrandIcon.css');
for (const slug of ['google','facebook','line']) {
 const svg=read('src/assets/simple-icons/'+slug+'.svg');
 assert.match(svg,/<svg\b/);
 assert.match(svg,/<path\s+d="/);
 assert.match(brand,new RegExp('simple-icons/'+slug+'\\.svg'));
}
assert.match(brand,/aria-hidden="true"/);
assert.match(login,/<BrandIcon brand="google"/);
assert.match(login,/href=\{'\/auth\/google\/start'/);
assert.match(footer,/<BrandIcon brand="facebook"/);
assert.match(footer,/<BrandIcon brand="line"/);
assert.match(shell,/<AdminSidebarItemIcon path=\{item\.to\}/);
assert.match(source,/from 'lucide-react'/);
assert.match(css,/:focus-visible/);
assert.match(css,/min-height:44px/);
assert.doesNotMatch(brand,/https?:\/\//);
console.log('RC22_LUCIDE_SIMPLE_ICONS_IMPECCABLE_CONTRACT=PASS');
