# Security Policy & Hardening Guidelines

Production member access is deny-by-default. Both React routes and PHP member
API groups enforce feature flags; hiding frontend controls is not treated as
an authorization boundary.
### RayongCoop Digital Portal — สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด

เอกสารนโยบายความมั่นคงปลอดภัยสารสนเทศ การรักษาความมั่นคงปลอดภัยไซเบอร์ (Cybersecurity) และมาตรการคุ้มครองข้อมูลส่วนบุคคล (PDPA Compliance) ตามมาตรฐานสถาบันการเงินและคำแนะนำของ OWASP สำหรับระบบ **RayongCoop Digital Portal**

---

## 1. การพิสูจน์และยืนยันตัวตน (Authentication & Access Control)

### 1.1 การเข้ารหัสและจัดเก็บรหัสผ่าน (Password Hashing)
- ใช้อัลกอริทึม **Argon2id** (`PASSWORD_ARGON2ID`) ซึ่งเป็นอัลกอริทึมที่ได้รับคำแนะนำสูงสุดจาก OWASP และ NIST ในการป้องกันการโจมตีแบบ GPU/ASIC Cracking
- พารามิเตอร์ความปลอดภัยขั้นสูง:
  - `memory_cost = 65536` (64 MB)
  - `time_cost = 4` (4 Iterations)
  - `threads = 2`
- กำหนดความซับซ้อนของรหัสผ่าน: ความยาวขั้นต่ำ 8 ตัวอักษร ประกอบด้วยตัวพิมพ์ใหญ่ ตัวพิมพ์เล็ก ตัวเลข และอักขระพิเศษ

### 1.2 การยืนยันตัวตนสองขั้นตอน (Two-Factor Authentication - 2FA)
- รองรับมาตรฐาน **RFC 6238 Time-Based One-Time Password (TOTP)**
- เข้ากันได้กับแอปพลิเคชัน Authenticator มาตรฐาน เช่น Google Authenticator, Microsoft Authenticator
- ระบบมีการออก **Backup Recovery Codes** ที่เข้ารหัสแบบทางเดียว (Hashed) เพื่อใช้กรณีฉุกเฉิน

### 1.3 การป้องกันการโจมตีแบบสุ่มรหัสผ่าน (Brute Force & Rate Limiting)
- จำกัดจำนวนครั้งในการล็อกอินผิดพลาด: ล็อกบัญชีชั่วคราว (Account Lockout) เมื่อกรอกรหัสผ่านผิดเกิน **5 ครั้ง** ภายในระยะเวลา 15 นาที
- หน่วงเวลาการตอบสนองแบบก้าวหน้า (Progressive Delay) สำหรับคำขอที่ไม่ถูกต้อง
- บันทึกประวัติการพยายามเข้าสู่ระบบทุกครั้งลงในตาราง `login_logs` พร้อมบันทึก IP Address และ User Agent

### 1.4 การจัดการสิทธิ์ตามบทบาท (Role-Based Access Control - RBAC)
- แบ่งแยกสิทธิ์ชัดเจนระหว่าง **Super Admin**, **Staff / Officer**, **Member** และ **Guest**
- เจ้าหน้าที่และผู้ดูแลระบบถูกจำกัดสิทธิ์ตาม Matrix สิทธิ์ที่ได้รับมอบหมายเท่านั้น
- นำหลักการ **Least Privilege** และ **Maker-Checker Workflow** มาใช้ในการตรวจสอบและอนุมัติคำขอกู้เงินและสวัสดิการสมาชิก

---

## 2. ความปลอดภัยเซสชันและโทเค็น (Session & Token Security)

- **Cookie Security Flags**:
  - `HttpOnly`: ป้องกัน JavaScript จากการเข้าถึง Session Cookie เพื่อขจัดความเสี่ยงต่อ Session Hijacking ผ่าน XSS
  - `Secure`: ส่ง Cookie ผ่านช่องทางที่เข้ารหัส HTTPS/TLS เท่านั้น
  - `SameSite=Lax` หรือ `SameSite=Strict`: ป้องกันการโจมตีแบบ Cross-Site Request Forgery (CSRF)
- **Session Lifecycle**:
  - **Idle Timeout**: สิ้นสุดเซสชันอัตโนมัติเมื่อไม่มีการเคลื่อนไหวเกิน **30 นาที**
  - **Session Regeneration**: หมุนเวียนรหัส Session ID ใหม่อัตโนมัติทุกครั้งที่มีการเปลี่ยนแปลงสถานะการพิสูจน์ตัวตน (Login/Logout) และทุกๆ 30 นาที
- **CSRF Token Validation**: ตรวจสอบโทเค็น CSRF ในทุกคำขอที่มีการเปลี่ยนแปลงสถานะข้อมูล (`POST`, `PUT`, `PATCH`, `DELETE`) ทั้ง HTML Forms และ Fetch/AJAX API (`X-CSRF-TOKEN`)

---

## 3. การป้องกันภัยคุกคามตามมาตรฐาน OWASP Top 10

### 3.1 การป้องกัน SQL Injection
- บังคับใช้ **PDO Prepared Statements 100%** ร่วมกับ Parameterized Queries ทั่วทั้งระบบ
- ห้ามนำ Input จากผู้ใช้งานมาต่อเป็น SQL String โดยตรงโดยเด็ดขาด
- มีการตรวจสอบชนิดข้อมูล (Strict Type Casting) ในระดับ Model และ Repository Layer

### 3.2 การป้องกัน Cross-Site Scripting (XSS)
- ในส่วน React Frontend มีการ Escape Output อัตโนมัติใน JSX
- ในส่วน Backend มีฟังก์ชัน `e()` สำหรับ Context-Aware Output Encoding (HTML, Attribute, JavaScript contexts)
- ข้อมูล Rich Text (เช่น เนื้อหาข่าวสาร/ประกาศ) ต้องผ่านการกรองด้วย **HTML Purifier** ก่อนบันทึกและแสดงผล

### 3.3 การรักษาความปลอดภัยการอัปโหลดไฟล์ (Secure File Upload Pipeline)
- ตรวจสอบชนิดไฟล์ที่แท้จริงจาก MIME Type (`finfo_file`) และ Magic Bytes ไม่เชื่อถือเฉพาะนามสกุลไฟล์
- กำหนด Whitelist นามสกุลไฟล์ที่อนุญาตเท่านั้น (เช่น `.jpg`, `.jpeg`, `.png`, `.pdf`, `.webp`)
- บล็อกและห้ามอัปโหลดไฟล์ที่สามารถ Execute ได้ เช่น `.php`, `.phtml`, `.phar`, `.exe`, `.sh`, `.js`, `.html`, `.svg`
- สุ่มสร้างชื่อไฟล์ใหม่ด้วย Cryptographic Random Hash (`random_bytes(16)`) เพื่อป้องกัน Path Traversal และ File Overwrite
- ปิดการทำงานของ PHP Script ในไดเรกทอรีจัดเก็บไฟล์ (`/storage/uploads/`) ผ่านการตั้งค่า Nginx Web Server

### 3.4 Security Response Headers
ระบบกำหนดค่า Security Headers ที่ระดับ Web Server (Nginx) และ Application:
```http
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;
```

---

## 4. การคุ้มครองข้อมูลส่วนบุคคล (PDPA & Privacy-by-Design)

- **Member Data Isolation**:
  - ข้อมูลส่วนบุคคล สัญญาเงินกู้ เงินฝาก และเรื่องร้องเรียน (Feedback & Complaints) จะถูกผูกกับรหัสสมาชิกของผู้ล็อกอินโดยตรง สมาชิกไม่สามารถเข้าถึงหรือดูข้อมูลของสมาชิกรายอื่นได้
- **Cookie Consent Management Platform (CMP)**:
  - บล็อกสคริปต์บุคคลภายนอก (Analytics / Marketing Pixels) จนกว่าผู้ใช้งานจะให้ความยินยอม
  - มีศูนย์ตั้งค่าความเป็นส่วนตัว (Cookie Preferences) ให้ผู้ใช้งานสามารถถอนหรือปรับเปลี่ยนความยินยอมได้ตลอดเวลา
- **Data Minimization & Retention**:
  - บันทึกเฉพาะข้อมูลที่จำเป็นต่อการให้บริการของสหกรณ์
  - ซ่อนข้อมูลสำคัญ (Data Masking) ในหน้าจอแสดงผล เช่น เลขบัตรประชาชนและเบอร์โทรศัพท์บางส่วน

---

## 5. บันทึกประวัติการทำงานที่ไม่สามารถแก้ไขได้ (Immutable Audit Trail)

- ทุกการทำธุรกรรม การอนุมัติคำขอกู้ การแก้ไขอัตราดอกเบี้ย หรือการเปลี่ยนแปลงข้อมูลสำคัญ จะถูกบันทึกอัตโนมัติลงในตาราง `audit_logs`
- ข้อมูลที่ถูกบันทึกประกอบด้วย:
  - รหัสผู้ดำเนินการ (`user_id`, `role`)
  - ประเภทการกระทำ (`action`: CREATE, UPDATE, DELETE, APPROVE, REJECT)
  - ข้อมูลก่อนแก้ไข (`old_values`) และข้อมูลหลังแก้ไข (`new_values`) ในรูปแบบ JSON
  - หมายเลขไอพี (`ip_address`) และข้อมูลเบราว์เซอร์ (`user_agent`)
  - วันเวลาที่เกิดรายการอย่างแม่นยำ (`created_at`)
- **Immutability**: ตาราง `audit_logs` ไม่มีคำสั่งแก้ไขหรือลบ (UPDATE / DELETE) ในระบบ CMS เพื่อคงไว้ซึ่งความโปร่งใสและตรวจสอบย้อนหลังได้

---

## 6. นโยบายการรายงานช่องโหว่ความปลอดภัย (Vulnerability Disclosure Policy)

สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด ให้ความสำคัญสูงสุดต่อความปลอดภัยของข้อมูลสมาชิก หากท่านค้นพบช่องโหว่หรือปัญหาด้านความปลอดภัยในระบบ กรุณาปฏิบัติตามแนวทางดังนี้:

### 6.1 ช่องทางการรายงาน
- **อีเมลประสานงานด้านความปลอดภัย**: `security@rayongcoop.com` หรือ `admin@rayongcoop.com`
- **โทรศัพท์ฝ่ายเทคโนโลยีสารสนเทศ**: 038-611-123
- กรุณาแนบรายละเอียดขั้นตอนการทำซ้ำ (Proof of Concept - PoC), URL, พารามิเตอร์ที่ได้รับผลกระทบ และระดับความรุนแรงที่คาดการณ์

### 6.2 คำมั่นสัญญาในการตอบสนอง (Response SLA)
- **การตอบรับการรายงาน (Acknowledge)**: ภายใน 24 ชั่วโมง
- **การประเมินและยืนยันช่องโหว่ (Triage & Assessment)**: ภายใน 48 ชั่วโมง
- **การออกแพตช์แก้ไข (Remediation & Patch)**: ภายใน 3–7 วันทำการขึ้นอยู่กับระดับความรุนแรง

### 6.3 หลักปฏิบัติที่ปลอดภัย (Responsible Disclosure)
- ห้ามใช้ช่องโหว่เพื่อเข้าถึง เปลี่ยนแปลง หรือลบข้อมูลของสมาชิกจริง
- ห้ามทำการโจมตีในลักษณะ Denial of Service (DoS/DDoS) หรือรบกวนการให้บริการตามปกติของสหกรณ์
- ให้เวลาทีมงานในการตรวจสอบและแก้ไขปัญหาก่อนการเปิดเผยข้อมูลสู่สาธารณะ

---

สงวนลิขสิทธิ์ © 2026 **สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด**
All Rights Reserved.
