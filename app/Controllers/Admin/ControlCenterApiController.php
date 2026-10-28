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
        } catch (\App\Exceptions\SecurityGateException $e) {
            $this->response->json([
                'success' => false,
                'code' => $e->getErrorCode(),
                'correlation_id' => $correlationId,
                'message' => $e->getMessage(),
                'context' => $e->getContext(),
            ], $e->getHttpStatus());
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
     * Execute a Dual-Control / Security Gate action with standard authentication,
     * non-member role guard, correlation ID header, and SecurityGateException mapping.
     */
    private function executeSecurityAction(callable $callback): void
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

        try {
            $payload = $callback();
            $this->response->json([
                'success' => true,
                'correlation_id' => $correlationId,
                'data' => $payload,
            ]);
        } catch (\App\Exceptions\SecurityGateException $e) {
            $this->response->json([
                'success' => false,
                'code' => $e->getErrorCode(),
                'correlation_id' => $correlationId,
                'message' => $e->getMessage(),
                'context' => $e->getContext(),
            ], $e->getHttpStatus());
        } catch (\InvalidArgumentException $e) {
            $this->response->json([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'correlation_id' => $correlationId,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('DualControl Security Gate Error [' . $correlationId . ']: ' . $e->getMessage());
            $this->response->json([
                'success' => false,
                'code' => 'INTERNAL_ERROR',
                'correlation_id' => $correlationId,
                'message' => 'ไม่สามารถประมวลผลคำสั่งด้านความปลอดภัยได้ในขณะนี้',
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

    /**
     * GET /api/admin/approvals
     * List Dual-Control approval requests with redacted metadata and policy status.
     */
    public function approvalsList(): void
    {
        $this->authorizeAndRespond(
            ['backup.view', 'backup.restore.request', 'backup.restore.approve', 'security.view', 'audit.view', 'operations.view', 'dashboard.view'],
            function () {
                $status = (string) $this->request->query('status', 'all');
                $module = (string) $this->request->query('module', '');
                $limit = max(1, min(100, (int) $this->request->query('limit', 50)));
                $requests = \App\Services\DualControlSecurityService::listRequests($status, $module, $limit);
                return [
                    'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
                    'restore_execution_enabled' => \App\Services\DualControlSecurityService::isRestoreExecutionEnabled(),
                    'requests' => $requests,
                ];
            }
        );
    }

    /**
     * POST /api/admin/approvals
     * Create a new Dual-Control approval request (Maker action).
     */
    public function createApprovalRequest(): void
    {
        $this->executeSecurityAction(function () {
            $userId = (int) Auth::id();
            $actionKey = trim((string) $this->request->input('action_key', ''));
            $reason = trim((string) $this->request->input('reason', ''));
            $targetType = $this->request->input('target_type') !== null
                ? trim((string) $this->request->input('target_type'))
                : null;
            $targetId = $this->request->input('target_id') !== null
                ? trim((string) $this->request->input('target_id'))
                : null;
            $payload = is_array($this->request->input('payload')) ? $this->request->input('payload') : [];

            return \App\Services\DualControlSecurityService::createRequest(
                $userId,
                $actionKey,
                $reason,
                $targetType,
                $targetId,
                $payload
            );
        });
    }

    /**
     * POST /api/admin/approvals/{id}/approve
     * Approve a pending Dual-Control request (Checker action).
     * Enforces: Checker permission, Maker != Checker (including super_admin),
     * pending status, non-expired, and fresh Server-Side Step-Up MFA.
     */
    public function approveRequest(string $id): void
    {
        $this->executeSecurityAction(function () use ($id) {
            $checkerUserId = (int) Auth::id();
            $requestId = (int) $id;
            $reviewNote = trim((string) $this->request->input('review_note', ''));
            $inlineTotp = $this->request->input('totp_code') !== null
                ? (string) $this->request->input('totp_code')
                : ($this->request->input('code') !== null ? (string) $this->request->input('code') : null);
            $expectedAction = $this->request->input('expected_action') !== null
                ? trim((string) $this->request->input('expected_action'))
                : null;
            $expectedTarget = $this->request->input('expected_target') !== null
                ? trim((string) $this->request->input('expected_target'))
                : null;

            return \App\Services\DualControlSecurityService::approveRequest(
                $requestId,
                $checkerUserId,
                $reviewNote,
                $inlineTotp,
                $expectedAction,
                $expectedTarget,
                $this->request->ip()
            );
        });
    }

    /**
     * POST /api/admin/approvals/{id}/reject
     * Reject a pending Dual-Control request (Checker action).
     */
    public function rejectRequest(string $id): void
    {
        $this->executeSecurityAction(function () use ($id) {
            $checkerUserId = (int) Auth::id();
            $requestId = (int) $id;
            $reviewNote = trim((string) $this->request->input('review_note', ''));

            return \App\Services\DualControlSecurityService::rejectRequest(
                $requestId,
                $checkerUserId,
                $reviewNote
            );
        });
    }

    /**
     * POST /api/admin/approvals/{id}/cancel
     * Cancel a pending Dual-Control request (Maker action).
     */
    public function cancelRequest(string $id): void
    {
        $this->executeSecurityAction(function () use ($id) {
            $userId = (int) Auth::id();
            $requestId = (int) $id;
            $note = trim((string) $this->request->input('reason', ''));

            return \App\Services\DualControlSecurityService::cancelRequest(
                $requestId,
                $userId,
                $note
            );
        });
    }

    /**
     * POST /api/admin/backups/restore-request
     * Maker endpoint to submit a Dual-Control Backup Restore Request with SHA-256 file pinning.
     */
    public function createRestoreRequest(): void
    {
        $this->executeSecurityAction(function () {
            $userId = (int) Auth::id();
            $filename = basename(trim((string) ($this->request->input('file') ?? $this->request->input('target_id') ?? '')));
            $reason = trim((string) $this->request->input('reason', ''));

            return \App\Services\DualControlSecurityService::createRequest(
                $userId,
                'backup.restore.request',
                $reason,
                'backup_archive',
                $filename,
                ['requested_via' => 'api/admin/backups/restore-request']
            );
        });
    }

    /**
     * POST /api/admin/backups/restore-execute
     * Two-Phase Non-Transactional Restore Execution Gate.
     * Requires:
     * 1. Valid Dual-Control approved request (Maker != Checker).
     * 2. Exact filename & SHA-256 integrity match.
     * 3. Fresh Server-Side Step-Up MFA.
     * 4. BACKUP_RESTORE_EXECUTION_ENABLED=true (defaults to false -> 423 Locked).
     */
    public function executeRestore(): void
    {
        $this->executeSecurityAction(function () {
            $userId = (int) Auth::id();
            $approvalId = (int) ($this->request->input('approval_request_id') ?? $this->request->input('approval_id') ?? 0);
            $filename = basename(trim((string) ($this->request->input('file') ?? $this->request->input('target_id') ?? '')));
            $inlineTotp = $this->request->input('totp_code') !== null
                ? (string) $this->request->input('totp_code')
                : ($this->request->input('code') !== null ? (string) $this->request->input('code') : null);

            return \App\Services\DualControlSecurityService::executeApprovedRestore(
                $approvalId,
                $userId,
                $filename,
                $inlineTotp,
                null,
                null,
                $this->request->ip()
            );
        });
    }
}
