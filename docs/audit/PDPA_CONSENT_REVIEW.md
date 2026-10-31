# RYCOOPWEB 3.0 — PDPA Privacy Notice & Mandatory Consent Review

Date: 2026-10-31
Regulation: Personal Data Protection Act B.E. 2562 (PDPA)
Entity: Rayong Public Health Cooperative Limited (สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด)

---

## 1. Statutory Notice Structure

The mandatory Privacy Notice for members must be presented upon first login and after any material policy revision.

### Notice Title:
**ประกาศความเป็นส่วนตัวสำหรับสมาชิกสหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด**

### 11 Mandatory Statutory Disclosures:
1. **ผู้ควบคุมข้อมูลส่วนบุคคล (Data Controller)**:
   - สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด สำนักงานตั้งอยู่ ณ จังหวัดระยอง
2. **ประเภทข้อมูลส่วนบุคคลที่เก็บรวบรวม (Categories of Personal Data)**:
   - ข้อมูลระบุตัวตน: ชื่อ-นามสกุล, เลขประจำตัวประชาชน 13 หลัก, เลขที่สมาชิก, วันเดือนปีเกิด
   - ข้อมูลติดต่อ: ที่อยู่, หมายเลขโทรศัพท์, อีเมล, สังกัดหน่วยงาน
   - ข้อมูลทางการเงินและธุรกรรม: ทุนเรือนหุ้น, บัญชีเงินฝาก, สัญญาเงินกู้, เงินปันผล-เฉลี่ยคืน, ข้อมูลผู้รับผลประโยชน์
3. **วัตถุประสงค์ในการประมวลผล (Processing Purposes)**:
   - การบริหารจัดการสมาชิกภาพ, การทำธุรกรรมทางการเงิน, การคำนวณและจ่ายเงินปันผล, การให้บริการสวัสดิการสมาชิก
4. **ฐานกฎหมายในการประมวลผล (Lawful Basis)**:
   - ฐานสัญญา (Contractual Necessity) สำหรับการดำเนินงานตามข้อบังคับสหกรณ์
   - ฐานหน้าที่ตามกฎหมาย (Legal Obligation) เช่น พ.ร.บ. สหกรณ์, กฎหมายป้องกันและปราบปรามการฟอกเงิน (ปปง.)
   - ฐานความยินยอม (Consent) สำหรับการสื่อสารประชาสัมพันธ์หรือกิจกรรมเสริมพิเศษ
5. **ผู้รับข้อมูลหรือการเปิดเผยข้อมูล (Recipients / Disclosure)**:
   - หน่วยงานกำกับดูแล (กรมส่งเสริมสหกรณ์, กรมตรวจบัญชีสหกรณ์), ธนาคารพันธมิตรเพื่อการโอนเงินปันผล/เงินกู้
6. **ระยะเวลาในการจัดเก็บข้อมูล (Data Retention Period)**:
   - ตลอดระยะเวลาการเป็นสมาชิก และต่อเนื่องอีก 10 ปีหลังสิ้นสุดสมาชิกภาพตามอายุความทางกฎหมาย
7. **มาตรการรักษาความปลอดภัย (Security Safeguards)**:
   - การเข้ารหัสข้อมูล (Encryption at Rest & in Transit TLS 1.3), สิทธิ์เข้าถึงตามบทบาท (RBAC Least Privilege), การยืนยันตัวตนสองขั้นตอน (MFA/TOTP), การตรวจสอบประวัติการเข้าถึง (Audit Trail)
8. **สิทธิของเจ้าของข้อมูลส่วนบุคคล (Data Subject Rights)**:
   - สิทธิขอเข้าถึงข้อมูล, สิทธิขอรับสำเนา, สิทธิขอแก้ไข, สิทธิคัดค้าน, สิทธิขอให้ลบหรือทำลาย (ตามเงื่อนไขกฎหมาย), สิทธิเพิกถอนความยินยอม
9. **ช่องทางการติดต่อผู้ควบคุมข้อมูลและเจ้าหน้าที่คุ้มครองข้อมูลส่วนบุคคล (DPO Contact)**:
   - สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด โทรศัพท์ 038-611178, อีเมล: `dpo@rayongcoop.org`
10. **ประกาศฉบับเต็ม (Full Policy Link)**:
    - เชื่อมโยงไปยังเอกสารฉบับเต็มที่ `/privacy/policy`
11. **เวอร์ชันและวันที่มีผลบังคับใช้ (Version & Effective Date)**:
    - Version: 3.0 (Effective Date: 1 พฤศจิกายน 2569)

---

## 2. Technical Gate Requirements (Backend & Frontend)

### A. Strict Notice Gate vs. Optional Consent Separation
```text
+-------------------------------------------------------------------------+
| ประกาศความเป็นส่วนตัวสำหรับสมาชิก (Privacy Notice)                           |
+-------------------------------------------------------------------------+
| [ สรุปเนื้อหาประกาศ 11 หมวด พร้อมลิงก์อ่านฉบับเต็ม /privacy/policy ]      |
|                                                                         |
| ( ) ข้าพเจ้าได้รับทราบประกาศความเป็นส่วนตัวฉบับนี้แล้ว (Mandatory Acknowledgment) |
|                                                                         |
| --- ความยินยอมทางเลือก (Optional Consent) ---                           |
| [ ] ยินยอมรับข้อมูลข่าวสารสิทธิประโยชน์พิเศษและบริการใหม่ผ่านทาง SMS/LINE   |
|     (ห้ามติ๊กเลือกไว้ล่วงหน้า / สมาชิกมีสิทธิ์ปฏิเสธโดยไม่กระทบสิทธิสมาชิก)  |
|                                                                         |
| [ ปุ่มยืนยันและดำเนินการต่อ ]                                             |
+-------------------------------------------------------------------------+
```

### B. Core Architectural Rules:
1. **No Pre-Ticked Boxes**: All optional consent checkboxes must default to unchecked (`false`).
2. **Server-Side Enforcement**:
   - Client-side storage (`localStorage`/`cookies`) alone **must never be used** as proof of PDPA compliance.
   - When a member calls `/api/member/profile`, `/api/member/shares`, or `/api/member/dividends`, the backend checks `member_consents` table. If the current version is unacknowledged, the API halts and returns:
     ```json
     {
       "success": false,
       "code": "PDPA_NOTICE_REQUIRED",
       "requires_pdpa_acknowledgment": true,
       "policy_version": "3.0"
     }
     ```
3. **Immutable Audit Persistence**:
   - Table `member_consents` records: `user_id`, `member_no`, `policy_version`, `acknowledged_at`, `optional_marketing_consent`, `ip_address`, `user_agent`.
4. **Revocability**:
   - Members can adjust optional consents anytime via `/member/settings`. Notice acknowledgment for contractual processing cannot be revoked while retaining membership.
5. **Legal & DPO Verification**:
   - The final text must receive written sign-off from cooperative legal counsel / DPO prior to Production enablement.
