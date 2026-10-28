-- ============================================================================
-- Migration: 025_create_admin_control_center_tables.sql
-- Purpose: Additive, non-destructive MySQL 8.0 schema and least-privilege RBAC
--          catalog for RYCOOPWEB 3.0 Enterprise Admin Control Center (14 Modules),
--          Dual-Control (Maker-Checker) approval requests, and per-user preferences.
--
-- Foreign-Key Type Alignment (Verified against 001_initial_schema.sql):
--   * users.id       -> BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
--   * roles.id       -> BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
--   * permissions.id -> BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
--   * approval_requests.requested_by      -> BIGINT UNSIGNED NOT NULL
--   * approval_requests.reviewed_by       -> BIGINT UNSIGNED NULL DEFAULT NULL
--   * admin_dashboard_preferences.user_id -> BIGINT UNSIGNED NOT NULL PRIMARY KEY
--
-- Idempotency & Safety Guarantees:
--   * Strictly additive (CREATE TABLE IF NOT EXISTS, INSERT INTO ... SELECT ... WHERE NOT EXISTS)
--   * Replaces ON DUPLICATE KEY UPDATE with WHERE NOT EXISTS so existing roles,
--     permissions, descriptions, timestamps, and AUTO_INCREMENT counters are untouched
--   * Zero modifications to users, user_roles, MFA secrets/recovery codes, or financial tables
--   * Enforces strict Separation of Duties (SoD) and Least Privilege across all roles
-- ============================================================================

-- 1. Dual-Control (Maker-Checker) Approval Requests Table
CREATE TABLE IF NOT EXISTS `approval_requests` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `request_no` VARCHAR(40) NOT NULL UNIQUE COMMENT 'Unique reference e.g. APR-20261028-0001',
    `module` VARCHAR(60) NOT NULL COMMENT 'Source module e.g. backup, roles, security, finance, members',
    `action_key` VARCHAR(100) NOT NULL COMMENT 'Action identifier e.g. backup.restore.request, roles.manage',
    `target_type` VARCHAR(80) NULL DEFAULT NULL COMMENT 'Target entity type e.g. backup_archive, role, user',
    `target_id` VARCHAR(120) NULL DEFAULT NULL COMMENT 'Target entity identifier or filename',
    `payload_json` JSON NULL DEFAULT NULL COMMENT 'Sanitized request parameters (never contains secrets)',
    `reason` TEXT NOT NULL COMMENT 'Business justification provided by Maker',
    `status` ENUM('pending', 'approved', 'rejected', 'cancelled', 'expired') NOT NULL DEFAULT 'pending',
    `requested_by` BIGINT UNSIGNED NOT NULL COMMENT 'Maker user ID (references users.id BIGINT UNSIGNED)',
    `reviewed_by` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Checker user ID (references users.id BIGINT UNSIGNED; must differ from Maker)',
    `reviewed_at` DATETIME NULL DEFAULT NULL,
    `review_note` TEXT NULL DEFAULT NULL,
    `expires_at` DATETIME NULL DEFAULT NULL,
    `executed_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_approval_status_created` (`status`, `created_at`),
    INDEX `idx_approval_module_action` (`module`, `action_key`),
    INDEX `idx_approval_requested_by` (`requested_by`),
    INDEX `idx_approval_reviewed_by` (`reviewed_by`),
    CONSTRAINT `fk_approval_requests_maker` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_approval_requests_checker` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Admin Control Center Per-User Preferences Table
CREATE TABLE IF NOT EXISTS `admin_dashboard_preferences` (
    `user_id` BIGINT UNSIGNED NOT NULL PRIMARY KEY COMMENT 'References users.id BIGINT UNSIGNED',
    `sidebar_collapsed` TINYINT(1) NOT NULL DEFAULT 0,
    `default_date_range` VARCHAR(20) NOT NULL DEFAULT '30d',
    `pinned_widgets_json` JSON NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_admin_dashboard_prefs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Seed Enterprise RBAC Roles Safely (WHERE NOT EXISTS preserves existing roles untouched)
INSERT INTO `roles` (`name`, `slug`, `description`)
SELECT `candidate`.`name`, `candidate`.`slug`, `candidate`.`description`
FROM (
    SELECT 'ผู้ดูแลระบบและโครงสร้างพื้นฐาน' AS `name`, 'it_admin' AS `slug`, 'ดูแลความพร้อมใช้งานของระบบ ตรวจสอบสถานะเซิร์ฟเวอร์ ฐานข้อมูล และการสำรองข้อมูล' AS `description`
    UNION ALL SELECT 'ผู้ดูแลความมั่นคงปลอดภัย', 'security_admin', 'กำกับดูแลสถานะ 2FA/MFA ประวัติการเข้าสู่ระบบ เหตุการณ์ความมั่นคงปลอดภัย และการเพิกถอนเซสชัน'
    UNION ALL SELECT 'ผู้ตรวจสอบกิจการ / ตรวจสอบภายใน', 'auditor', 'สิทธิ์อ่านอย่างเดียว (Read-Only) สำหรับตรวจสอบ Audit Logs ประวัติการเข้าใช้ และส่งออกรายงาน'
    UNION ALL SELECT 'ผู้บริหาร / คณะกรรมการ', 'executive', 'ดูภาพรวมสถิติผู้บริหารและรายงานสรุปโดยไม่เข้าถึงข้อมูลความลับหรือแก้ไขการตั้งค่าระบบ'
    UNION ALL SELECT 'ผู้จัดการสหกรณ์', 'manager', 'ผู้จัดการสหกรณ์ ดูรายงานภาพรวมและผลการดำเนินงาน'
    UNION ALL SELECT 'ผู้ดูแลข้อมูลการเงิน', 'finance', 'จัดการข้อมูลอัตราดอกเบี้ยเงินฝาก-เงินกู้ สถิติทางการเงิน และรายงานทางการเงินที่ได้รับอนุญาต'
    UNION ALL SELECT 'เจ้าหน้าที่สินเชื่อและบัญชี', 'loan_officer', 'ตรวจสอบข้อมูลผลิตภัณฑ์สินเชื่อ คำขอกู้ และข้อมูลประกอบการประมวลผลทางการเงิน'
    UNION ALL SELECT 'ผู้ดูแลเนื้อหาและประชาสัมพันธ์', 'pr_officer', 'สร้าง แก้ไข และเผยแพร่ข่าวสาร ประกาศ คำถามที่พบบ่อย และข้อมูลพื้นฐานเว็บไซต์'
    UNION ALL SELECT 'ผู้ดูแลระบบสมาชิก', 'member_admin', 'บริหารจัดการข้อมูลสมาชิก คำขอแก้ไขข้อมูล และนำเข้าเงินปันผล-เฉลี่ยคืน (แยกออกจากผู้ดูแลระบบหลัก)'
    UNION ALL SELECT 'เจ้าหน้าที่ปฏิบัติการ', 'staff', 'เข้าถึงพื้นที่ทำงานเจ้าหน้าที่ การตรวจสอบข้อมูล LED และงานปฏิบัติการตามที่ได้รับมอบหมาย'
) AS `candidate`
WHERE NOT EXISTS (
    SELECT 1 FROM `roles` `r` WHERE `r`.`slug` = `candidate`.`slug`
);

-- 4. Seed Enterprise Control Center & Legacy Permissions Safely (WHERE NOT EXISTS)
INSERT INTO `permissions` (`name`, `module`, `action`)
SELECT `candidate`.`name`, `candidate`.`module`, `candidate`.`action`
FROM (
    -- Executive Dashboard & Operations
    SELECT 'ดูแดชบอร์ดผู้บริหารและภาพรวมระบบ' AS `name`, 'dashboard' AS `module`, 'view' AS `action`
    UNION ALL SELECT 'ดูศูนย์ติดตามงานค้างและคำขออนุมัติ', 'operations', 'view'
    UNION ALL SELECT 'ดูโครงสร้างแผนกและอัตรากำลังเจ้าหน้าที่', 'departments', 'view'
    UNION ALL SELECT 'ดูรายงานสรุปประจำวันและประจำเดือน', 'reports', 'view'

    -- Visitor Analytics & Web Traffic (matches AdminControlCenterService::ENTERPRISE_PERMISSIONS)
    UNION ALL SELECT 'ดูสถิติเชิงลึกและแนวโน้มการใช้งาน', 'analytics', 'view'
    UNION ALL SELECT 'ดูสถิติผู้เข้าชมเว็บไซต์และการวิเคราะห์ทราฟฟิก', 'analytics', 'visitors.view'
    UNION ALL SELECT 'ส่งออกรายงานสถิติผู้เข้าชมเว็บไซต์ (CSV)', 'analytics', 'visitors.export'

    -- User, Role & Permission Administration (mutation restricted to super_admin)
    UNION ALL SELECT 'ดูรายชื่อผู้ใช้งานและเจ้าหน้าที่', 'users', 'view'
    UNION ALL SELECT 'สร้างบัญชีหรือส่งคำเชิญเจ้าหน้าที่', 'users', 'create'
    UNION ALL SELECT 'แก้ไขข้อมูลและบทบาทเจ้าหน้าที่', 'users', 'update'
    UNION ALL SELECT 'ระงับหรือปิดการใช้งานบัญชีเจ้าหน้าที่', 'users', 'disable'
    UNION ALL SELECT 'ดูรายการบทบาท (Roles)', 'roles', 'view'
    UNION ALL SELECT 'สร้างและแก้ไขบทบาท', 'roles', 'manage'
    UNION ALL SELECT 'กำหนดสิทธิ์การใช้งานเชิงลึก', 'permissions', 'manage'

    -- Member & Financial Services Governance
    UNION ALL SELECT 'ดูข้อมูลทะเบียนสมาชิก', 'members', 'view'
    UNION ALL SELECT 'แก้ไขข้อมูลสมาชิกและอนุมัติคำขอ', 'members', 'update'
    UNION ALL SELECT 'นำเข้าข้อมูลสมาชิกและเงินปันผล', 'members', 'import'
    UNION ALL SELECT 'แก้ไขข้อมูลเงินปันผลรายบุคคล', 'dividends', 'update'
    UNION ALL SELECT 'นำเข้าชุดข้อมูลเงินปันผลประจำปี', 'dividends', 'import'
    UNION ALL SELECT 'ดูรายงานการเงิน เงินฝาก และสินเชื่อ', 'finance', 'view'
    UNION ALL SELECT 'ส่งออกรายงานทางการเงิน', 'finance', 'export'
    UNION ALL SELECT 'ดูอัตราดอกเบี้ย', 'rates', 'view'
    UNION ALL SELECT 'สร้างร่างแก้ไขอัตราดอกเบี้ย', 'rates', 'update'
    UNION ALL SELECT 'อนุมัติเผยแพร่อัตราดอกเบี้ย', 'rates', 'publish'
    UNION ALL SELECT 'ดูผลิตภัณฑ์สหกรณ์', 'products', 'view'
    UNION ALL SELECT 'สร้างร่างแก้ไขผลิตภัณฑ์', 'products', 'update'
    UNION ALL SELECT 'อนุมัติเผยแพร่ผลิตภัณฑ์', 'products', 'publish'

    -- Content & Public Portal Governance
    UNION ALL SELECT 'สร้างเนื้อหาเว็บไซต์และประกาศ', 'content', 'create'
    UNION ALL SELECT 'อนุมัติเผยแพร่เนื้อหาเว็บไซต์', 'content', 'publish'
    UNION ALL SELECT 'ดูรายการข่าวประชาสัมพันธ์', 'news', 'view'
    UNION ALL SELECT 'สร้างข่าวประชาสัมพันธ์', 'news', 'create'
    UNION ALL SELECT 'แก้ไขข่าวประชาสัมพันธ์', 'news', 'update'
    UNION ALL SELECT 'ลบข่าวประชาสัมพันธ์', 'news', 'delete'
    UNION ALL SELECT 'อนุมัติเผยแพร่ข่าวประชาสัมพันธ์', 'news', 'publish'
    UNION ALL SELECT 'ดูคำถามที่พบบ่อย', 'faqs', 'view'
    UNION ALL SELECT 'เพิ่มคำถามที่พบบ่อย', 'faqs', 'create'
    UNION ALL SELECT 'แก้ไขคำถามที่พบบ่อย', 'faqs', 'update'
    UNION ALL SELECT 'ลบคำถามที่พบบ่อย', 'faqs', 'delete'
    UNION ALL SELECT 'ดูข้อมูลพื้นฐานสหกรณ์', 'coop_info', 'view'
    UNION ALL SELECT 'แก้ไขร่างข้อมูลพื้นฐานสหกรณ์', 'coop_info', 'update'
    UNION ALL SELECT 'เผยแพร่ข้อมูลพื้นฐานสหกรณ์', 'coop_info', 'publish'
    UNION ALL SELECT 'ดูข้อมูลกรรมการและบุคลากร', 'people', 'view'
    UNION ALL SELECT 'แก้ไขร่างข้อมูลกรรมการและบุคลากร', 'people', 'update'
    UNION ALL SELECT 'เผยแพร่ข้อมูลกรรมการและบุคลากร', 'people', 'publish'

    -- Audit Log & Security Center (MFA & Sessions)
    UNION ALL SELECT 'ตรวจสอบบันทึก Audit Logs และประวัติการเข้าใช้', 'audit', 'view'
    UNION ALL SELECT 'ส่งออกข้อมูล Audit Logs (CSV)', 'audit', 'export'
    UNION ALL SELECT 'ดูศูนย์ความมั่นคงปลอดภัยและสถานะ MFA', 'security', 'view'
    UNION ALL SELECT 'ดูสถานะการลงทะเบียน MFA ของเจ้าหน้าที่', 'security', 'mfa.view'
    UNION ALL SELECT 'เพิกถอนเซสชันผู้ใช้งาน', 'security', 'sessions.revoke'

    -- Backup, Recovery & Dual-Control Governance (Maker vs Checker strictly separated)
    UNION ALL SELECT 'ดูสถานะและประวัติการสำรองข้อมูล', 'backup', 'view'
    UNION ALL SELECT 'ตรวจสอบความสมบูรณ์ของไฟล์สำรอง (SHA-256)', 'backup', 'verify'
    UNION ALL SELECT 'ส่งคำขอกู้คืนข้อมูล (ต้องรออนุมัติคู่)', 'backup', 'restore.request'
    UNION ALL SELECT 'อนุมัติคำขอกู้คืนข้อมูล (Dual Control)', 'backup', 'restore.approve'

    -- System Health & Settings
    UNION ALL SELECT 'ดูสถานะความพร้อมของเซิร์ฟเวอร์และฐานข้อมูล', 'system', 'health.view'
    UNION ALL SELECT 'ดูสถานะความพร้อมใช้งานของระบบ (Health Check)', 'health', 'view'
    UNION ALL SELECT 'ดูการตั้งค่าระบบ', 'settings', 'view'
    UNION ALL SELECT 'แก้ไขการตั้งค่าระบบ', 'settings', 'update'
) AS `candidate`
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` `p`
    WHERE `p`.`module` = `candidate`.`module` AND `p`.`action` = `candidate`.`action`
);

-- 5. Least-Privilege Role-Permission Mappings (Strictly aligned with ENTERPRISE_ROLE_MAP)
--    Uses WHERE NOT EXISTS to guarantee idempotency and preserve existing custom grants.

-- 5.1 it_admin (System Operations & Dual-Control Restore MAKER — cannot approve restores or mutate users/roles/settings)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT `r`.`id`, `p`.`id`
FROM `roles` `r`
JOIN `permissions` `p` ON (
    (`p`.`module` = 'dashboard' AND `p`.`action` = 'view') OR
    (`p`.`module` = 'analytics' AND `p`.`action` IN ('view', 'visitors.view', 'visitors.export')) OR
    (`p`.`module` = 'users' AND `p`.`action` = 'view') OR
    (`p`.`module` = 'roles' AND `p`.`action` = 'view') OR
    (`p`.`module` = 'audit' AND `p`.`action` = 'view') OR
    (`p`.`module` = 'security' AND `p`.`action` = 'view') OR
    (`p`.`module` = 'backup' AND `p`.`action` IN ('view', 'verify', 'restore.request')) OR
    (`p`.`module` = 'system' AND `p`.`action` = 'health.view') OR
    (`p`.`module` = 'settings' AND `p`.`action` = 'view')
)
WHERE `r`.`slug` = 'it_admin'
  AND NOT EXISTS (
      SELECT 1 FROM `role_permissions` `rp`
      WHERE `rp`.`role_id` = `r`.`id` AND `rp`.`permission_id` = `p`.`id`
  );

-- 5.2 security_admin (Security Governance & Dual-Control Restore CHECKER — cannot request restores or mutate users/roles)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT `r`.`id`, `p`.`id`
FROM `roles` `r`
JOIN `permissions` `p` ON (
    (`p`.`module` = 'dashboard' AND `p`.`action` = 'view') OR
    (`p`.`module` = 'analytics' AND `p`.`action` IN ('view', 'visitors.view')) OR
    (`p`.`module` = 'users' AND `p`.`action` = 'view') OR
    (`p`.`module` = 'roles' AND `p`.`action` = 'view') OR
    (`p`.`module` = 'security' AND `p`.`action` IN ('view', 'mfa.view', 'sessions.revoke')) OR
    (`p`.`module` = 'audit' AND `p`.`action` IN ('view', 'export')) OR
    (`p`.`module` = 'backup' AND `p`.`action` IN ('view', 'restore.approve')) OR
    (`p`.`module` = 'system' AND `p`.`action` = 'health.view')
)
WHERE `r`.`slug` = 'security_admin'
  AND NOT EXISTS (
      SELECT 1 FROM `role_permissions` `rp`
      WHERE `rp`.`role_id` = `r`.`id` AND `rp`.`permission_id` = `p`.`id`
  );

-- 5.3 auditor (Independent Internal Audit — strictly read-only + report/audit CSV export; zero mutation permissions)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT `r`.`id`, `p`.`id`
FROM `roles` `r`
JOIN `permissions` `p` ON (
    (`p`.`module` = 'dashboard' AND `p`.`action` = 'view') OR
    (`p`.`module` = 'analytics' AND `p`.`action` IN ('view', 'visitors.view', 'visitors.export')) OR
    (`p`.`module` = 'users' AND `p`.`action` = 'view') OR
    (`p`.`module` = 'roles' AND `p`.`action` = 'view') OR
    (`p`.`module` = 'members' AND `p`.`action` = 'view') OR
    (`p`.`module` = 'finance' AND `p`.`action` IN ('view', 'export')) OR
    (`p`.`module` = 'audit' AND `p`.`action` IN ('view', 'export')) OR
    (`p`.`module` = 'security' AND `p`.`action` = 'view') OR
    (`p`.`module` = 'backup' AND `p`.`action` = 'view') OR
    (`p`.`module` = 'system' AND `p`.`action` = 'health.view')
)
WHERE `r`.`slug` = 'auditor'
  AND NOT EXISTS (
      SELECT 1 FROM `role_permissions` `rp`
      WHERE `rp`.`role_id` = `r`.`id` AND `rp`.`permission_id` = `p`.`id`
  );

-- 5.4 executive & manager (Executive Oversight — read-only summary metrics; no PII/security/mutation access)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT `r`.`id`, `p`.`id`
FROM `roles` `r`
JOIN `permissions` `p` ON (
    (`p`.`module` = 'dashboard' AND `p`.`action` = 'view') OR
    (`p`.`module` = 'analytics' AND `p`.`action` IN ('view', 'visitors.view')) OR
    (`p`.`module` = 'finance' AND `p`.`action` = 'view')
)
WHERE `r`.`slug` IN ('executive', 'manager')
  AND NOT EXISTS (
      SELECT 1 FROM `role_permissions` `rp`
      WHERE `rp`.`role_id` = `r`.`id` AND `rp`.`permission_id` = `p`.`id`
  );

-- 5.5 finance (Financial Administrator — rates/products draft update & finance view/export; no self-publish)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT `r`.`id`, `p`.`id`
FROM `roles` `r`
JOIN `permissions` `p` ON (
    (`p`.`module` = 'dashboard' AND `p`.`action` = 'view') OR
    (`p`.`module` = 'analytics' AND `p`.`action` = 'view') OR
    (`p`.`module` = 'finance' AND `p`.`action` IN ('view', 'export')) OR
    (`p`.`module` = 'rates' AND `p`.`action` IN ('view', 'update')) OR
    (`p`.`module` = 'products' AND `p`.`action` IN ('view', 'update'))
)
WHERE `r`.`slug` = 'finance'
  AND NOT EXISTS (
      SELECT 1 FROM `role_permissions` `rp`
      WHERE `rp`.`role_id` = `r`.`id` AND `rp`.`permission_id` = `p`.`id`
  );

-- 5.6 loan_officer (Loan & Accounting Officer — products draft update & rates/finance view)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT `r`.`id`, `p`.`id`
FROM `roles` `r`
JOIN `permissions` `p` ON (
    (`p`.`module` = 'dashboard' AND `p`.`action` = 'view') OR
    (`p`.`module` = 'finance' AND `p`.`action` = 'view') OR
    (`p`.`module` = 'rates' AND `p`.`action` = 'view') OR
    (`p`.`module` = 'products' AND `p`.`action` IN ('view', 'update'))
)
WHERE `r`.`slug` = 'loan_officer'
  AND NOT EXISTS (
      SELECT 1 FROM `role_permissions` `rp`
      WHERE `rp`.`role_id` = `r`.`id` AND `rp`.`permission_id` = `p`.`id`
  );

-- 5.7 member_admin (Member Administrator — strictly scoped to members & dividends)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT `r`.`id`, `p`.`id`
FROM `roles` `r`
JOIN `permissions` `p` ON (
    (`p`.`module` = 'members' AND `p`.`action` IN ('view', 'update', 'import')) OR
    (`p`.`module` = 'dividends' AND `p`.`action` IN ('update', 'import'))
)
WHERE `r`.`slug` = 'member_admin'
  AND NOT EXISTS (
      SELECT 1 FROM `role_permissions` `rp`
      WHERE `rp`.`role_id` = `r`.`id` AND `rp`.`permission_id` = `p`.`id`
  );

-- 5.8 pr_officer (Content Administrator — news, FAQs, coop_info & people draft/content management)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT `r`.`id`, `p`.`id`
FROM `roles` `r`
JOIN `permissions` `p` ON (
    (`p`.`module` = 'content' AND `p`.`action` IN ('create', 'publish')) OR
    (`p`.`module` = 'news' AND `p`.`action` IN ('view', 'create', 'update', 'publish')) OR
    (`p`.`module` = 'faqs' AND `p`.`action` IN ('view', 'create', 'update')) OR
    (`p`.`module` = 'coop_info' AND `p`.`action` IN ('view', 'update')) OR
    (`p`.`module` = 'people' AND `p`.`action` IN ('view', 'update'))
)
WHERE `r`.`slug` = 'pr_officer'
  AND NOT EXISTS (
      SELECT 1 FROM `role_permissions` `rp`
      WHERE `rp`.`role_id` = `r`.`id` AND `rp`.`permission_id` = `p`.`id`
  );

-- 5.9 staff (Operational Staff Viewer — dashboard view only; zero administrative mutation rights)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT `r`.`id`, `p`.`id`
FROM `roles` `r`
JOIN `permissions` `p` ON (
    `p`.`module` = 'dashboard' AND `p`.`action` = 'view'
)
WHERE `r`.`slug` = 'staff'
  AND NOT EXISTS (
      SELECT 1 FROM `role_permissions` `rp`
      WHERE `rp`.`role_id` = `r`.`id` AND `rp`.`permission_id` = `p`.`id`
  );
