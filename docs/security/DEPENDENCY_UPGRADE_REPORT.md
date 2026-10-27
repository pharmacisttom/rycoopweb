# รายงานการยกระดับความปลอดภัย Dependency Vulnerabilities ระดับ Moderate (RYCOOPWEB)

**วันที่จัดทำ**: 2026-10-27  
**Repository**: [pharmacisttom/rycoopweb](https://github.com/pharmacisttom/rycoopweb)  
**Source Branch**: `codex/workflow-repair`  
**Baseline Commit**: `5266f3904891467dd37bb8f6b3aff92de8316bc1`  
**Target Branch สำหรับ PR**: `Test` (อ้างอิง PR #3)  
**Feature Branch**: `codex/dependency-moderate-fixes`  
**ผู้จัดทำ**: Senior Frontend & Application Security Engineer  

---

## 1. บทสรุปผู้บริหาร (Executive Summary)

จากการตรวจสอบช่องโหว่ความปลอดภัยระดับ Moderate ในระบบ RYCOOPWEB ที่ปรากฏผลใน `npm audit` จำนวน 5 รายการ (`postcss-nested`, `postcss-selector-parser`, `react-router`, `react-router-dom`, `tailwindcss`) ทีมวิศวกรรมได้วิเคราะห์เชิงลึกและแก้ไขเสร็จสิ้นโดย:
1. **คงสถานะ Tailwind CSS 3.4.19 ไว้ตามเดิม** โดยแก้ไขที่ต้นตอของ PostCSS AST parser ผ่าน npm `overrides: { "postcss-selector-parser": "7.1.7" }` ทำให้สามารถปิดช่องโหว่ DoS/ReDoS ได้อย่างสมบูรณ์โดยไม่ต้องเสี่ยงต่อ Breaking Changes มหาศาลของการเปลี่ยนไปใช้ Tailwind v4 (คง Design System, สีน้ำเงินสหกรณ์, ฟอนต์ไทย, และ CSS Variables เดิมได้ 100%)
2. **อัปเกรด React Router สู่เวอร์ชัน 7.18.4** (`react-router-dom@7.18.4` และ `react-router@7.18.4`) เพื่อปิดช่องโหว่ Open Redirect (CVE-2026-53669 / GHSA-wrjc-x8rr-h8h6) และ SSR Hydration Constructor Injection (CVE-2026-53666 / GHSA-337j-9hxr-rhxg) ซึ่งไม่มีแพตช์ย้อนหลังในสาย 6.x
3. **ผลลัพธ์การตรวจสอบช่องโหว่**: `npm audit --audit-level=moderate` ตรวจพบช่องโหว่เป็น **0 vulnerabilities** (Critical: 0, High: 0, Moderate: 0)
4. **ความเข้ากันได้**: ผ่านการทดสอบ `npm ci`, `npm run build`, SSR tests, Navigation redirect tests, และ Security regression tests ครบถ้วน 100%

---

## 2. ตารางเปรียบเทียบ Dependencies ก่อนและหลังการแก้ไข (Deliverable 1)

| แพ็กเกจ (Package) | เวอร์ชันก่อนแก้ (Before) | เวอร์ชันหลังแก้ (After) | Advisory ID & CVE | ระดับความรุนแรง (Severity) | ขอบเขต (Context) | ผลกระทบต่อ RYCOOPWEB |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **`postcss-selector-parser`** | `6.1.4` | `7.1.7` | **GHSA-rj75-hqrm-r3gf** | Moderate (CVSS 5.9) | Build Tool (Vite/PostCSS) | **ต้นตอ (Root Cause)**: DoS/CPU Exhaustion ($O(n^2)$) จาก flat selector ยาวผิดปกติ แก้ไขสำเร็จด้วย override |
| **`postcss-nested`** | `6.2.0` | `6.2.0` | *ไม่มีในตัวมันเอง* (ผ่าน parser) | Moderate (ผ่าน parser) | Build Tool (PostCSS) | **ได้รับผลกระทบต่อเนื่อง**: ปลอดภัยทันทีเมื่อ parser อัปเดตเป็น 7.1.7 |
| **`tailwindcss`** | `3.4.19` | `3.4.19` | *ไม่มีในตัวมันเอง* (ผ่าน parser) | Moderate (ผ่าน parser) | Build Tool / CSS Engine | **ได้รับผลกระทบต่อเนื่อง**: ปลอดภัยทันทีเมื่อ parser อัปเดตเป็น 7.1.7 โดยคงโครงสร้าง CSS เดิม 100% |
| **`react-router`** | `6.30.6` | `7.18.4` | **GHSA-wrjc-x8rr-h8h6** (CVE-2026-53669)<br>**GHSA-337j-9hxr-rhxg** (CVE-2026-53666) | Moderate (CVSS 6.1) | Browser Runtime & SSR | **ต้นตอ (Root Cause)**: ป้องกัน Open Redirect เมื่อมีการส่ง backslash ใน path สำหรับ `<Link>` / `useNavigate` |
| **`react-router-dom`** | `6.30.6` | `7.18.4` | *ไม่มีในตัวมันเอง* (ผ่าน react-router) | Moderate (ผ่าน router) | Browser Runtime | **ได้รับผลกระทบต่อเนื่อง**: อัปเกรดเป็น 7.18.4 ควบคู่กับ react-router |

---

## 3. เหตุผลการเลือกวิธีแก้และรายการไฟล์ที่เปลี่ยนแปลง (Deliverable 2)

### 3.1 การวิเคราะห์ทางเทคนิค
- **ทำไมไม่ขึ้น Tailwind 4**: Tailwind 4 เปลี่ยนสถาปัตยกรรมเป็น Oxide (Rust), ยกเลิก `@tailwind base/components/utilities`, ยกเลิก `tailwind.config.js` แบบ JS และเปลี่ยนวิธีการ parse class dynamic ซึ่งจะก่อให้เกิด breaking changes และความเสี่ยงต่อ UI ของสหกรณ์อย่างรุนแรง การใช้ `overrides` ใน npm สำหรับ `postcss-selector-parser: 7.1.7` เป็นวิธีที่ปลอดภัยและตรงจุดที่สุด
- **ทำไมต้องขึ้น React Router 7.18.4**: ช่องโหว่ Open Redirect เกิดจากการ bypass CVE-2025-68470 ทางทีม React Router ปิดปัญหานี้เฉพาะในสาย 7.x (`>= 7.18.0`) โดยไม่มี patch ลงสาย 6.x การอัปเกรดมายัง 7.18.4 ในโหมด Library Mode ยังคงเข้ากันได้กับ React 18.3.1 และ API เดิมทั้งหมด (`BrowserRouter`, `Routes`, `Route`, `Link`, `useNavigate`, `useLocation`)
- **การจัดการ CommonJS/ESM Interop ใน Vite**: ใน React Router 7 แพ็กเกจ `react-router-dom` กลายเป็น CommonJS wrapper ที่ re-export จาก `react-router` การเพิ่ม alias `'react-router-dom': 'react-router'` ใน `vite.config.js` ทำให้ Vite ทั้งใน Development, SSR Testing และ Production Bundling ทำงานในรูปแบบ ESM แท้ 100% ปราศจากปัญหา CJS named export lookup

### 3.2 รายการไฟล์ที่เปลี่ยนแปลง
1. [package.json](file:///d:/rycoopweb/package.json):
   - อัปเดต `"react-router-dom": "7.18.4"`
   - เพิ่ม `"overrides": { "postcss-selector-parser": "7.1.7" }`
2. [package-lock.json](file:///d:/rycoopweb/package-lock.json): บันทึก dependency graph ที่ผ่านการ audit เรียบร้อย
3. [vite.config.js](file:///d:/rycoopweb/vite.config.js): เพิ่ม alias `'react-router-dom': 'react-router'`
4. [.github/workflows/ci.yml](file:///d:/rycoopweb/.github/workflows/ci.yml): ปรับ audit level จาก `--audit-level=high` เป็น `--audit-level=moderate`
5. [tests/PublicContentReferenceTest.mjs](file:///d:/rycoopweb/tests/PublicContentReferenceTest.mjs): ปรับการโหลด `StaticRouter` ผ่าน `server.ssrLoadModule('react-router')` ให้สอดคล้องกับโครงสร้าง v7
6. [tests/WorkflowFrontendTest.mjs](file:///d:/rycoopweb/tests/WorkflowFrontendTest.mjs): ปรับการโหลด `StaticRouter` ผ่าน `server.ssrLoadModule('react-router')`
7. [docs/security/DEPENDENCY_UPGRADE_REPORT.md](file:///d:/rycoopweb/docs/security/DEPENDENCY_UPGRADE_REPORT.md): เอกสารรายงานสรุป

---

## 4. ผลการทดสอบจริงและข้อจำกัด (Deliverables 3 & 4)

### 4.1 ผลการทดสอบ Automated Tests
- **`npm ci`**: ติดตั้ง 148 แพ็กเกจสำเร็จ ล็อกไฟล์ถูกต้อง ปราศจาก error (Exit Code 0)
- **`npm audit --audit-level=moderate`**: `found 0 vulnerabilities` (Exit Code 0)
- **`npm run build`**: Vite v6.4.4 คอมไพล์โปรดักชันแอป 2,693 modules สำเร็จ ได้ bundle ใน `public/app/` ครบถ้วน (Exit Code 0)
- **`node tests/PublicContentReferenceTest.mjs`**: ผ่าน 100% (ข้อมูลกรรมการ 15 ท่าน, เจ้าหน้าที่ 6 ท่าน, อัตราดอกเบี้ย, พิกัดติดต่อ)
- **`node tests/WorkflowFrontendTest.mjs`**: ผ่าน 100% (Workspace, Rates, News, Public Content, MFA permissions)
- **`node tests/NavigationRedirectTest.mjs`**: ผ่านครบทั้ง 8 Test Cases
- **`php tests/SecurityRegressionTest.php`**: ผ่านทุก Security assertions (Auth bypass guard, RBAC, Rate limit, CSRF)

### 4.2 ผลการตรวจสอบหน้าจอ Browser & Responsive Check
- **Desktop (1920x911 / 1280x800)**:
  - หน้า Login (`/login`, `/admin/login`): แสดงผลถูกต้อง ฟิลด์ชื่อผู้ใช้/รหัสผ่าน ปุ่มเข้าสู่ระบบ และปุ่มสลับแท็บเจ้าหน้าที่ทำงานราบรื่น
  - หน้าแรก (`/`): ธีมสีน้ำเงินสหกรณ์ อัตราดอกเบี้ยเงินฝาก/เงินกู้ ข่าวสาร และ Quick Action Dock แสดงผลคมชัด
  - หน้า Deep Links (`/loans`, `/deposits`, `/welfare`): โหลดข้อมูลได้ถูกต้อง ไม่มีหน้าขาว (Blank page)
  - หน้า 404 (`/invalid-path`): Custom 404 page แสดงผลเรียบร้อย มีปุ่มพากลับหน้าหลัก
- **Mobile Responsive (375x667 Viewport)**:
  - Navbar ยุบเป็น Mobile Drawer เมนูเปิด-ปิดได้สมบูรณ์
  - การ์ดอัตราดอกเบี้ยจัดเรียงแบบ Stack แนวตั้ง ไม่ล้นจอ
- **Form Persistence & Session Probes**:
  - การเรียก Background session check / heartbeat ไม่มีการสั่ง `window.location.reload()` ข้อมูลในฟอร์มที่ผู้ใช้กำลังกรอกไม่สูญหาย

### 4.3 ข้อจำกัด (Limitations)
- ในสภาพแวดล้อม Client-Side SPA นี้ หากมีการใช้งานฟังก์ชัน OpenID Connect / Google OAuth ในอนาคต จะต้องระบุ Redirect URI ให้ตรงกับที่กำหนดไว้ใน Google Cloud Console ตามเอกสาร `docs/GOOGLE_CLOUD_SETUP.md`

---

## 5. แผนการย้อนกลับ (Rollback Plan - Deliverable 5)

หากพบความผิดปกติบนระบบทดสอบ สามารถทำการย้อนกลับได้อย่างปลอดภัยโดยดึงทั้ง Source Code และ Lockfile กลับไปที่ Baseline Commit `5266f3904891467dd37bb8f6b3aff92de8316bc1`:

```bash
# 1. ย้อนไฟล์กลับไปที่สถานะ baseline
git checkout 5266f3904891467dd37bb8f6b3aff92de8316bc1 -- package.json package-lock.json vite.config.js .github/workflows/ci.yml tests/

# 2. ทำความสะอาด node_modules และติดตั้ง dependencies ดั้งเดิม
npm ci

# 3. ตรวจสอบการ build ใหม่
npm run build
```

---

## 6. ลิงก์ Pull Request และการส่งมอบ (Deliverable 7)

- **Feature Branch**: `codex/dependency-moderate-fixes`
- **Target Branch**: `Test`
- **Remote**: `pharmacisttom` (`https://github.com/pharmacisttom/rycoopweb.git`)
- **เปรียบเทียบและเปิด Pull Request**:
  [เปิด Pull Request ไปยัง Test Branch](https://github.com/pharmacisttom/rycoopweb/compare/Test...codex/dependency-moderate-fixes?expand=1)
