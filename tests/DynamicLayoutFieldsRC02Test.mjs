#!/usr/bin/env node
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const jsx=readFileSync('src/components/admin/DynamicLayoutEditor.jsx','utf8');
const css=readFileSync('src/components/admin/DynamicLayoutEditor.css','utf8');
for(const name of ['dynamic-layout-editor','dynamic-layout-fields','dynamic-layout-field-full','dynamic-layout-mfa']) {
 assert(jsx.includes(name), 'missing field component scope: '+name);
 assert(css.includes(name), 'missing CSS scope: '+name);
}
assert(jsx.includes("import './DynamicLayoutEditor.css';"));
assert(css.includes('border: 1.5px solid var(--dl-field-border)'));
assert(css.includes(':focus-visible'));
assert(css.includes('@media (max-width: 780px)'));
assert(css.includes('grid-template-columns: minmax(0, 1fr)'));
assert(!/(?:^|\n)\s*(?:html|body)\s*\{/m.test(css),'global layout style forbidden');
for(const key of ['textarea','select','input:not([type="checkbox"])'])assert(css.includes(key));
console.log('PASS: Dynamic Layout form fields have visible borders, focus states and mobile layout');
