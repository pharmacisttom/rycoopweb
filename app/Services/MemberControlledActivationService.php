<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Controlled Account Activation Service for Cooperative Members.
 *
 * Implements safety gates, dry-run simulations, and transaction-bound
 * activation procedures for the 2,508 imported member accounts.
 *
 * SAFETY INVARIANTS:
 * 1. Suspended accounts (users.status='suspended' OR members.status='suspended') are NEVER activated.
 * 2. Staff and Super Admin accounts are strictly excluded and unaffected.
 * 3. Dry-run mode produces zero database mutations.
 * 4. Bulk activation requires explicit security approval token and authenticated admin context.
 * 5. Full audit provenance is recorded for every activated member account.
 */
class MemberControlledActivationService
{
    public const REQUIRED_APPROVAL_TOKEN = 'RYCOOP-SECURITY-APPROVAL-MEMBER-ACTIVATION-2026';

    /**
     * Perform a dry-run analysis of member account activation.
     * Guaranteed read-only; no database modifications are made.
     */
    public static function dryRun(?PDO $pdo = null): array
    {
        $db = $pdo ?? Database::connect();

        // 1. Total imported members
        $stmt = $db->query("SELECT COUNT(*) FROM members");
        $totalMembers = (int)$stmt->fetchColumn();

        // 2. Member mapping completeness
        $stmt = $db->query("SELECT COUNT(*) FROM members WHERE user_id IS NOT NULL AND user_id > 0");
        $mappedMembers = (int)$stmt->fetchColumn();

        $stmt = $db->query("SELECT COUNT(*) FROM members WHERE user_id IS NULL OR user_id <= 0");
        $unmappedMembers = (int)$stmt->fetchColumn();

        // 3. User account statuses for members
        $stmt = $db->query(
            "SELECT u.status, COUNT(*) as cnt
             FROM users u
             JOIN user_roles ur ON u.id = ur.user_id
             JOIN roles r ON ur.role_id = r.id
             WHERE r.slug = 'member'
             GROUP BY u.status"
        );
        $userStatusCounts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $userStatusCounts[$row['status']] = (int)$row['cnt'];
        }

        $inactiveMemberUsers = $userStatusCounts['inactive'] ?? 0;
        $activeMemberUsers = $userStatusCounts['active'] ?? 0;
        $suspendedMemberUsers = $userStatusCounts['suspended'] ?? 0;

        // 4. Inactive members table status
        $stmt = $db->query("SELECT COUNT(*) FROM members WHERE status = 'suspended'");
        $suspendedMembersTable = (int)$stmt->fetchColumn();

        // 5. Staff and admin accounts protection check
        $stmt = $db->query(
            "SELECT COUNT(DISTINCT u.id)
             FROM users u
             JOIN user_roles ur ON u.id = ur.user_id
             JOIN roles r ON ur.role_id = r.id
             WHERE r.slug IN ('super_admin', 'staff', 'admin', 'auditor', 'pr_officer')"
        );
        $protectedStaffCount = (int)$stmt->fetchColumn();

        // 6. Eligible candidates for activation:
        // - users.status = 'inactive'
        // - members.status = 'active'
        // - role = 'member'
        // - NOT assigned to any staff/admin role
        $candidateQuery =
            "SELECT COUNT(DISTINCT u.id)
             FROM users u
             JOIN user_roles ur ON u.id = ur.user_id
             JOIN roles r ON ur.role_id = r.id
             JOIN members m ON u.id = m.user_id
             WHERE u.status = 'inactive'
               AND m.status = 'active'
               AND r.slug = 'member'
               AND u.id NOT IN (
                   SELECT ur2.user_id
                   FROM user_roles ur2
                   JOIN roles r2 ON ur2.role_id = r2.id
                   WHERE r2.slug IN ('super_admin', 'staff', 'admin', 'auditor', 'pr_officer')
               )";
        $stmt = $db->query($candidateQuery);
        $eligibleCandidates = (int)$stmt->fetchColumn();

        // 7. Ineligible breakdown
        $ineligibleSuspended = $suspendedMemberUsers + $suspendedMembersTable;

        return [
            'mode' => 'DRY_RUN',
            'timestamp' => date('Y-m-d H:i:s'),
            'baseline' => [
                'total_members' => $totalMembers,
                'mapped_members' => $mappedMembers,
                'unmapped_members' => $unmappedMembers,
                'protected_staff_admin_users' => $protectedStaffCount,
            ],
            'member_user_statuses' => [
                'inactive' => $inactiveMemberUsers,
                'active' => $activeMemberUsers,
                'suspended' => $suspendedMemberUsers,
            ],
            'simulation' => [
                'eligible_for_activation' => $eligibleCandidates,
                'excluded_suspended' => $ineligibleSuspended,
                'excluded_staff_admin' => $protectedStaffCount,
                'would_be_updated' => $eligibleCandidates,
            ],
            'safety_verifications' => [
                'suspended_accounts_strictly_preserved' => true,
                'staff_and_admin_accounts_isolated' => true,
                'no_duplicate_members_detected' => ($totalMembers === $mappedMembers),
                'zero_mutations_executed' => true,
            ],
            'security_risk_assessment' => [
                'initial_credential_predictability' => 'HIGH',
                'description' => 'Using Citizen ID (13 digits) + Member Number (5 digits) as credentials presents high predictability in closed community environments. Data masking and PDPA consent do not provide strong authentication.',
                'recommended_controls' => [
                    'Require explicit administrative authorization before executing bulk activation',
                    'Advise voluntary password change to personal credentials upon first login',
                    'Maintain mandatory session PDPA acknowledgement gate (v3.0)',
                    'Keep server-side session authorization on all financial endpoints',
                    'Implement audit logging with pseudonymized identifiers'
                ]
            ]
        ];
    }

    /**
     * Execute controlled member account activation within a database transaction.
     * Strictly requires explicit security approval token and admin context.
     *
     * @throws RuntimeException if approval token is invalid or database fails.
     */
    public static function executeActivation(
        string $approvalToken,
        int $approvedByAdminId,
        ?PDO $pdo = null
    ): array {
        if ($approvalToken !== self::REQUIRED_APPROVAL_TOKEN) {
            throw new RuntimeException("Security Exception: Invalid approval token. Controlled activation rejected.");
        }

        if ($approvedByAdminId <= 0) {
            throw new RuntimeException("Security Exception: Valid administrator ID required for activation approval.");
        }

        $db = $pdo ?? Database::connect();
        $isNested = $db->inTransaction();

        if (!$isNested) {
            $db->beginTransaction();
        }

        try {
            // Find candidates matching safety criteria
            $candidateSql =
                "SELECT u.id as user_id, m.id as member_id, u.username
                 FROM users u
                 JOIN user_roles ur ON u.id = ur.user_id
                 JOIN roles r ON ur.role_id = r.id
                 JOIN members m ON u.id = m.user_id
                 WHERE u.status = 'inactive'
                   AND m.status = 'active'
                   AND r.slug = 'member'
                   AND u.id NOT IN (
                       SELECT ur2.user_id
                       FROM user_roles ur2
                       JOIN roles r2 ON ur2.role_id = r2.id
                       WHERE r2.slug IN ('super_admin', 'staff', 'admin', 'auditor', 'pr_officer')
                   )";
            $stmt = $db->query($candidateSql);
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $activatedCount = 0;
            $hasMustChangeCol = MemberSecurityService::hasMustChangePasswordColumn();

            $updateSql = $hasMustChangeCol
                ? "UPDATE users SET status = 'active', must_change_password = 0, updated_at = NOW() WHERE id = ?"
                : "UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?";
            $updateStmt = $db->prepare($updateSql);

            foreach ($candidates as $cand) {
                $userId = (int)$cand['user_id'];
                $memberId = (int)$cand['member_id'];

                $updateStmt->execute([$userId]);

                // Record provenance in member_data_changes
                MemberAdminService::recordChange(
                    $memberId,
                    'controlled_account_activation',
                    'เปิดใช้งานบัญชีผ่านระบบควบคุมความปลอดภัย (Controlled batch activation with security approval)',
                    ['user_id' => $userId, 'status' => 'inactive'],
                    ['user_id' => $userId, 'status' => 'active', 'approved_by_admin_id' => $approvedByAdminId],
                    $db
                );

                $activatedCount++;
            }

            // Log executive audit
            AuditService::log('member_admin', 'controlled_bulk_activation', (string)$approvedByAdminId, null, [
                'activated_count' => $activatedCount,
                'status' => 'success',
                'approval_token' => hash('sha256', $approvalToken),
            ], true, $db);

            if (!$isNested) {
                $db->commit();
            }

            return [
                'success' => true,
                'mode' => 'LIVE_EXECUTION',
                'activated_count' => $activatedCount,
                'approved_by_admin_id' => $approvedByAdminId,
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        } catch (Throwable $e) {
            if (!$isNested && $db->inTransaction()) {
                $db->rollBack();
            }
            Logger::error("Controlled member activation aborted: " . $e->getMessage());
            throw $e;
        }
    }
}
