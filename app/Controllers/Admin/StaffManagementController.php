<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Services\AuditService;
use App\Services\GoogleAuthService;
use App\Services\StaffInvitationService;
use App\Services\TwoFactorService;

class StaffManagementController extends Controller
{
    /**
     * List all staff, their linked Google identities, MFA statuses, and invitations.
     * GET /api/admin/staff
     */
    public function index(): void
    {
        $sql = "SELECT u.id, u.uuid, u.name, u.username, u.email, u.status, u.department,
                       u.two_factor_enabled, u.two_factor_confirmed_at, u.session_version,
                       u.last_login_at, u.last_login_ip, u.created_at,
                       r.id as role_id, r.slug as role_slug, r.name as role_name,
                       gi.google_sub, gi.google_email, gi.linked_at, gi.last_login_at as google_last_login_at
                FROM users u
                JOIN user_roles ur ON u.id = ur.user_id
                JOIN roles r ON ur.role_id = r.id
                LEFT JOIN user_google_identities gi ON u.id = gi.user_id
                WHERE u.deleted_at IS NULL AND r.slug != 'member'
                ORDER BY (r.slug = 'super_admin') DESC, u.id ASC";

        $rows = Database::query($sql);
        $staffList = array_map(function ($row) {
            $hasMfa = (int) ($row['two_factor_enabled'] ?? 0) === 1 && !empty($row['two_factor_confirmed_at']);
            $remainingRecoveryCodes = $hasMfa ? TwoFactorService::getRecoveryCodesRemaining((int) $row['id']) : 0;

            return [
                'id' => (int) $row['id'],
                'uuid' => $row['uuid'],
                'name' => $row['name'],
                'username' => $row['username'],
                'email' => $row['email'],
                'status' => $row['status'],
                'department' => $row['department'] ?? '',
                'role_id' => (int) $row['role_id'],
                'role_slug' => $row['role_slug'],
                'role_name' => $row['role_name'],
                'session_version' => (int) ($row['session_version'] ?? 1),
                'last_login_at' => $row['last_login_at'],
                'last_login_ip' => $row['last_login_ip'],
                'created_at' => $row['created_at'],
                'mfa' => [
                    'enrolled' => $hasMfa,
                    'confirmed_at' => $row['two_factor_confirmed_at'],
                    'remaining_recovery_codes' => $remainingRecoveryCodes,
                ],
                'google' => [
                    'linked' => !empty($row['google_sub']),
                    'google_sub' => $row['google_sub'] ?? null,
                    'google_email' => $row['google_email'] ?? null,
                    'linked_at' => $row['linked_at'] ?? null,
                    'last_login_at' => $row['google_last_login_at'] ?? null,
                ],
            ];
        }, $rows);

        $invitations = StaffInvitationService::listInvitations();

        // Get available roles (excluding member)
        $roles = Database::query("SELECT id, slug, name, description FROM roles WHERE slug != 'member' ORDER BY id ASC");

        $this->response->json([
            'success' => true,
            'staff' => $staffList,
            'invitations' => $invitations,
            'roles' => $roles,
        ]);
    }

    /**
     * Invite a new staff member via Google account.
     * POST /api/admin/staff/invite
     */
    public function invite(): void
    {
        $name = trim((string) $this->request->input('name'));
        $email = trim((string) $this->request->input('email'));
        $roleId = (int) $this->request->input('role_id');
        $department = trim((string) $this->request->input('department'));
        $invitedBy = (int) Auth::id();

        try {
            $invitation = StaffInvitationService::createInvitation($name, $email, $roleId, $department, $invitedBy);
            $this->response->json([
                'success' => true,
                'message' => 'สร้างคำเชิญเจ้าหน้าที่เรียบร้อยแล้ว',
                'invitation' => $invitation,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Logger::error('Failed to create staff invitation: ' . $e->getMessage());
            $this->response->json(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการสร้างคำเชิญ'], 500);
        }
    }

    /**
     * Revoke a staff invitation.
     * POST /api/admin/staff/invitations/{id}/revoke
     */
    public function revokeInvitation(string $id): void
    {
        $invitationId = (int) $id;
        try {
            StaffInvitationService::revokeInvitation($invitationId, (int) Auth::id());
            $this->response->json(['success' => true, 'message' => 'ยกเลิกคำเชิญเรียบร้อยแล้ว']);
        } catch (\InvalidArgumentException $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            $this->response->json(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการยกเลิกคำเชิญ'], 500);
        }
    }

    /**
     * Resend/Renew a staff invitation.
     * POST /api/admin/staff/invitations/{id}/resend
     */
    public function resendInvitation(string $id): void
    {
        $invitationId = (int) $id;
        try {
            $renewed = StaffInvitationService::resendInvitation($invitationId, (int) Auth::id());
            $this->response->json([
                'success' => true,
                'message' => 'ต่ออายุและสร้างลิงก์คำเชิญใหม่เรียบร้อยแล้ว',
                'invitation' => $renewed,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            $this->response->json(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการสร้างคำเชิญใหม่'], 500);
        }
    }

    /**
     * Suspend staff account.
     * POST /api/admin/staff/{id}/suspend
     */
    public function suspend(string $id): void
    {
        $userId = (int) $id;
        $this->protectFinalSuperAdmin($userId, 'ระงับการใช้งาน');

        if ($userId === (int) Auth::id()) {
            $this->response->json(['success' => false, 'message' => 'ไม่สามารถระงับบัญชีของตนเองได้'], 400);
            return;
        }

        Database::execute("UPDATE users SET status = 'suspended', updated_at = NOW() WHERE id = ?", [$userId]);
        // Immediately revoke any active sessions
        Auth::revokeAllSessions($userId);

        AuditService::log('staff_management', 'suspend_account', (string) $userId, null, [
            'suspended_by' => Auth::id(),
        ]);

        $this->response->json(['success' => true, 'message' => 'ระงับการใช้งานบัญชีเจ้าหน้าที่เรียบร้อยแล้ว']);
    }

    /**
     * Activate staff account.
     * POST /api/admin/staff/{id}/activate
     */
    public function activate(string $id): void
    {
        $userId = (int) $id;
        Database::execute("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?", [$userId]);

        AuditService::log('staff_management', 'activate_account', (string) $userId, null, [
            'activated_by' => Auth::id(),
        ]);

        $this->response->json(['success' => true, 'message' => 'เปิดใช้งานบัญชีเจ้าหน้าที่เรียบร้อยแล้ว']);
    }

    /**
     * Update staff role and department.
     * POST /api/admin/staff/{id}/role
     */
    public function updateRole(string $id): void
    {
        $userId = (int) $id;
        $roleId = (int) $this->request->input('role_id');
        $department = trim((string) $this->request->input('department'));

        $role = Database::first("SELECT id, slug, name FROM roles WHERE id = ? LIMIT 1", [$roleId]);
        if (!$role || $role['slug'] === 'member') {
            $this->response->json(['success' => false, 'message' => 'บทบาทที่เลือกไม่ถูกต้อง'], 422);
            return;
        }

        // If user is currently a super_admin and being changed to another role, verify they are not the last active super_admin
        $currentRole = Database::value(
            "SELECT r.slug FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ? LIMIT 1",
            [$userId]
        );
        if ($currentRole === 'super_admin' && $role['slug'] !== 'super_admin') {
            $this->protectFinalSuperAdmin($userId, 'เปลี่ยนบทบาทของผู้ดูแลระบบสูงสุดคนสุดท้าย');
        }

        Database::transaction(function () use ($userId, $roleId, $department, $role) {
            Database::execute("UPDATE users SET department = ?, updated_at = NOW() WHERE id = ?", [$department, $userId]);
            Database::execute("DELETE FROM user_roles WHERE user_id = ?", [$userId]);
            Database::execute("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)", [$userId, $roleId]);

            // Invalidate current sessions so role change takes immediate effect
            Auth::revokeAllSessions($userId);
        });

        AuditService::log('staff_management', 'update_role', (string) $userId, null, [
            'new_role_id' => $roleId,
            'new_role_name' => $role['name'],
            'department' => $department,
            'updated_by' => Auth::id(),
        ]);

        $this->response->json(['success' => true, 'message' => 'อัปเดตบทบาทและแผนกเรียบร้อยแล้ว']);
    }

    /**
     * Revoke all active sessions for a staff member.
     * POST /api/admin/staff/{id}/revoke-sessions
     */
    public function revokeSessions(string $id): void
    {
        $userId = (int) $id;
        Auth::revokeAllSessions($userId);

        AuditService::log('staff_management', 'revoke_sessions', (string) $userId, null, [
            'revoked_by' => Auth::id(),
        ]);

        $this->response->json(['success' => true, 'message' => 'ยกเลิกเซสชันการใช้งานทั้งหมดของบัญชีนี้เรียบร้อยแล้ว']);
    }

    /**
     * Reset/Unlink Google identity for authorized account recovery.
     * POST /api/admin/staff/{id}/relink
     */
    public function relink(string $id): void
    {
        $userId = (int) $id;
        $googleAuth = new GoogleAuthService();
        $googleAuth->unlinkUser($userId);

        // Also bump session version
        Auth::revokeAllSessions($userId);

        AuditService::log('staff_management', 'reset_google_link', (string) $userId, null, [
            'reset_by' => Auth::id(),
        ]);

        $this->response->json(['success' => true, 'message' => 'รีเซ็ตการเชื่อมต่อ Google สำเร็จ ผู้ใช้สามารถผูกบัญชีใหม่เมื่อเข้าสู่ระบบ']);
    }

    /**
     * Security Dashboard Metrics and Live Event Feed.
     * GET /api/admin/security/dashboard
     */
    public function securityDashboard(): void
    {
        // 1. Staff and Invitation Metrics
        $totalStaff = (int) Database::value(
            "SELECT COUNT(DISTINCT u.id) FROM users u 
             JOIN user_roles ur ON u.id = ur.user_id 
             JOIN roles r ON ur.role_id = r.id 
             WHERE u.deleted_at IS NULL AND r.slug != 'member'"
        );

        $activeStaff = (int) Database::value(
            "SELECT COUNT(DISTINCT u.id) FROM users u 
             JOIN user_roles ur ON u.id = ur.user_id 
             JOIN roles r ON ur.role_id = r.id 
             WHERE u.deleted_at IS NULL AND u.status = 'active' AND r.slug != 'member'"
        );

        $suspendedStaff = (int) Database::value(
            "SELECT COUNT(DISTINCT u.id) FROM users u 
             JOIN user_roles ur ON u.id = ur.user_id 
             JOIN roles r ON ur.role_id = r.id 
             WHERE u.deleted_at IS NULL AND u.status = 'suspended' AND r.slug != 'member'"
        );

        $pendingInvitations = (int) Database::value(
            "SELECT COUNT(*) FROM staff_invitations WHERE status = 'pending' AND expires_at > NOW()"
        );

        // 2. MFA Metrics
        $mfaEnrolledCount = (int) Database::value(
            "SELECT COUNT(DISTINCT u.id) FROM users u 
             JOIN user_roles ur ON u.id = ur.user_id 
             JOIN roles r ON ur.role_id = r.id 
             WHERE u.deleted_at IS NULL AND u.status = 'active' AND r.slug != 'member' 
               AND u.two_factor_enabled = 1 AND u.two_factor_confirmed_at IS NOT NULL"
        );

        $mfaAdoptionRate = $activeStaff > 0 ? round(($mfaEnrolledCount / $activeStaff) * 100, 1) : 0;

        // 3. Google Linked Metrics
        $googleLinkedCount = (int) Database::value(
            "SELECT COUNT(DISTINCT u.id) FROM users u 
             JOIN user_roles ur ON u.id = ur.user_id 
             JOIN roles r ON ur.role_id = r.id 
             JOIN user_google_identities gi ON u.id = gi.user_id
             WHERE u.deleted_at IS NULL AND r.slug != 'member'"
        );

        // 4. Failed Logins Summary (Last 7 days)
        $failedLogins7d = (int) Database::value(
            "SELECT COUNT(*) FROM login_logs WHERE status != 'success' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );

        $failedLogins24h = (int) Database::value(
            "SELECT COUNT(*) FROM login_logs WHERE status != 'success' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        $topFailureReasons = Database::query(
            "SELECT failure_reason, COUNT(*) as count 
             FROM login_logs 
             WHERE status != 'success' AND failure_reason IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
             GROUP BY failure_reason 
             ORDER BY count DESC 
             LIMIT 5"
        );

        // 5. Recent Authentication & Identity Events
        $recentAuthLogs = Database::query(
            "SELECT l.id, l.email, l.status, l.auth_provider, l.failure_reason, l.ip_address, l.user_agent, l.created_at,
                    u.name as user_name
             FROM login_logs l
             LEFT JOIN users u ON l.user_id = u.id
             ORDER BY l.created_at DESC
             LIMIT 15"
        );

        $this->response->json([
            'success' => true,
            'metrics' => [
                'total_staff' => $totalStaff,
                'active_staff' => $activeStaff,
                'suspended_staff' => $suspendedStaff,
                'pending_invitations' => $pendingInvitations,
                'mfa_enrolled_count' => $mfaEnrolledCount,
                'mfa_adoption_rate' => $mfaAdoptionRate,
                'google_linked_count' => $googleLinkedCount,
                'failed_logins_24h' => $failedLogins24h,
                'failed_logins_7d' => $failedLogins7d,
            ],
            'top_failure_reasons' => $topFailureReasons,
            'recent_events' => $recentAuthLogs,
        ]);
    }

    // -------------------------------------------------------------------------
    // Safety Protections
    // -------------------------------------------------------------------------

    private function protectFinalSuperAdmin(int $userId, string $action): void
    {
        $role = Database::value(
            "SELECT r.slug FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ? LIMIT 1",
            [$userId]
        );

        if ($role === 'super_admin') {
            $activeSuperAdmins = (int) Database::value(
                "SELECT COUNT(DISTINCT u.id) FROM users u 
                 JOIN user_roles ur ON u.id = ur.user_id 
                 JOIN roles r ON ur.role_id = r.id 
                 WHERE r.slug = 'super_admin' AND u.status = 'active' AND u.deleted_at IS NULL"
            );

            if ($activeSuperAdmins <= 1) {
                throw new \InvalidArgumentException("ไม่สามารถ{$action}ได้ เนื่องจากเป็นผู้ดูแลระบบสูงสุด (Super Admin) บัญชีเดียวที่ยังเปิดใช้งานอยู่");
            }
        }
    }
}
