import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const src = path => readFileSync(path, 'utf8');
const menu = src('src/components/layout/PublishedMegaMenu.jsx');
const css = src('src/index.css');
const editor = src('src/pages/AdminMegaMenuPage.jsx');
const manual = src('src/pages/AdminUserManualPage.jsx');
const shell = src('src/components/admin/AdminControlCenterShell.jsx');
assert.match(menu, /mega-menu-grid mega-menu-grid--published/);
assert.match(menu, /mega-menu-col-main mega-menu-col-main--published/);
assert.match(css, /\.mega-menu-col-main--published\s*\{[^}]*grid-column:\s*1\s*\/\s*-1;/s);
assert.match(css, /\.mega-menu-grid--published\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\);/s);
assert.match(css, /\.mega-menu-wrapper\s*\{[^}]*max-height:\s*min\(72vh,\s*640px\);/s);
assert.match(editor, /to="\/admin\/manual#manual-mega-menu"/);
assert.match(editor, /คู่มือปุ่มและวิธีใช้งาน Mega Menu/);
assert.match(manual, /id="manual-mega-menu"/);
assert.match(shell, /จัดการ Mega Menu \(เมนูหน้าบ้าน\)/);
const labels = [
  'เพิ่มหมวดเมนูหลัก', 'เพิ่มรายการ', 'Preview ร่าง', 'โหลดข้อมูลล่าสุด',
  '1. บันทึกร่าง', '2. อนุมัติเผยแพร่', 'ย้อนกลับเวอร์ชันก่อนหน้า',
  'คืนค่า Navbar เดิม', 'ลบหมวดจากร่าง', 'ลบจากร่าง'
];
for(const label of labels) assert.ok(manual.includes(label), 'Missing manual button explanation: ' + label);
console.log('MEGA_MENU_LAYOUT_AND_MANUAL_CONTRACT=PASS');
