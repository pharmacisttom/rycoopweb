#!/usr/bin/env node
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const read = path => readFileSync(path,'utf8');
const shell=read('src/components/admin/AdminControlCenterShell.jsx');
const css=read('src/components/admin/AdminVisualConsistencyRC03.css');
const editor=read('src/components/admin/DynamicLayoutEditor.jsx');
const service=read('app/Services/DynamicLayoutService.php');
const publicUI=read('src/components/layout/PublishedDynamicSections.jsx');
assert(shell.includes("import './AdminVisualConsistencyRC03.css';"));
assert(shell.indexOf("import './AdminLayoutPolishRC02.css';") < shell.indexOf("import './AdminVisualConsistencyRC03.css';"));
for(const fragment of ['.acc-shell .acc-content','--admin-ui-gridline','border-right: 1px solid','overflow-x: auto','focus-visible','@media (max-width: 760px)']) {
 assert(css.includes(fragment),'missing admin visual contract '+fragment);
}
assert(!/(^|\n)\s*(?:html|body)\s*\{/m.test(css),'global CSS forbidden');
assert(css.includes('.admin-module,.member-admin,.public-content-admin'),'must protect multiple admin modules');
for(const fragment of ['APPEARANCE_PRESETS','type="color"','dynamic-layout-color-preview','current.appearance'])assert(editor.includes(fragment),'missing color editor '+fragment);
assert(service.includes('validateAppearance') && service.includes('contrastRatio'),'color backend validation missing');
assert(service.includes('self::DEFAULT_APPEARANCE'),'legacy default missing');
assert(publicUI.includes('backgroundColor:allowedColor') && publicUI.includes('color:allowedColor'),'published color display missing');
console.log('PASS: Admin-wide visual consistency and section color control contracts');
