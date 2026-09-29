> Current production scope: public website and staff CMS. Member services are
> retained for future activation but disabled by default.

1.Ask
2.Plan
3.Implement
4.Review Diff
5.Run/Test
6.Commit (Thai Language)


# RayongCoop Digital Portal & Financial Cooperative System
### สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด (Rayong Public Health Savings and Credit Cooperative Limited)

[![React](https://img.shields.io/badge/React-18.3.1-61DAFB?logo=react&logoColor=black)](https://reactjs.org/)
[![Vite](https://img.shields.io/badge/Vite-6.0.1-646CFF?logo=vite&logoColor=white)](https://vitejs.dev/)
[![TailwindCSS](https://img.shields.io/badge/TailwindCSS-3.4.19-38B2AC?logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2%20%7C%208.4-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Security Standard](https://img.shields.io/badge/Security-Argon2id%20%2B%202FA%20%2B%20PDPA-073B74)](./SECURITY.md)

---

## 🌟 ภาพรวมระบบ (System Overview)

**RayongCoop Digital Portal** คือระบบเว็บพอร์ทัลและระบบบริหารจัดการข้อมูลการเงินดิจิทัล (Digital Financial & Cooperative Portal) ที่ออกแบบและพัฒนาขึ้นตามมาตรฐานสถาบันการเงิน (Financial Institution Standard) เพื่อให้บริการแก่สมาชิกและบุคลากรสาธารณสุขจังหวัดระยองอย่างครอบคลุม ปลอดภัย ทันสมัย และเข้าถึงง่าย (1–2 Click Access)

ระบบผสมผสานการทำงานระหว่าง **Modern Single Page Application (React 18 + Vite + Tailwind CSS)** ในส่วนหน้าบ้าน (Frontend) และ **PHP 8.4 Clean Architecture / REST Services** พร้อมฐานข้อมูล MySQL 8.0+ เพื่อรองรับทั้งการแสดงผลที่รวดเร็ว การประมวลผลธุรกรรมทางการเงินที่แม่นยำ และระบบการรักษาความปลอดภัยขั้นสูง

---

## 🚀 คุณสมบัติเด่นและโมดูลสำคัญ (Key Features & Modules)

### 1. 🏛️ Modern Financial Design System & Dual Theme
- **Theme Switcher**: รองรับโหมดสว่าง (Light Mode) และโหมดมืด (Dark Mode) พร้อมบันทึกสถานะลงใน LocalStorage
- **Financial Color Palette**: โทนสีมาตรฐานสถาบันการเงิน Navy Blue (`#073B74`), Royal Blue (`#0B5ED7`), Soft Light Blue (`#EAF4FF`) และ Gold Accent (`#C99A2E`)
- **Interactive UI Components**: Quick Action Dock, Header/Navbar พร้อม Dropdown เมนู 2 ระดับ, แถบประกาศด่วน (Announcement Ticker) และ Banner Carousel

### 2. 🤖 RayongCoop AI Copilot & Fun Interactive Tools
- **AI Virtual Assistant**: ผู้ช่วยดิจิทัลอัจฉริยะคอยตอบคำถามด้านระเบียบเงินกู้ เงินฝาก อัตราดอกเบี้ย สวัสดิการสมาชิก และขั้นตอนการติดต่อสหกรณ์
- **เครื่องมือสนุกๆ คลายเครียดสำหรับบุคลากรสาธารณสุข**:
  - 📝 **เครื่องสร้างข้ออ้างฉุกเฉิน (Excuse Generator)**: ช่วยคิดเหตุผลสุภาพและน่าเชื่อถือเมื่อติดภารกิจ
  - 🔮 **ดูดวงการเงินรายวัน (Daily Financial Fortune Teller)**: เสี่ยงเซียมซีดวงการเงินและตัวเลขมงคล
  - 🚨 **โหมดเจ้านายเดินมา (Boss Escape Alarm)**: ซ่อนหน้าจอและสลับเป็นหน้าเอกสารทางการแพทย์/สถิติด่วนใน 1 คลิก

### 3. 👥 Multi-Tier Role-Based Access Control (RBAC) & E-Services
ระบบจัดการสิทธิ์ผู้ใช้งาน 4 ระดับ (Super Admin, Staff / Officer, Member, Guest):

- **👤 Member Portal (ระบบสมาชิก)**:
  - ตรวจสอบยอดเงินฝาก หุ้นสะสม สัญญาเงินกู้ และยอดเงินปันผลประจำปี
  - ประวัติและสถานะการขอรับเงินสวัสดิการสมาชิก (สมรส, คลอดบุตร, ทุนการศึกษา, บำเหน็จ)
  - ระบบยื่นคำขอกู้เงินออนไลน์ (Digital Loan Application) พร้อมแนบเอกสาร
  - **Member Feedback & Complaints**: ระบบส่งข้อเสนอแนะและเรื่องร้องเรียน พร้อมระบบรักษาความเป็นส่วนตัว (Privacy Isolation) ที่แสดงเฉพาะรายการของสมาชิกผู้เข้าสู่ระบบเท่านั้น
  - **Profile Management**: จัดการข้อมูลส่วนตัว อัปเดตรูปโปรไฟล์ (Avatar Upload) และเบอร์โทรศัพท์ติดต่อ

- **👔 Staff / Officer Dashboard (ระบบเจ้าหน้าที่)**:
  - **Maker-Checker Workflow Review Modal**: ระบบตรวจสอบและอนุมัติคำขอกู้เงินและสวัสดิการของสมาชิก
  - บันทึกผลการพิจารณา (อนุมัติ, ปฏิเสธพร้อมระบุเหตุผล, ขอเอกสารเพิ่มเติม)
  - ระบบ Real-time Local Storage Synchronization เพื่ออัปเดตสถานะไปยังสมาชิกทันที

- **🛡️ Super Admin CMS (ระบบผู้ดูแลระบบสูงสุด)**:
  - **Executive Financial Dashboard**: สรุปตัวเลขทางการเงิน (สินทรัพย์รวม, ทุนเรือนหุ้น, เงินฝาก, สินเชื่อคงค้าง, NPL, อัตราส่วนสภาพคล่อง)
  - **Interactive Drag & Drop Popup Campaign Manager**: จัดการป็อปอัปประชาสัมพันธ์ พร้อมโซนลากวางไฟล์รูปภาพ (Drag & Drop File Upload) และจัดลำดับความสำคัญ (Critical, High, Normal, Low)
  - **Member Complaints Management**: ระบบจัดการและตอบกลับข้อร้องเรียนของสมาชิก พร้อมบันทึกประวัติ
  - **Master Rates & Products**: บริหารจัดการอัตราดอกเบี้ยเงินฝาก-เงินกู้ ข่าวสาร และเอกสารดาวน์โหลด

### 4. 🧮 Interactive Financial Calculators
- **Loan Amortization Calculator**: คำนวณตารางผ่อนชำระเงินกู้แบบลดต้นลดดอก แสดงยอดผ่อนชำระต่อเดือน สัดส่วนเงินต้นและดอกเบี้ยรวม
- **Deposit Interest Calculator**: คำนวณผลตอบแทนเงินฝากออมทรัพย์พิเศษและเงินฝากประจำ

### 5. 📜 Digital Document & E-Receipt Verification
- **Verify Receipt Gateway**: ระบบตรวจสอบความถูกต้องของใบเสร็จรับเงินดิจิทัลผ่านรหัสอ้างอิงและ QR Code เพื่อป้องกันการปลอมแปลงเอกสาร

### 6. 🛡️ PDPA & Privacy-by-Design Cookie CMP
- **Cookie Consent Management Platform**: แบนเนอร์ขอความยินยอมตาม พ.ร.บ. คุ้มครองข้อมูลส่วนบุคคล (PDPA)
- **Cookie Preference Center**: ให้ผู้ใช้เลือกเปิด/ปิดคุกกี้สถิติและการตลาดได้ตามความต้องการ พร้อมระบบ Anonymous Consent Logging

---

## 🛠️ Technology Stack

| หมวดหมู่ (Category) | เทคโนโลยีที่ใช้ (Technologies) |
|---|---|
| **Frontend Framework** | [React 18.3.1](https://reactjs.org/) + [Vite 6.0.1](https://vitejs.dev/) (ES Module Build) |
| **Styling & UI** | [Tailwind CSS 3.4.19](https://tailwindcss.com/) + PostCSS + Custom CSS Tokens |
| **Icons & Visuals** | [Lucide React Icons](https://lucide.dev/), Canvas Confetti |
| **Routing & State** | React Router DOM v6, React Context API (`AuthContext`, `ThemeContext`) |
| **Backend & CLI** | PHP 8.2+ / PHP 8.4+ (Clean MVC, Service Layer, Repository Pattern, `php bin/console`) |
| **Database** | MySQL 8.0+ / MariaDB 10.4+ (36 Normalized Tables with Foreign Keys & Indexes) |
| **Security & Auth** | Argon2id Hashing, RFC 6238 TOTP 2FA, PDO Prepared Statements, CSRF Tokens |
| **Server Support** | Ubuntu 24.04 LTS, Nginx, PHP-FPM 8.4, SSL/TLS (Let's Encrypt) |

---

## 💻 การติดตั้งและเริ่มใช้งาน (Getting Started)

### 1. ติดตั้งและรัน Frontend (React + Vite)
```bash
# 1. ติดตั้ง Node.js dependencies
npm install

# 2. รันโหมด Development Server
npm run dev

# 3. ตรวจสอบและ Build สำหรับ Production
npm run build
```
เมื่อรัน `npm run dev` ระบบจะเปิดเซิร์ฟเวอร์ที่ `http://localhost:5173/`

### 2. ติดตั้งและกำหนดค่า Backend (PHP + MySQL)
```bash
# 1. ติดตั้ง Composer dependencies
composer install

# 2. คัดลอกและตั้งค่า Environment
cp .env.example .env

# 3. รัน CLI สำหรับสร้างฐานข้อมูลและ Seeding
php bin/console db:migrate
php bin/console db:seed
```

---

## 📁 โครงสร้างโปรเจกต์ (Project Directory Structure)

```text
rayongcoop-react/
├── public/                 # Static public assets, icons, logos
├── src/
│   ├── components/         # React Reusable Components
│   │   ├── calculators/    # Financial & Amortization Loan Calculators
│   │   ├── common/         # Common UI (AIChatWidget, CampaignModal, Alerts)
│   │   ├── layout/         # Navbar, TopBar, Footer, QuickActionDock
│   │   ├── member/         # Member Modals (AuthModal, EditProfileModal)
│   │   └── staff/          # Staff Review & Maker-Checker Modals
│   ├── context/            # React Contexts (AuthContext, ThemeContext)
│   ├── data/               # Mock data, rate matrices, initial fixtures
│   ├── pages/              # Single Page Views (Home, Member Dashboard, Admin, Profile, etc.)
│   ├── App.jsx             # Main Application Routing & Providers
│   ├── index.css           # Global Design Tokens & Tailwind Directives
│   └── main.jsx            # React Application Entry Point
├── app/                    # PHP Backend MVC Application Core
├── bin/                    # PHP Console CLI Tool (`bin/console`)
├── config/                 # Application & Database Configuration
├── database/               # SQL Migrations, Seeders & Schemas
├── storage/                # Logs, Uploads, File Caching & Backups
├── DEPLOYMENT.md           # Production Deployment Guide (Ubuntu + Nginx)
├── INSTALLATION.md         # Detailed Installation Instructions
├── SECURITY.md             # Security Policies, OWASP Hardening & PDPA
├── BACKUP.md               # Database Backup & Disaster Recovery Guide
├── MIGRATION.md            # Legacy Data Migration Strategy
└── package.json            # NPM Scripts and Frontend Dependencies
```

---

## 📚 เอกสารคู่มือระบบ (Documentation)

- 📖 [INSTALLATION.md](./INSTALLATION.md) — คู่มือการติดตั้งระบบฉบับละเอียดสำหรับ Local Development & Test Server
- 🚀 [DEPLOYMENT.md](./DEPLOYMENT.md) — คู่มือการ Deploy ขึ้นระบบจริงบน Ubuntu 24.04 LTS + Nginx + PHP-FPM 8.4 + SSL
- 🔒 [SECURITY.md](./SECURITY.md) — นโยบายความมั่นคงปลอดภัยไซเบอร์ มาตรฐานการเข้ารหัส และ PDPA
- 💾 [BACKUP.md](./BACKUP.md) — คู่มือการสำรองข้อมูลอัตโนมัติและการกู้คืนระบบ (Disaster Recovery Protocol)
- 🔄 [MIGRATION.md](./MIGRATION.md) — แผนและกลยุทธ์การถ่ายโอนข้อมูลจากระบบเดิม (Legacy Migration)

---

## 📄 ใบอนุญาต (License)
สงวนลิขสิทธิ์ © 2026 **สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด (Rayong Public Health Savings and Credit Cooperative Limited)**
All Rights Reserved.
