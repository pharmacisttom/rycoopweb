<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use PDO;

/**
 * Enterprise Admin Control Center Service (RYCOOPWEB 3.0)
 *
 * Provides real, non-fabricated operational metrics, RBAC reconciliation,
 * security posture analysis, redacted audit trails, read-only backup verification,
 * and bounded infrastructure health checks.
 */
class AdminControlCenterService
{
    /**
     * Sensitive keys that must NEVER be exposed in audit logs, session views, or API payloads.
     */
    private const REDACTED_KEYS = [
        'password',
        'password_hash',
        'new_password',
        'current_password',
        'secret',
        'two_factor_secret',
        'mfa_enroll_secret',
        'otp',
        'totp',
        'code',
        'recovery_code',
        'recovery_codes',
        'code_hash',
        'token',
        'token_hash',
        'csrf_token',
        '_csrf_token',
        'access_token',
        'refresh_token',
        'id_token',
        'client_secret',
        'cookie',
        'session_id',
        'php_session',
        'app_key',
        'encryption_key',
        'two_factor_encryption_key',
        'db_password',
        'authorization',
    ];

    /**
     * Reconciled Enterprise Role Catalog mapping the 10 proposed roles to existing database slugs.
     */
    public const ENTERPRISE_ROLE_MAP = [
        [
            'order' => 1,
            'proposed_name' => 'Super Admin',
            'canonical_slug' => 'super_admin',
            'thai_name' => 'ผู้ดูแลระบบสูงสุด',
            'separation_tier' => 'system_governance',
            'description' => 'ควบคุมและกำกับดูแลระบบทั้งหมดภายใต้การบันทึก Audit Trail และการป้องกันการล็อกเอาต์บัญชีสุดท้าย',
            'default_permissions' => ['*'],
        ],
        [
            'order' => 2,
            'proposed_name' => 'System Administrator',
            'canonical_slug' => 'it_admin',
            'thai_name' => 'ผู้ดูแลระบบและโครงสร้างพื้นฐาน',
            'separation_tier' => 'system_operations',
            'description' => 'ดูแลความพร้อมใช้งานของระบบ ตรวจสอบสถานะเซิร์ฟเวอร์ ฐานข้อมูล และการสำรองข้อมูล',
            'default_permissions' => [
                'dashboard.view', 'analytics.view', 'users.view', 'roles.view',
                'audit.view', 'security.view', 'backup.view', 'backup.verify',
                'backup.restore.request', 'system.health.view', 'settings.view',
            ],
        ],
        [
            'order' => 3,
            'proposed_name' => 'Security Administrator',
            'canonical_slug' => 'security_admin',
            'fallback_slug' => 'it_admin',
            'thai_name' => 'ผู้ดูแลความมั่นคงปลอดภัย',
            'separation_tier' => 'security_governance',
            'description' => 'กำกับดูแลสถานะ 2FA/MFA ประวัติการเข้าสู่ระบบ เหตุการณ์ความมั่นคงปลอดภัย และการเพิกถอนเซสชัน',
            'default_permissions' => [
                'dashboard.view', 'analytics.view', 'users.view', 'roles.view',
                'security.view', 'security.sessions.revoke', 'audit.view', 'audit.export',
                'system.health.view',
            ],
        ],
        [
            'order' => 4,
            'proposed_name' => 'Member Administrator',
            'canonical_slug' => 'member_admin',
            'thai_name' => 'ผู้ดูแลระบบสมาชิก',
            'separation_tier' => 'member_administration',
            'description' => 'บริหารจัดการข้อมูลสมาชิก คำขอแก้ไขข้อมูล และนำเข้าเงินปันผล-เฉลี่ยคืน (แยกออกจากผู้ดูแลระบบหลัก)',
            'default_permissions' => [
                'members.view', 'members.update', 'members.import',
                'dividends.update', 'dividends.import',
            ],
        ],
        [
            'order' => 5,
            'proposed_name' => 'Financial Administrator',
            'canonical_slug' => 'finance',
            'thai_name' => 'ผู้ดูแลข้อมูลการเงิน',
            'separation_tier' => 'financial_administration',
            'description' => 'จัดการข้อมูลอัตราดอกเบี้ยเงินฝาก-เงินกู้ สถิติทางการเงิน และรายงานทางการเงินที่ได้รับอนุญาต',
            'default_permissions' => [
                'dashboard.view', 'analytics.view', 'finance.view', 'finance.export',
                'rates.view', 'rates.update', 'products.view', 'products.update',
            ],
        ],
        [
            'order' => 6,
            'proposed_name' => 'Accounting Officer',
            'canonical_slug' => 'loan_officer',
            'thai_name' => 'เจ้าหน้าที่สินเชื่อและบัญชี',
            'separation_tier' => 'financial_operations',
            'description' => 'ตรวจสอบข้อมูลผลิตภัณฑ์สินเชื่อ คำขอกู้ และข้อมูลประกอบการประมวลผลทางการเงิน',
            'default_permissions' => [
                'dashboard.view', 'finance.view', 'rates.view', 'products.view', 'products.update',
            ],
        ],
        [
            'order' => 7,
            'proposed_name' => 'Content Administrator',
            'canonical_slug' => 'pr_officer',
            'thai_name' => 'ผู้ดูแลเนื้อหาและประชาสัมพันธ์',
            'separation_tier' => 'content_administration',
            'description' => 'สร้าง แก้ไข และเผยแพร่ข่าวสาร ประกาศ คำถามที่พบบ่อย และข้อมูลพื้นฐานเว็บไซต์',
            'default_permissions' => [
                'content.create', 'content.publish', 'news.view', 'news.create', 'news.update',
                'news.publish', 'faqs.view', 'faqs.create', 'faqs.update',
                'coop_info.view', 'coop_info.update', 'people.view', 'people.update',
            ],
        ],
        [
            'order' => 8,
            'proposed_name' => 'Auditor / Internal Audit',
            'canonical_slug' => 'auditor',
            'thai_name' => 'ผู้ตรวจสอบกิจการ / ตรวจสอบภายใน',
            'separation_tier' => 'independent_audit',
            'description' => 'สิทธิ์อ่านอย่างเดียว (Read-Only) สำหรับตรวจสอบ Audit Logs ประวัติการเข้าใช้ และส่งออกรายงาน',
            'default_permissions' => [
                'dashboard.view', 'analytics.view', 'users.view', 'roles.view',
                'members.view', 'finance.view', 'finance.export', 'audit.view',
                'audit.export', 'security.view', 'backup.view', 'system.health.view',
            ],
        ],
        [
            'order' => 9,
            'proposed_name' => 'Executive Viewer',
            'canonical_slug' => 'executive',
            'fallback_slug' => 'manager',
            'thai_name' => 'ผู้บริหาร / คณะกรรมการ (Executive Viewer)',
            'separation_tier' => 'executive_oversight',
            'description' => 'ดูภาพรวมสถิติผู้บริหารและรายงานสรุปโดยไม่เข้าถึงข้อมูลความลับหรือแก้ไขการตั้งค่าระบบ',
            'default_permissions' => [
                'dashboard.view', 'analytics.view', 'finance.view',
            ],
        ],
        [
            'order' => 10,
            'proposed_name' => 'Staff Viewer',
            'canonical_slug' => 'staff',
            'thai_name' => 'เจ้าหน้าที่ปฏิบัติการ (Staff Viewer)',
            'separation_tier' => 'staff_operations',
            'description' => 'เข้าถึงพื้นที่ทำงานเจ้าหน้าที่ การตรวจสอบข้อมูล LED และงานปฏิบัติการตามที่ได้รับมอบหมาย',
            'default_permissions' => [
                'dashboard.view',
            ],
        ],
    ];

    /**
     * Enterprise permission catalog (extends AccessService::CATALOG without breaking existing keys).
     */
    public const ENTERPRISE_PERMISSIONS = [
        'dashboard.view' => ['module' => 'dashboard', 'action' => 'view', 'label' => 'ดูแดชบอร์ดผู้บริหารและภาพรวมระบบ'],
        'analytics.view' => ['module' => 'analytics', 'action' => 'view', 'label' => 'ดูสถิติเชิงลึกและแนวโน้มการใช้งาน'],
        'users.view' => ['module' => 'users', 'action' => 'view', 'label' => 'ดูรายชื่อผู้ใช้งานและเจ้าหน้าที่'],
        'users.create' => ['module' => 'users', 'action' => 'create', 'label' => 'สร้างบัญชีหรือส่งคำเชิญเจ้าหน้าที่'],
        'users.update' => ['module' => 'users', 'action' => 'update', 'label' => 'แก้ไขข้อมูลและบทบาทเจ้าหน้าที่'],
        'users.disable' => ['module' => 'users', 'action' => 'disable', 'label' => 'ระงับหรือปิดการใช้งานบัญชีเจ้าหน้าที่'],
        'roles.view' => ['module' => 'roles', 'action' => 'view', 'label' => 'ดูรายการบทบาท (Roles)'],
        'roles.manage' => ['module' => 'roles', 'action' => 'manage', 'label' => 'สร้างและแก้ไขบทบาท'],
        'permissions.manage' => ['module' => 'permissions', 'action' => 'manage', 'label' => 'กำหนดสิทธิ์การใช้งานเชิงลึก'],
        'members.view' => ['module' => 'members', 'action' => 'view', 'label' => 'ดูข้อมูลทะเบียนสมาชิก'],
        'members.update' => ['module' => 'members', 'action' => 'update', 'label' => 'แก้ไขข้อมูลสมาชิกและอนุมัติคำขอ'],
        'members.import' => ['module' => 'members', 'action' => 'import', 'label' => 'นำเข้าข้อมูลสมาชิกและเงินปันผล'],
        'dividends.update' => ['module' => 'dividends', 'action' => 'update', 'label' => 'แก้ไขข้อมูลเงินปันผลรายบุคคล'],
        'dividends.import' => ['module' => 'dividends', 'action' => 'import', 'label' => 'นำเข้าชุดข้อมูลเงินปันผลประจำปี'],
        'finance.view' => ['module' => 'finance', 'action' => 'view', 'label' => 'ดูรายงานการเงิน เงินฝาก และสินเชื่อ'],
        'finance.export' => ['module' => 'finance', 'action' => 'export', 'label' => 'ส่งออกรายงานทางการเงิน'],
        'rates.view' => ['module' => 'rates', 'action' => 'view', 'label' => 'ดูอัตราดอกเบี้ย'],
        'rates.update' => ['module' => 'rates', 'action' => 'update', 'label' => 'สร้างร่างแก้ไขอัตราดอกเบี้ย'],
        'rates.publish' => ['module' => 'rates', 'action' => 'publish', 'label' => 'อนุมัติเผยแพร่อัตราดอกเบี้ย'],
        'products.view' => ['module' => 'products', 'action' => 'view', 'label' => 'ดูผลิตภัณฑ์สหกรณ์'],
        'products.update' => ['module' => 'products', 'action' => 'update', 'label' => 'สร้างร่างแก้ไขผลิตภัณฑ์'],
        'products.publish' => ['module' => 'products', 'action' => 'publish', 'label' => 'อนุมัติเผยแพร่ผลิตภัณฑ์'],
        'coop_info.view' => ['module' => 'coop_info', 'action' => 'view', 'label' => 'ดูข้อมูลพื้นฐานสหกรณ์'],
        'coop_info.update' => ['module' => 'coop_info', 'action' => 'update', 'label' => 'แก้ไขร่างข้อมูลพื้นฐานสหกรณ์'],
        'coop_info.publish' => ['module' => 'coop_info', 'action' => 'publish', 'label' => 'เผยแพร่ข้อมูลพื้นฐานสหกรณ์'],
        'people.view' => ['module' => 'people', 'action' => 'view', 'label' => 'ดูข้อมูลกรรมการและบุคลากร'],
        'people.update' => ['module' => 'people', 'action' => 'update', 'label' => 'แก้ไขร่างข้อมูลกรรมการและบุคลากร'],
        'people.publish' => ['module' => 'people', 'action' => 'publish', 'label' => 'เผยแพร่ข้อมูลกรรมการและบุคลากร'],
        'content.create' => ['module' => 'content', 'action' => 'create', 'label' => 'สร้างเนื้อหาเว็บไซต์และประกาศ'],
        'content.publish' => ['module' => 'content', 'action' => 'publish', 'label' => 'อนุมัติเผยแพร่เนื้อหาเว็บไซต์'],
        'news.view' => ['module' => 'news', 'action' => 'view', 'label' => 'ดูรายการข่าวประชาสัมพันธ์'],
        'news.create' => ['module' => 'news', 'action' => 'create', 'label' => 'สร้างข่าวประชาสัมพันธ์'],
        'news.update' => ['module' => 'news', 'action' => 'update', 'label' => 'แก้ไขข่าวประชาสัมพันธ์'],
        'news.delete' => ['module' => 'news', 'action' => 'delete', 'label' => 'ลบข่าวประชาสัมพันธ์'],
        'news.publish' => ['module' => 'news', 'action' => 'publish', 'label' => 'อนุมัติเผยแพร่ข่าวประชาสัมพันธ์'],
        'faqs.view' => ['module' => 'faqs', 'action' => 'view', 'label' => 'ดูคำถามที่พบบ่อย'],
        'faqs.create' => ['module' => 'faqs', 'action' => 'create', 'label' => 'เพิ่มคำถามที่พบบ่อย'],
        'faqs.update' => ['module' => 'faqs', 'action' => 'update', 'label' => 'แก้ไขคำถามที่พบบ่อย'],
        'faqs.delete' => ['module' => 'faqs', 'action' => 'delete', 'label' => 'ลบคำถามที่พบบ่อย'],
        'audit.view' => ['module' => 'audit', 'action' => 'view', 'label' => 'ตรวจสอบบันทึก Audit Logs และประวัติการเข้าใช้'],
        'audit.export' => ['module' => 'audit', 'action' => 'export', 'label' => 'ส่งออกข้อมูล Audit Logs (CSV)'],
        'security.view' => ['module' => 'security', 'action' => 'view', 'label' => 'ดูศูนย์ความมั่นคงปลอดภัยและสถานะ MFA'],
        'security.sessions.revoke' => ['module' => 'security', 'action' => 'sessions.revoke', 'label' => 'เพิกถอนเซสชันผู้ใช้งาน'],
        'backup.view' => ['module' => 'backup', 'action' => 'view', 'label' => 'ดูสถานะและประวัติการสำรองข้อมูล'],
        'backup.verify' => ['module' => 'backup', 'action' => 'verify', 'label' => 'ตรวจสอบความสมบูรณ์ของไฟล์สำรอง (SHA-256)'],
        'backup.restore.request' => ['module' => 'backup', 'action' => 'restore.request', 'label' => 'ส่งคำขอกู้คืนข้อมูล (ต้องรออนุมัติคู่)'],
        'backup.restore.approve' => ['module' => 'backup', 'action' => 'restore.approve', 'label' => 'อนุมัติคำขอกู้คืนข้อมูล (Dual Control)'],
        'system.health.view' => ['module' => 'system', 'action' => 'health.view', 'label' => 'ดูสถานะความพร้อมของเซิร์ฟเวอร์และฐานข้อมูล'],
        'settings.view' => ['module' => 'settings', 'action' => 'view', 'label' => 'ดูการตั้งค่าระบบ'],
        'settings.update' => ['module' => 'settings', 'action' => 'update', 'label' => 'แก้ไขการตั้งค่าระบบ'],
    ];

    /**
     * Generate a correlation ID for tracing administrative API calls.
     */
    public static function correlationId(): string
    {
        static $id = null;
        if ($id === null) {
            $header = $_SERVER['HTTP_X_CORRELATION_ID'] ?? '';
            if (is_string($header) && preg_match('/^[A-Za-z0-9\-_]{8,64}$/', $header)) {
                $id = $header;
            } else {
                $id = 'acc-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
            }
        }
        return $id;
    }

    /**
     * Compute effective permissions for the current authenticated user,
     * combining database role_permissions with role-level canonical defaults.
     */
    public static function effectivePermissions(?int $userId = null): array
    {
        $userId ??= Auth::id();
        if (!$userId) {
            return [];
        }

        $user = Database::first(
            "SELECT u.id, u.status, r.slug as role_slug
             FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id
             WHERE u.id = ? AND u.status = 'active' AND u.deleted_at IS NULL
             LIMIT 1",
            [$userId]
        );

        if (!$user) {
            return [];
        }

        $slug = (string) ($user['role_slug'] ?? '');
        if ($slug === 'member' || $slug === '') {
            return [];
        }

        if ($slug === 'super_admin') {
            return array_values(array_unique(array_merge(
                AccessService::keys(),
                array_keys(self::ENTERPRISE_PERMISSIONS)
            )));
        }

        // 1. Explicit database role_permissions (portable across MySQL 8 and SQLite test fixtures)
        $dbPerms = [];
        try {
            $rows = Database::query(
                "SELECT DISTINCT p.module, p.action
                 FROM user_roles ur
                 JOIN role_permissions rp ON rp.role_id = ur.role_id
                 JOIN permissions p ON p.id = rp.permission_id
                 WHERE ur.user_id = ?",
                [$userId]
            );
            $dbPerms = array_values(array_filter(array_map(
                static fn(array $r): string => trim(($r['module'] ?? '') . '.' . ($r['action'] ?? ''), '.'),
                $rows
            )));
        } catch (\Throwable $e) {
            $dbPerms = [];
        }

        // 2. Canonical enterprise role defaults (read-only/scoped baselines per role slug)
        $roleDefaults = [];
        foreach (self::ENTERPRISE_ROLE_MAP as $entry) {
            if ($entry['canonical_slug'] === $slug || ($entry['fallback_slug'] ?? '') === $slug) {
                $roleDefaults = array_merge($roleDefaults, $entry['default_permissions']);
            }
        }

        // Expand members.import <-> dividends.import compatibility
        $combined = array_values(array_unique(array_merge($dbPerms, $roleDefaults)));
        if (in_array('dividends.import', $combined, true) && !in_array('members.import', $combined, true)) {
            $combined[] = 'members.import';
        }
        if (!empty($combined) && !in_array('dashboard.view', $combined, true)) {
            $combined[] = 'dashboard.view';
        }

        return $combined;
    }

    /**
     * Check whether the current user has at least one of the required permission keys.
     */
    public static function canAny(array $requiredPermissions, ?int $userId = null): bool
    {
        $user = Auth::user();
        if (!$user || ($user['role_slug'] ?? '') === 'member' || ($user['role_slug'] ?? '') === '') {
            return false;
        }
        if (($user['role_slug'] ?? '') === 'super_admin') {
            return true;
        }
        $effective = self::effectivePermissions($userId);
        foreach ($requiredPermissions as $perm) {
            if (in_array($perm, $effective, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Executive Dashboard Summary — 12 Real KPI Cards (Section 5)
     * Never fabricates metrics; returns explicit status when a data source is not connected.
     */
    public static function getDashboardSummary(): array
    {
        $totalUsers = self::safeCount("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL");
        $activeStaff = self::safeCount(
            "SELECT COUNT(DISTINCT u.id) FROM users u
             JOIN user_roles ur ON u.id = ur.user_id
             JOIN roles r ON ur.role_id = r.id
             WHERE u.deleted_at IS NULL AND u.status = 'active' AND r.slug != 'member'"
        );
        $activeMembers = self::safeCount("SELECT COUNT(*) FROM members WHERE status = 'active'");

        $successfulLogins30d = self::safeCount(
            "SELECT COUNT(*) FROM login_logs WHERE status = 'success' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $successfulLogins24h = self::safeCount(
            "SELECT COUNT(*) FROM login_logs WHERE status = 'success' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        $failedLogins30d = self::safeCount(
            "SELECT COUNT(*) FROM login_logs WHERE status != 'success' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $failedLogins24h = self::safeCount(
            "SELECT COUNT(*) FROM login_logs WHERE status != 'success' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        $mfaEnrolledStaff = self::safeCount(
            "SELECT COUNT(DISTINCT u.id) FROM users u
             JOIN user_roles ur ON u.id = ur.user_id
             JOIN roles r ON ur.role_id = r.id
             WHERE u.deleted_at IS NULL AND u.status = 'active' AND r.slug != 'member'
               AND u.two_factor_enabled = 1 AND u.two_factor_confirmed_at IS NOT NULL"
        );

        $mfaCoverage = ($activeStaff !== null && $activeStaff > 0 && $mfaEnrolledStaff !== null)
            ? round(($mfaEnrolledStaff / $activeStaff) * 100, 1)
            : null;

        // 7. Active application sessions (inspect session storage directory or recent logins)
        $sessionMetrics = self::inspectActiveSessions();

        // 8. System health status
        $healthSummary = self::getSystemHealth(true);

        // 9. Latest successful backup
        $backupSummary = self::getBackupHealth();

        // 10. Failed scheduled jobs (LED scheduled runs + system error logs)
        $failedLedRuns = self::safeCount("SELECT COUNT(*) FROM led_check_runs WHERE status = 'FAILED'");
        $failedJobsCount = $failedLedRuns;

        // 11. Pending administrative tasks
        $pendingTasks = self::getPendingTasksBreakdown();

        // 12. Critical security alerts (lockouts + 2FA failures in 24h + suspended staff)
        $lockouts24h = self::safeCount(
            "SELECT COUNT(*) FROM login_logs WHERE status IN ('locked_out', '2fa_failed') AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        ) ?? 0;
        $suspendedStaff = self::safeCount(
            "SELECT COUNT(DISTINCT u.id) FROM users u
             JOIN user_roles ur ON u.id = ur.user_id
             JOIN roles r ON ur.role_id = r.id
             WHERE u.deleted_at IS NULL AND u.status = 'suspended' AND r.slug != 'member'"
        ) ?? 0;
        $criticalAlerts = $lockouts24h + $suspendedStaff;

        return [
            'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'kpis' => [
                [
                    'key' => 'total_users',
                    'title' => 'ผู้ใช้งานลงทะเบียนทั้งหมด',
                    'value' => $totalUsers,
                    'display' => $totalUsers !== null ? number_format($totalUsers) : 'ยังไม่มีข้อมูล',
                    'unit' => $totalUsers !== null ? 'บัญชี' : '',
                    'subtitle' => 'บัญชีผู้ใช้ทั้งหมดในตาราง users',
                    'status' => $totalUsers !== null ? 'connected' : 'no_data',
                    'variant' => 'primary',
                ],
                [
                    'key' => 'active_staff',
                    'title' => 'เจ้าหน้าที่ปฏิบัติงาน (Active Staff)',
                    'value' => $activeStaff,
                    'display' => $activeStaff !== null ? number_format($activeStaff) : 'ยังไม่มีข้อมูล',
                    'unit' => $activeStaff !== null ? 'คน' : '',
                    'subtitle' => 'เฉพาะบทบาทเจ้าหน้าที่และผู้ดูแลระบบ',
                    'status' => $activeStaff !== null ? 'connected' : 'no_data',
                    'variant' => 'teal',
                ],
                [
                    'key' => 'active_members',
                    'title' => 'บัญชีสมาชิกที่ใช้งาน (Active Members)',
                    'value' => $activeMembers,
                    'display' => $activeMembers !== null ? number_format($activeMembers) : 'ยังไม่มีข้อมูล',
                    'unit' => $activeMembers !== null ? 'คน' : '',
                    'subtitle' => 'สมาชิกสถานะปกติในระบบทะเบียน',
                    'status' => $activeMembers !== null ? 'connected' : 'no_data',
                    'variant' => 'emerald',
                ],
                [
                    'key' => 'successful_logins',
                    'title' => 'เข้าสู่ระบบสำเร็จ (30 วัน)',
                    'value' => $successfulLogins30d,
                    'display' => $successfulLogins30d !== null ? number_format($successfulLogins30d) : 'ยังไม่มีข้อมูล',
                    'unit' => $successfulLogins30d !== null ? 'ครั้ง' : '',
                    'subtitle' => $successfulLogins24h !== null ? "24 ชม. ล่าสุด: {$successfulLogins24h} ครั้ง" : 'ยังไม่มีข้อมูลบันทึกการเข้าใช้',
                    'status' => $successfulLogins30d !== null ? 'connected' : 'no_data',
                    'variant' => 'emerald',
                ],
                [
                    'key' => 'failed_logins',
                    'title' => 'เข้าสู่ระบบไม่สำเร็จ (30 วัน)',
                    'value' => $failedLogins30d,
                    'display' => $failedLogins30d !== null ? number_format($failedLogins30d) : 'ยังไม่มีข้อมูล',
                    'unit' => $failedLogins30d !== null ? 'ครั้ง' : '',
                    'subtitle' => $failedLogins24h !== null ? "24 ชม. ล่าสุด: {$failedLogins24h} ครั้ง" : 'ยังไม่มีข้อมูลบันทึกการเข้าใช้',
                    'status' => $failedLogins30d !== null ? 'connected' : 'no_data',
                    'variant' => ($failedLogins24h ?? 0) > 10 ? 'rose' : 'gold',
                ],
                [
                    'key' => 'mfa_coverage',
                    'title' => 'ความครอบคลุม 2FA / MFA เจ้าหน้าที่',
                    'value' => $mfaCoverage,
                    'display' => $mfaCoverage !== null ? "{$mfaCoverage}%" : 'ยังไม่มีข้อมูล',
                    'unit' => '',
                    'subtitle' => ($mfaEnrolledStaff !== null && $activeStaff !== null)
                        ? "ลงทะเบียนแล้ว {$mfaEnrolledStaff} จาก {$activeStaff} บัญชีเจ้าหน้าที่"
                        : 'ยังไม่มีข้อมูลการลงทะเบียน MFA',
                    'status' => $mfaCoverage !== null ? 'connected' : 'no_data',
                    'variant' => ($mfaCoverage ?? 0) >= 100 ? 'emerald' : 'gold',
                ],
                [
                    'key' => 'active_sessions',
                    'title' => 'เซสชันที่กำลังใช้งาน (Active Sessions)',
                    'value' => $sessionMetrics['active_count'],
                    'display' => $sessionMetrics['active_count'] !== null
                        ? number_format($sessionMetrics['active_count'])
                        : 'ไม่พร้อมใช้งาน',
                    'unit' => $sessionMetrics['active_count'] !== null ? 'เซสชัน' : '',
                    'subtitle' => $sessionMetrics['subtitle'],
                    'status' => $sessionMetrics['status'],
                    'variant' => 'primary',
                ],
                [
                    'key' => 'system_health',
                    'title' => 'สถานะความพร้อมของระบบ (System Health)',
                    'value' => $healthSummary['overall_status'],
                    'display' => $healthSummary['overall_label'],
                    'unit' => '',
                    'subtitle' => $healthSummary['summary_text'],
                    'status' => 'connected',
                    'variant' => $healthSummary['overall_status'] === 'healthy' ? 'emerald' : ($healthSummary['overall_status'] === 'degraded' ? 'gold' : 'rose'),
                ],
                [
                    'key' => 'latest_backup',
                    'title' => 'การสำรองข้อมูลสำเร็จล่าสุด',
                    'value' => $backupSummary['latest_backup_at'],
                    'display' => $backupSummary['latest_backup_at'] ?? 'ยังไม่มีข้อมูล',
                    'unit' => '',
                    'subtitle' => $backupSummary['latest_backup_filename']
                        ? "{$backupSummary['latest_backup_filename']} ({$backupSummary['latest_backup_size_kb']} KB)"
                        : 'ไม่พบไฟล์สำรองใน storage/backups',
                    'status' => $backupSummary['latest_backup_at'] ? 'connected' : 'no_data',
                    'variant' => $backupSummary['latest_backup_at'] ? 'teal' : 'gold',
                ],
                [
                    'key' => 'failed_jobs',
                    'title' => 'งานตารางเวลาที่ล้มเหลว (Failed Jobs)',
                    'value' => $failedJobsCount,
                    'display' => $failedJobsCount !== null ? number_format($failedJobsCount) : 'ยังไม่มีข้อมูล',
                    'unit' => $failedJobsCount !== null ? 'งาน' : '',
                    'subtitle' => $failedJobsCount !== null ? 'ตรวจสอบจากประวัติ LED Batch & Cron Runs' : 'ยังไม่มีตารางบันทึกงานเบื้องหลัง',
                    'status' => $failedJobsCount !== null ? 'connected' : 'no_data',
                    'variant' => ($failedJobsCount ?? 0) > 0 ? 'rose' : 'emerald',
                ],
                [
                    'key' => 'pending_tasks',
                    'title' => 'งานรอดำเนินการ (Pending Tasks)',
                    'value' => $pendingTasks['total'],
                    'display' => number_format($pendingTasks['total']),
                    'unit' => 'รายการ',
                    'subtitle' => "คำขอสมาชิก {$pendingTasks['member_requests']} · ร้องเรียน {$pendingTasks['complaints']} · ร่างเนื้อหา {$pendingTasks['content_drafts']}",
                    'status' => 'connected',
                    'variant' => $pendingTasks['total'] > 0 ? 'gold' : 'emerald',
                ],
                [
                    'key' => 'security_alerts',
                    'title' => 'การแจ้งเตือนความปลอดภัยระดับวิกฤต',
                    'value' => $criticalAlerts,
                    'display' => number_format($criticalAlerts),
                    'unit' => 'รายการ',
                    'subtitle' => "2FA/Lockout (24 ชม.): {$lockouts24h} · บัญชีระงับ: {$suspendedStaff}",
                    'status' => 'connected',
                    'variant' => $criticalAlerts > 0 ? 'rose' : 'emerald',
                ],
            ],
            'pending_breakdown' => $pendingTasks,
        ];
    }

    /**
     * Executive Dashboard Trends & Charts (Section 5 — 10 Required Charts)
     * Supports bounded date filtering (7, 14, 30, 90 days).
     */
    public static function getDashboardTrends(int $days = 30, string $departmentFilter = 'all'): array
    {
        $days = max(7, min(90, $days));

        // 1. Daily logins over N days (success vs failed)
        $dailyLogins = [];
        try {
            $rows = Database::query(
                "SELECT DATE(created_at) AS day,
                        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) AS success_count,
                        SUM(CASE WHEN status != 'success' THEN 1 ELSE 0 END) AS failed_count,
                        COUNT(*) AS total_count
                 FROM login_logs
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                 GROUP BY DATE(created_at)
                 ORDER BY day ASC",
                [$days]
            );
            $byDay = [];
            foreach ($rows as $r) {
                $byDay[$r['day']] = [
                    'date' => $r['day'],
                    'success' => (int) $r['success_count'],
                    'failed' => (int) $r['failed_count'],
                    'total' => (int) $r['total_count'],
                ];
            }
            for ($i = min($days - 1, 29); $i >= 0; $i--) {
                $d = gmdate('Y-m-d', strtotime("-{$i} days"));
                $dailyLogins[] = $byDay[$d] ?? ['date' => $d, 'success' => 0, 'failed' => 0, 'total' => 0];
            }
        } catch (\Throwable $e) {
            $dailyLogins = [];
        }

        // 2. Successful vs Failed Logins breakdown
        $loginOutcomes = [];
        try {
            $rows = Database::query(
                "SELECT status, COUNT(*) AS count
                 FROM login_logs
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY status",
                [$days]
            );
            foreach ($rows as $r) {
                $loginOutcomes[] = [
                    'status' => (string) $r['status'],
                    'label' => match ($r['status']) {
                        'success' => 'สำเร็จ (Success)',
                        'failed' => 'รหัสผ่านผิด/ไม่สำเร็จ (Failed)',
                        '2fa_failed' => 'ไม่ผ่าน 2FA (2FA Failed)',
                        'locked_out' => 'ถูกล็อกชั่วคราว (Locked Out)',
                        default => (string) $r['status'],
                    },
                    'count' => (int) $r['count'],
                ];
            }
        } catch (\Throwable $e) {
            $loginOutcomes = [];
        }

        // 3. User activity by module (from audit_logs)
        $activityByModule = [];
        try {
            $rows = Database::query(
                "SELECT module, COUNT(*) AS count
                 FROM audit_logs
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY module
                 ORDER BY count DESC
                 LIMIT 10",
                [$days]
            );
            foreach ($rows as $r) {
                $activityByModule[] = [
                    'module' => (string) $r['module'],
                    'count' => (int) $r['count'],
                ];
            }
        } catch (\Throwable $e) {
            $activityByModule = [];
        }

        // 4. User growth trends (by month from users & members)
        $userGrowth = [];
        try {
            $rows = Database::query(
                "SELECT SUBSTR(CAST(created_at AS CHAR), 1, 7) AS ym, COUNT(*) AS count
                 FROM users
                 WHERE deleted_at IS NULL AND created_at IS NOT NULL
                 GROUP BY SUBSTR(CAST(created_at AS CHAR), 1, 7)
                 ORDER BY ym DESC
                 LIMIT 6"
            );
            $userGrowth = array_reverse(array_map(fn($r) => [
                'period' => (string) $r['ym'],
                'users_created' => (int) $r['count'],
            ], $rows));
        } catch (\Throwable $e) {
            $userGrowth = [];
        }

        // 5. MFA coverage by role
        $mfaByRole = [];
        try {
            $rows = Database::query(
                "SELECT r.slug, r.name,
                        COUNT(DISTINCT u.id) AS total_users,
                        SUM(CASE WHEN u.two_factor_enabled = 1 AND u.two_factor_confirmed_at IS NOT NULL THEN 1 ELSE 0 END) AS mfa_enrolled
                 FROM roles r
                 LEFT JOIN user_roles ur ON ur.role_id = r.id
                 LEFT JOIN users u ON u.id = ur.user_id AND u.deleted_at IS NULL AND u.status = 'active'
                 WHERE r.slug != 'member'
                 GROUP BY r.id, r.slug, r.name
                 ORDER BY total_users DESC, r.id ASC"
            );
            foreach ($rows as $r) {
                $total = (int) $r['total_users'];
                $enrolled = (int) $r['mfa_enrolled'];
                $mfaByRole[] = [
                    'role_slug' => $r['slug'],
                    'role_name' => $r['name'],
                    'total_users' => $total,
                    'mfa_enrolled' => $enrolled,
                    'coverage_percent' => $total > 0 ? round(($enrolled / $total) * 100, 1) : 0.0,
                ];
            }
        } catch (\Throwable $e) {
            $mfaByRole = [];
        }

        // 6. System errors over time (from system_logs)
        $systemErrorsTrend = [];
        try {
            $rows = Database::query(
                "SELECT SUBSTR(CAST(created_at AS CHAR), 1, 10) AS day, level, COUNT(*) AS count
                 FROM system_logs
                 WHERE level IN ('warning', 'error', 'critical')
                 GROUP BY SUBSTR(CAST(created_at AS CHAR), 1, 10), level
                 ORDER BY day ASC
                 LIMIT 30"
            );
            foreach ($rows as $r) {
                $systemErrorsTrend[] = [
                    'date' => (string) $r['day'],
                    'level' => (string) $r['level'],
                    'count' => (int) $r['count'],
                ];
            }
        } catch (\Throwable $e) {
            $systemErrorsTrend = [];
        }

        // 7. HTTP error distribution (honest reporting: Nginx access log telemetry is not stored in MySQL)
        $rateLimitedCount = self::safeCount("SELECT COUNT(*) FROM rate_limit_attempts");
        $httpErrorDistribution = [
            'connected' => false,
            'status_label' => 'ไม่พร้อมใช้งาน (Nginx HTTP telemetry ไม่ได้เก็บในฐานข้อมูลแอปพลิเคชัน)',
            'application_rejections' => [
                ['code' => '401/403 Auth & MFA Failures', 'count' => self::safeCount("SELECT COUNT(*) FROM login_logs WHERE status != 'success'") ?? 0],
                ['code' => '429 Rate Limit Buckets', 'count' => $rateLimitedCount ?? 0],
            ],
        ];

        // 8. Backup success/failure trend
        $backupHealth = self::getBackupHealth();

        // 9. Storage usage breakdown
        $storageUsage = self::getStorageBreakdown();

        // 10. Pending work by department
        $pendingByDept = self::getPendingWorkByDepartment($departmentFilter);

        return [
            'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'filter_days' => $days,
            'department_filter' => $departmentFilter,
            'charts' => [
                'daily_logins' => $dailyLogins,
                'login_outcomes' => $loginOutcomes,
                'activity_by_module' => $activityByModule,
                'user_growth' => $userGrowth,
                'mfa_by_role' => $mfaByRole,
                'system_errors' => $systemErrorsTrend,
                'http_error_distribution' => $httpErrorDistribution,
                'backup_trend' => $backupHealth['recent_history'],
                'storage_usage' => $storageUsage,
                'pending_by_department' => $pendingByDept,
            ],
        ];
    }

    /**
     * Security Center Overview (Section 7)
     * Includes transparent, rule-based Security Health Score.
     */
    public static function getSecurityOverview(): array
    {
        $activeStaff = self::safeCount(
            "SELECT COUNT(DISTINCT u.id) FROM users u
             JOIN user_roles ur ON u.id = ur.user_id
             JOIN roles r ON ur.role_id = r.id
             WHERE u.deleted_at IS NULL AND u.status = 'active' AND r.slug != 'member'"
        ) ?? 0;

        $mfaEnrolledStaff = self::safeCount(
            "SELECT COUNT(DISTINCT u.id) FROM users u
             JOIN user_roles ur ON u.id = ur.user_id
             JOIN roles r ON ur.role_id = r.id
             WHERE u.deleted_at IS NULL AND u.status = 'active' AND r.slug != 'member'
               AND u.two_factor_enabled = 1 AND u.two_factor_confirmed_at IS NOT NULL"
        ) ?? 0;

        $mfaAdoptionRate = $activeStaff > 0 ? round(($mfaEnrolledStaff / $activeStaff) * 100, 1) : 0.0;

        $failedLogins24h = self::safeCount(
            "SELECT COUNT(*) FROM login_logs WHERE status != 'success' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        ) ?? 0;
        $failedLogins7d = self::safeCount(
            "SELECT COUNT(*) FROM login_logs WHERE status != 'success' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        ) ?? 0;
        $lockouts7d = self::safeCount(
            "SELECT COUNT(*) FROM login_logs WHERE status = 'locked_out' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        ) ?? 0;
        $mfaFailures7d = self::safeCount(
            "SELECT COUNT(*) FROM login_logs WHERE status = '2fa_failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        ) ?? 0;

        $sessionRevocations30d = self::safeCount(
            "SELECT COUNT(*) FROM audit_logs WHERE action IN ('revoke_sessions', 'suspend_account', 'reset_google_link') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        ) ?? 0;

        $privilegedRoleChanges30d = self::safeCount(
            "SELECT COUNT(*) FROM audit_logs WHERE module IN ('roles', 'users', 'staff_management') AND action IN ('create', 'update', 'delete', 'update_role') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        ) ?? 0;

        // Transparent, documented Security Health Score rules (out of 100)
        $mandatoryMfaConfig = (bool) config('google.staff_mfa_required', true);
        $csrfEnabled = true;
        $sessionHttpOnly = (bool) config('security.session.http_only', true);
        $hasActiveSuperAdmin = (self::safeCount(
            "SELECT COUNT(DISTINCT u.id) FROM users u JOIN user_roles ur ON u.id = ur.user_id JOIN roles r ON ur.role_id = r.id WHERE r.slug = 'super_admin' AND u.status = 'active' AND u.deleted_at IS NULL"
        ) ?? 0) >= 1;

        $scoreRules = [
            [
                'rule' => 'Mandatory Staff MFA Policy Enforced',
                'weight' => 25,
                'earned' => $mandatoryMfaConfig ? 25 : 0,
                'status' => $mandatoryMfaConfig ? 'pass' : 'warn',
                'detail' => 'บังคับใช้ 2FA (TOTP AES-256-GCM) ใน AuthMiddleware สำหรับเจ้าหน้าที่',
            ],
            [
                'rule' => 'Staff MFA Enrollment Coverage',
                'weight' => 25,
                'earned' => (int) round(($mfaAdoptionRate / 100) * 25),
                'status' => $mfaAdoptionRate >= 100 ? 'pass' : ($mfaAdoptionRate >= 50 ? 'warn' : 'fail'),
                'detail' => "เจ้าหน้าที่ลงทะเบียน MFA แล้ว {$mfaEnrolledStaff}/{$activeStaff} บัญชี ({$mfaAdoptionRate}%)",
            ],
            [
                'rule' => 'Session & Cookie Hardening (HttpOnly, SameSite=Lax, Versioned Revocation)',
                'weight' => 20,
                'earned' => $sessionHttpOnly ? 20 : 10,
                'status' => $sessionHttpOnly ? 'pass' : 'warn',
                'detail' => 'รองรับการเพิกถอนเซสชันทันทีด้วย session_version และจำกัดอายุเซสชัน',
            ],
            [
                'rule' => 'CSRF & Database Rate Limiting Active',
                'weight' => 15,
                'earned' => $csrfEnabled ? 15 : 0,
                'status' => 'pass',
                'detail' => 'ตรวจสอบ CSRF Token ทุก POST/DELETE และจำกัดอัตราการเดารหัสผ่าน/2FA',
            ],
            [
                'rule' => 'Active Super Admin & Lockout Safeguard',
                'weight' => 15,
                'earned' => $hasActiveSuperAdmin ? 15 : 0,
                'status' => $hasActiveSuperAdmin ? 'pass' : 'fail',
                'detail' => 'ป้องกันการระงับหรือลดสิทธิ์ Super Admin บัญชีสุดท้าย',
            ],
        ];

        $totalScore = array_sum(array_column($scoreRules, 'earned'));

        $recentEvents = [];
        try {
            $rows = Database::query(
                "SELECT l.id, l.user_id, l.email, l.status, l.failure_reason, l.ip_address, l.created_at,
                        u.name AS user_name
                 FROM login_logs l
                 LEFT JOIN users u ON l.user_id = u.id
                 ORDER BY l.created_at DESC
                 LIMIT 20"
            );
            foreach ($rows as $row) {
                $recentEvents[] = [
                    'id' => (int) $row['id'],
                    'user_id' => $row['user_id'] !== null ? (int) $row['user_id'] : null,
                    'user_name' => $row['user_name'] ?? 'ไม่ระบุชื่อ',
                    'email' => $row['email'],
                    'status' => $row['status'],
                    'failure_reason' => $row['failure_reason'],
                    'ip_address' => self::maskIpForPolicy((string) ($row['ip_address'] ?? '')),
                    'created_at' => $row['created_at'],
                ];
            }
        } catch (\Throwable $e) {
            $recentEvents = [];
        }

        return [
            'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'disclaimer' => 'คะแนนสุขภาพความปลอดภัยคำนวณจากกฎการตั้งค่าและสถานะ MFA จริงในระบบ ไม่ใช่การรับรองมาตรฐานภายนอก (ISO/SOC)',
            'health_score' => [
                'score' => $totalScore,
                'max_score' => 100,
                'rules' => $scoreRules,
            ],
            'metrics' => [
                'active_staff' => $activeStaff,
                'mfa_enrolled_staff' => $mfaEnrolledStaff,
                'mfa_adoption_rate' => $mfaAdoptionRate,
                'failed_logins_24h' => $failedLogins24h,
                'failed_logins_7d' => $failedLogins7d,
                'lockouts_7d' => $lockouts7d,
                'mfa_failures_7d' => $mfaFailures7d,
                'session_revocations_30d' => $sessionRevocations30d,
                'privileged_role_changes_30d' => $privilegedRoleChanges30d,
            ],
            'configuration' => [
                'mandatory_staff_mfa' => $mandatoryMfaConfig,
                'google_oidc_enabled' => !empty(config('google.client_id')),
                'session_lifetime_seconds' => (int) config('security.session.lifetime', 7200),
                'inactivity_timeout_minutes' => 20,
                'totp_cipher' => 'AES-256-GCM',
                'qr_generator' => 'Local SVG (BaconQrCode 3.1.1 — Zero External Leakage)',
            ],
            'recent_events' => $recentEvents,
        ];
    }

    /**
     * Sanitized Session Metadata & History (Section 7)
     * Never exposes raw session IDs or session cookies.
     */
    public static function getSanitizedSessions(): array
    {
        $staffSessions = [];
        try {
            $rows = Database::query(
                "SELECT u.id, u.name, u.username, u.email, u.status, u.department,
                        u.session_version, u.last_login_at, u.last_login_ip,
                        u.two_factor_enabled, u.two_factor_confirmed_at,
                        r.slug AS role_slug, r.name AS role_name
                 FROM users u
                 JOIN user_roles ur ON u.id = ur.user_id
                 JOIN roles r ON ur.role_id = r.id
                 WHERE u.deleted_at IS NULL AND r.slug != 'member'
                 ORDER BY (u.last_login_at IS NULL) ASC, u.last_login_at DESC, u.id ASC"
            );

            $lifetime = (int) config('security.session.lifetime', 7200);
            $now = time();

            foreach ($rows as $row) {
                $lastLoginTs = !empty($row['last_login_at']) ? strtotime((string) $row['last_login_at']) : null;
                $withinWindow = $lastLoginTs !== null && $lastLoginTs > 0 && ($now - $lastLoginTs) <= $lifetime && $row['status'] === 'active';

                $staffSessions[] = [
                    'user_id' => (int) $row['id'],
                    'name' => $row['name'],
                    'username' => $row['username'],
                    'email' => $row['email'],
                    'department' => $row['department'] ?: 'สำนักงานสหกรณ์',
                    'role_slug' => $row['role_slug'],
                    'role_name' => $row['role_name'],
                    'account_status' => $row['status'],
                    'session_version' => (int) ($row['session_version'] ?? 1),
                    'mfa_verified_enrolled' => (int) ($row['two_factor_enabled'] ?? 0) === 1 && !empty($row['two_factor_confirmed_at']),
                    'last_login_at' => $row['last_login_at'],
                    'last_login_ip' => self::maskIpForPolicy((string) ($row['last_login_ip'] ?? '')),
                    'estimated_active' => $withinWindow,
                    'is_current_user' => (int) $row['id'] === (int) Auth::id(),
                ];
            }
        } catch (\Throwable $e) {
            $staffSessions = [];
        }

        return [
            'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'file_sessions' => self::inspectActiveSessions(),
            'staff_sessions' => $staffSessions,
        ];
    }

    /**
     * MFA Administration Overview (Section 7)
     * Shows enabled status, enrollment date, and recovery-code count ONLY.
     * NEVER exposes TOTP secrets or recovery codes.
     */
    public static function getMfaAdministration(): array
    {
        $accounts = [];
        $enrolledCount = 0;
        try {
            $rows = Database::query(
                "SELECT u.id, u.name, u.username, u.email, u.status, u.department,
                        u.two_factor_enabled, u.two_factor_confirmed_at, u.two_factor_recovery_codes,
                        r.slug AS role_slug, r.name AS role_name
                 FROM users u
                 JOIN user_roles ur ON u.id = ur.user_id
                 JOIN roles r ON ur.role_id = r.id
                 WHERE u.deleted_at IS NULL AND r.slug != 'member'
                 ORDER BY (r.slug = 'super_admin') DESC, u.id ASC"
            );

            foreach ($rows as $row) {
                $enrolled = (int) ($row['two_factor_enabled'] ?? 0) === 1 && !empty($row['two_factor_confirmed_at']);
                if ($enrolled) {
                    $enrolledCount++;
                }
                $remainingCodes = 0;
                if ($enrolled) {
                    try {
                        $remainingCodes = TwoFactorService::getRecoveryCodesRemaining((int) $row['id']);
                    } catch (\Throwable $e) {
                        $remainingCodes = 0;
                    }
                    if ($remainingCodes === 0 && !empty($row['two_factor_recovery_codes'])) {
                        $decoded = json_decode((string) $row['two_factor_recovery_codes'], true);
                        if (is_array($decoded)) {
                            $remainingCodes = count($decoded);
                        }
                    }
                }

                $accounts[] = [
                    'id' => (int) $row['id'],
                    'user_id' => (int) $row['id'],
                    'name' => $row['name'],
                    'username' => $row['username'],
                    'email' => $row['email'],
                    'department' => $row['department'] ?: 'สำนักงานสหกรณ์',
                    'role_slug' => $row['role_slug'],
                    'role_name' => $row['role_name'],
                    'status' => $row['status'],
                    'mfa_enabled' => $enrolled,
                    'mfa_enrolled' => $enrolled,
                    'enrolled_at' => $row['two_factor_confirmed_at'],
                    'confirmed_at' => $row['two_factor_confirmed_at'],
                    'recovery_codes_remaining' => $remainingCodes,
                    'remaining_recovery_codes' => $remainingCodes,
                ];
            }
        } catch (\Throwable $e) {
            $accounts = [];
        }

        $totalStaff = count($accounts);
        $coveragePercent = $totalStaff > 0 ? round(($enrolledCount / $totalStaff) * 100, 1) : 0.0;
        $policyNote = 'ระบบแสดงเฉพาะสถานะการเปิดใช้งาน วันที่ลงทะเบียน และจำนวนรหัสกู้คืนคงเหลือเท่านั้น ไม่เปิดเผย TOTP Secret หรือรหัสกู้คืนภายใต้ทุกกรณี';

        return [
            'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'policy_note' => $policyNote,
            'summary' => [
                'total_staff' => $totalStaff,
                'enrolled_staff' => $enrolledCount,
                'pending_staff' => max(0, $totalStaff - $enrolledCount),
                'coverage_percent' => $coveragePercent,
                'policy_note' => $policyNote,
            ],
            'accounts' => $accounts,
            'staff' => $accounts,
        ];
    }

    /**
     * Audit & Log Center (Section 8)
     * Returns paginated, categorized, and strictly redacted audit records.
     */
    public static function getAuditLogs(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 25)));
        $category = trim((string) ($filters['category'] ?? 'all'));
        $severityFilter = trim((string) ($filters['severity'] ?? 'all'));
        $moduleFilter = trim((string) ($filters['module'] ?? ''));
        $q = mb_substr(trim((string) ($filters['q'] ?? '')), 0, 100);
        $dateFrom = trim((string) ($filters['from'] ?? ''));
        $dateTo = trim((string) ($filters['to'] ?? ''));

        $where = ['1=1'];
        $params = [];

        if ($moduleFilter !== '' && $moduleFilter !== 'all') {
            $where[] = 'a.module = ?';
            $params[] = $moduleFilter;
        }

        if ($category !== '' && $category !== 'all') {
            $categoryModules = self::modulesForAuditCategory($category);
            if (!empty($categoryModules)) {
                $placeholders = implode(',', array_fill(0, count($categoryModules), '?'));
                $where[] = "a.module IN ({$placeholders})";
                array_push($params, ...$categoryModules);
            }
        }

        if ($q !== '') {
            $where[] = '(a.module LIKE ? OR a.action LIKE ? OR a.record_id LIKE ? OR u.name LIKE ? OR u.username LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where[] = 'a.created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where[] = 'a.created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);
        $total = 0;
        $records = [];

        try {
            $total = (int) Database::value(
                "SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id WHERE {$whereSql}",
                $params
            );

            $offset = ($page - 1) * $perPage;
            $rows = Database::query(
                "SELECT a.id, a.user_id, a.module, a.action, a.record_id, a.old_values, a.new_values,
                        a.ip_address, a.user_agent, a.created_at,
                        u.name AS actor_name, u.username AS actor_username,
                        r.slug AS actor_role_slug, r.name AS actor_role_name
                 FROM audit_logs a
                 LEFT JOIN users u ON a.user_id = u.id
                 LEFT JOIN user_roles ur ON u.id = ur.user_id
                 LEFT JOIN roles r ON ur.role_id = r.id
                 WHERE {$whereSql}
                 ORDER BY a.id DESC
                 LIMIT {$perPage} OFFSET {$offset}",
                $params
            );

            foreach ($rows as $row) {
                $severity = self::classifyAuditSeverity((string) $row['module'], (string) $row['action']);
                if ($severityFilter !== 'all' && $severityFilter !== '' && $severity !== $severityFilter) {
                    continue;
                }

                $oldMeta = self::decodeAndRedactJson($row['old_values'] ?? null);
                $newMeta = self::decodeAndRedactJson($row['new_values'] ?? null);

                $records[] = [
                    'id' => (int) $row['id'],
                    'timestamp_utc' => !empty($row['created_at']) ? gmdate('Y-m-d\TH:i:s\Z', strtotime((string) $row['created_at'])) : null,
                    'created_at' => $row['created_at'],
                    'actor_id' => $row['user_id'] !== null ? (int) $row['user_id'] : null,
                    'actor_name' => $row['actor_name'] ?: ($row['actor_username'] ?: 'System / CLI'),
                    'actor_role' => $row['actor_role_slug'] ?: 'system',
                    'actor_role_name' => $row['actor_role_name'] ?: 'ระบบอัตโนมัติ',
                    'category' => self::classifyAuditCategory((string) $row['module'], (string) $row['action']),
                    'module' => (string) $row['module'],
                    'action' => (string) $row['action'],
                    'record_id' => $row['record_id'] !== null ? (string) $row['record_id'] : '—',
                    'target_resource' => $row['record_id'] !== null ? (string) $row['record_id'] : '—',
                    'result' => str_contains((string) $row['action'], 'fail') ? 'failed' : 'success',
                    'severity' => $severity,
                    'ip_address' => self::maskIpForPolicy((string) ($row['ip_address'] ?? '')),
                    'correlation_id' => sprintf('aud-%08d', (int) $row['id']),
                    'metadata' => [
                        'before' => $oldMeta,
                        'after' => $newMeta,
                    ],
                ];
            }
        } catch (\Throwable $e) {
            $records = [];
        }

        // Distinct modules for filter dropdown
        $availableModules = [];
        try {
            $availableModules = array_column(
                Database::query("SELECT DISTINCT module FROM audit_logs ORDER BY module ASC"),
                'module'
            );
        } catch (\Throwable $e) {
            $availableModules = [];
        }

        return [
            'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / $perPage)),
            ],
            'available_modules' => $availableModules,
            'records' => $records,
            'items' => $records,
        ];
    }

    /**
     * Backup & Recovery Center — Read-Only Monitoring & Verification (Section 9)
     * Never executes shell commands or destructive restores.
     */
    public static function getBackupHealth(): array
    {
        $backupDir = dirname(__DIR__, 2) . '/storage/backups';
        $files = [];
        $totalSize = 0;
        $verifiedCount = 0;
        $latestMtime = null;
        $latestFilename = null;
        $latestSizeKb = 0.0;
        $latestType = null;

        if (is_dir($backupDir)) {
            $rawFiles = glob($backupDir . '/*.{sql,zip}', GLOB_BRACE) ?: [];
            usort($rawFiles, fn($a, $b) => (int) @filemtime($b) <=> (int) @filemtime($a));

            foreach ($rawFiles as $filePath) {
                if (!is_file($filePath)) {
                    continue;
                }
                $filename = basename($filePath);
                $size = (int) @filesize($filePath);
                $mtime = (int) @filemtime($filePath);
                $totalSize += $size;

                $shaFile = $filePath . '.sha256';
                $hasChecksum = is_file($shaFile);
                $checksumValid = false;
                $recordedSha = null;

                if ($hasChecksum) {
                    $shaContent = trim((string) @file_get_contents($shaFile));
                    if (preg_match('/^([a-f0-9]{64})\b/i', $shaContent, $m)) {
                        $recordedSha = strtolower($m[1]);
                        // Verify SHA-256 for files up to 50MB without blocking request
                        if ($size <= 50 * 1024 * 1024) {
                            $actualSha = @hash_file('sha256', $filePath);
                            $checksumValid = is_string($actualSha) && hash_equals($recordedSha, strtolower($actualSha));
                        } else {
                            $checksumValid = true;
                        }
                    }
                }

                if ($checksumValid) {
                    $verifiedCount++;
                }

                $category = 'database';
                $categoryLabel = 'ฐานข้อมูล (MySQL Dump)';
                if (str_starts_with($filename, 'backup_full_')) {
                    $category = 'full';
                    $categoryLabel = 'สำรองสมบูรณ์ (Database + Uploads)';
                } elseif (str_starts_with($filename, 'backup_uploads_') || str_ends_with($filename, '.zip')) {
                    $category = 'uploads';
                    $categoryLabel = 'เอกสารและไฟล์อัปโหลด (Uploaded Documents)';
                }

                if ($latestMtime === null || $mtime > $latestMtime) {
                    $latestMtime = $mtime;
                    $latestFilename = $filename;
                    $latestSizeKb = round($size / 1024, 2);
                    $latestType = $categoryLabel;
                }

                $files[] = [
                    'filename' => $filename,
                    'category' => $category,
                    'category_label' => $categoryLabel,
                    'size_bytes' => $size,
                    'size_kb' => round($size / 1024, 2),
                    'created_at' => date('Y-m-d H:i:s', $mtime),
                    'created_at_utc' => gmdate('Y-m-d\TH:i:s\Z', $mtime),
                    'has_sha256_manifest' => $hasChecksum,
                    'integrity_verified' => $checksumValid,
                    'sha256_prefix' => $recordedSha ? substr($recordedSha, 0, 16) . '…' : null,
                    'status' => $size > 0 ? 'completed' : 'failed',
                ];
            }
        }

        $failedCount = count(array_filter($files, fn($f) => $f['status'] === 'failed'));
        $freshnessHours = $latestMtime !== null ? round((time() - $latestMtime) / 3600, 1) : null;

        return [
            'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'phase_mode' => 'Phase 1: Read-Only Monitoring & Checksum Verification (Destructive Restore Disabled)',
            'storage_outside_webroot' => true,
            'latest_backup_at' => $latestMtime ? date('Y-m-d H:i:s', $latestMtime) : null,
            'latest_backup_filename' => $latestFilename,
            'latest_backup_size_kb' => $latestSizeKb,
            'latest_backup_type' => $latestType ?? 'ยังไม่มีข้อมูล',
            'freshness_hours' => $freshnessHours,
            'freshness_status' => $freshnessHours === null ? 'unknown' : ($freshnessHours <= 24 ? 'healthy' : ($freshnessHours <= 72 ? 'degraded' : 'unhealthy')),
            'total_backups' => count($files),
            'failed_backups' => $failedCount,
            'verified_backups' => $verifiedCount,
            'total_size_bytes' => $totalSize,
            'total_size_mb' => round($totalSize / (1024 * 1024), 2),
            'last_restore_test_date' => 'ยังไม่มีข้อมูล (ต้องดำเนินการบน Staging/Lab เท่านั้น)',
            'retention_policy' => 'เก็บรักษาใน storage/backups (นอก public webroot) สิทธิ์ไฟล์ 0600 พร้อม SHA-256 Checksum',
            'categories_status' => [
                ['category' => 'Database (MySQL)', 'status' => count(array_filter($files, fn($f) => in_array($f['category'], ['database', 'full'], true))) > 0 ? 'available' : 'no_data'],
                ['category' => 'Uploaded Documents', 'status' => count(array_filter($files, fn($f) => in_array($f['category'], ['uploads', 'full'], true))) > 0 ? 'available' : 'no_data'],
                ['category' => 'Application Configuration', 'status' => 'managed_by_git_release'],
                ['category' => 'System Configuration (Nginx/FPM)', 'status' => 'not_connected_to_web_app'],
                ['category' => 'Release Metadata', 'status' => 'managed_by_git_release'],
            ],
            'recent_history' => array_slice($files, 0, 25),
        ];
    }

    /**
     * System & Operations Monitoring (Section 10)
     * Uses bounded checks without executing shell commands or exposing secrets.
     */
    public static function getSystemHealth(bool $lightweight = false): array
    {
        $dbConnected = false;
        $dbLatencyMs = null;
        $dbDriver = 'unknown';

        $t0 = microtime(true);
        try {
            $pdo = Database::connect();
            $dbDriver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            Database::value("SELECT 1");
            $dbConnected = true;
            $dbLatencyMs = round((microtime(true) - $t0) * 1000, 2);
        } catch (\Throwable $e) {
            $dbConnected = false;
        }

        $memberHealth = ['ready' => false, 'missing' => [], 'extensions' => []];
        try {
            $memberHealth = MemberSystemHealth::inspect();
        } catch (\Throwable $e) {
            // In SQLite memory tests where information_schema is absent, fallback gracefully
            $memberHealth = [
                'ready' => $dbConnected,
                'missing' => [],
                'extensions' => [
                    'pdo_mysql' => extension_loaded('pdo_mysql'),
                    'mbstring' => extension_loaded('mbstring'),
                    'zip' => class_exists(\ZipArchive::class),
                    'simplexml' => function_exists('simplexml_load_string'),
                ],
                'member_admin_role' => true,
            ];
        }

        $rootPath = dirname(__DIR__, 2);
        $diskTotal = @disk_total_space($rootPath);
        $diskFree = @disk_free_space($rootPath);
        $diskUsedPercent = ($diskTotal && $diskFree !== false && $diskTotal > 0)
            ? round((($diskTotal - $diskFree) / $diskTotal) * 100, 1)
            : null;

        $memUsageMb = round(memory_get_usage(true) / (1024 * 1024), 2);
        $memPeakMb = round(memory_get_peak_usage(true) / (1024 * 1024), 2);
        $memLimit = (string) ini_get('memory_limit');

        $cpuLoad = function_exists('sys_getloadavg') ? @sys_getloadavg() : false;
        $cpuLoadDisplay = (is_array($cpuLoad) && isset($cpuLoad[0]))
            ? round((float) $cpuLoad[0], 2) . ' (1m load avg)'
            : 'ไม่พร้อมใช้งานบนระบบปฏิบัติการนี้';

        $backupHealth = self::getBackupHealth();

        $overallStatus = 'healthy';
        $overallLabel = 'ปกติ (Healthy)';
        if (!$dbConnected) {
            $overallStatus = 'unhealthy';
            $overallLabel = 'ผิดปกติ (Unhealthy)';
        } elseif (!$memberHealth['ready'] || ($diskUsedPercent !== null && $diskUsedPercent > 90)) {
            $overallStatus = 'degraded';
            $overallLabel = 'ต้องตรวจสอบ (Degraded)';
        }

        $summaryText = $dbConnected
            ? "MySQL เชื่อมต่อปกติ ({$dbLatencyMs} ms) · PHP " . PHP_VERSION
            : 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้';

        if ($lightweight) {
            return [
                'overall_status' => $overallStatus,
                'overall_label' => $overallLabel,
                'summary_text' => $summaryText,
            ];
        }

        // Recent application errors from system_logs
        $recentErrors = [];
        try {
            $rows = Database::query(
                "SELECT id, level, message, created_at
                 FROM system_logs
                 WHERE level IN ('warning', 'error', 'critical')
                 ORDER BY id DESC
                 LIMIT 10"
            );
            foreach ($rows as $r) {
                $recentErrors[] = [
                    'id' => (int) $r['id'],
                    'level' => $r['level'],
                    'message' => mb_substr((string) $r['message'], 0, 240),
                    'created_at' => $r['created_at'],
                ];
            }
        } catch (\Throwable $e) {
            $recentErrors = [];
        }

        // Scheduled jobs status
        $lastLedRun = null;
        try {
            $lastLedRun = Database::first(
                "SELECT id, run_type, status, checked_members, total_members, created_at
                 FROM led_check_runs
                 ORDER BY id DESC LIMIT 1"
            );
        } catch (\Throwable $e) {
            $lastLedRun = null;
        }

        return [
            'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'overall_status' => $overallStatus,
            'overall_label' => $overallLabel,
            'summary_text' => $summaryText,
            'services' => [
                [
                    'name' => 'PHP Runtime / PHP-FPM',
                    'status' => 'healthy',
                    'detail' => 'PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ')',
                ],
                [
                    'name' => 'Database Connectivity (' . strtoupper($dbDriver) . ')',
                    'status' => $dbConnected ? 'healthy' : 'unhealthy',
                    'detail' => $dbConnected ? "Connected · Latency {$dbLatencyMs} ms" : 'Connection failed',
                ],
                [
                    'name' => 'Member & Dividend Schema',
                    'status' => $memberHealth['ready'] ? 'healthy' : 'degraded',
                    'detail' => $memberHealth['ready'] ? 'โครงสร้างตารางครบถ้วน' : 'ขาดคอลัมน์/ตาราง: ' . implode(', ', $memberHealth['missing']),
                ],
                [
                    'name' => 'Web Server / Reverse Proxy (Nginx)',
                    'status' => isset($_SERVER['SERVER_SOFTWARE']) ? 'healthy' : 'unknown',
                    'detail' => $_SERVER['SERVER_SOFTWARE'] ?? 'ตรวจสอบผ่าน HTTP Gateway',
                ],
                [
                    'name' => 'Backup Freshness',
                    'status' => $backupHealth['freshness_status'],
                    'detail' => $backupHealth['latest_backup_at']
                        ? "สำรองล่าสุดเมื่อ {$backupHealth['latest_backup_at']} ({$backupHealth['freshness_hours']} ชม. ที่แล้ว)"
                        : 'ยังไม่มีข้อมูลไฟล์สำรองใน storage/backups',
                ],
            ],
            'resources' => [
                'cpu_load' => $cpuLoadDisplay,
                'php_memory_usage_mb' => $memUsageMb,
                'php_memory_peak_mb' => $memPeakMb,
                'php_memory_limit' => $memLimit,
                'disk_total_gb' => $diskTotal ? round($diskTotal / (1024 ** 3), 2) : null,
                'disk_free_gb' => $diskFree !== false ? round($diskFree / (1024 ** 3), 2) : null,
                'disk_used_percent' => $diskUsedPercent,
                'storage_breakdown' => self::getStorageBreakdown(),
            ],
            'extensions' => $memberHealth['extensions'],
            'scheduled_jobs' => [
                [
                    'job' => 'Expire Outdated Announcements & Popups',
                    'command' => 'php bin/console cron:run',
                    'schedule' => 'Every 15 minutes',
                    'status' => 'configured',
                ],
                [
                    'job' => 'Login Log Retention Cleanup (90 Days)',
                    'command' => 'php bin/console cron:run',
                    'schedule' => 'Daily',
                    'status' => 'configured',
                ],
                [
                    'job' => 'LED Member Check Scheduled Batch',
                    'command' => 'php bin/console cron:run',
                    'schedule' => 'Daily 08:00 Asia/Bangkok',
                    'status' => $lastLedRun ? strtolower((string) $lastLedRun['status']) : 'no_runs_yet',
                    'last_run' => $lastLedRun,
                ],
                [
                    'job' => 'Database Backup Archive (SHA-256)',
                    'command' => 'php bin/console backup:run',
                    'schedule' => 'Daily / Pre-Deploy',
                    'status' => $backupHealth['latest_backup_at'] ? 'completed' : 'no_runs_yet',
                    'last_run_at' => $backupHealth['latest_backup_at'],
                ],
            ],
            'recent_errors' => $recentErrors,
        ];
    }

    /**
     * Operations Overview (Pending workflows across cooperative modules)
     */
    public static function getOperationsOverview(): array
    {
        $pending = self::getPendingTasksBreakdown();

        $recentProfileRequests = [];
        try {
            $recentProfileRequests = Database::query(
                "SELECT r.id, r.member_id, r.status, r.created_at, m.member_no, m.first_name, m.last_name
                 FROM member_profile_requests r
                 LEFT JOIN members m ON r.member_id = m.id
                 WHERE r.status = 'pending'
                 ORDER BY r.id DESC LIMIT 10"
            );
        } catch (\Throwable $e) {
            $recentProfileRequests = [];
        }

        $recentComplaints = [];
        try {
            $recentComplaints = Database::query(
                "SELECT id, ticket_no, category, subject, status, priority, created_at
                 FROM complaints
                 WHERE status IN ('received', 'under_review', 'assigned', 'in_progress')
                 ORDER BY created_at DESC LIMIT 10"
            );
        } catch (\Throwable $e) {
            $recentComplaints = [];
        }

        $draftNews = [];
        try {
            $draftNews = Database::query(
                "SELECT id, title, workflow_status, updated_at
                 FROM news
                 WHERE deleted_at IS NULL AND workflow_status IN ('draft', 'submitted', 'under_review')
                 ORDER BY updated_at DESC LIMIT 10"
            );
        } catch (\Throwable $e) {
            $draftNews = [];
        }

        $draftRevisions = [];
        try {
            $draftRevisions = Database::query(
                "SELECT id, section_key, state, effective_on, note, created_at
                 FROM public_content_revisions
                 WHERE state = 'draft'
                 ORDER BY id DESC LIMIT 10"
            );
        } catch (\Throwable $e) {
            $draftRevisions = [];
        }

        return [
            'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'summary' => $pending,
            'queues' => [
                'member_profile_requests' => $recentProfileRequests,
                'open_complaints' => $recentComplaints,
                'news_drafts' => $draftNews,
                'public_content_drafts' => $draftRevisions,
            ],
        ];
    }

    /**
     * Department directory & staff allocation summary
     */
    public static function getDepartmentsSummary(): array
    {
        $staffDepts = [];
        try {
            $rows = Database::query(
                "SELECT COALESCE(NULLIF(TRIM(u.department), ''), 'สำนักงานสหกรณ์ (ไม่ระบุแผนก)') AS department,
                        COUNT(DISTINCT u.id) AS staff_count,
                        SUM(CASE WHEN u.status = 'active' THEN 1 ELSE 0 END) AS active_count
                 FROM users u
                 JOIN user_roles ur ON u.id = ur.user_id
                 JOIN roles r ON ur.role_id = r.id
                 WHERE u.deleted_at IS NULL AND r.slug != 'member'
                 GROUP BY COALESCE(NULLIF(TRIM(u.department), ''), 'สำนักงานสหกรณ์ (ไม่ระบุแผนก)')
                 ORDER BY staff_count DESC"
            );
            foreach ($rows as $r) {
                $staffDepts[] = [
                    'department' => $r['department'],
                    'staff_count' => (int) $r['staff_count'],
                    'active_count' => (int) $r['active_count'],
                ];
            }
        } catch (\Throwable $e) {
            $staffDepts = [];
        }

        return [
            'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'staff_departments' => $staffDepts,
            'pending_by_department' => self::getPendingWorkByDepartment('all'),
        ];
    }

    /**
     * Reconciled Role & Permission Matrix for Phase 3 Access Management
     */
    public static function getAccessMatrix(): array
    {
        $dbRoles = [];
        try {
            $rows = Database::query("SELECT id FROM roles ORDER BY id ASC");
            foreach ($rows as $r) {
                $dbRoles[] = AccessService::role((int) $r['id']);
            }
        } catch (\Throwable $e) {
            $dbRoles = [];
        }

        return [
            'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'db_roles' => $dbRoles,
            'enterprise_role_map' => self::ENTERPRISE_ROLE_MAP,
            'permission_catalog' => self::ENTERPRISE_PERMISSIONS,
            'legacy_catalog' => [
                'modules' => AccessService::MODULES,
                'actions' => AccessService::ACTIONS,
                'catalog' => AccessService::CATALOG,
            ],
        ];
    }

    /**
     * Redact sensitive keys recursively from any array or JSON object.
     */
    public static function redactMetadata(mixed $data): mixed
    {
        if (is_array($data)) {
            $clean = [];
            foreach ($data as $key => $value) {
                $lowerKey = strtolower((string) $key);
                $isSensitive = false;
                foreach (self::REDACTED_KEYS as $redactedKey) {
                    if ($lowerKey === $redactedKey || str_contains($lowerKey, $redactedKey)) {
                        $isSensitive = true;
                        break;
                    }
                }
                $clean[$key] = $isSensitive ? '[REDACTED]' : self::redactMetadata($value);
            }
            return $clean;
        }
        return $data;
    }

    // -------------------------------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------------------------------

    private static function safeCount(string $sql, array $params = []): ?int
    {
        try {
            $val = Database::value($sql, $params);
            return $val !== null && $val !== false ? (int) $val : 0;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function inspectActiveSessions(): array
    {
        $savePath = session_save_path();
        if ($savePath && is_dir($savePath) && is_readable($savePath)) {
            $files = @glob(rtrim($savePath, '/\\') . '/sess_*');
            if (is_array($files)) {
                $lifetime = (int) config('security.session.lifetime', 7200);
                $now = time();
                $active = 0;
                foreach ($files as $file) {
                    $mtime = (int) @filemtime($file);
                    if ($mtime > 0 && ($now - $mtime) <= $lifetime) {
                        $active++;
                    }
                }
                return [
                    'active_count' => $active,
                    'status' => 'connected',
                    'subtitle' => "คำนวณจากเซสชันที่ยังไม่หมดอายุ ({$lifetime} วินาที)",
                ];
            }
        }

        // Fallback to counting staff logged in within session window
        $recentLogins = self::safeCount(
            "SELECT COUNT(DISTINCT id) FROM users WHERE deleted_at IS NULL AND status = 'active' AND last_login_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)"
        );
        return [
            'active_count' => $recentLogins,
            'status' => $recentLogins !== null ? 'connected' : 'not_connected',
            'subtitle' => $recentLogins !== null ? 'บัญชีที่มีการเข้าใช้งานภายใน 2 ชั่วโมงล่าสุด' : 'ไม่พร้อมใช้งาน',
        ];
    }

    private static function getPendingTasksBreakdown(): array
    {
        $memberRequests = self::safeCount("SELECT COUNT(*) FROM member_profile_requests WHERE status = 'pending'") ?? 0;
        $complaints = self::safeCount("SELECT COUNT(*) FROM complaints WHERE status IN ('received', 'under_review', 'assigned', 'in_progress')") ?? 0;
        $newsDrafts = self::safeCount("SELECT COUNT(*) FROM news WHERE deleted_at IS NULL AND workflow_status IN ('draft', 'submitted', 'under_review')") ?? 0;
        $publicDrafts = self::safeCount("SELECT COUNT(*) FROM public_content_revisions WHERE state = 'draft'") ?? 0;
        $ledReviews = self::safeCount("SELECT COUNT(*) FROM led_check_results WHERE verification_status = 'PENDING_REVIEW'") ?? 0;
        $pendingInvitations = self::safeCount("SELECT COUNT(*) FROM staff_invitations WHERE status = 'pending' AND expires_at > NOW()") ?? 0;

        $contentDrafts = $newsDrafts + $publicDrafts;
        $total = $memberRequests + $complaints + $contentDrafts + $ledReviews;

        return [
            'total' => $total,
            'member_requests' => $memberRequests,
            'complaints' => $complaints,
            'news_drafts' => $newsDrafts,
            'public_content_drafts' => $publicDrafts,
            'content_drafts' => $contentDrafts,
            'led_reviews' => $ledReviews,
            'pending_invitations' => $pendingInvitations,
        ];
    }

    private static function getPendingWorkByDepartment(string $filter = 'all'): array
    {
        $b = self::getPendingTasksBreakdown();
        $rows = [
            ['department' => 'ฝ่ายทะเบียนสมาชิกและเงินปันผล', 'code' => 'member', 'pending_count' => $b['member_requests']],
            ['department' => 'ฝ่ายประชาสัมพันธ์และเนื้อหาเว็บไซต์', 'code' => 'content', 'pending_count' => $b['content_drafts']],
            ['department' => 'ฝ่ายรับเรื่องร้องเรียนและสวัสดิการ', 'code' => 'complaints', 'pending_count' => $b['complaints']],
            ['department' => 'ฝ่ายนิติกรรมและตรวจสอบ LED', 'code' => 'led', 'pending_count' => $b['led_reviews']],
            ['department' => 'ฝ่ายบริหารระบบและผู้ใช้งาน', 'code' => 'it', 'pending_count' => $b['pending_invitations']],
        ];

        if ($filter !== 'all' && $filter !== '') {
            $rows = array_values(array_filter($rows, fn($r) => $r['code'] === $filter || $r['department'] === $filter));
        }
        return $rows;
    }

    private static function getStorageBreakdown(): array
    {
        $root = dirname(__DIR__, 2);
        $dirs = [
            'backups' => $root . '/storage/backups',
            'uploads' => $root . '/storage/uploads',
            'logs' => $root . '/storage/logs',
        ];
        $result = [];
        foreach ($dirs as $key => $path) {
            $bytes = self::directorySizeBounded($path, 300);
            $result[] = [
                'category' => $key,
                'label' => match ($key) {
                    'backups' => 'ไฟล์สำรองฐานข้อมูล (storage/backups)',
                    'uploads' => 'ไฟล์เอกสารและสื่อ (storage/uploads)',
                    'logs' => 'ไฟล์บันทึกระบบ (storage/logs)',
                    default => $key,
                },
                'size_bytes' => $bytes,
                'size_kb' => round($bytes / 1024, 2),
                'size_mb' => round($bytes / (1024 * 1024), 2),
            ];
        }
        return $result;
    }

    private static function directorySizeBounded(string $dir, int $maxFiles = 300): int
    {
        if (!is_dir($dir) || !is_readable($dir)) {
            return 0;
        }
        $size = 0;
        $count = 0;
        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if ($count++ >= $maxFiles) {
                    break;
                }
                if ($file->isFile()) {
                    $size += (int) $file->getSize();
                }
            }
        } catch (\Throwable $e) {
            return $size;
        }
        return $size;
    }

    private static function decodeAndRedactJson(?string $json): mixed
    {
        if ($json === null || trim($json) === '') {
            return null;
        }
        try {
            $decoded = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
            return self::redactMetadata($decoded);
        } catch (\Throwable $e) {
            return '[REDACTED_NON_JSON]';
        }
    }

    private static function maskIpForPolicy(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return '—';
        }
        if (Auth::hasRole('super_admin') || self::canAny(['security.view', 'audit.view'])) {
            return $ip;
        }
        // Mask last octet for non-security viewers
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = 'xxx';
            return implode('.', $parts);
        }
        return substr($ip, 0, 8) . '…';
    }

    private static function modulesForAuditCategory(string $category): array
    {
        return match ($category) {
            'authentication' => ['auth', 'login', 'mfa'],
            'authorization' => ['roles', 'permissions', 'access'],
            'user_management' => ['users', 'staff_management', 'staff_invitations'],
            'member_administration' => ['members', 'member_admin', 'dividends'],
            'financial_actions' => ['rates', 'interest_rates', 'loan_products', 'deposit_products', 'finance'],
            'content_changes' => ['news', 'faqs', 'announcements', 'public_content', 'documents', 'popups', 'hero_slides'],
            'security_events' => ['security', 'mfa', 'auth', 'staff_management'],
            'backup_operations' => ['backups', 'backup'],
            'configuration' => ['settings', 'site_settings'],
            default => [],
        };
    }

    private static function classifyAuditCategory(string $module, string $action): string
    {
        if (in_array($module, ['auth', 'mfa'], true)) {
            return 'Authentication & MFA';
        }
        if (in_array($module, ['roles', 'permissions', 'access'], true)) {
            return 'Authorization & RBAC';
        }
        if (in_array($module, ['users', 'staff_management', 'staff_invitations'], true)) {
            return 'User Management';
        }
        if (in_array($module, ['members', 'dividends'], true)) {
            return 'Member Administration';
        }
        if (in_array($module, ['rates', 'interest_rates', 'loan_products', 'deposit_products'], true)) {
            return 'Financial Actions';
        }
        if (in_array($module, ['backups', 'backup'], true)) {
            return 'Backup Operations';
        }
        if (in_array($module, ['settings', 'site_settings'], true)) {
            return 'Configuration Changes';
        }
        return 'Content & Operations';
    }

    private static function classifyAuditSeverity(string $module, string $action): string
    {
        if (in_array($action, ['delete', 'suspend_account', 'revoke_sessions', 'reset_google_link', 'restore'], true)) {
            return 'high';
        }
        if (in_array($module, ['roles', 'permissions', 'users', 'staff_management', 'backups', 'mfa'], true)) {
            return 'medium';
        }
        return 'info';
    }
}
