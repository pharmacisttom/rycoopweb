# RYCOOPWEB — Legacy Website Gap Analysis & Migration Strategy

**Document Version:** 1.0.0
**Target Environment:** TEST (`https://test.rayongcoop.org/`)
**Legacy Source Website:** `https://rayongcoop.com/`
**Application Target:** `pharmacisttom/rycoopweb`
**Target Database:** `rycoopweb_test`

---

## 1. Executive Summary

This Gap Analysis compares the legacy website of the **Rayong Public Health Savings and Credit Cooperative Limited** (`https://rayongcoop.com/`) with the newly engineered modern web platform **RYCOOPWEB** (`https://test.rayongcoop.org/`).

The legacy website is built on legacy PHP (`page.php?pid=...`) without responsive design, lacking mobile optimization, modern RBAC, audit logging, or seamless integrated member self-service. RYCOOPWEB provides a high-security, responsive React + PHP 8.3 REST API architecture with dedicated MFA 2FA, granular role-based access control (RBAC), PDPA 3.0 compliance, and immutable release deployment.

---

## 2. Comprehensive 14-Module Audit Matrix

The following table details the status of each system/content domain between the legacy site and RYCOOPWEB, identifying required architectural and content actions.

| # | ระบบ / เนื้อหา (Module / Content) | เว็บไซต์เดิม (Legacy `rayongcoop.com`) | RYCOOPWEB ใหม่ (Modern Platform) | การประเมินสถานะ (Gap & Action Required) |
|---|---|---|---|---|
| 1 | **ภาพประชาสัมพันธ์ Slide (Hero Banners)** | มีแบนเนอร์ภาพนิ่งหลายภาพแสดงหน้าแรก สลับอัตโนมัติแบบดั้งเดิม | มีโครงสร้างตาราง `hero_slides` ในฐานข้อมูล แต่หน้าแรกยังเป็น Static Hero | **ดำเนินการ:** ติดตั้ง `HeroSlider.jsx` เต็มรูปแบบ (Desktop 1920x650 / Mobile 750x900) พร้อม 4 แบนเนอร์เริ่มต้น และเชื่อมโยง Banner Management CMS |
| 2 | **ข่าวประชาสัมพันธ์ (News & PR)** | มีข่าวย้อนหลังจำนวนมาก แบ่งหมวดหมู่กิจกรรม ประชุม และประชาสัมพันธ์ | มี CMS ข่าว พร้อมระบบ Workflow (Draft / Published / Archived) และ Tags | **ดำเนินการ:** จัดทำโครงสร้างการนำเข้าข่าวย้อนหลัง ข่าวสำคัญ และรูปภาพกิจกรรมลงสู่ `news` |
| 3 | **ประกาศสหกรณ์ (Announcements)** | มีประกาศทางการ ประกาศรับสมัครงาน และประกาศระเบียบ | มีระบบจัดการ `important_announcements` รองรับการปักหมุดและกำหนดวันหมดอายุ | **ดำเนินการ:** ตรวจสอบความครบถ้วนของประกาศฉบับปัจจุบัน และจัดทำลิงก์ดาวน์โหลดประกาศฉบับเต็ม |
| 4 | **เอกสารดาวน์โหลด (Download Center)** | มีไฟล์ PDF จำนวนกว่า 44 รายการ ใน `pid=11` รวมทุกหมวดหมู่ไว้ในหน้าเดียว | มีโมดูลเอกสาร `documents` และหมวดหมู่ แต่ยังไม่ครอบคลุมครบ 11 หมวด | **ดำเนินการ:** จัดระเบียบศูนย์ดาวน์โหลดเป็น 11 หมวดหมู่ รองรับการค้นหาแบบ Real-time และดาวน์โหลดไฟล์โดยตรง |
| 5 | **ระเบียบและข้อบังคับ (Regulations & Bylaws)** | มีระเบียบข้อบังคับสหกรณ์ `pid=9` เช่น ระเบียบการถือหุ้น การให้เงินกู้ การฝากเงิน | มีโครงสร้างหน้าข้อบังคับและกฎหมายที่เกี่ยวข้อง | **ดำเนินการ:** ย้ายเอกสารข้อบังคับฉบับปรับปรุงล่าสุดเข้าสู่ระบบ จัดทำหมวดหมู่ระเบียบสหกรณ์ให้สืบค้นง่าย |
| 6 | **แผนยุทธศาสตร์ (Strategic Plans)** | มีแผนกลยุทธ์ 3 ช่วงปี (พ.ศ. 2566-2570, 2568-2570, 2569-2573) ใน `pid=2` | มี CMS เนื้อหาโครงสร้างยุทธศาสตร์ | **ดำเนินการ:** บรรจุไฟล์ PDF แผนกลยุทธ์ทั้ง 3 ฉบับ และจัดทำหน้าแสดงวิสัยทัศน์ พันธกิจ ยุทธศาสตร์ 4 มิติ |
| 7 | **คณะกรรมการและเจ้าหน้าที่ (Board & Staff)** | มีทำเนียบคณะกรรมการ 15 ท่าน, ผู้ตรวจสอบ 2 ท่าน, ที่ปรึกษา 5 ท่าน และฝ่ายจัดการ 8 ท่าน (`pid=3,4`) | มีระบบข้อมูลบุคลากร โครงสร้างองค์กร และบทบาทหน้าที่ | **ดำเนินการ:** ตรวจสอบภาพถ่าย วาระการดำรงตำแหน่ง และรายชื่อคณะกรรมการชุดปัจจุบันให้ตรงกับฐานข้อมูลสหกรณ์ |
| 8 | **เงินฝากและเงินกู้ (Deposits & Loans)** | มีรายละเอียดและอัตราดอกเบี้ยใน `pid=5` (เงินฝาก) และ `pid=6` (เงินกู้) | มีหน้าผลิตภัณฑ์เงินฝากและเงินกู้ พร้อมระบบ Dynamic Interest Rates | **ดำเนินการ:** ปรับหัวข้อผลิตภัณฑ์เงินกู้เป็น "เงินกู้ของสหกรณ์" เรียบร้อยแล้ว ตรวจสอบเงื่อนไข วงเงิน และอัตราดอกเบี้ยจริง |
| 9 | **เครื่องคำนวณเงินกู้ (Loan Calculator)** | มีโปรแกรมคำนวณเงินกู้และเงินงวดแบบดั้งเดิม | มี Interactive Loan Calculator พร้อมตารางผ่อนชำระ (Amortization Schedule) | **ดำเนินการ:** ตรวจสอบสูตรคำนวณดอกเบี้ยแบบลดต้นลดดอก (Effective Rate) และแบบคงที่ ให้ตรงตามข้อบังคับสหกรณ์ |
| 10 | **สวัสดิการสมาชิก (Member Welfare)** | มีสวัสดิการสมาชิกหลายประเภท (`pid=8`) เช่น สวัสดิการสมรส, สมาชิกโสด, สงเคราะห์บำนาญ | มีหน้า `WelfarePage.jsx` แสดงสิทธิประโยชน์ | **ดำเนินการ:** ย้ายรายละเอียดสวัสดิการทุกประเภท แบบฟอร์มขอรับสวัสดิการ และหลักฐานที่ต้องใช้ให้ครบถ้วน |
| 11 | **ตรวจสอบปันผล (Dividend & Patronage)** | ลิงก์ไปยัง E-Service ภายนอก | มีระบบ Member Portal และหน้ารายละเอียดเงินปันผล/เฉลี่ยคืนรายบุคคล | **ดำเนินการ:** เชื่อมโยงสิทธิ์การใช้งานผ่าน PDPA 3.0 พร้อมระบบ Masking ข้อมูลชื่อ-นามสกุล และซ่อนปุ่มเปลี่ยนรหัสผ่านตามมติ |
| 12 | **สสธท. / กสธท. (Affiliated Associations)** | มีลิงก์สมาคมฌาปนกิจสงเคราะห์ สสธท., กสธท. และ สส.ชสอ. พร้อมแบบฟอร์ม | ยังไม่มีเมนูเฉพาะในหน้าหลัก | **ดำเนินการ:** เพิ่มการ์ดสมาคมฌาปนกิจสงเคราะห์ พันธมิตร (สสธท., กสธท., สส.ชสอ.) พร้อมลิงก์ภายนอกและแบบฟอร์มสมัคร |
| 13 | **กำหนดการยื่นกู้ (Loan Application Calendar)** | มีประกาศกำหนดการยื่นกู้และจ่ายเงินกู้ประจำปี พ.ศ. 2569 (`pay2569.png`) | ยังไม่มียืนยันหน้าเผยแพร่ปฏิทิน | **ดำเนินการ:** สร้างหน้า `/loan-schedule` แสดงตารางกำหนดการยื่นคำขอกู้ วันตัดยอด และวันโอนเงินกู้เข้าบัญชีประจำปี 2569 |
| 14 | **เรื่องร้องเรียน (Complaints & Grievances)** | มีช่องทางรับเรื่องร้องเรียนและระเบียบว่าด้วยการรับเรื่องร้องเรียน (`pid=17`) | มีโครงสร้างรับเรื่องร้องเรียน พร้อมรหัสติดตาม (Tracking Code) | **ดำเนินการ:** บรรจุระเบียบข้อร้องเรียน และทดสอบการส่งแบบฟอร์มร้องเรียนออนไลน์อย่างปลอดภัย |

---

## 3. Detailed Architectural Gap Analysis

### 3.1 Homepage Presentation Layer
- **Legacy:** หน้าแรกเป็นตาราง HTML แบบ Fixed Width (980px) ไม่รองรับสมาร์ทโฟน แบนเนอร์ภาพนิ่งไม่มี Call to Action (CTA) ที่มีประสิทธิภาพ
- **RYCOOPWEB:** ออกแบบใหม่ด้วย Tailwind-compatible CSS Variables รองรับ Full Responsive (Mobile First 320px - 4K) โดยมี `HeroSlider` เป็นพระเอกของหน้าแรก แสดงผลด้วยอัตราส่วน 1920x650 บนเดสก์ท็อป และ 750x900 บนมือถือ พร้อมระบบสัมผัส (Touch Gesture Swipe) และการปฏิบัติตามมาตรฐาน Accessibility (WCAG 2.1 AA / prefers-reduced-motion).

### 3.2 Content Management System (CMS) & RBAC
- **Legacy:** อัปเดตผ่าน FTP และการแก้ไขไฟล์ตรง ไม่มีระบบบันทึกประวัติ (Audit Trail)
- **RYCOOPWEB:** มีระบบจัดการหลังบ้านแบบ Granular RBAC (11 บทบาท) พร้อมระบบ Optimistic Lock (Version Hash) และ Audit Logging บันทึกทุกคำขอโดยระบุผู้กระทำ, IP, วันเวลา และ Delta ข้อมูลก่อน-หลังแก้ไข

### 3.3 Member Data Protection & Security Gates
- **Legacy:** เชื่อมโยงผ่านลิงก์เว็บภายนอกโดยไม่มีการปกป้องข้อมูลส่วนบุคคลตาม พ.ร.บ. คุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562
- **RYCOOPWEB:** บังคับใช้ PDPA 3.0 Consent Gate ก่อนเข้าถึงข้อมูลการเงิน มีระบบ `MemberPiiMaskingService` ซ่อนเลขบัตรประชาชน, เลขสมาชิก และชื่อ-นามสกุล ป้องกันการรั่วไหลของข้อมูลการเงิน

---

## 4. Prioritized Action Plan

1. **Phase 1 (Immediate / Core Experience):**
   - พัฒนาและติดตั้ง `HeroSlider` บนหน้าแรก พร้อม 4 แบนเนอร์หลัก
   - พัฒนาระบบ API และ UI สำหรับจัดการแบนเนอร์ (`/admin/banners`)
   - พัฒนาศูนย์ดาวน์โหลดเอกสารครบ 11 หมวดหมู่ (`/documents`)
2. **Phase 2 (Content & Member Value):**
   - พัฒนาหน้าปฏิทินกำหนดการยื่นกู้ประจำปี 2569 (`/loan-schedule`)
   - พัฒนาส่วนงานสมาคมฌาปนกิจสงเคราะห์ สสธท., กสธท., สส.ชสอ. ในหน้าระบบสวัสดิการ (`/welfare`)
   - นำเข้าแผนกลยุทธ์และรายงานประจำปีฉบับย้อนหลัง
3. **Phase 3 (Verification & Hardening):**
   - ดำเนินการทดสอบ UAT ครอบคลุมทุกบทบาทบนสภาพแวดล้อม TEST
   - ยืนยันความปลอดภัยบนฐานข้อมูล `rycoopweb_test` โดยไม่กระทบ Production
