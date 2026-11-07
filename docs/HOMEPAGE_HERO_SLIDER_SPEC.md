# RYCOOPWEB — Homepage Hero Slider Technical Specification

**Module:** Homepage Hero Promotional Slider (`HeroSlider.jsx`)
**Backend Entity:** `hero_slides`
**Target Environment:** TEST (`https://test.rayongcoop.org/`)
**Status:** Implementation Ready

---

## 1. Visual & Responsive Design Specifications

The Homepage Hero Slider serves as the primary visual engagement component of RYCOOPWEB. It presents cooperative promotions, announcements, and vital member services with responsive art-direction and interactive controls.

### 1.1 Responsive Dimensions & Aspect Ratios
- **Desktop (>= 1024px):**
  - Container dimensions: `1920px x 650px` (Aspect ratio ~ 2.95:1)
  - Content alignment: Configurable left / center / right with max-width `680px`
  - Typography: Heading `clamp(2rem, 3.5vw, 3.25rem)` (Extra Bold 800) with subtle drop shadow
- **Tablet (768px - 1023px):**
  - Container dimensions: `1024px x 500px` (Aspect ratio ~ 2:1)
  - Typography: Heading `clamp(1.75rem, 2.5vw, 2.25rem)`
- **Mobile (< 768px):**
  - Container dimensions: `750px x 900px` (Aspect ratio 1:1.2 to 9:16 portrait)
  - Text layout: Centered or bottom-aligned with higher overlay contrast (`rgba(15, 23, 42, 0.75)`)
  - Typography: Heading `1.65rem` with high legibility

---

## 2. Interactive Behavior & Features

### 2.1 Autoplay & Timing
- **Duration:** 6,000 milliseconds (6 seconds) per slide.
- **Pause on Interaction:**
  - Mouse hover (`onMouseEnter` / `onMouseLeave`)
  - Keyboard focus inside slider (`onFocus` / `onBlur`)
  - Active drag / swipe in progress
- **Play/Pause Toggle:** Dedicated accessible toggle button allowing users to freeze or resume rotation.
- **Motion Preference:** Automatic disabling of autoplay when `prefers-reduced-motion: reduce` is active in user OS settings.

### 2.2 Navigation Controls
- **Arrow Buttons:** Previous (`ChevronLeft`) and Next (`ChevronRight`) with rounded translucent glassmorphism background and keyboard activation.
- **Pagination Indicators (Dots):** Visual bar or dot indicators with active slide indicator pill. Clicking any indicator immediately transitions to that slide.
- **Touch Gesture Handling:** Hardware-accelerated Pointer Events (`onPointerDown`, `onPointerMove`, `onPointerUp`, `onPointerCancel`) with a swipe threshold of 48px.

### 2.3 Accessibility (WCAG 2.1 AA)
- Component attributes: `role="region"`, `aria-roledescription="carousel"`, `aria-label="ภาพประชาสัมพันธ์สหกรณ์"`
- Slide items: `aria-roledescription="slide"`, `aria-label="สไลด์ {n} จาก {total}"`
- Dynamic announcements: `aria-live="polite"` when autoplay is paused, `aria-live="off"` during continuous autoplay.

---

## 3. The 4 Initial Promotional Banners

| Slide # | Slide Title | Subtitle / Description | Call to Action (CTA) | Target Route | Background Visual |
|---|---|---|---|---|---|
| **1** | **ยินดีต้อนรับสมาชิกสหกรณ์** | บริการออนไลน์ สะดวก รวดเร็ว เข้าถึงข้อมูลสมาชิกได้ง่าย ตรวจสอบสถานะและธุรกรรมได้ทุกที่ทุกเวลา | **บริการสมาชิก** | `/eservice` (หรือ `/member/login`) | Deep Blue & Emerald Gradient with Cooperative Emblem |
| **2** | **เงินกู้ของสหกรณ์** | รู้จักผลิตภัณฑ์เงินกู้และสิทธิประโยชน์สำหรับสมาชิก ดอกเบี้ยเป็นธรรม ผ่อนสบาย เพื่อคุณภาพชีวิตที่ดีขึ้น | **ดูผลิตภัณฑ์เงินกู้** | `/loans` | Financial Growth & Member Security Pattern |
| **3** | **เงินปันผลและเงินเฉลี่ยคืน** | ตรวจสอบรายการรายรับ รายหัก และยอดสุทธิของสมาชิก พร้อมข้อมูลสิทธิประโยชน์ประจำปี | **ตรวจสอบเงินปันผล** | `/member/dividends` | Dividend Gold Coins & Prosperity Graphics |
| **4** | **ข่าวสารและกิจกรรมสหกรณ์** | ติดตามประกาศ การประชุม ผลการดำเนินงาน และกิจกรรมประชาสัมพันธ์ของสหกรณ์อย่างต่อเนื่อง | **อ่านข่าวประชาสัมพันธ์** | `/news` | Cooperative Community & Public Health Care Theme |

---

## 4. Backend Database Schema (`hero_slides`)

The MySQL table `hero_slides` supports both desktop and mobile assets, custom CTA links, scheduling, and ordering:

```sql
CREATE TABLE IF NOT EXISTS `hero_slides` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `desktop_image` varchar(255) NOT NULL,
  `mobile_image` varchar(255) DEFAULT NULL,
  `button_text` varchar(100) DEFAULT NULL,
  `button_url` varchar(255) DEFAULT NULL,
  `button_target` enum('_self','_blank') NOT NULL DEFAULT '_self',
  `text_position` enum('left','center','right') NOT NULL DEFAULT 'left',
  `text_alignment` enum('left','center','right') NOT NULL DEFAULT 'left',
  `overlay_opacity` decimal(3,2) NOT NULL DEFAULT '0.40',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `priority` int(11) NOT NULL DEFAULT '0',
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `status` enum('draft','active','inactive') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hero_slides_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Banner Management CMS Architecture

### 5.1 API Endpoints
- `GET /api/admin/banners`: List all slides (including draft and scheduled), with sorting by `priority DESC, sort_order ASC`.
- `POST /api/admin/banners`: Create new slide with desktop and optional mobile image file upload.
- `POST /api/admin/banners/{id}/update`: Update slide details, images, scheduling dates, and status.
- `POST /api/admin/banners/{id}/delete`: Soft delete slide (`deleted_at = NOW()`).
- `POST /api/admin/banners/reorder`: Update `sort_order` sequence.

### 5.2 Security & Access Controls
- Enforced with `AuthMiddleware`, `RoleMiddleware(['super_admin', 'pr_officer', 'staff', 'it_admin'])`, and `CsrfMiddleware`.
- Mandatory Audit Logging via `AuditService::log('hero_slides', ...)` with old vs new state capture.
