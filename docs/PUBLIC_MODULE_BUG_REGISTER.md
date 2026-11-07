# RYCOOPWEB — PUBLIC MODULE BUG REGISTER

**Project:** RYCOOPWEB  
**Branch:** `fix/frontend-backend-data-consistency`  
**Generated Date:** 6 พฤศจิกายน 2569  
**Specification:** Master Prompt Section 12

---

## 1. Summary of Discovered Bugs

| Bug ID | Affected Route | Module | Severity | Root Cause Summary | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **BUG-001** | `/board`, `/board_committee` | Board Committee | **CRITICAL** | Frontend uses static JSON while Admin modifies `boards` table; missing `public_content_revisions` triggers 503 error; risk of `undefined.name` crash | **OPEN (Priority 1)** |
| **BUG-002** | `/board_officer` | Management & Staff | **HIGH** | Frontend uses static JSON while Admin modifies `staff` table | **OPEN (Priority 1)** |
| **BUG-003** | `/welfare` | Welfare | **HIGH** | `SpaApiController@welfare` queries non-existent `welfare_types` table (should be `welfare`); frontend imports `mockData.js` | **OPEN (Priority 2)** |
| **BUG-004** | `/documents` | Documents | **MEDIUM** | Frontend completely hardcodes `OFFICIAL_DOCUMENTS` array; does not consume `/api/public/documents` | **OPEN (Priority 2)** |
| **BUG-005** | `/eservice` | E-Services | **HIGH** | Hardcoded mock data (`04892`, `นายเกียรติศักดิ์ พูลเพิ่ม`, `081-234-5678`) in form; hardcoded `SERVICES` array | **OPEN (Priority 2)** |
| **BUG-006** | `/contact` | Contact & Complaints | **MEDIUM** | Complaint form submission saves to browser `localStorage` instead of calling backend API | **OPEN (Priority 3)** |
| **BUG-007** | `/` | Hero Slider & Rates | **LOW** | Home API queries database but then overwrites rates/products with catalog fallback | **OPEN (Priority 3)** |

---

## 2. Detailed Bug Reports

### BUG-001: Board Committee Public Frontend Disconnected from Admin Management
- **Bug ID:** BUG-001
- **Affected Route:** `/board`, `/board_committee`
- **Severity:** **CRITICAL**
- **Reproduction Steps:**
  1. เข้าสู่ระบบหลังบ้านที่ `/admin/board-staff`
  2. แก้ไขชื่อหรือตำแหน่งกรรมการในรายการ
  3. เปิดหน้าสาธารณะ `/board` หรือ `/board_committee`
  4. ผลลัพธ์: หน้าเว็บยังคงแสดงข้อมูลเดิมจากไฟล์ JSON `legacyReference.json` ข้อมูลที่แก้ไขในหลังบ้านไม่ปรากฏ
- **Root Cause:**
  - `AdminBoardPersonnelPage.jsx` บันทึกลงตาราง `boards` ผ่าน `Admin\BoardStaffController`
  - `BoardPage.jsx` ดึงข้อมูลผ่าน `PublicContentContext` จาก `/api/public/content` ซึ่งอาศัย `PublicContentService::catalog()`
  - `PublicContentService::catalog()` ทำงานล้มเหลว (ตาราง `public_content_revisions` ไม่พบ) แล้วโยนข้อยกเว้น HTTP 503
  - `BoardPage.jsx` มีโค้ดค้นหาตาม ID `BOARD_MEMBERS.find(member => member.id === id).name` ซึ่งหาก ID ไม่ตรงจะเกิด runtime crash
- **Affected Files:**
  - `app/Controllers/Api/SpaApiController.php`
  - `app/Services/PublicContentService.php`
  - `src/pages/BoardPage.jsx`
  - `src/context/PublicContentContext.jsx`
- **Proposed Fix:**
  - ปรับปรุงเอนด์พอยต์ `/api/public/board-members` ให้ดึงข้อมูลสดจากตาราง `boards` (โดยกรอง `deleted_at IS NULL` และ `status = 'active'`) ผสานเข้ากับโครงสร้างคณะกรรมการ
  - ปรับปรุง `BoardPage.jsx` ให้อ่านข้อมูลจาก `/api/public/board-members` หรือ Context ที่สะท้อนตาราง `boards` จริง
  - เพิ่ม Safe Optional Chaining `?.name` ใน `BoardPage.jsx` ป้องกันหน้าขาว
  - ใส่ระบบ Image Fallback ปลอดภัยเมื่อไม่มีรูปโปรไฟล์
- **Final Status:** OPEN (Target for Phase 2)

---

### BUG-002: Management & Staff Directory Disconnected from Admin Management
- **Bug ID:** BUG-002
- **Affected Route:** `/board_officer`
- **Severity:** **HIGH**
- **Reproduction Steps:**
  1. เข้าสู่ระบบหลังบ้าน `/admin/board-staff` แท็บ "เจ้าหน้าที่"
  2. เพิ่มเจ้าหน้าที่คนใหม่
  3. เปิดหน้าสาธารณะ `/board_officer`
  4. ผลลัพธ์: รายชื่อใหม่ไม่ปรากฏ แสดงเฉพาะ 10 รายชื่อดั้งเดิมจาก `legacyReference.json`
- **Root Cause:**
  - `BoardOfficerPage.jsx` ดึงข้อมูลผ่าน `usePublicInformation()` (`STAFF_MEMBERS`) ซึ่งมาจาก `legacyReference.json`
  - ไม่ได้เชื่อมต่อกับตาราง `staff` ในฐานข้อมูล
- **Affected Files:**
  - `app/Controllers/Api/SpaApiController.php`
  - `src/pages/BoardOfficerPage.jsx`
- **Proposed Fix:**
  - เชื่อมโยง `/api/public/board-members?type=staff` เข้ากับตาราง `staff` ในฐานข้อมูล
  - ปรับปรุง `BoardOfficerPage.jsx` ให้แสดงผลเจ้าหน้าที่จากฐานข้อมูล
- **Final Status:** OPEN (Target for Phase 2)

---

### BUG-003: Welfare Public Endpoint Queries Non-Existent Table
- **Bug ID:** BUG-003
- **Affected Route:** `/welfare`
- **Severity:** **HIGH**
- **Reproduction Steps:**
  1. ส่งคำขอ `GET /api/public/welfare`
  2. ผลลัพธ์: ได้รับ `data: []` ว่างเปล่า
  3. ตรวจสอบโค้ด `SpaApiController.php:203` พบการคิวรี `FROM welfare_types`
- **Root Cause:**
  - ตารางจริงในฐานข้อมูลมีชื่อว่า `welfare` ไม่ใช่ `welfare_types`
  - ใน `WelfarePage.jsx` นำเข้า `import { WELFARE_ITEMS } from '../data/mockData'` มาแสดงแทนที่จะดึงจาก API
- **Affected Files:**
  - `app/Controllers/Api/SpaApiController.php`
  - `src/pages/WelfarePage.jsx`
- **Proposed Fix:**
  - แก้ไขคำสั่ง SQL ใน `SpaApiController.php` ให้คิวรีจากตาราง `welfare` จริง
  - ปรับปรุง `WelfarePage.jsx` ให้ดึงข้อมูลจาก `/api/public/welfare`
- **Final Status:** OPEN (Target for Phase 3)

---

### BUG-004: Documents Page Completely Hardcoded
- **Bug ID:** BUG-004
- **Affected Route:** `/documents`
- **Severity:** **MEDIUM**
- **Reproduction Steps:**
  1. อัปโหลดเอกสารใหม่ผ่านระบบหลังบ้าน `/admin/document-categories`
  2. ตรวจสอบหน้า `/documents`
  3. เอกสารใหม่ไม่ปรากฏ หน้าเว็บแสดงเฉพาะ 23 รายการคงที่ใน `OFFICIAL_DOCUMENTS`
- **Root Cause:**
  - `DocumentsPage.jsx` ไม่ได้เรียกใช้ `fetchDocuments()` หรือ `/api/public/documents`
- **Affected Files:**
  - `src/pages/DocumentsPage.jsx`
  - `app/Controllers/Api/SpaApiController.php`
- **Proposed Fix:**
  - ปรับปรุง `DocumentsPage.jsx` ให้เรียก `/api/public/documents` โดยคง `OFFICIAL_DOCUMENTS` เป็น fallback ในกรณีออฟไลน์/ฐานข้อมูลยังไม่มีไฟล์
- **Final Status:** OPEN (Target for Phase 3)

---

### BUG-005: E-Services Hardcoded Mock Member Information
- **Bug ID:** BUG-005
- **Affected Route:** `/eservice`
- **Severity:** **HIGH**
- **Reproduction Steps:**
  1. เปิดหน้า `/eservice` (เมื่อเปิดโหมด Feature Flag)
  2. คลิกยื่นคำขอบริการออนไลน์
  3. พบค่าจำลอง `memberId: '04892'`, `name: 'นายเกียรติศักดิ์ พูลเพิ่ม'`, `phone: '081-234-5678'` ปรากฏในฟอร์ม
- **Root Cause:**
  - ใน `EServicePage.jsx:56-58` มีการกำหนดค่าเริ่มต้นเป็นข้อมูลจำลอง
- **Affected Files:**
  - `src/pages/EServicePage.jsx`
- **Proposed Fix:**
  - ถอดค่าจำลองทั้งหมดออก ให้เริ่มต้นด้วยค่าว่าง `''` หรือดึงจาก `user` ที่ล็อกอินอยู่จริง
  - เชื่อมโยงรายการบริการออนไลน์เข้ากับตาราง `eservice_links`
- **Final Status:** OPEN (Target for Phase 3)

