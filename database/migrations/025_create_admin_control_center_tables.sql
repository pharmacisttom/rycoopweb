-- Migration: 025_create_admin_control_center_tables.sql
-- Purpose: Additive, non-destructive MySQL 8.0 schema and RBAC permission catalog
--          for RYCOOPWEB 3.0 Enterprise Admin Control Center (14 Modules),
--          Dual-Control (Maker-Checker) approval requests, and per-user dashboard preferences.
-- Safety Guarantees:
--   * Strictly additive (CREATE TABLE IF NOT EXISTS, INSERT ... ON DUPLICATE KEY UPDATE / INSERT IGNORE)
--   * Zero modifications to users, user_roles, MFA secrets/recovery codes, or financial tables
--   * Safe to test in rycoopweb_migration_lab (--lab --dry-run / --lab) prior to rycoopweb_test

-- 1. Dual-Control (Maker-Checker) Approval Requests Table
CREATE TABLE IF NOT EXISTS approval_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_no VARCHAR(40) NOT NULL UNIQUE COMMENT 'Unique reference e.g. APR-20261028-0001',
    module VARCHAR(60) NOT NULL COMMENT 'Source module e.g. backup, roles, security, finance, members',
    action_key VARCHAR(100) NOT NULL COMMENT 'Action identifier e.g. backup.restore.request, roles.manage',
    target_type VARCHAR(80) NULL COMMENT 'Target entity type e.g. backup_archive, role, user',
    target_id VARCHAR(120) NULL COMMENT 'Target entity identifier or filename',
    payload_json JSON NULL COMMENT 'Sanitized request parameters (never contains secrets)',
    reason TEXT NOT NULL COMMENT 'Business justification provided by Maker',
    status ENUM('pending', 'approved', 'rejected', 'cancelled', 'expired') NOT NULL DEFAULT 'pending',
    requested_by INT UNSIGNED NOT NULL COMMENT 'Maker user ID (must differ from Checker on approval)',
    reviewed_by INT UNSIGNED NULL COMMENT 'Checker user ID',
    reviewed_at DATETIME NULL,
    review_note TEXT NULL,
    expires_at DATETIME NULL,
    executed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_approval_status_created (status, created_at),
    INDEX idx_approval_module_action (module, action_key),
    INDEX idx_approval_requested_by (requested_by),
    INDEX idx_approval_reviewed_by (reviewed_by),
    CONSTRAINT fk_approval_requests_maker FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_approval_requests_checker FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Admin Control Center Per-User Preferences Table
CREATE TABLE IF NOT EXISTS admin_dashboard_preferences (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    sidebar_collapsed TINYINT(1) NOT NULL DEFAULT 0,
    default_date_range VARCHAR(20) NOT NULL DEFAULT '30d',
    pinned_widgets_json JSON NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_dashboard_prefs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Seed Enterprise RBAC Roles (No-op on existing roles to preserve customized names/descriptions)
INSERT INTO roles (name, slug, description) VALUES
    ('ผู้ดูแลระบบสารสนเทศ', 'it_admin', 'ดูแลโครงสร้างพื้นฐาน สุขภาพเซิร์ฟเวอร์ การสำรองข้อมูล และผู้ใช้งาน'),
    ('ผู้ดูแลความปลอดภัยระบบ', 'security_admin', 'กำกับดูแลความปลอดภัย MFA เซสชัน สิทธิ์การใช้งาน และอนุมัติ Dual-Control'),
    ('ผู้ตรวจสอบภายใน', 'auditor', 'ตรวจสอบบันทึกกิจกรรม (Audit Log) ความปลอดภัย และรายงานย้อนหลังแบบอ่านอย่างเดียว'),
    ('คณะกรรมการดำเนินการ / ผู้บริหาร', 'executive', 'ดูภาพรวมผู้บริหาร รายงานสถิติ และผลการดำเนินงานแบบอ่านอย่างเดียว'),
    ('เจ้าหน้าที่สินเชื่อและนิติกรรม', 'loan_officer', 'ดูแลข้อมูลผลิตภัณฑ์สินเชื่อ อัตราดอกเบี้ย และตรวจสอบข้อมูลบังคับคดี (LED)'),
    ('เจ้าหน้าที่ประชาสัมพันธ์', 'pr_officer', 'จัดการข่าวสาร ประกาศ คำถามที่พบบ่อย สื่อเว็บไซต์ และบุคลากร'),
    ('เจ้าหน้าที่ทะเบียนสมาชิก', 'member_admin', 'ดูแลข้อมูลสมาชิก คำขอแก้ไขข้อมูลสมาชิก และข้อมูลปันผล-เฉลี่ยคืน')
ON DUPLICATE KEY UPDATE
    slug = VALUES(slug);

-- 4. Seed Enterprise Control Center Permissions (Additive across all 14 modules)
INSERT INTO permissions (name, module, action) VALUES
    -- Executive Dashboard & Operations
    ('ดูแดชบอร์ดศูนย์ควบคุมผู้บริหาร', 'dashboard', 'view'),
    ('ดูศูนย์ติดตามงานค้างและคำขออนุมัติ', 'operations', 'view'),
    ('ดูโครงสร้างแผนกและอัตรากำลังเจ้าหน้าที่', 'departments', 'view'),
    ('ดูรายงานสรุปประจำวันและประจำเดือน', 'reports', 'view'),

    -- Visitor Analytics & Web Traffic
    ('ดูสถิติผู้เข้าชมและภาพรวมการเข้าใช้งานเว็บไซต์', 'analytics', 'view'),
    ('ดูรายละเอียดสถิติผู้เข้าชมเว็บไซต์เชิงลึก', 'analytics.visitors', 'view'),
    ('ส่งออกรายงานสถิติผู้เข้าชมเว็บไซต์ (CSV)', 'analytics.visitors', 'export'),

    -- User, Role & Permission Administration
    ('ดูรายชื่อผู้ใช้งานและเจ้าหน้าที่', 'users', 'view'),
    ('สร้างบัญชีผู้ใช้งานและส่งคำเชิญเจ้าหน้าที่', 'users', 'create'),
    ('แก้ไขข้อมูลผู้ใช้งานและสถานะบัญชี', 'users', 'update'),
    ('ระงับหรือปิดการใช้งานบัญชีเจ้าหน้าที่', 'users', 'disable'),
    ('ดูบทบาทหน้าที่และตารางสิทธิ์ (Access Matrix)', 'roles', 'view'),
    ('จัดการบทบาทหน้าที่ของระบบ', 'roles', 'manage'),
    ('กำหนดสิทธิ์การใช้งานเชิงลึก (RBAC Permissions)', 'permissions', 'manage'),

    -- Member & Financial Services Governance
    ('นำเข้าข้อมูลสมาชิกจากไฟล์ (Batch Import)', 'members', 'import'),
    ('ดูภาพรวมข้อมูลทางการเงิน อัตราดอกเบี้ย และปันผล', 'finance', 'view'),
    ('ส่งออกรายงานข้อมูลทางการเงินและปันผล', 'finance', 'export'),

    -- Content & Public Portal Governance
    ('สร้างและแก้ไขร่างเนื้อหาเว็บไซต์', 'content', 'create'),
    ('อนุมัติและเผยแพร่เนื้อหาขึ้นสู่เว็บไซต์สาธารณะ', 'content', 'publish'),

    -- Audit Log & Security Center (MFA & Sessions)
    ('ดูบันทึกกิจกรรมและการตรวจสอบย้อนหลัง (Audit Log)', 'audit', 'view'),
    ('ส่งออกบันทึกกิจกรรมเพื่อการตรวจสอบ (Audit CSV)', 'audit', 'export'),
    ('ดูศูนย์ความปลอดภัยและเหตุการณ์ผิดปกติ', 'security', 'view'),
    ('ดูสถานะการลงทะเบียน MFA ของเจ้าหน้าที่', 'security.mfa', 'view'),
    ('สั่งยกเลิกเซสชันหรือบังคับลงทะเบียน MFA ใหม่', 'security.sessions', 'revoke'),

    -- Backup, Recovery & Dual-Control Governance
    ('ดูสถานะการสำรองข้อมูลและพื้นที่จัดเก็บ', 'backup', 'view'),
    ('ตรวจสอบความสมบูรณ์ของไฟล์สำรองข้อมูล', 'backup', 'verify'),
    ('สร้างคำขอกู้คืนข้อมูล (Maker Restore Request)', 'backup.restore', 'request'),
    ('อนุมัติคำขอกู้คืนข้อมูลแบบ Dual-Control (Checker Approve)', 'backup.restore', 'approve'),

    -- System Health & Settings
    ('ดูสถานะสุขภาพระบบและโครงสร้างพื้นฐานเชิงลึก', 'system.health', 'view'),
    ('ดูสถานะความพร้อมใช้งานของระบบ (Health Check)', 'health', 'view'),
    ('ดูการตั้งค่าระบบและนโยบายความปลอดภัย', 'settings', 'view'),
    ('แก้ไขการตั้งค่าระบบและนโยบายความปลอดภัย', 'settings', 'update')
ON DUPLICATE KEY UPDATE
    name = name;

-- 5. Additive Role-Permission Mappings (INSERT IGNORE preserves all existing role_permissions)

-- 5.1 it_admin: System health, backups, users, roles view, analytics, audit, settings
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON (
    (p.module = 'dashboard' AND p.action = 'view') OR
    (p.module = 'operations' AND p.action = 'view') OR
    (p.module = 'departments' AND p.action = 'view') OR
    (p.module = 'reports' AND p.action = 'view') OR
    (p.module = 'analytics' AND p.action = 'view') OR
    (p.module = 'analytics.visitors' AND p.action IN ('view', 'export')) OR
    (p.module = 'users' AND p.action IN ('view', 'create', 'update', 'disable')) OR
    (p.module = 'roles' AND p.action = 'view') OR
    (p.module = 'audit' AND p.action = 'view') OR
    (p.module = 'security' AND p.action = 'view') OR
    (p.module = 'security.mfa' AND p.action = 'view') OR
    (p.module = 'backup' AND p.action IN ('view', 'verify')) OR
    (p.module = 'backup.restore' AND p.action = 'request') OR
    (p.module = 'system.health' AND p.action = 'view') OR
    (p.module = 'health' AND p.action = 'view') OR
    (p.module = 'settings' AND p.action IN ('view', 'update'))
)
WHERE r.slug = 'it_admin';

-- 5.2 security_admin: Security, MFA, session revocation, roles/permissions, Dual-Control restore approval, audit
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON (
    (p.module = 'dashboard' AND p.action = 'view') OR
    (p.module = 'operations' AND p.action = 'view') OR
    (p.module = 'departments' AND p.action = 'view') OR
    (p.module = 'reports' AND p.action = 'view') OR
    (p.module = 'users' AND p.action IN ('view', 'update', 'disable')) OR
    (p.module = 'roles' AND p.action IN ('view', 'manage')) OR
    (p.module = 'permissions' AND p.action = 'manage') OR
    (p.module = 'audit' AND p.action IN ('view', 'export')) OR
    (p.module = 'security' AND p.action = 'view') OR
    (p.module = 'security.mfa' AND p.action = 'view') OR
    (p.module = 'security.sessions' AND p.action = 'revoke') OR
    (p.module = 'backup' AND p.action IN ('view', 'verify')) OR
    (p.module = 'backup.restore' AND p.action = 'approve') OR
    (p.module = 'system.health' AND p.action = 'view') OR
    (p.module = 'health' AND p.action = 'view') OR
    (p.module = 'settings' AND p.action = 'view')
)
WHERE r.slug = 'security_admin';

-- 5.3 auditor: Read-only audit, security, analytics, reports, health, finance/members view
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON (
    (p.module = 'dashboard' AND p.action = 'view') OR
    (p.module = 'operations' AND p.action = 'view') OR
    (p.module = 'departments' AND p.action = 'view') OR
    (p.module = 'reports' AND p.action = 'view') OR
    (p.module = 'analytics' AND p.action = 'view') OR
    (p.module = 'analytics.visitors' AND p.action IN ('view', 'export')) OR
    (p.module = 'users' AND p.action = 'view') OR
    (p.module = 'roles' AND p.action = 'view') OR
    (p.module = 'members' AND p.action = 'view') OR
    (p.module = 'finance' AND p.action = 'view') OR
    (p.module = 'audit' AND p.action IN ('view', 'export')) OR
    (p.module = 'security' AND p.action = 'view') OR
    (p.module = 'security.mfa' AND p.action = 'view') OR
    (p.module = 'backup' AND p.action = 'view') OR
    (p.module = 'system.health' AND p.action = 'view') OR
    (p.module = 'health' AND p.action = 'view') OR
    (p.module = 'settings' AND p.action = 'view')
)
WHERE r.slug = 'auditor';

-- 5.4 executive & manager: Executive dashboard, analytics, operations, departments, reports, finance view
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON (
    (p.module = 'dashboard' AND p.action = 'view') OR
    (p.module = 'operations' AND p.action = 'view') OR
    (p.module = 'departments' AND p.action = 'view') OR
    (p.module = 'reports' AND p.action = 'view') OR
    (p.module = 'analytics' AND p.action = 'view') OR
    (p.module = 'analytics.visitors' AND p.action IN ('view', 'export')) OR
    (p.module = 'users' AND p.action = 'view') OR
    (p.module = 'roles' AND p.action = 'view') OR
    (p.module = 'members' AND p.action = 'view') OR
    (p.module = 'finance' AND p.action = 'view') OR
    (p.module = 'content' AND p.action = 'publish') OR
    (p.module = 'audit' AND p.action = 'view') OR
    (p.module = 'security' AND p.action = 'view') OR
    (p.module = 'backup' AND p.action = 'view') OR
    (p.module = 'system.health' AND p.action = 'view') OR
    (p.module = 'health' AND p.action = 'view') OR
    (p.module = 'settings' AND p.action = 'view')
)
WHERE r.slug IN ('executive', 'manager');

-- 5.5 finance & loan_officer: Dashboard, operations, finance, members, rates, products, dividends
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON (
    (p.module = 'dashboard' AND p.action = 'view') OR
    (p.module = 'operations' AND p.action = 'view') OR
    (p.module = 'reports' AND p.action = 'view') OR
    (p.module = 'members' AND p.action = 'view') OR
    (p.module = 'finance' AND p.action IN ('view', 'export')) OR
    (p.module = 'rates' AND p.action IN ('view', 'update')) OR
    (p.module = 'products' AND p.action IN ('view', 'create', 'update'))
)
WHERE r.slug IN ('finance', 'loan_officer');

-- 5.6 member_admin: Dashboard, operations, members (view/update/import), dividends, reports
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON (
    (p.module = 'dashboard' AND p.action = 'view') OR
    (p.module = 'operations' AND p.action = 'view') OR
    (p.module = 'reports' AND p.action = 'view') OR
    (p.module = 'members' AND p.action IN ('view', 'update', 'import')) OR
    (p.module = 'dividends' AND p.action IN ('update', 'import')) OR
    (p.module = 'finance' AND p.action = 'view')
)
WHERE r.slug = 'member_admin';

-- 5.7 pr_officer & staff: Dashboard, operations, analytics view, content create/publish, news, faqs, coop_info, people
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON (
    (p.module = 'dashboard' AND p.action = 'view') OR
    (p.module = 'operations' AND p.action = 'view') OR
    (p.module = 'analytics' AND p.action = 'view') OR
    (p.module = 'analytics.visitors' AND p.action = 'view') OR
    (p.module = 'content' AND p.action IN ('create', 'publish')) OR
    (p.module = 'news' AND p.action IN ('view', 'create', 'update')) OR
    (p.module = 'faqs' AND p.action IN ('view', 'create', 'update')) OR
    (p.module = 'coop_info' AND p.action = 'view') OR
    (p.module = 'people' AND p.action = 'view')
)
WHERE r.slug IN ('pr_officer', 'staff');

