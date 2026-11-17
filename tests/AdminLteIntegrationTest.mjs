import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import postcss from 'postcss';

const pkg = JSON.parse(fs.readFileSync('package.json','utf8'));
const vendor = JSON.parse(fs.readFileSync('node_modules/admin-lte/package.json','utf8'));
assert.equal(pkg.dependencies['admin-lte'],'4.10.2');
assert.equal(vendor.version,'4.10.2');
assert.equal(pkg.dependencies.bootstrap,'5.3.8');
const cssFile=path.resolve('node_modules/admin-lte/dist/css/adminlte.rycoop.css');
assert.ok(fs.existsSync(cssFile),'generated AdminLTE CSS missing');
const css=fs.readFileSync(cssFile,'utf8');
assert.match(css,/AdminLTE v4\.10\.2/);
assert.match(css,/MIT License/);
const root=postcss.parse(css);
let rules=0;
root.walkRules(rule=>{
  let p=rule.parent, keyframe=false;
  while(p){if(p.type==='atrule'&&/keyframes$/i.test(p.name))keyframe=true;p=p.parent;}
  if(keyframe)return;
  rules++;
  assert.ok(rule.selector.split(',').every(s=>s.trimStart().startsWith('.acc-shell')),
    'Unscoped AdminLTE selector: '+rule.selector.slice(0,120));
});
assert.ok(rules>1000,'AdminLTE rule coverage incomplete');
const shell=fs.readFileSync('src/components/admin/AdminControlCenterShell.jsx','utf8');
for(const cls of ['app-wrapper','app-sidebar','app-header','app-main','app-footer']){
  assert.ok(shell.includes(cls),'AdminLTE shell class missing: '+cls);
}
assert.ok(!shell.includes("import 'admin-lte/dist/js/"),'Do not load DOM-mutating AdminLTE JavaScript');
console.log('PASS: official AdminLTE 4.10.2 installed, CSS scoped to staff shell, and layout integrated');
