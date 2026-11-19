#!/usr/bin/env node
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';

const shell = readFileSync('src/components/admin/AdminControlCenterShell.jsx','utf8');
const css = readFileSync('src/components/admin/AdminLayoutPolishRC02.css','utf8');
assert(shell.includes("import './AdminLayoutPolishRC02.css';"), 'admin fix stylesheet must be loaded');
assert(shell.indexOf("import './AdminModernRefresh.css';") < shell.indexOf("import './AdminLayoutPolishRC02.css';"), 'override stylesheet must follow existing admin theme');
for (const part of [
  '.acc-shell :is(.admin-table-scroll,.acc-table-wrap,.table-responsive)',
  '.acc-shell :is(.admin-card,.surface-card,.acc-card,.card)',
  '@media (max-width: 760px)',
]) assert(css.includes(part), 'missing layout guard ' + part);
assert(!/(?:^|\\n)\\s*(?:body|html)\\s*\\{/m.test(css), 'global document CSS is forbidden');
console.log('PASS: Admin Layout RC02 scoped responsive CSS contract');
