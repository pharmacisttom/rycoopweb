# RYCOOPWEB — TEST UAT Checklist & Verification Matrix

**Environment:** TEST ONLY (`https://test.rayongcoop.org/`)
**Target Database:** `rycoopweb_test`
**Execution Objective:** Comprehensive User Acceptance Testing (UAT)

---

## 1. Homepage & Hero Slider Verification

- [ ] **TC-HERO-01: Visual Appearance & Dimensions**
  - Verify Hero Slider renders directly beneath TopBar and Navbar on Desktop (1920x650 viewport) and Mobile (375px/750px viewport).
  - Confirm background gradients, typography, and badges render cleanly with zero overflow.
- [ ] **TC-HERO-02: 4 Initial Promotional Slides**
  - Slide 1: Welcome ("ยินดีต้อนรับสมาชิกสหกรณ์" / CTA: "บริการสมาชิก" -> `/eservice` or `/member/login`).
  - Slide 2: Cooperative Loans ("เงินกู้ของสหกรณ์" / CTA: "ดูผลิตภัณฑ์เงินกู้" -> `/loans`).
  - Slide 3: Dividends ("เงินปันผลและเงินเฉลี่ยคืน" / CTA: "ตรวจสอบเงินปันผล" -> `/member/dividends`).
  - Slide 4: News ("ข่าวสารและกิจกรรมสหกรณ์" / CTA: "อ่านข่าวประชาสัมพันธ์" -> `/news`).
- [ ] **TC-HERO-03: Carousel Autoplay & Pause Behavior**
  - Verify slides automatically transition every 6 seconds.
  - Hovering the mouse over the slider pauses autoplay. Moving mouse out resumes autoplay.
  - Clicking the dedicated Play/Pause toggle button stops and resumes rotation.
- [ ] **TC-HERO-04: Navigation & Touch Swipe**
  - Clicking Prev/Next buttons navigates slides accurately with infinite loop.
  - Clicking pagination dots jumps to corresponding slide.
  - On mobile/touch devices, swiping left transitions to next slide; swiping right transitions to previous slide.
- [ ] **TC-HERO-05: Loan Section Headline**
  - Verify the main loan products headline displays as **"เงินกู้ของสหกรณ์"** (not "สินเชื่อเพื่อบุคลากรสาธารณสุข").

---

## 2. Banner Management CMS (`/admin/banners`)

- [ ] **TC-CMS-01: RBAC Access Control**
  - Verify access is permitted for `super_admin`, `pr_officer`, `manager`, `it_admin`.
  - Verify unauthorized roles receive HTTP 403 Forbidden.
- [ ] **TC-CMS-02: Slide Creation & Media Upload**
  - Upload valid desktop image (JPEG/PNG/WebP <= 10MB) with Thai title and CTA link.
  - Verify new slide is saved in `hero_slides` and logged in `audit_logs`.
- [ ] **TC-CMS-03: Slide Editing & Reordering**
  - Edit slide title, sort order, and status (active/draft/inactive).
  - Verify changes reflect immediately on homepage API (`/api/public/home`).
- [ ] **TC-CMS-04: Soft Deletion**
  - Delete a slide; confirm `deleted_at` timestamp is set and slide disappears from public view.

---

## 3. Document Download Center (`/documents`)

- [ ] **TC-DOC-01: 11 Standardized Categories**
  - Confirm all 11 categories exist in the filter sidebar:
    1. เงินกู้ของสหกรณ์
    2. เงินฝาก
    3. สมาชิกและหุ้น
    4. สวัสดิการสมาชิก
    5. สมาคมฌาปนกิจ (สสธท./กสธท./สส.ชสอ.)
    6. การคุ้มครองข้อมูลส่วนบุคคล (PDPA)
    7. ระเบียบและข้อบังคับ
    8. รายงานประจำปี / ผลการดำเนินงาน
    9. แผนกลยุทธ์สหกรณ์
    10. แบบฟอร์มคำขอทั่วไปและเรื่องร้องเรียน
    11. ประกาศและเอกสารอื่นๆ
- [ ] **TC-DOC-02: Instant Search**
  - Enter keywords (e.g. "คำขอกู้", "ปันผล", "2568"); verify table filters instantly without page reload.
- [ ] **TC-DOC-03: File Download & Security**
  - Verify clicking download delivers the intended PDF file securely.

---

## 4. Loan Application Calendar (`/loan-schedule`)

- [ ] **TC-CAL-01: Route & Navigation**
  - Access `/loan-schedule` directly and via menu links.
- [ ] **TC-CAL-02: Content & Calendar Accuracy**
  - Verify 2569 monthly schedule displays deadline, meeting date, and transfer date clearly.
  - Verify image preview (`pay2569.png`) renders cleanly on mobile and desktop.

---

## 5. Member Welfare & Partner Organizations (`/welfare`)

- [ ] **TC-WEL-01: Internal Welfare Benefits**
  - Review marriage, single member, and pension welfare conditions and claim guidelines.
- [ ] **TC-WEL-02: Partner Associations (สสธท. / กสธท. / สส.ชสอ.)**
  - Verify dedicated card section exists with official links (`https://cpct.or.th`, `https://matpf.com`, etc.) and downloadable enrollment forms.

---

## 6. Member Portal & Dividend Security Verification

- [ ] **TC-MEM-01: Member Name Display Masking**
  - Log in as any member (e.g. `02723` or any of 2,508 members).
  - Verify name renders with Unicode Thai grapheme-safe masking: `นายจัตุxx กันxxx` (title intact, 4 first-name graphemes + `xx`, 3 surname graphemes + `xxx`).
- [ ] **TC-MEM-02: Password Change UI Hidden**
  - Confirm no member password change button, modal, or initial password reset prompt appears.
- [ ] **TC-MEM-03: Financial Isolation**
  - Confirm all dividend and member inquiries run strictly against `rycoopweb_test`.
