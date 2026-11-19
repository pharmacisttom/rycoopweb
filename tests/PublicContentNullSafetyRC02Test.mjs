#!/usr/bin/env node
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const page = readFileSync('src/pages/PublicContentAdminPage.jsx', 'utf8');
const start = page.indexOf('const safeRows =');
const end = page.indexOf('function Preview(', start);
assert(start !== -1 && end > start, 'safe payload normalizers exist');
const sandbox = {};
vm.runInNewContext(page.slice(start, end) + '\nthis.testApi = {safeRows, safeRevisionValue};', sandbox);
const {safeRows, safeRevisionValue} = sandbox.testApi;
assert.equal(safeRows(null, 'boards').length, 0);
assert.equal(safeRows({}, 'boards').length, 0);
assert.equal(safeRows([{id:1}], 'boards').length, 1);
assert.equal(safeRows(null, 'profile').length, 0);
assert.equal(safeRows({nameTh:'Test'}, 'profile').length, 1);
assert.equal(safeRevisionValue(null, 'boards').length, 0);
assert.equal(safeRevisionValue('null', 'boards').length, 0);
assert.equal(safeRevisionValue('{incomplete', 'boards').length, 0);
assert.equal(safeRevisionValue('[{"id":1}]', 'boards').length, 1);
assert.equal(safeRevisionValue('{bad', 'profile'), null);
assert.equal(safeRevisionValue('{"nameTh":"Test"}', 'profile').nameTh, 'Test');
assert(page.includes("(Array.isArray(data.history) ? data.history : []).map(revision=>"));
assert(page.includes("safeRevisionValue(revision.previous_payload,section)"));
assert(page.includes("safeRevisionValue(revision.payload,section)"));
assert(page.includes("Array.isArray(catalog?.boardMembers)"));
console.log('PASS: Public Content malformed history and missing board catalog do not crash preview');
