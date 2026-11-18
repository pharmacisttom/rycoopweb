#!/usr/bin/env node
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
const shell=readFileSync('src/components/admin/AdminControlCenterShell.jsx','utf8');
const app=readFileSync('src/App.jsx','utf8');
const page=readFileSync('src/pages/AdminUserManualPage.jsx','utf8');
const css=readFileSync('src/pages/AdminUserManualPage.css','utf8');
assert(shell.includes("id: 'help'") && shell.includes("to: '/admin/manual'"),'manual sidebar missing');
assert(shell.includes('help: true'),'manual sidebar group not expanded by default');
assert(app.includes('path="/admin/manual" element={<StaffProtectedRoute><AdminUserManualPage /></StaffProtectedRoute>}'),'manual route must be protected');
assert(page.includes('AdminControlCenterShell') && page.includes('window.print()'),'manual page or print missing');
for(const word of ['คณะกรรมการชุดที่ 38/39','MFA','ระบบสมาชิก','Audit Trail','Maker / Checker'])assert(page.includes(word),word);
assert(css.includes('@media print'),'print stylesheet absent');
console.log('PASS: authenticated staff handbook linked from sidebar and printable');
