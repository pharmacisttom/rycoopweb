<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\AdminControlCenterService;
use App\Services\AuditService;
use App\Services\VisitorCounterService;

/**
 * REST API Controller for RYCOOPWEB 3.0 Enterprise Admin Control Center.
 *
 * Enforces authentication, mandatory MFA, least-privilege RBAC, correlation IDs,
 * bounded queries, and strict secret redaction on every endpoint.
 */
class ControlCenterApiController extends Controller
{
    /**
     * Authorize the current request against required permission keys and execute callback.
     */
    private function authorizeAndRespond(array $requiredPermissions, callable $callback): void
    {
        $correlationId = AdminControlCenterService::correlationId();
        if (!headers_sent()) {
            header('Cache-Control: no-store, private');
            header('X-Correlation-Id: ' . $correlationId);
        }

        if (!Auth::check() || !Auth::user()) {
            $this->response->json([
                'success' => false,
                'code' => 'UNAUTHORIZED',
                'correlation_id' => $correlationId,
                'message' => 'เซสชันหมดอายุหรือยังไม่ได้ยืนยันตัวตน กรุณาเข้าสู่ระบบใหม่',
            ], 401);
            return;
        }

        $roleSlug = (string) (Auth::user()['role_slug'] ?? '');
        if ($roleSlug === '' || $roleSlug === 'member') {
            $this->response->json([
                'success' => false,
                'code' => 'FORBIDDEN',
                'correlation_id' => $correlationId,
                'message' => 'บัญชีสมาชิกไม่สามารถเข้าถึงศูนย์ควบคุมผู้ดูแลระบบได้',
            ], 403);
            return;
        }

        if (!AdminControlCenterService::canAny($requiredPermissions)) {
            $this->response->json([
                'success' => false,
                'code' => 'FORBIDDEN',
                'correlation_id' => $correlationId,
                'message' => 'คุณไม่มีสิทธิ์เข้าถึงข้อมูลส่วนนี้ (Least-Privilege Policy)',
            ], 403);
            return;
        }

        try {
            $payload = $callback();
            $this->response->json([
                'success' => true,
                'correlation_id' => $correlationId,
                'data' => $payload,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->response->json([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'correlation_id' => $correlationId,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('AdminControlCenter API Error [' . $correlationId . ']: ' . $e->getMessage());
            $this->response->json([
                'success' => false,
                'code' => 'INTERNAL_ERROR',
                'correlation_id' => $correlationId,
                'message' => 'ไม่สามารถประมวลผลข้อมูลได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง',
            ], 500);
        }
    }

    /**
     * GET /api/admin/dashboard/summary
     */
    public function summary(): void
    {
        $this->authorizeAndRespond(
            ['dashboard.view', 'analytics.view'],
            fn() => AdminControlCenterService::getDashboardSummary()
        );
    }

    /**
     * GET /api/admin/dashboard/trends
     */
    public function trends(): void
    {
        $this->authorizeAndRespond(
            ['dashboard.view', 'analytics.view'],
            function () {
                $days = (int) $this->request->query('days', 30);
                $dept = trim((string) $this->request->query('department', 'all'));
                return AdminControlCenterService::getDashboardTrends($days, $dept);
            }
        );
    }

    /**
     * GET /api/admin/analytics/usage
     */
    public function usage(): void
    {
        $this->authorizeAndRespond(
            ['analytics.view', 'dashboard.view'],
            function () {
                $visitorAnalytics = null;
                try {
                    $visitorAnalytics = VisitorCounterService::getDetailedAnalytics();
                } catch (\Throwable $e) {
                    $visitorAnalytics = null;
                }
                $trends = AdminControlCenterService::getDashboardTrends(30, 'all');
                return [
                    'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
                    'visitor_analytics' => $visitorAnalytics,
                    'module_activity' => $trends['charts']['activity_by_module'],
                    'storage_usage' => $trends['charts']['storage_usage'],
                ];
            }
        );
    }

    /**
     * GET /api/admin/operations/overview
     */
    public function operations(): void
    {
        $this->authorizeAndRespond(
            ['dashboard.view', 'members.view', 'news.view', 'rates.view'],
            fn() => AdminControlCenterService::getOperationsOverview()
        );
    }

    /**
     * GET /api/admin/security/overview
     */
    public function securityOverview(): void
    {
        $this->authorizeAndRespond(
            ['security.view', 'audit.view'],
            fn() => AdminControlCenterService::getSecurityOverview()
        );
    }

    /**
     * GET /api/admin/security/sessions
     */
    public function securitySessions(): void
    {
        $this->authorizeAndRespond(
            ['security.view', 'security.sessions.revoke'],
            fn() => AdminControlCenterService::getSanitizedSessions()
        );
    }

    /**
     * GET /api/admin/security/mfa
     */
    public function securityMfa(): void
    {
        $this->authorizeAndRespond(
            ['security.view'],
            fn() => AdminControlCenterService::getMfaAdministration()
        );
    }

    /**
     * GET /api/admin/audit-logs/center
     */
    public function auditLogs(): void
    {
        $this->authorizeAndRespond(
            ['audit.view', 'security.view'],
            function () {
                $filters = [
                    'page' => $this->request->query('page', 1),
                    'per_page' => $this->request->query('per_page', 25),
                    'category' => $this->request->query('category', 'all'),
                    'severity' => $this->request->query('severity', 'all'),
                    'module' => $this->request->query('module', ''),
                    'q' => $this->request->query('q', ''),
                    'from' => $this->request->query('from', ''),
                    'to' => $this->request->query('to', ''),
                ];
                return AdminControlCenterService::getAuditLogs($filters);
            }
        );
    }

    /**
     * GET /api/admin/backups
     */
    public function backups(): void
    {
        $this->authorizeAndRespond(
            ['backup.view', 'backup.verify'],
            fn() => AdminControlCenterService::getBackupHealth()
        );
    }

    /**
     * GET /api/admin/backups/health
     */
    public function backupsHealth(): void
    {
        $this->authorizeAndRespond(
            ['backup.view', 'backup.verify', 'system.health.view'],
            fn() => AdminControlCenterService::getBackupHealth()
        );
    }

    /**
     * GET /api/admin/system/health
     */
    public function systemHealth(): void
    {
        $this->authorizeAndRespond(
            ['system.health.view', 'settings.view'],
            fn() => AdminControlCenterService::getSystemHealth(false)
        );
    }

    /**
     * GET /api/admin/access/permissions
     */
    public function accessPermissions(): void
    {
        $this->authorizeAndRespond(
            ['roles.view', 'roles.manage', 'permissions.manage', 'users.view'],
            fn() => AdminControlCenterService::getAccessMatrix()
        );
    }

    /**
     * GET /api/admin/departments
     */
    public function departments(): void
    {
        $this->authorizeAndRespond(
            ['users.view', 'roles.view', 'dashboard.view'],
            fn() => AdminControlCenterService::getDepartmentsSummary()
        );
    }

    /**
     * GET /api/admin/reports/catalog
     */
    public function reportsCatalog(): void
    {
        $this->authorizeAndRespond(
            ['dashboard.view', 'audit.view', 'finance.view', 'members.view'],
            function () {
                $canFinance = AdminControlCenterService::canAny(['finance.view', 'finance.export', 'rates.view']);
                $canAudit = AdminControlCenterService::canAny(['audit.view', 'audit.export', 'security.view']);
                $canMembers = AdminControlCenterService::canAny(['members.view', 'dividends.import']);

                $finStats = [];
                if ($canFinance) {
                    try {
                        $finStats = Database::query(
                            "SELECT year, month, total_members, share_capital, total_deposits, total_loans,
                                    total_assets, reserve_fund, net_profit, dividend_rate
                             FROM financial_statistics
                             ORDER BY year DESC, month DESC LIMIT 12"
                        );
                    } catch (\Throwable $e) {
                        $finStats = [];
                    }
                }

                $dividendSummary = [];
                if ($canMembers || $canFinance) {
                    try {
                        $dividendSummary = Database::query(
                            "SELECT year, COUNT(*) AS member_records, MAX(imported_at) AS last_imported_at
                             FROM member_dividends
                             GROUP BY year
                             ORDER BY year DESC LIMIT 10"
                        );
                    } catch (\Throwable $e) {
                        $dividendSummary = [];
                    }
                }

                return [
                    'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
                    'permissions' => [
                        'can_finance' => $canFinance,
                        'can_audit' => $canAudit,
                        'can_members' => $canMembers,
                    ],
                    'financial_statistics' => $finStats,
                    'dividend_years' => $dividendSummary,
                ];
            }
        );
    }

    /**
     * GET /api/admin/analytics/visitors
     * Enterprise Admin Visitor Analytics Dashboard endpoint.
     */
    public function visitorAnalytics(): void
    {
        $days = max(7, min(90, (int) $this->request->query('days', 30)));
        $this->authorizeAndRespond(
            ['analytics.visitors.view', 'analytics.view'],
            fn() => \App\Services\VisitorCounterService::getDetailedAnalytics($days, true)
        );
    }

    /**
     * POST /api/admin/analytics/visitors/export
     * Authorized export of visitor analytics with Audit Trail logging.
     */
    public function exportVisitorAnalytics(): void
    {
        $days = max(7, min(90, (int) $this->request->input('days', 30)));
        $format = strtolower(trim((string) $this->request->input('format', 'csv')));
        if (!in_array($format, ['csv', 'json'], true)) {
            $format = 'csv';
        }

        $this->authorizeAndRespond(
            ['analytics.visitors.export', 'audit.export'],
            function () use ($days, $format) {
                $analytics = \App\Services\VisitorCounterService::getDetailedAnalytics($days, true);
                \App\Services\AuditService::log(
                    'visitor_analytics',
                    'export',
                    'visitors_' . $days . 'd',
                    null,
                    [
                        'days' => $days,
                        'format' => $format,
                        'exported_by' => \App\Core\Auth::id(),
                    ]
                );
                return [
                    'exported_at_bangkok' => $analytics['generated_at_bangkok'] ?? null,
                    'format' => $format,
                    'days' => $days,
                    'analytics' => $analytics,
                ];
            }
        );
    }
}
