# รายงานการทบทวนความมั่นคงปลอดภัยระบบยืนยันตัวตน Google OIDC (Google Auth Security Review)

**ระบบ**: RYCOOPWEB 2.3
**วันที่จัดทำ**: 2026-10-27
**ผู้จัดทำ**: PHP Security Architect & Application Security Engineer
**ขอบเขต**: สถาปัตยกรรม Google OAuth 2.0 / OpenID Connect, มาตรการป้องกันการยกระดับสิทธิ์, และความเข้ากันได้กับ TEST Gateway

---

## 1. ภาพรวมสถาปัตยกรรม (Architecture Overview)

ระบบเข้าสู่ระบบด้วย Google สำหรับเจ้าหน้าที่และผู้ดูแลระบบสหกรณ์ (Back-office Staff) พัฒนาตามมาตรฐาน **OAuth 2.0 Authorization Code Flow ร่วมกับ OpenID Connect (OIDC)** โดยมีมาตรการความมั่นคงปลอดภัยขั้นสูงดังนี้:

```
[ผู้ใช้/เจ้าหน้าที่] ──(1) คลิก "เข้าสู่ระบบด้วย Google"──► [RYCOOPWEB]
                                                          │
               (สร้าง PKCE Verifier/Challenge,           │
                สร้าง Random State & Nonce 32 ตัวอักษร)   │
                                                          ▼
[Google Accounts] ◄──(2) Redirect พร้อม State/Nonce/PKCE──┘
       │
  (ยืนยันตัวตน)
       │
       ▼
[Google Callback] ──(3) ส่ง Authorization Code & State──► [RYCOOPWEB /auth/google/callback]
                                                                │
                               (ตรวจสอบ State ป้องกัน CSRF)      │
                               (ส่ง Code + Code Verifier แลก Token) │
                                                                ▼
[Google Token API] ◄────────────────────────────────────────────┘
       │
  (ส่งคืน ID Token & Access Token)
       │
       ▼
[RYCOOPWEB Backend]
  ├── ตรวจสอบลายมือชื่อและ Claims ใน ID Token (iss, aud, exp, nonce, email_verified)
  ├── ค้นหาการผูกบัญชีด้วย Google `sub` (Immutable Subject ID)
  ├── หากเป็นเจ้าหน้าที่ใหม่: ตรวจสอบ Staff Invitation Token
  ├── หากเป็น Primary Super Admin: ตรวจสอบสิทธิ์การ Bootstrap
  └── บันทึก Audit Log และเปลี่ยนผ่านสู่กระบวนการ Mandatory MFA
```

---

## 2. มาตรการควบคุมความปลอดภัย 10 ประการ (10 Security Controls)

### 2.1 การป้องกัน Authorization Code Interception ด้วย PKCE
- ระบบสร้าง `code_verifier` (ความยาว 64 ไบต์แบบสุ่ม) และคำนวณ `code_challenge` ด้วยอัลกอริทึม SHA-256 (`S256`)
- ฝั่งเซิร์ฟเวอร์ Google จะตรวจสอบ `code_verifier` ในขั้นตอนแลกเปลี่ยนโทเคน เพื่อให้มั่นใจว่าผู้ที่แลกเปลี่ยนรหัสคือไคลเอนต์เครื่องเดิม

### 2.2 การป้องกัน CSRF ด้วย Cryptographic State
- ระบบสร้างค่า `state` แบบสุ่มความยาว 16 ไบต์ (`bin2hex(random_bytes(16))`) เก็บไว้ใน PHP Session
- เมื่อได้รับ Callback ระบบใช้ฟังก์ชัน `hash_equals()` ในการตรวจสอบความถูกต้อง เพื่อป้องกัน Timing Attack

### 2.3 การป้องกัน Token Replay ด้วย Nonce
- ระบบส่งค่า `nonce` แบบสุ่มไปยัง Google Authorization Endpoint
- เมื่อได้รับ ID Token ระบบตรวจสอบว่า claim `nonce` ใน JWT ตรงกับค่าที่บันทึกไว้ในเซสชัน เพื่อป้องกันการนำ Token เก่ามาใช้ซ้ำ

### 2.4 การตรวจสอบ ID Token ฝั่งเซิร์ฟเวอร์ (Server-Side Token Verification)
- **Issuer (`iss`)**: ต้องเป็น `https://accounts.google.com` หรือ `accounts.google.com`
- **Audience (`aud`)**: ต้องตรงกับ `GOOGLE_OAUTH_CLIENT_ID` ของสหกรณ์
- **Expiration (`exp`)**: ต้องยังไม่หมดอายุตามเวลาปัจจุบัน
- **Email Verified (`email_verified`)**: บัญชี Google ต้องผ่านการยืนยันอีเมลแล้วเท่านั้น (`true`)

### 2.5 การผูกบัญชีด้วย Immutable Subject (`sub`)
- ระบบผูกข้อมูลผู้ใช้ในตาราง `user_google_identities` ด้วยค่า Google `sub` (Unique ID คงที่ตลอดชีพของบัญชี Google) แทนการใช้ `email` เพราะอีเมลอาจมีการเปลี่ยนชื่อหรือโอนย้ายได้ในอนาคต

### 2.6 การป้องกันการยกระดับสิทธิ์อัตโนมัติ (No Privilege Escalation)
- **หลักการสำคัญ**: การที่บุคคลใดล็อกอินผ่าน Google สำเร็จ **ไม่ได้หมายความว่าจะได้รับสิทธิ์เข้าใช้งานระบบเจ้าหน้าที่โดยอัตโนมัติ**
- หากไม่พบบัญชีที่เคยผูกไว้ และไม่มีบัตรเชิญเจ้าหน้าที่ที่ถูกต้อง ระบบจะปฏิเสธการเข้าถึงทันที (`Unauthorized Google login attempt`) และบันทึกลง `login_logs`

### 2.7 ระบบเชิญเจ้าหน้าที่ (Staff Invitation Workflow)
- ผู้ดูแลระบบสร้างคำเชิญผ่าน `bin/console staff:invite` หรือเมนูจัดการเจ้าหน้าที่
- ระบบสร้างโทเคนแบบสุ่มและเก็บในรูป Hash (`token_hash`) พร้อมกำหนดวันหมดอายุ (ค่าเริ่มต้น 7 วัน)
- เมื่อเจ้าหน้าที่กดลิงก์คำเชิญและล็อกอินผ่าน Google ระบบจะจับคู่โทเคน ตรวจสอบความถูกต้อง แล้วจึงสร้างและผูกบัญชีตามบทบาทที่ระบุในคำเชิญเท่านั้น

### 2.8 กระบวนการ Bootstrap บัญชี Primary Super Admin
- สำหรับอีเมลผู้ดูแลระบบหลักที่กำหนดในระบบ (`pharmacisttom@gmail.com`):
- การผูกบัญชี Super Admin กับ Google จะเกิดขึ้นได้เฉพาะเมื่อ:
  1. มีระเบียนคำสั่งเปิดสิทธิ์ใน `site_settings` หรือสถานะ `hasAnyGoogleLinks == false` (การเริ่มต้นระบบครั้งแรก)
  2. ผูกกับบัญชี local user ที่มีบทบาท `super_admin` ที่มีอยู่แล้วเท่านั้น (ไม่สร้าง user ใหม่ที่มีสิทธิ์โดยพลการ)
  3. เมื่อผูกสำเร็จ ระบบจะลบ flag การ bootstrap ทันที และบันทึก Audit Log ชนิด `bootstrap_superadmin`

### 2.9 การแยกสิทธิ์และการจัดเก็บ Client Secret
- `GOOGLE_OAUTH_CLIENT_SECRET` ถูกจัดเก็บในไฟล์คอนฟิกูเรชันฝั่งเซิร์ฟเวอร์ (`.env`) เท่านั้น ไม่มีการส่งต่อไปยัง Frontend React หรือเปิดเผยใน Git

### 2.10 ความเข้ากันได้กับ TEST Gateway (HTTP Basic Auth Compatibility)
บนสภาพแวดล้อม TEST VPS (`https://test.rayongcoop.org`) มีการติดตั้ง HTTP Basic Authentication ที่ระดับ Nginx เพื่อป้องกันบุคคลภายนอก:
- **แนวทางแก้ไขที่ปลอดภัย**: ปลดล็อก Basic Auth เฉพาะ Endpoint Callback `/auth/google/callback` ใน Nginx:
  ```nginx
  location = /auth/google/callback {
      auth_basic off;
      try_files $uri $uri/ /index.php?$query_string;
  }
  ```
- **เหตุผลความปลอดภัย**: Endpoint ดังกล่าวไม่สามารถถูกโจมตีได้เนื่องจากมีเกราะป้องกัน Cryptographic State และ Nonce หากไม่มีเซสชันที่เริ่มต้นจากหน้าเว็บสหกรณ์ การเรียกเข้ามาตรงๆ จะถูกดีดออกทันที
