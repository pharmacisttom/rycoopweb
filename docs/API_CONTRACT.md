# RYCOOPWEB — PUBLIC API CONTRACT STANDARDIZATION

**Project:** RYCOOPWEB  
**Branch:** `fix/frontend-backend-data-consistency`  
**Generated Date:** 6 พฤศจิกายน 2569  
**Specification:** Master Prompt Section 8

---

## 1. Global Response Standards

เอนด์พอยต์สาธารณะ (Public APIs) ทั้งหมดต้องตอบกลับด้วยโครงสร้าง JSON มาตรฐานเดียวกัน:

### Success Response Format
```json
{
  "success": true,
  "data": [],
  "meta": {
    "page": 1,
    "per_page": 12,
    "total": 0
  }
}
```

### Error Response Format
```json
{
  "success": false,
  "code": "ERROR_CODE",
  "message": "ข้อความอธิบายความผิดพลาดสำหรับผู้ใช้งาน"
}
```

---

## 2. Standardized Public Endpoint Specifications

### 1. Board Committee & Staff: `GET /api/public/board-members`
- **Purpose:** ให้ข้อมูลคณะกรรมการดำเนินการ ที่ปรึกษา ผู้ตรวจสอบ และฝ่ายจัดการสำหรับหน้า `/board` และ `/board_officer`
- **Parameters:**
  - `type` (optional): `board` | `staff` | `all` (default: `all`)
  - `term` (optional): `2568-2569` (ปีวาระ)
- **Response Format:**
```json
{
  "success": true,
  "data": {
    "term": "ชุดที่ 38 (2568 - 2569)",
    "boards": [
      {
        "id": 1,
        "name": "นางสาวอุษา อิศรางกูร ณ อยุธยา",
        "position": "ประธานกรรมการ",
        "role_type": "director",
        "term_years": "2568-2569",
        "photo": "/assets/img/committee/committee-01-president.webp",
        "workplace": "สำนักงานสาธารณสุขจังหวัดระยอง",
        "sort_order": 1,
        "status": "active"
      }
    ],
    "staff": [
      {
        "id": 1,
        "name": "เจ้าหน้าที่ฝ่ายจัดการ",
        "position": "ผู้จัดการสหกรณ์",
        "department": "ฝ่ายบริหารทั่วไป",
        "photo": "/assets/img/staff/staff-01.webp",
        "sort_order": 1,
        "status": "active"
      }
    ],
    "advisors": [],
    "auditors": [],
    "board_groups": []
  }
}
```

---

### 2. News Articles: `GET /api/public/news`
- **Purpose:** รายการข่าวสารและกิจกรรมพร้อมระบบแบ่งหน้า
- **Parameters:**
  - `page` (integer, default: 1)
  - `per_page` (integer, default: 12)
  - `category_id` (optional integer)
- **Response Format:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "หัวข้อข่าวประชาสัมพันธ์",
      "slug": "sample-news-slug",
      "excerpt": "สรุปย่อของเนื้อหาข่าว",
      "content": "<p>เนื้อหาฉบับเต็ม...</p>",
      "cover_image": "/uploads/news/cover.webp",
      "gallery_images": ["/uploads/news/img1.webp"],
      "publish_at": "2026-10-15 09:00:00",
      "is_pinned": 1,
      "category_name": "ข่าวทั่วไป"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 12,
    "total": 2
  }
}
```

---

### 3. Public Documents: `GET /api/public/documents`
- **Purpose:** รายการแบบฟอร์มและเอกสารดาวน์โหลดแยกตามหมวดหมู่
- **Parameters:**
  - `category` (optional slug or name)
- **Response Format:**
```json
{
  "success": true,
  "data": {
    "categories": [
      { "slug": "loans", "label": "เงินกู้ของสหกรณ์" },
      { "slug": "deposits", "label": "เงินฝาก" },
      { "slug": "membership", "label": "สมาชิกและหุ้น" },
      { "slug": "welfare", "label": "สวัสดิการสมาชิก" }
    ],
    "documents": [
      {
        "id": 1,
        "title": "คำขอกู้เงินสามัญและหนังสือยินยอมให้หักเงินเดือน",
        "category_slug": "loans",
        "category_label": "เงินกู้ของสหกรณ์",
        "file_path": "/uploads/documents/loan-form-1.pdf",
        "file_size": "540 KB",
        "file_format": "PDF",
        "download_count": 1420
      }
    ]
  }
}
```

---

### 4. Welfare Benefits: `GET /api/public/welfare`
- **Purpose:** สวัสดิการสมาชิกของสหกรณ์
- **Response Format:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "สวัสดิการสมาชิกถึงแก่กรรม",
      "category": "สงเคราะห์ครอบครัว",
      "amount_text": "สูงสุด 200,000 บาท",
      "description": "ช่วยเหลือครอบครัวสมาชิกตามอายุการเป็นสมาชิก เพื่อบรรเทาความเดือดร้อน",
      "icon": "HeartHandshake",
      "sort_order": 1
    }
  ]
}
```

---

### 5. Financial Statistics: `GET /api/public/statistics`
- **Purpose:** สถิติทางการเงินและสินทรัพย์ย้อนหลัง
- **Response Format:**
```json
{
  "success": true,
  "data": [
    {
      "year": 2568,
      "month": 9,
      "total_members": 2508,
      "total_assets": 1250000000.0,
      "share_capital": 450000000.0,
      "total_deposits": 550000000.0,
      "total_loans": 980000000.0,
      "dividend_rate": 5.5,
      "liquidity_ratio": 1.2
    }
  ]
}
```

---

## 3. Member Administration API Contracts (Sprint 2B.1)

All endpoints require Staff Session Authentication, RBAC permission `members.view`, and MFA validation.

### 1. Member Registry Dashboard: `GET /api/admin/members/dashboard`
- **Purpose:** สรุปสถิติทะเบียนสมาชิก, จำนวนบัญชีเชื่อมโยง, ยอดปันผลสะสมรายปี, ประวัตินำเข้า และบันทึกการแก้ไขล่าสุด
- **Auth:** `AuthMiddleware`, `PermissionMiddleware('members', 'view')`
- **Response Format:**
```json
{
  "success": true,
  "members": {
    "total": 2508,
    "active": 2490,
    "suspended": 12,
    "resigned": 6,
    "no_account": 350
  },
  "years": [
    {
      "year": 2568,
      "records": 2480,
      "total_income": "12500000.00",
      "total_deductions": "1200000.00",
      "net_total": "11300000.00",
      "updated_at": "2026-10-15 14:30:00"
    }
  ],
  "runs": [
    {
      "id": 12,
      "created_at": "2026-10-15 14:30:00",
      "source_name": "dividend_2568_final.xlsx",
      "year": 2568,
      "record_count": 2480,
      "created_count": 105,
      "updated_count": 2375,
      "unchanged_count": 0,
      "net_total": "11300000.00",
      "actor": "เภสัชกร ทอม"
    }
  ],
  "changes": [
    {
      "id": 45,
      "member_id": 1,
      "member_no": "00004",
      "member_name": "ทอxx วิศิxxx",
      "action": "profile_update",
      "reason": "ปรับปรุงเบอร์โทรศัพท์ติดต่อ",
      "created_at": "2026-11-01 09:15:00",
      "actor": "เจ้าหน้าที่ สหกรณ์"
    }
  ],
  "warnings": []
}
```

---

### 2. Member Directory & Search: `GET /api/admin/members`
- **Purpose:** ค้นหา กรอง และแสดงรายการสมาชิก พร้อมตรวจสอบสถานะการผูกบัญชี (Identity Mapping) และการปกปิดข้อมูลตาม PDPA
- **Parameters:**
  - `q` (string, max 100): คำค้นหา (เลขสมาชิก, ชื่อ-สกุล, สังกัด, เลขบัตร)
  - `status` (string, optional): `active` | `suspended` | `resigned`
  - `account` (string, optional): `linked` (มีบัญชีแล้ว) | `unlinked` (ยังไม่มีบัญชี)
  - `year` (string, 4 digits, optional): ปีกรองเงินปันผล เช่น `2568`
  - `page` (integer, default 1)
  - `limit` (integer: 25, 50, 100, default 25)
- **Response Format:**
```json
{
  "success": true,
  "items": [
    {
      "id": 1,
      "member_no": "00004",
      "name": "ทอม วิศิษฏ์",
      "name_pdpa": "ทอxx วิศิxxx",
      "department": "กลุ่มงานเภสัชกรรม รพ.ระยอง",
      "status": "active",
      "has_account": 1,
      "id_card_pdpa": "3-2199-00100-95-9",
      "record_count": 2,
      "total_income": "45000.00",
      "total_deductions": "5000.00",
      "net": "40000.00"
    }
  ],
  "total": 2508,
  "page": 1,
  "limit": 25,
  "year": "2568",
  "account": "linked",
  "available_years": [2568, 2567],
  "warnings": []
}
```

---

### 3. Member Detail & Historical Dividends: `GET /api/admin/members/{id}`
- **Purpose:** รายละเอียดสมาชิก, การตรวจสอบอัตลักษณ์ 1:1, บัญชีผู้ใช้ที่เชื่อมโยง, ข้อมูลปันผลรายปีย้อนหลังแบบ Read-Only และ Audit Trail
- **Security Audit:** บันทึกการเรียกดูข้อมูล (`SecurityAuditService::recordAdminMemberView`) ทุกครั้ง
- **Response Format:**
```json
{
  "success": true,
  "member": {
    "id": 1,
    "member_no": "00004",
    "user_id": 2,
    "prefix": "ภก.",
    "first_name": "ทอม",
    "last_name": "วิศิษฏ์",
    "name_pdpa": "ภก.ทอxx วิศิxxx",
    "id_card": "3219900100959",
    "id_card_pdpa": "3-2199-00100-95-9",
    "department": "กลุ่มงานเภสัชกรรม รพ.ระยอง",
    "position": "เภสัชกรชำนาญการพิเศษ",
    "phone": "081-xxx-xxxx",
    "email": "pharmacistxxx@rayongcoop.com",
    "address": "รพ.ระยอง อ.เมือง จ.ระยอง",
    "status": "active"
  },
  "account": {
    "id": 2,
    "name_pdpa": "ทอxx วิศิxxx",
    "username_pdpa": "3-2199-00100-95-9",
    "status": "active",
    "created_at": "2024-01-15 10:00:00",
    "updated_at": "2026-10-15 14:30:00"
  },
  "duplicates": {
    "member_no": 0,
    "id_card": 0
  },
  "dividends": [
    {
      "record": {
        "year": 2568,
        "total_income": 45000,
        "total_deductions": 5000,
        "net": 40000,
        "dividend_rate": 5.25,
        "refund_rate": 12.5,
        "income": {
          "เงินปันผลตามหุ้น": 35000,
          "เงินเฉลี่ยคืนเงินกู้": 10000
        },
        "deductions": {
          "หักชำระหนี้พิเศษ": 3000,
          "หักเข้าบัญชีเงินฝากออมทรัพย์": 2000
        }
      },
      "version": "sha256-hash",
      "updated_at": "2026-10-15 14:30:00"
    }
  ],
  "history": [
    {
      "id": 101,
      "action": "profile_update",
      "reason": "ปรับปรุงเบอร์โทรศัพท์ติดต่อ",
      "before_json": "{\"phone\":\"081-111-1111\"}",
      "after_json": "{\"phone\":\"081-999-9999\"}",
      "created_at": "2026-11-01 09:15:00",
      "actor": "เจ้าหน้าที่ทะเบียน"
    }
  ],
  "read_only": false,
  "warnings": []
}
```

---

### 4. Member System Health & Diagnostics: `GET /api/admin/members/health`
- **Purpose:** ตรวจสอบความสมบูรณ์ของโครงสร้างตารางฐานข้อมูล, ส่วนขยาย PHP และสิทธิ์ `member_admin`
- **Response Format:**
```json
{
  "success": true,
  "health": {
    "ready": true,
    "missing": [],
    "extensions": {
      "pdo_mysql": true,
      "mbstring": true,
      "zip": true,
      "simplexml": true
    },
    "member_admin_role": true
  }
}
```

---

### 5. Dividend Read-Only Protection Gate: `POST /api/admin/members/{id}/dividends/{year}`
- **Security Policy:** Annual dividend balances are immutable via direct API edits.
- **Enforcement:** ระบบจะปฏิเสธการแก้ไขด้วย HTTP 422 `VALIDATION_ERROR` ทันที
- **Response Format:**
```json
{
  "success": false,
  "code": "VALIDATION_ERROR",
  "message": "ข้อมูลเงินปันผลรายปีได้รับการคุ้มครองความถูกต้อง (Read-Only) การแก้ไขตัวเลขย้อนหลังโดยตรงถูกปิดใช้งาน การปรับปรุงยอดเงินต้องดำเนินการผ่านระบบ Preflight & Import อย่างเป็นทางการ"
}
```


