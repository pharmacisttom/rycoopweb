# เอกสารข้อกำหนดการส่งมอบ Release A: การแก้ปัญหา Dependency ระดับ Moderate (Release A Specification)

**รหัสเวอร์ชัน**: Release A (Dependency Security Fix)  
**Branch ต้นทาง**: `codex/dependency-moderate-fixes`  
**Commit**: [`88f8805`](https://github.com/pharmacisttom/rycoopweb/commit/88f8805)  
**Target Branch**: `Test` (อ้างอิง Pull Request #3)  
**สถานะการอนุมัติ**: พร้อมส่งมอบ (Production-Ready)  

---

## 1. ขอบเขตการเปลี่ยนแปลง (Scope of Release A)

Release A มีเป้าหมายเดียวที่ชัดเจนและจำกัดขอบเขต: **แก้ไข Dependency Vulnerabilities ระดับ Moderate ทั้งหมดให้เหลือ 0 โดยไม่มีการเปลี่ยนแปลงพฤติกรรมทางธุรกิจ ไม่แตะต้องข้อมูลทางการเงิน และไม่ต้องการ Database Migration ใดๆ**

### รายการแพ็กเกจที่ได้รับการแก้ไข
1. **`postcss-selector-parser`** -> `7.1.7` (ผ่าน npm `overrides`) ปิดช่องโหว่ ReDoS/CPU Exhaustion (GHSA-rj75-hqrm-r3gf)
2. **`postcss-nested`** -> `6.2.0` (คงเดิม ปลอดภัยผ่าน parser 7.1.7)
3. **`tailwindcss`** -> `3.4.19` (คงเดิม ปลอดภัยผ่าน parser 7.1.7 คง Design System และ CSS เดิม 100%)
4. **`react-router`** & **`react-router-dom`** -> `7.18.4` ปิดช่องโหว่ CVE-2026-53669 (Open Redirect) และ CVE-2026-53666 (SSR Constructor Injection)
5. **`vite.config.js`** -> เพิ่ม alias `'react-router-dom': 'react-router'` สำหรับ ESM Interop
6. **`.github/workflows/ci.yml`** -> ปรับ CI Audit Gate เป็น `--audit-level=moderate`

---

## 2. สิ่งที่อยู่นอกขอบเขตของ Release A (Explicit Non-Goals)

- ไม่มีการรัน Database Migration (017–023)
- ไม่มีการปรับปรุงอัตราดอกเบี้ยหรือข้อมูลทางการเงิน (Migrations 019, 020)
- ไม่มีการเปลี่ยนโครงสร้าง Database Schema บน TEST หรือ Production
- ไม่มีการปรับเปลี่ยน User Interface หรือ Flow ทางธุรกิจ

---

## 3. เกณฑ์การยอมรับ (Acceptance Criteria)

- [x] `npm audit --audit-level=moderate` แสดงผล `found 0 vulnerabilities`
- [x] `npm ci` และ `npm run build` ผ่านสมบูรณ์บน Node.js 20+
- [x] ชุดทดสอบ `node tests/PublicContentReferenceTest.mjs` และ `tests/WorkflowFrontendTest.mjs` ผ่าน 100%
- [x] การแสดงผลหน้าจอ Browser และ Responsive Layout ใน Desktop/Mobile ทำงานปกติ
- [x] ไม่มีคำสั่ง `window.location.reload()` ใน Background probes ทำให้ Form Data ไม่สูญหาย
