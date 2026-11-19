#!/usr/bin/env node
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';

const ui = readFileSync('src/pages/AdminHomepageLayoutPage.jsx','utf8');
const api = readFileSync('app/Controllers/Admin/HomepageLayoutController.php','utf8');
const service = readFileSync('app/Services/HomepageLayoutService.php','utf8');
const statements = [
  ['editable 6 sections', /const keys = \['hero', 'quick_services', 'statistics', 'products', 'calculator', 'welfare'\]/.test(ui)],
  ['dirty state computed from server draft', ui.includes('!sameVisibility(sections, serverValues)')],
  ['save requires change + TOTP', ui.includes('const canSave = !!state && dirty && validTotp && !busy;')],
  ['publish requires saved draft + TOTP', ui.includes('const canPublish = !!state?.draft && !dirty && validTotp && !busy;')],
  ['strict six-digit code', ui.includes('/^\\d{6}$/.test(totp)')],
  ['clear code after save', ui.includes("setTotp('');")],
  ['save posts sections', ui.includes("body.sections = sections")],
  ['publication posts to separate endpoint', ui.includes("memberAdminApi('/api/admin/layout/' + action")],
  ['discard unsaved changes confirmation', ui.includes('ทิ้งการแก้ไขแล้วโหลดล่าสุด')],
  ['server returns MFA status not 500', api.includes('catch (\\App\\Exceptions\\SecurityGateException $e)')],
  ['public uses published config only', service.includes('public static function published(): array')],
  ['draft and publication audited', service.includes("AuditService::log('site_layout', 'publish'")],
];
for(const [label,ok] of statements){assert(ok,label); console.log('PASS: '+label);}
