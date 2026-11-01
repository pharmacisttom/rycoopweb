<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Exceptions\SecurityGateException;
use App\Services\DualControlSecurityService;
use App\Services\MemberPiiMaskingService;
use App\Services\SecurityAuditService;
use Throwable;

class AuditLogController extends Controller
{
    private const ALLOWED_ROLES = ['super_admin', 'auditor', 'it_admin'];

    /**
     * Verify caller is an authenticated backend staff member with 2FA completed
     * and appropriate audit viewing privileges.
     */
    private function assertAuditAccess(string $action = 'view'): bool
    {
        if (!Auth::check() || !Session::has('user_id') || Session::get('2fa_verified', false) !== true) {
            if (Auth::checkPending2FA() || Auth::checkPendingMfaEnrollment()) {
                $this->response->json([
                    'success' => false,
                    'code' => 'TWO_FACTOR_REQUIRED',
                    'message' => 'จำเป็นต้องยืนยันรหัสความปลอดภัย 2FA ก่อนเข้าใช้งาน',
                ], 403);
                return false;
            }

            $this->response->json([
                'success' => false,
                'code' => 'UNAUTHORIZED',
                'message' => 'เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่',
            ], 401);
            return false;
        }

        // Member accounts are strictly forbidden from viewing audit trails
        $authContext = (string) Session::get('auth_context', '');
        $user = Auth::user();
        $roleSlug = (string) ($user['role_slug'] ?? '');

        if ($authContext === 'member' || $roleSlug === 'member') {
            SecurityAuditService::recordMemberAccessDenied(
                (int) Auth::id(),
                'member',
                $this->request->uri(),
                'Member attempted access to audit log viewer'
            );

            $this->response->json([
                'success' => false,
                'code' => 'FORBIDDEN',
                'message' => 'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้ (สำหรับเจ้าหน้าที่ที่ได้รับอนุญาตเท่านั้น)',
            ], 403);
            return false;
        }

        // Privileged role or explicit permission check
        $hasRole = in_array($roleSlug, self::ALLOWED_ROLES, true);
        $hasPerm = Auth::hasPermission('audit', $action) || Auth::hasPermission('security', 'view');

        if (!$hasRole && !$hasPerm) {
            $this->response->json([
                'success' => false,
                'code' => 'PERMISSION_DENIED',
                'message' => 'คุณไม่มีสิทธิ์เข้าถึงข้อมูลประวัติการตรวจสอบ (Permission Denied)',
            ], 403);
            return false;
        }

        return true;
    }

    /**
     * GET /admin/audit-logs
     * GET /api/admin/audit-logs
     *
     * List paginated audit logs from security_audit_logs with support for
     * filters (actor_type, event_type, result, date range, user reference)
     * and preserved legacy audit_logs access.
     */
    public function index(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, private');
        }

        if (!$this->assertAuditAccess('view')) {
            return;
        }

        $page = max(1, (int) $this->request->query('page', 1));
        $perPage = max(1, min(100, (int) $this->request->query('per_page', 25)));
        $actorType = strtolower(trim((string) $this->request->query('actor_type', 'all')));
        $eventType = trim((string) $this->request->query('event_type', ''));
        $result = strtolower(trim((string) $this->request->query('result', 'all')));
        $dateFrom = trim((string) $this->request->query('from', ''));
        $dateTo = trim((string) $this->request->query('to', ''));
        $q = mb_substr(trim((string) $this->request->query('q', '')), 0, 100);
        $userRef = trim((string) $this->request->query('user_id', ''));
        $source = strtolower(trim((string) $this->request->query('source', 'security')));

        // Build parameterized query for security_audit_logs
        $where = ['1=1'];
        $params = [];

        if (in_array($actorType, ['member', 'staff'], true)) {
            $where[] = 's.actor_type = ?';
            $params[] = $actorType;
        }

        if ($eventType !== '' && $eventType !== 'all') {
            $where[] = 's.event_type = ?';
            $params[] = $eventType;
        }

        if (in_array($result, ['success', 'failure', 'denied'], true)) {
            $where[] = 's.result = ?';
            $params[] = $result;
        }

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where[] = 's.timestamp_utc >= ?';
            $params[] = "{$dateFrom} 00:00:00";
        }

        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where[] = 's.timestamp_utc <= ?';
            $params[] = "{$dateTo} 23:59:59";
        }

        if ($userRef !== '' && ctype_digit($userRef)) {
            $where[] = 's.actor_user_id = ?';
            $params[] = (int) $userRef;
        }

        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(s.event_type LIKE ? OR s.target_resource LIKE ? OR s.correlation_id LIKE ? OR s.role LIKE ? OR u.name LIKE ? OR u.username LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        $whereSql = implode(' AND ', $where);

        $total = 0;
        $records = [];
        $stats = [];

        try {
            $countSql = "SELECT COUNT(*) FROM security_audit_logs s LEFT JOIN users u ON s.actor_user_id = u.id WHERE {$whereSql}";
            $total = (int) Database::value($countSql, $params);

            $offset = ($page - 1) * $perPage;
            $dataSql = "SELECT s.*, u.name AS actor_name, u.username AS actor_username
                        FROM security_audit_logs s
                        LEFT JOIN users u ON s.actor_user_id = u.id
                        WHERE {$whereSql}
                        ORDER BY s.id DESC
                        LIMIT {$perPage} OFFSET {$offset}";

            $rows = Database::query($dataSql, $params);

            foreach ($rows as $row) {
                $meta = json_decode((string) ($row['metadata'] ?? '{}'), true) ?: [];

                // PII Masking: strictly mask member names and citizen IDs
                $isMember = ($row['actor_type'] ?? '') === 'member';
                $actorDisplayName = $row['actor_name'] ?: ($row['actor_username'] ?: 'System / CLI');

                if ($isMember && !empty($row['actor_name'])) {
                    $actorDisplayName = MemberPiiMaskingService::maskFullName($row['actor_name']);
                }

                $targetResource = (string) ($row['target_resource'] ?? '—');
                if (preg_match('/\b\d{13}\b/', $targetResource, $matchId)) {
                    $targetResource = str_replace($matchId[0], MemberPiiMaskingService::maskCitizenId($matchId[0]), $targetResource);
                }

                $maskedMeta = SecurityAuditService::scrubMetadata($meta);

                $records[] = [
                    'id' => (int) $row['id'],
                    'event_id' => (string) $row['event_id'],
                    'timestamp_utc' => (string) $row['timestamp_utc'],
                    'created_at' => (string) ($row['created_at'] ?? $row['timestamp_utc']),
                    'actor_id' => $row['actor_user_id'] !== null ? (int) $row['actor_user_id'] : null,
                    'actor_name' => $actorDisplayName,
                    'actor_type' => (string) $row['actor_type'],
                    'role' => (string) $row['role'],
                    'role_name' => $this->formatRoleName((string) $row['role']),
                    'event_type' => (string) $row['event_type'],
                    'module' => $this->classifyModule((string) $row['event_type']),
                    'action' => (string) $row['event_type'],
                    'target_resource' => $targetResource,
                    'result' => (string) $row['result'],
                    'severity' => $this->classifySeverity((string) $row['event_type'], (string) $row['result']),
                    'correlation_id' => (string) $row['correlation_id'],
                    'session_fingerprint' => (string) $row['session_fingerprint'],
                    'ip_address' => $this->maskIpForDisplay((string) $row['ip_address']),
                    'user_agent' => (string) ($row['user_agent'] ?? ''),
                    'metadata' => $maskedMeta,
                ];
            }

            $stats = $this->calculateStats();
            // Available distinct event types for filter dropdown
            $eventTypes = $this->getAvailableEventTypes();
        } catch (Throwable $e) {
            Logger::error("AuditLogController::index query failed: " . $e->getMessage());
            $this->response->json([
                'success' => false,
                'code' => 'AUDIT_QUERY_FAILED',
                'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลบันทึกการตรวจสอบความปลอดภัย',
            ], 500);
            return;
        }

        $responsePayload = [
            'success' => true,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / $perPage)),
            ],
            'stats' => $stats,
            'filters' => [
                'actor_types' => ['all', 'member', 'staff'],
                'results' => ['all', 'success', 'failure', 'denied'],
                'event_types' => $eventTypes,
            ],
            'records' => $records,
            'items' => $records,
        ];

        $isAjax = $this->request->isAjax()
            || $this->request->header('Accept') === 'application/json'
            || $this->request->query('format') === 'json'
            || str_starts_with($this->request->uri(), '/api/');

        if ($isAjax) {
            $this->response->json($responsePayload);
            return;
        }

        // Fallback for direct browser access: redirect to React SPA /admin/audit-logs
        $this->response->redirect('/admin/audit-logs');
    }

    /**
     * GET /api/admin/audit-logs/stats
     * Dashboard KPI summary of security events.
     */
    public function stats(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, private');
        }

        if (!$this->assertAuditAccess('view')) {
            return;
        }

        try {
            $stats = $this->calculateStats();
            $this->response->json([
                'success' => true,
                'stats' => $stats,
            ]);
        } catch (Throwable $e) {
            Logger::error("AuditLogController::stats query failed: " . $e->getMessage());
            $this->response->json([
                'success' => false,
                'code' => 'AUDIT_QUERY_FAILED',
                'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลบันทึกการตรวจสอบความปลอดภัย',
            ], 500);
            return;
        }
    }

    /**
     * POST /api/admin/audit-logs/export
     *
     * Privileged Audit Export requiring fresh Step-Up MFA.
     * Enforces POST method, CSRF, and POST body-only Step-Up verification.
     * Prohibits TOTP/Step-Up codes in URL Query String.
     */
    public function export(): void
    {
        if (!$this->assertAuditAccess('export')) {
            return;
        }

        // Reject GET requests attempting privileged export
        if ($this->request->method() !== 'POST') {
            $this->response->json([
                'success' => false,
                'code' => 'METHOD_NOT_ALLOWED',
                'message' => 'การส่งออกข้อมูลที่มีสิทธิ์สูงต้องกระทำผ่านคำขอ POST เท่านั้น (Prohibited in GET)',
            ], 405);
            return;
        }

        // Prohibit TOTP / Step-Up codes in URL Query String (fail-closed)
        if ($this->request->query('totp_code') !== null
            || $this->request->query('step_up_code') !== null
            || isset($_GET['totp_code'])
            || isset($_GET['step_up_code'])
        ) {
            $this->response->json([
                'success' => false,
                'code' => 'CREDENTIAL_IN_URL_PROHIBITED',
                'message' => 'ห้ามระบุรหัสยืนยันตัวตน TOTP/Step-Up ทาง URL Query Parameters โดยเด็ดขาด',
            ], 400);
            return;
        }

        $userId = (int) Auth::id();
        $ip = $this->request->ip();

        // Accept Step-Up MFA code ONLY from POST Body (never from query string)
        $inlineCode = trim((string) (
            $this->request->input('totp_code')
            ?? $this->request->input('step_up_code')
            ?? ''
        ));

        try {
            DualControlSecurityService::requireFreshStepUpMfa(
                $userId,
                $inlineCode !== '' ? $inlineCode : null,
                'audit.export',
                true,
                $ip
            );
        } catch (SecurityGateException $e) {
            $this->response->json([
                'success' => false,
                'code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
                'requires_step_up_mfa' => true,
            ], $e->getHttpStatus());
            return;
        }

        // Filters for export (strictly from POST body)
        $actorType = strtolower(trim((string) ($this->request->input('actor_type') ?? 'all')));
        $eventType = trim((string) ($this->request->input('event_type') ?? ''));
        $result = strtolower(trim((string) ($this->request->input('result') ?? 'all')));
        $dateFrom = trim((string) ($this->request->input('from') ?? ''));
        $dateTo = trim((string) ($this->request->input('to') ?? ''));

        $where = ['1=1'];
        $params = [];

        if (in_array($actorType, ['member', 'staff'], true)) {
            $where[] = 's.actor_type = ?';
            $params[] = $actorType;
        }

        if ($eventType !== '' && $eventType !== 'all') {
            $where[] = 's.event_type = ?';
            $params[] = $eventType;
        }

        if (in_array($result, ['success', 'failure', 'denied'], true)) {
            $where[] = 's.result = ?';
            $params[] = $result;
        }

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where[] = 's.timestamp_utc >= ?';
            $params[] = "{$dateFrom} 00:00:00";
        }

        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where[] = 's.timestamp_utc <= ?';
            $params[] = "{$dateTo} 23:59:59";
        }

        $whereSql = implode(' AND ', $where);

        try {
            $sql = "SELECT s.event_id, s.timestamp_utc, s.actor_type, s.role, s.event_type,
                           s.target_resource, s.result, s.correlation_id, s.ip_address
                    FROM security_audit_logs s
                    WHERE {$whereSql}
                    ORDER BY s.id DESC
                    LIMIT 5000";

            $rows = Database::query($sql, $params);
        } catch (Throwable $e) {
            Logger::error("AuditLogController::export query failed: " . $e->getMessage());
            $this->response->json([
                'success' => false,
                'code' => 'AUDIT_QUERY_FAILED',
                'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลสำหรับการส่งออก',
            ], 500);
            return;
        }

        // Record audit event for privileged export
        SecurityAuditService::recordAdminFinancialExport(
            $userId,
            (string) (Auth::user()['role_slug'] ?? 'staff'),
            'security_audit_logs',
            count($rows)
        );

        $filename = 'security_audit_' . gmdate('Ymd_His') . '.csv';
        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-store, private');
        }

        $out = fopen('php://output', 'w');
        // Add UTF-8 BOM for Excel compatibility
        fwrite($out, "\xEF\xBB\xBF");

        // Minimized authorized headers
        fputcsv($out, [
            'Event ID',
            'Timestamp (UTC)',
            'Actor Type',
            'Role',
            'Event Type',
            'Target Resource',
            'Result',
            'Correlation ID',
            'IP Address',
        ]);

        foreach ($rows as $row) {
            $target = (string) ($row['target_resource'] ?? '');
            if (preg_match('/\b\d{13}\b/', $target, $matchId)) {
                $target = str_replace($matchId[0], MemberPiiMaskingService::maskCitizenId($matchId[0]), $target);
            }

            fputcsv($out, [
                $row['event_id'],
                $row['timestamp_utc'],
                $row['actor_type'],
                $row['role'],
                $row['event_type'],
                $target,
                $row['result'],
                $row['correlation_id'],
                $this->maskIpForDisplay((string) $row['ip_address']),
            ]);
        }

        fclose($out);
        return;
    }

    /**
     * Compute dashboard activity KPI statistics.
     * Throws on security_audit_logs query failure to ensure fail-closed behavior.
     */
    private function calculateStats(): array
    {
        $defaultStats = [
            'total_events' => 0,
            'member_events' => 0,
            'staff_events' => 0,
            'failed_events' => 0,
            'denied_events' => 0,
            'success_events' => 0,
            'legacy_total' => 0,
        ];

        $row = Database::first("
            SELECT
                COUNT(*) AS total_events,
                SUM(CASE WHEN actor_type = 'member' THEN 1 ELSE 0 END) AS member_events,
                SUM(CASE WHEN actor_type = 'staff' THEN 1 ELSE 0 END) AS staff_events,
                SUM(CASE WHEN result = 'failure' THEN 1 ELSE 0 END) AS failed_events,
                SUM(CASE WHEN result = 'denied' THEN 1 ELSE 0 END) AS denied_events,
                SUM(CASE WHEN result = 'success' THEN 1 ELSE 0 END) AS success_events
            FROM security_audit_logs
        ");

        if ($row) {
            $defaultStats['total_events'] = (int) ($row['total_events'] ?? 0);
            $defaultStats['member_events'] = (int) ($row['member_events'] ?? 0);
            $defaultStats['staff_events'] = (int) ($row['staff_events'] ?? 0);
            $defaultStats['failed_events'] = (int) ($row['failed_events'] ?? 0);
            $defaultStats['denied_events'] = (int) ($row['denied_events'] ?? 0);
            $defaultStats['success_events'] = (int) ($row['success_events'] ?? 0);
        }

        // Preserved legacy history count (if legacy table exists)
        try {
            $legacyCount = (int) Database::value("SELECT COUNT(*) FROM audit_logs");
            $defaultStats['legacy_total'] = $legacyCount;
        } catch (Throwable $e) {
            // If legacy table does not exist in testing environment, keep legacy_total 0
        }

        return $defaultStats;
    }

    private function getAvailableEventTypes(): array
    {
        $rows = Database::query("SELECT DISTINCT event_type FROM security_audit_logs ORDER BY event_type ASC");
        $types = array_column($rows, 'event_type');
        if (!empty($types)) {
            return $types;
        }

        return [
            SecurityAuditService::MEMBER_LOGIN_SUCCESS,
            SecurityAuditService::MEMBER_LOGIN_FAILED,
            SecurityAuditService::MEMBER_LOGOUT,
            SecurityAuditService::MEMBER_PDPA_VIEWED,
            SecurityAuditService::MEMBER_PDPA_ACKNOWLEDGED,
            SecurityAuditService::MEMBER_FINANCIAL_VIEW,
            SecurityAuditService::MEMBER_SESSION_EXPIRED,
            SecurityAuditService::MEMBER_ACCESS_DENIED,
            SecurityAuditService::ADMIN_LOGIN_SUCCESS,
            SecurityAuditService::ADMIN_LOGIN_FAILED,
            SecurityAuditService::ADMIN_2FA_SUCCESS,
            SecurityAuditService::ADMIN_2FA_FAILED,
            SecurityAuditService::ADMIN_LOGOUT,
            SecurityAuditService::ADMIN_MEMBER_VIEW,
            SecurityAuditService::ADMIN_MEMBER_UPDATE,
            SecurityAuditService::ADMIN_FINANCIAL_IMPORT,
            SecurityAuditService::ADMIN_FINANCIAL_EXPORT,
            SecurityAuditService::ADMIN_ACCOUNT_ACTIVATION,
            SecurityAuditService::ADMIN_PERMISSION_CHANGED,
        ];
    }

    private function classifyModule(string $eventType): string
    {
        if (str_starts_with($eventType, 'MEMBER_PDPA')) return 'member_pdpa';
        if (str_starts_with($eventType, 'MEMBER_FINANCIAL')) return 'member_finance';
        if (str_starts_with($eventType, 'MEMBER_')) return 'member_auth';
        if (str_starts_with($eventType, 'ADMIN_2FA')) return 'mfa_security';
        if (str_starts_with($eventType, 'ADMIN_MEMBER')) return 'member_management';
        if (str_starts_with($eventType, 'ADMIN_FINANCIAL')) return 'financial_operations';
        if (str_starts_with($eventType, 'ADMIN_PERMISSION')) return 'access_control';
        if (str_starts_with($eventType, 'ADMIN_ACCOUNT')) return 'account_lifecycle';
        return 'security_auth';
    }

    private function classifySeverity(string $eventType, string $result): string
    {
        if ($result === 'denied' || $eventType === 'MEMBER_ACCESS_DENIED') {
            return 'critical';
        }
        if ($result === 'failure' || str_ends_with($eventType, '_FAILED')) {
            return 'warning';
        }
        if (in_array($eventType, ['ADMIN_PERMISSION_CHANGED', 'ADMIN_ACCOUNT_ACTIVATION', 'ADMIN_FINANCIAL_IMPORT'], true)) {
            return 'warning';
        }
        return 'info';
    }

    private function formatRoleName(string $role): string
    {
        return match ($role) {
            'super_admin' => 'ผู้ดูแลระบบสูงสุด',
            'member_admin' => 'ผู้ดูแลระบบสมาชิก',
            'staff' => 'เจ้าหน้าที่สินเชื่อ/การเงิน',
            'finance' => 'เจ้าหน้าที่การเงิน',
            'auditor' => 'ผู้ตรวจสอบกิจการ',
            'it_admin' => 'ผู้ดูแลระบบไอที',
            'member' => 'สมาชิกสหกรณ์',
            default => $role ?: 'เจ้าหน้าที่สหกรณ์',
        };
    }

    private function maskIpForDisplay(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') {
            return $ip;
        }

        // IPv4 masking: preserve first 3 octets, mask last
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.xxx';
            }
        }

        // IPv6 masking
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            if (count($parts) >= 4) {
                return $parts[0] . ':' . $parts[1] . ':' . $parts[2] . ':xxxx';
            }
        }

        return $ip;
    }
}
