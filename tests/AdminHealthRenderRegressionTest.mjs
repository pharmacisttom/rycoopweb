import assert from 'node:assert/strict';
import fs from 'node:fs';
const src=fs.readFileSync('src/pages/AdminControlCenterPage.jsx','utf8');
const checks=[
 'healthData.resources?.disk_free_gb != null',
 'healthData.resources?.disk_used_percent != null',
 'Array.isArray(healthData.services)',
 'Array.isArray(healthData.scheduled_jobs)',
];
for(const s of checks) assert.ok(src.includes(s),'missing defensive Health API handling: '+s);
assert.ok(!src.includes('healthData.resources?.disk_free_gb !== null'),'null/undefined guard must cover both');
assert.ok(!src.includes('healthData.resources?.disk_used_percent !== null'),'null/undefined guard must cover both');
assert.ok(src.includes("memberAdminApi('/api/admin/system/health'"),'Health API integration must remain');
console.log('PASS: health dashboard survives missing resources, nullable metrics and malformed collections');
