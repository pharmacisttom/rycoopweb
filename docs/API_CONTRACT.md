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

