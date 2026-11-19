#!/usr/bin/env node
/**
 * TEST-only risk exception for CVE-2026-112366 in picomatch build tooling.
 * Still fails on every other moderate-or-higher advisory; never silently disables auditing.
 * Remove immediately after an upstream fixed npm version is available.
 */
import { readFileSync } from 'node:fs';

const filename = process.argv[2];
if (!filename) { console.error('Expected npm audit JSON path'); process.exit(2); }

let report;
try { report = JSON.parse(readFileSync(filename, 'utf8')); }
catch (error) { console.error('Invalid or missing npm audit JSON:', error.message); process.exit(2); }

if (report.error || !report.vulnerabilities || typeof report.vulnerabilities !== 'object') {
  console.error('Audit command was unsuccessful or returned an unexpected shape');
  process.exit(2);
}

const approvedRoot = 'picomatch';
const approvedAdvisory = 'GHSA-fc5q-39xc-vjpf';
const approvedUrl = 'https://github.com/advisories/' + approvedAdvisory;
const vulnerabilityNames = Object.keys(report.vulnerabilities);
const identified = new Set();
const visiting = new Set();
const errors = [];

function isOnlyPicomatchRisk(name) {
  if (identified.has(name)) return true;
  if (visiting.has(name)) return false;
  const item = report.vulnerabilities[name];
  if (!item || !['moderate','high','critical','low','info'].includes(item.severity)) return false;
  visiting.add(name);
  const via = Array.isArray(item.via) ? item.via : [];
  const valid = via.length > 0 && via.every(edge => {
    if (typeof edge === 'string') return isOnlyPicomatchRisk(edge);
    if (!edge || typeof edge !== 'object') return false;
    const url = String(edge.url || '');
    return name === approvedRoot && edge.name === approvedRoot
      && url === approvedUrl && edge.severity === 'high'
      && String(edge.title || '').toLowerCase().includes('redos');
  });
  visiting.delete(name);
  if (valid) identified.add(name);
  return valid;
}

for (const name of vulnerabilityNames) {
  if (!isOnlyPicomatchRisk(name)) errors.push('Unapproved advisory or dependency chain: ' + name);
}
if (!identified.has(approvedRoot)) errors.push('Expected picomatch advisory not found; reassess exception policy');
if (errors.length) {
  for (const e of errors) console.error(e);
  process.exit(1);
}

console.warn('TEST-ONLY TEMPORARY SECURITY EXCEPTION: ' + approvedAdvisory);
console.warn('Affected tooling:', vulnerabilityNames.join(', '));
console.warn('No fixed upstream picomatch release was available as of 2026-11-18.');
console.warn('Full npm audit remains non-zero. Production dependencies are separately audited and must be clean.');
console.warn('Never expose Vite dev server, npm/build tooling, or user-supplied glob patterns to untrusted traffic.');
console.warn('Reassess by 2026-12-02; do not promote to Production without resolving this advisory.');
