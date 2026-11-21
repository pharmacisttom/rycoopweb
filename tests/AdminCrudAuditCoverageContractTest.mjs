import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const coverage = readFileSync('docs/audit/CRUD_COVERAGE_MATRIX.md', 'utf8');
const report = readFileSync('docs/qa/RC17_ADMIN_CRUD_AND_MANUAL_AUDIT_20261121.md', 'utf8');
const manual = readFileSync('src/pages/AdminUserManualPage.jsx', 'utf8');
const workflow = readFileSync('.github/workflows/ci.yml', 'utf8');

const expected = [
  'User & Staff Administration', 'Roles Management', 'Permissions Management',
  'Departments', 'Staff Invitations', 'Google Account Linking', 'News Management',
  'Activity Gallery', 'Slideshow', 'Announcements', 'FAQ Management',
  'Public Content CMS', 'Cooperative Information', 'Board & Officers',
  'Documents Management', 'Member Management', 'Dividend Import',
  'Financial Content', 'Settings', 'Visitor Analytics', 'Audit Logs',
  'Backup Management', 'Reports'
];

assert.equal(expected.length, 23);
for (const item of expected) assert.ok(coverage.includes('**' + item + '**'), 'Source inventory missing ' + item);
for (let i = 1; i <= 23; i += 1) {
  assert.ok(new RegExp('^\\| ' + i + ' \\|', 'm').test(report), 'QA matrix missing module ' + i);
}
for (const test of [
  'AdminCrudMySqlTest.php', 'AdminCrudBrowserTest.mjs',
  'AdminUIBrowserTest.mjs', 'AdminUserManualRC03Test.mjs',
  'SecurityRegressionTest.php'
]) {
  assert.ok(workflow.includes(test), 'CI does not reference ' + test);
  assert.ok(report.includes(test), 'QA report does not describe ' + test);
}
for (const text of ['manual-crud', 'manual-cookie', 'Cookie Consent', 'SweetAlert2', '409', '419']) {
  assert.ok(manual.includes(text), 'Manual missing: ' + text);
}
assert.ok(report.includes('TEST UAT') && report.includes('Read-only') && report.includes('BLOCKED BY POLICY'),
  'Audit must distinguish CI coverage from live UAT and locked features');
console.log('ADMIN_CRUD_23_MODULE_COVERAGE_CONTRACT=PASS');
