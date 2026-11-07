# RYCOOPWEB — DATABASE RELATIONSHIP AUDIT

**Project:** RYCOOPWEB
**Branch:** `fix/frontend-backend-data-consistency`
**Generated Date:** 6 พฤศจิกายน 2569
**Specification:** Master Prompt Section 5.3

---

## 1. Schema Inventory & Statistics

การตรวจสอบฐานข้อมูลจริง `rayongcoop_db` (และจำลอง `rycoopweb_test`) พบทั้งหมด 61 ตาราง:

| ตารางหลัก (Content Tables) | จำนวนเรคคอร์ด | สถานะความสัมพันธ์ (Relationship Status) | ประเด็นที่ตรวจพบ (Findings) |
| :--- | :--- | :--- | :--- |
| `boards` | 5 | อิสระ (Standalone) | มีคอลัมน์ `role_type`, `term_years`, `photo`, `sort_order`, `status` แต่ชื่อไฟล์รูป `photo` ยังเป็นชื่อสั้น เช่น `board_president.jpg` ซึ่งยังไม่มีไฟล์จริงในระบบสตอเรจสาธารณะ |
| `staff` | 0 | อิสระ (Standalone) | มีคอลัมน์ `name`, `position`, `department`, `phone`, `email`, `photo` ยังไม่มีข้อมูลเรคคอร์ดในตาราง |
| `news` | 2 | สัมพันธ์กับ `news_categories` | `category_id` ชี้ไปยัง `news_categories.id` ถูกต้อง ครอบคลุม `cover_image`, `gallery_images`, `publish_at` |
| `news_categories` | 4 | ตารางประเภทข่าว | ข้อมูลประเภทข่าวเชื่อมต่อสมบูรณ์ |
| `announcements` | 1 | อิสระ | เชื่อมโยงกับประเภทประกาศ |
| `welfare` | 3 | อิสระ | ตารางจริงชื่อ `welfare` มีคอลัมน์ `title`, `description`, `amount_text`, `icon`, `category` แต่ `SpaApiController` ไปคิวรีหา `welfare_types` ซึ่งไม่มีจริง |
| `document_categories` | 6 | สัมพันธ์กับ `documents` | มีคอลัมน์ `name`, `slug`, `sort_order` |
| `documents` | 0 | สัมพันธ์กับ `document_categories` | ตารางจริงยังว่างอยู่ ขณะที่หน้าบ้านใช้ชุดข้อมูล Hardcoded |
| `deposit_products` | 4 | อิสระ | มีคอลัมน์ `name`, `interest_rate`, `min_deposit` |
| `loan_products` | 10 | อิสระ | มีคอลัมน์ `name`, `interest_rate`, `max_loan_limit`, `max_term_months` |
| `interest_rates` | 16 | อิสระ | มีคอลัมน์ `product_name`, `product_type`, `rate`, `effective_date` |
| `hero_slides` | 3 | อิสระ | มีคอลัมน์ `title`, `image_url`, `desktop_image`, `mobile_image`, `priority`, `sort_order` |
| `financial_statistics` | 4 | อิสระ | ข้อมูลสถิติทางการเงินประจำปี |
| `eservice_links` | 5 | อิสระ | รายการบริการออนไลน์ แต่หน้าบ้าน Hardcode |
| `public_content_revisions` | *Missing (0)* | สัมพันธ์กับ `users` | ยังไม่ถูก Migration ทำให้ระบบ Catalog โยนข้อยกเว้น |

---

## 2. Identified Schema & Data Inconsistencies

### A. Missing Relationship & Table Name Mismatch
1. **`welfare` vs `welfare_types`:**
   - ในโค้ด `SpaApiController.php:203`:
     ```php
     Database::query("SELECT id, name, description, max_amount, icon, requirements FROM welfare_types WHERE status = 'active' ORDER BY id ASC");
     ```
   - ในฐานข้อมูลจริง: มีตาราง `welfare` ที่มีคอลัมน์ `id, title, description, amount_text, icon, category, status`
   - ไม่มีตารางชื่อ `welfare_types` ส่งผลให้การเรียก `/api/public/welfare` ล้มเหลวและได้ผลลัพธ์เป็นอาร์เรย์ว่าง `[]`
2. **`public_content_revisions`:**
   - โค้ดใน `PublicContentService.php` และ `PublicContentController.php` พึ่งพาตาราง `public_content_revisions`
   - ตารางนี้ยังไม่ถูกสร้างในฐานข้อมูล ส่งผลให้เอนด์พอยต์ `/api/public/content` ตอบกลับเป็น 503 เสมอ

### B. Image Paths & Media Storage
1. **คอลัมน์ `photo` ในตาราง `boards`:**
   - เรคคอร์ดในตาราง `boards` มีค่าเช่น:
     - `board_president.jpg`
     - `board_vp1.jpg`
     - `board_vp2.jpg`
   - ฟังก์ชัน `resolve_media_url()` คาดหวัง path เช่น `/uploads/...` หรือ URL สัมบูรณ์
   - ขณะที่รูปโปรไฟล์จริงของกรรมการที่เว็บไซต์เดิมใช้อยู่ใน `src/data/legacyReference.json` มี path เช่น `./assets/img/committee/committee-01-president.webp` ซึ่งมีไฟล์จริงอยู่ใน `public/assets/img/committee/`
2. **คอลัมน์ `cover_image` ในตาราง `news`:**
   - ใช้งานร่วมกับ `withPublicNewsImageUrl()` เพื่อตรวจสอบพาธและ Fallback ปลอดภัย

### C. Frontend Fields with No Database Source
1. ในหน้า `BoardPage.jsx`:
   - มีการแสดงผลคณะกรรมการย่อย (`boardGroups`), ที่ปรึกษา (`advisors`), และผู้ตรวจสอบกิจการ (`auditors`)
   - ในตาราง `boards` มีฟิลด์ `role_type` ENUM('director', 'auditor', 'advisor', 'manager') ซึ่งสามารถแยกกลุ่มได้ แต่ยังไม่มีฟิลด์ระบุกลุ่มคณะกรรมการย่อย (Sub-committee Groups) ในตาราง `boards`
   - ดังนั้น การนำเสนอคณะกรรมการย่อยต้องรองรับทั้งจากตาราง `boards` (โดยใช้ `role_type`) และโครงสร้างกรรมการที่กำหนดไว้ใน Catalog

### D. Historical vs Active Records
- ตาราง `boards`, `news`, `announcements`, `hero_slides` รองรับคอลัมน์ `deleted_at` (Soft Delete) และ `status = 'active'` หรือ `workflow_status = 'published'`
- การดึงข้อมูลสู่สาธารณะต้องกรองเฉพาะเรคคอร์ดที่มี `deleted_at IS NULL` และ `status = 'active'` เท่านั้น
