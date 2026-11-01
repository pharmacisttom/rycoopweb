<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Service for Member PDPA Privacy Notice & Session Acknowledgement Gate.
 *
 * Implements:
 * - Session-specific login nonce generation and hashing (SHA-256).
 * - Administrator-approved policy versioning & active policy discovery.
 * - Server-side acknowledgement verification against MySQL.
 * - Idempotent, transactional acknowledgement persistence.
 * - Fail-closed audit logging with privacy-safe metadata hashing.
 */
class MemberPdpaService
{
    public const NONCE_SESSION_KEY = 'member_login_nonce';
    public const ACK_SESSION_KEY = 'member_pdpa_acknowledged';

    /**
     * Get active privacy policy.
     */
    public static function getActivePolicy(?PDO $pdo = null): ?array
    {
        $sql = "SELECT id, version, title, content, content_sha256, effective_at, is_active, created_at
                FROM member_privacy_policies
                WHERE is_active = 1 AND effective_at <= UTC_TIMESTAMP()
                ORDER BY effective_at DESC, id DESC
                LIMIT 1";

        if ($pdo !== null) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }

        return Database::first($sql) ?: null;
    }

    /**
     * Get privacy policy by exact version.
     */
    public static function getPolicyByVersion(string $version, ?PDO $pdo = null): ?array
    {
        $sql = "SELECT id, version, title, content, content_sha256, effective_at, is_active, created_at
                FROM member_privacy_policies
                WHERE version = ?
                LIMIT 1";

        if ($pdo !== null) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$version]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }

        return Database::first($sql, [$version]) ?: null;
    }

    /**
     * Generate cryptographically secure login nonce.
     */
    public static function generateLoginNonce(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Hash login nonce for database persistence (SHA-256).
     */
    public static function hashNonce(string $nonce): string
    {
        return hash('sha256', $nonce);
    }

    /**
     * Initialize member session on successful authentication.
     * Generates a new nonce, resets acknowledgement state.
     */
    public static function initMemberSession(array $user): string
    {
        $nonce = self::generateLoginNonce();
        Session::set(self::NONCE_SESSION_KEY, $nonce);
        Session::remove(self::ACK_SESSION_KEY);
        return $nonce;
    }

    /**
     * Retrieve the current session login nonce.
     */
    public static function getCurrentSessionNonce(): ?string
    {
        $nonce = Session::get(self::NONCE_SESSION_KEY);
        return is_string($nonce) && $nonce !== '' ? $nonce : null;
    }

    /**
     * Check if the authenticated member has acknowledged the active policy
     * for the current login session nonce.
     */
    public static function isAcknowledged(
        int $userId,
        ?string $nonce = null,
        ?int $policyId = null,
        ?PDO $pdo = null
    ): bool {
        if ($userId <= 0) {
            return false;
        }

        $sessionNonce = $nonce ?? self::getCurrentSessionNonce();
        if (empty($sessionNonce)) {
            Session::remove(self::ACK_SESSION_KEY);
            return false;
        }

        $targetPolicyId = $policyId;
        if ($targetPolicyId === null || $targetPolicyId <= 0) {
            $activePolicy = self::getActivePolicy($pdo);
            if (!$activePolicy) {
                Session::remove(self::ACK_SESSION_KEY);
                return false;
            }
            $targetPolicyId = (int) $activePolicy['id'];
        }

        $nonceHash = self::hashNonce($sessionNonce);

        $sql = "SELECT id FROM member_pdpa_acknowledgements
                WHERE user_id = ? AND login_nonce_hash = ? AND policy_id = ?
                LIMIT 1";

        if ($pdo !== null) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $nonceHash, $targetPolicyId]);
            $exists = (bool) $stmt->fetchColumn();
        } else {
            $exists = (bool) Database::value($sql, [$userId, $nonceHash, $targetPolicyId]);
        }

        if ($exists) {
            Session::set(self::ACK_SESSION_KEY, true);
            return true;
        }

        Session::remove(self::ACK_SESSION_KEY);
        return false;
    }

    /**
     * Record member PDPA acknowledgement in a transaction.
     * Fail-closed: audit logging and database insert must succeed.
     */
    public static function recordAcknowledgement(
        int $userId,
        int $memberId,
        string $policyVersion,
        string $nonce,
        ?string $ip = null,
        ?string $userAgent = null,
        ?PDO $pdo = null
    ): array {
        if ($userId <= 0 || $memberId <= 0) {
            throw new RuntimeException("Invalid user or member identity.");
        }

        if (empty($nonce)) {
            throw new RuntimeException("Missing login session nonce.");
        }

        $activePolicy = self::getActivePolicy($pdo);
        if (!$activePolicy) {
            throw new RuntimeException("No active privacy policy found.");
        }

        // Exact policy version match: if policy changed between GET and POST, reject with conflict
        if ($activePolicy['version'] !== $policyVersion) {
            return [
                'success' => false,
                'code' => 'POLICY_VERSION_MISMATCH',
                'message' => 'นโยบายความเป็นส่วนตัวมีการปรับปรุง กรุณาตรวจสอบและรับทราบนโยบายฉบับล่าสุด',
                'current_version' => $activePolicy['version'],
            ];
        }

        $policyId = (int) $activePolicy['id'];
        $nonceHash = self::hashNonce($nonce);

        // Privacy-safe hashing for IP and User-Agent
        $salt = (string) (config('security.app_key') ?? config('app.key') ?? 'rycoopweb-pdpa-salt');
        $ipHash = $ip !== null && $ip !== '' ? hash('sha256', $ip . $salt) : null;
        $uaHash = $userAgent !== null && $userAgent !== '' ? hash('sha256', $userAgent . $salt) : null;

        $db = $pdo ?? Database::pdo();
        $driver = (string) $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        // Fast-path: Check if already acknowledged (idempotency)
        $checkStmt = $db->prepare(
            "SELECT id FROM member_pdpa_acknowledgements
             WHERE user_id = ? AND login_nonce_hash = ? AND policy_id = ?
             LIMIT 1"
        );
        $checkStmt->execute([$userId, $nonceHash, $policyId]);
        $existingId = $checkStmt->fetchColumn();

        if ($existingId) {
            Session::set(self::ACK_SESSION_KEY, true);
            return [
                'success' => true,
                'idempotent' => true,
                'acknowledged' => true,
                'policy_version' => $policyVersion,
            ];
        }

        $inTx = $db->inTransaction();
        if (!$inTx) {
            $db->beginTransaction();
        }

        try {
            $utcNow = gmdate('Y-m-d H:i:s');
            $insertSql = "INSERT INTO member_pdpa_acknowledgements
                (user_id, member_id, policy_id, policy_version, login_nonce_hash, acknowledged_at, ip_hash, user_agent_hash, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $db->prepare($insertSql);
            $stmt->execute([
                $userId,
                $memberId,
                $policyId,
                $policyVersion,
                $nonceHash,
                $utcNow,
                $ipHash,
                $uaHash,
                $utcNow,
            ]);

            $ackId = (int) $db->lastInsertId();

            // Fail-closed audit logging: $required = true
            AuditService::log(
                'member_pdpa',
                'acknowledge',
                (string) $ackId,
                null,
                [
                    'user_id' => $userId,
                    'member_id' => $memberId,
                    'policy_id' => $policyId,
                    'policy_version' => $policyVersion,
                    'acknowledged_at' => $utcNow,
                ],
                true
            );

            if (!$inTx) {
                $db->commit();
            }

            Session::set(self::ACK_SESSION_KEY, true);

            return [
                'success' => true,
                'idempotent' => false,
                'acknowledged' => true,
                'policy_version' => $policyVersion,
            ];
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) {
                $db->rollBack();
            }
            Session::remove(self::ACK_SESSION_KEY);

            // If unique constraint race condition occurred, check if record now exists
            $checkStmt->execute([$userId, $nonceHash, $policyId]);
            if ($checkStmt->fetchColumn()) {
                Session::set(self::ACK_SESSION_KEY, true);
                return [
                    'success' => true,
                    'idempotent' => true,
                    'acknowledged' => true,
                    'policy_version' => $policyVersion,
                ];
            }

            Logger::error("PDPA acknowledgement persistence failure: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Clear acknowledgement session state.
     */
    public static function revokeSessionAcknowledgement(): void
    {
        Session::remove(self::ACK_SESSION_KEY);
        Session::remove(self::NONCE_SESSION_KEY);
    }
}
