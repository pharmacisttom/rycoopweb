<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Exceptions\SecurityGateException;
use PDO;

/**
 * Enterprise Dual-Control (Maker-Checker), Server-Side Step-Up MFA, and
 * Non-Transactional Restore Execution Security Gate for RYCOOPWEB 3.0.
 *
 * Security Invariants:
 * 1. Backend RBAC enforcement on every Maker, Checker, and Execution action.
 * 2. Server-side Step-Up MFA freshness verification (never trusts login-time MFA alone).
 * 3. Strict Maker != Checker separation of duties across ALL roles, including super_admin.
 * 4. Transactional pessimistic row locks (FOR UPDATE) + atomic CAS updates on approval_requests.
 * 5. Two-phase claim-before-execute state machine + durable filesystem journal for
 *    non-transactional SQL restore jobs.
 * 6. Restore execution remains disabled by default (BACKUP_RESTORE_EXECUTION_ENABLED=false)
 *    until backend enforcement and Manual Security UAT are signed off.
 * 7. Zero secrets (OTP, TOTP secret, recovery codes, passwords, tokens) ever stored in
 *    approval_requests, audit_logs, or API responses.
 */
class DualControlSecurityService
{
    /**
     * Catalog of Dual-Control protected actions and their required Maker / Checker / Execute permissions.
     */
    public const ACTION_POLICIES = [
        'backup.restore.request' => [
            'module' => 'backup',
            'maker_permissions' => ['backup.restore.request'],
            'checker_permissions' => ['backup.restore.approve'],
            'execute_permissions' => ['backup.restore.request', 'backup.restore.approve'],
            'default_ttl_seconds' => 3600,
        ],
        'backup.delete' => [
            'module' => 'backup',
            'maker_permissions' => ['backup.restore.request', 'backup.verify'],
            'checker_permissions' => ['backup.restore.approve'],
            'execute_permissions' => ['backup.restore.approve', 'backup.restore.request'],
            'default_ttl_seconds' => 3600,
        ],
        'roles.manage' => [
            'module' => 'roles',
            'maker_permissions' => ['roles.manage'],
            'checker_permissions' => ['roles.manage', 'permissions.manage'],
            'execute_permissions' => ['roles.manage'],
            'default_ttl_seconds' => 3600,
        ],
        'permissions.manage' => [
            'module' => 'permissions',
            'maker_permissions' => ['permissions.manage'],
            'checker_permissions' => ['permissions.manage'],
            'execute_permissions' => ['permissions.manage'],
            'default_ttl_seconds' => 3600,
        ],
        'security.sessions.revoke' => [
            'module' => 'security',
            'maker_permissions' => ['security.sessions.revoke'],
            'checker_permissions' => ['security.sessions.revoke', 'security.view'],
            'execute_permissions' => ['security.sessions.revoke'],
            'default_ttl_seconds' => 1800,
        ],
        'settings.update' => [
            'module' => 'settings',
            'maker_permissions' => ['settings.update'],
            'checker_permissions' => ['settings.update'],
            'execute_permissions' => ['settings.update'],
            'default_ttl_seconds' => 3600,
        ],
    ];

    private static ?string $customJournalPath = null;

    /**
     * Override durable restore execution journal path (used by isolated test suites).
     */
    public static function setJournalPath(?string $path): void
    {
        self::$customJournalPath = $path;
    }

    public static function getJournalPath(): string
    {
        if (self::$customJournalPath !== null) {
            return self::$customJournalPath;
        }
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/restore_execution_journal.jsonl';
    }

    /**
     * Verify a live 6-digit TOTP code for Step-Up MFA and record freshness in server session.
     * Enforces replay protection via users.two_factor_last_used_slice under row lock.
     */
    public static function verifyAndRecordStepUpMfa(
        int $userId,
        string $code,
        string $scope = 'general',
        string $ip = '127.0.0.1'
    ): array {
        $correlationId = AdminControlCenterService::correlationId();
        if (!Auth::check() || (int) Auth::id() !== $userId) {
            throw new SecurityGateException(
                'เซสชันไม่ถูกต้องหรือยังไม่ได้ผ่านการยืนยันตัวตนขั้นต้น',
                401,
                'UNAUTHORIZED'
            );
        }

        $cleanCode = trim($code);
        if (!preg_match('/^\d{6}$/', $cleanCode)) {
            throw new SecurityGateException(
                'กรุณากรอกรหัส Step-Up MFA 6 หลักที่เป็นตัวเลขเท่านั้น',
                422,
                'STEP_UP_MFA_FORMAT_INVALID'
            );
        }

        if (MfaRateLimiter::isRateLimited($userId, $ip)) {
            self::logSecurityAudit('security', 'step_up_mfa_rate_limited', (string) $userId, [
                'scope' => $scope,
                'ip' => $ip,
            ]);
            throw new SecurityGateException(
                'คุณกรอกรหัส Step-Up MFA ผิดเกินจำนวนที่กำหนด กรุณารอ 5 นาทีก่อนลองใหม่',
                429,
                'STEP_UP_MFA_RATE_LIMITED'
            );
        }

        $forUpdate = self::forUpdateClause();
        $ttlSeconds = max(60, (int) config('security.dual_control.step_up_ttl_seconds', 300));
        $now = time();

        $verifiedData = self::runInTransaction(function () use ($userId, $cleanCode, $forUpdate, $ip, $scope) {
            $user = Database::first(
                "SELECT id, email, status, deleted_at, session_version,
                        two_factor_enabled, two_factor_confirmed_at, two_factor_secret, two_factor_last_used_slice
                 FROM users
                 WHERE id = ?{$forUpdate}",
                [$userId]
            );

            if (!$user || ($user['status'] ?? '') !== 'active' || !empty($user['deleted_at'])) {
                throw new SecurityGateException('บัญชีผู้ใช้งานไม่พร้อมใช้งาน', 401, 'ACCOUNT_INACTIVE');
            }

            if ((int) ($user['two_factor_enabled'] ?? 0) !== 1 || empty($user['two_factor_confirmed_at']) || empty($user['two_factor_secret'])) {
                throw new SecurityGateException(
                    'บัญชีนี้ยังไม่ได้เปิดใช้งาน MFA จึงไม่สามารถทำรายการ Step-Up MFA ได้',
                    403,
                    'MFA_NOT_ENROLLED'
                );
            }

            $lastSlice = $user['two_factor_last_used_slice'] !== null ? (int) $user['two_factor_last_used_slice'] : null;
            $matchedSlice = null;

            $isValid = TwoFactorService::verifyCode(
                (string) $user['two_factor_secret'],
                $cleanCode,
                (int) config('security.two_factor.window', 1),
                $lastSlice,
                $matchedSlice
            );

            if (!$isValid || $matchedSlice === null) {
                MfaRateLimiter::recordFailedAttempt($userId, $ip);
                self::logSecurityAudit('security', 'step_up_mfa_failed', (string) $userId, [
                    'scope' => $scope,
                    'reason' => 'invalid_or_replayed_totp',
                ]);
                throw new SecurityGateException(
                    'รหัส Step-Up MFA 6 หลักไม่ถูกต้องหรือถูกใช้งานไปแล้ว (Replay Protection)',
                    403,
                    'STEP_UP_MFA_INVALID'
                );
            }

            $updated = Database::execute(
                "UPDATE users
                 SET two_factor_last_used_slice = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND (two_factor_last_used_slice IS NULL OR two_factor_last_used_slice < ?)",
                [$matchedSlice, $userId, $matchedSlice]
            );

            if ($updated !== 1) {
                throw new SecurityGateException(
                    'ตรวจพบการใช้รหัส OTP ซ้ำในเวลาเดียวกัน (Replay Attack Blocked)',
                    403,
                    'STEP_UP_MFA_REPLAY_BLOCKED'
                );
            }

            return [
                'session_version' => (int) ($user['session_version'] ?? 1),
                'matched_slice' => (int) $matchedSlice,
            ];
        });

        MfaRateLimiter::clear($userId, $ip);

        Session::start();
        Session::set('mfa_step_up_verified_at', $now);
        Session::set('mfa_step_up_expires_at', $now + $ttlSeconds);
        Session::set('mfa_step_up_user_id', $userId);
        Session::set('mfa_step_up_session_version', $verifiedData['session_version']);
        Session::set('mfa_step_up_slice', $verifiedData['matched_slice']);
        Session::set('mfa_step_up_scope', $scope);
        Session::set('mfa_step_up_consumed', false);

        self::logSecurityAudit('security', 'step_up_mfa_verified', (string) $userId, [
            'scope' => $scope,
            'verified_at_utc' => gmdate('Y-m-d\TH:i:s\Z', $now),
            'expires_at_utc' => gmdate('Y-m-d\TH:i:s\Z', $now + $ttlSeconds),
        ]);

        return [
            'verified' => true,
            'scope' => $scope,
            'verified_at' => $now,
            'expires_at' => $now + $ttlSeconds,
            'ttl_seconds' => $ttlSeconds,
            'correlation_id' => $correlationId,
        ];
    }

    /**
     * Assert that the user has a fresh, unconsumed server-side Step-Up MFA verification.
     * NEVER accepts login-time `2fa_verified` as sufficient for Step-Up!
     */
    public static function requireFreshStepUpMfa(
        int $userId,
        ?string $inlineCode = null,
        string $requiredScope = 'general',
        bool $consumeSingleUse = true,
        string $ip = '127.0.0.1'
    ): void {
        if (!Auth::check() || (int) Auth::id() !== $userId) {
            throw new SecurityGateException('กรุณาเข้าสู่ระบบและยืนยันตัวตนก่อนดำเนินการ', 401, 'UNAUTHORIZED');
        }

        if ($inlineCode !== null && trim($inlineCode) !== '') {
            self::verifyAndRecordStepUpMfa($userId, $inlineCode, $requiredScope, $ip);
            if ($consumeSingleUse) {
                Session::set('mfa_step_up_consumed', true);
            }
            return;
        }

        Session::start();
        $stepUpAt = (int) Session::get('mfa_step_up_verified_at', 0);
        $stepUpUserId = (int) Session::get('mfa_step_up_user_id', 0);
        $stepUpSlice = Session::get('mfa_step_up_slice');
        $consumed = (bool) Session::get('mfa_step_up_consumed', false);

        // Login-time MFA alone never sets mfa_step_up_verified_at or mfa_step_up_slice
        if ($stepUpAt <= 0 || $stepUpUserId !== $userId || $stepUpSlice === null) {
            self::logSecurityAudit('security', 'step_up_mfa_missing', (string) $userId, [
                'required_scope' => $requiredScope,
            ]);
            throw new SecurityGateException(
                'การดำเนินการนี้ต้องยืนยันตัวตนขั้นสูง (Step-Up MFA) ใหม่ฝั่งเซิร์ฟเวอร์ ไม่สามารถใช้สถานะ MFA ตอนเข้าสู่ระบบได้',
                403,
                'STEP_UP_MFA_REQUIRED'
            );
        }

        $ttlSeconds = max(60, (int) config('security.dual_control.step_up_ttl_seconds', 300));
        if ((time() - $stepUpAt) > $ttlSeconds) {
            self::logSecurityAudit('security', 'step_up_mfa_expired', (string) $userId, [
                'required_scope' => $requiredScope,
                'age_seconds' => time() - $stepUpAt,
            ]);
            throw new SecurityGateException(
                'การยืนยันตัวตนขั้นสูง (Step-Up MFA) หมดอายุแล้ว กรุณากรอกรหัส OTP 6 หลักใหม่อีกครั้ง',
                403,
                'STEP_UP_MFA_EXPIRED'
            );
        }

        if ($consumeSingleUse && $consumed) {
            self::logSecurityAudit('security', 'step_up_mfa_replay_blocked', (string) $userId, [
                'required_scope' => $requiredScope,
            ]);
            throw new SecurityGateException(
                'การยืนยันตัวตนขั้นสูง (Step-Up MFA) ถูกใช้งานไปแล้วสำหรับคำสั่งก่อนหน้า กรุณายืนยันรหัส OTP 6 หลักใหม่',
                403,
                'STEP_UP_MFA_ALREADY_CONSUMED'
            );
        }

        // Verify session_version against DB to prevent revoked session from using Step-Up
        $dbSessionVersion = (int) (Database::value("SELECT session_version FROM users WHERE id = ? AND status = 'active' AND deleted_at IS NULL", [$userId]) ?? 1);
        $stepUpSessionVersion = (int) Session::get('mfa_step_up_session_version', 1);
        if ($dbSessionVersion !== $stepUpSessionVersion) {
            throw new SecurityGateException('เซสชันถูกเพิกถอนแล้ว กรุณาเข้าสู่ระบบใหม่', 401, 'SESSION_REVOKED');
        }

        if ($consumeSingleUse) {
            Session::set('mfa_step_up_consumed', true);
        }
    }

    /**
     * Check whether a user has Maker permission to initiate a Dual-Control request.
     * Note: Checker-only roles (e.g. security_admin on backup.restore.request) are denied!
     */
    public static function hasMakerPermission(int $userId, string $actionKey): bool
    {
        $policy = self::ACTION_POLICIES[$actionKey] ?? null;
        if ($policy === null) {
            return false;
        }
        return self::userHasAnyExplicitOrRolePermission($userId, $policy['maker_permissions']);
    }

    /**
     * Check whether a user has Checker permission to approve/reject a Dual-Control request.
     * Note: Maker-only roles (e.g. it_admin on backup.restore.request) are strictly denied!
     */
    public static function hasCheckerPermission(int $userId, string $actionKey): bool
    {
        $policy = self::ACTION_POLICIES[$actionKey] ?? null;
        if ($policy === null) {
            return false;
        }
        return self::userHasAnyExplicitOrRolePermission($userId, $policy['checker_permissions']);
    }

    /**
     * Check whether a user has Execution permission for an approved Dual-Control request.
     */
    public static function hasExecutePermission(int $userId, string $actionKey): bool
    {
        $policy = self::ACTION_POLICIES[$actionKey] ?? null;
        if ($policy === null) {
            if (preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)+$/', $actionKey)) {
                return self::userHasAnyExplicitOrRolePermission($userId, [$actionKey]);
            }
            return false;
        }
        return self::userHasAnyExplicitOrRolePermission($userId, $policy['execute_permissions']);
    }

    /**
     * Create a new Dual-Control approval request (Maker action).
     */
    public static function createRequest(
        int $makerUserId,
        string $actionKey,
        string $reason,
        ?string $targetType = null,
        ?string $targetId = null,
        array $payload = [],
        ?int $ttlSeconds = null,
        ?string $backupDir = null
    ): array {
        if (!Auth::check() || (int) Auth::id() !== $makerUserId) {
            throw new SecurityGateException('กรุณาเข้าสู่ระบบก่อนสร้างคำขออนุมัติ', 401, 'UNAUTHORIZED');
        }

        $policy = self::ACTION_POLICIES[$actionKey] ?? null;
        if ($policy === null) {
            throw new SecurityGateException('ประเภทคำขออนุมัติ (action_key) ไม่ถูกต้องหรือไม่ได้รับอนุญาต', 422, 'INVALID_ACTION_KEY');
        }

        if (!self::hasMakerPermission($makerUserId, $actionKey)) {
            self::logSecurityAudit('dual_control', 'maker_permission_denied', $actionKey, [
                'maker_user_id' => $makerUserId,
                'action_key' => $actionKey,
                'target_id' => $targetId,
            ]);
            throw new SecurityGateException(
                'คุณไม่มีสิทธิ์สร้างคำขออนุมัติสำหรับรายการนี้ (Maker Permission Denied)',
                403,
                'MAKER_PERMISSION_DENIED'
            );
        }

        $cleanReason = trim($reason);
        if (mb_strlen($cleanReason) < 10 || mb_strlen($cleanReason) > 2000) {
            throw new SecurityGateException(
                'กรุณาระบุเหตุผลและความจำเป็นในการดำเนินการอย่างน้อย 10 ตัวอักษร',
                422,
                'INVALID_REASON'
            );
        }

        $sanitizedPayload = is_array(AdminControlCenterService::redactMetadata($payload))
            ? AdminControlCenterService::redactMetadata($payload)
            : [];

        // Special validation & SHA-256 pinning for backup restore/delete requests
        if ($actionKey === 'backup.restore.request' || $actionKey === 'backup.delete') {
            $filename = basename(trim((string) $targetId));
            $pattern = $actionKey === 'backup.restore.request'
                ? '/^[a-zA-Z0-9_\-\.]+\.sql$/i'
                : '/^[a-zA-Z0-9_\-\.]+\.(sql|zip)$/i';

            if ($filename === '' || !preg_match($pattern, $filename)) {
                throw new SecurityGateException(
                    'ชื่อไฟล์สำรองข้อมูลไม่ถูกต้อง (สำหรับการกู้คืนอนุญาตเฉพาะไฟล์ .sql เท่านั้น)',
                    422,
                    'INVALID_BACKUP_FILENAME'
                );
            }

            $resolvedBackupDir = $backupDir ?: dirname(__DIR__, 2) . '/storage/backups';
            $realBackupDir = realpath($resolvedBackupDir);
            $filePath = $realBackupDir ? realpath($realBackupDir . DIRECTORY_SEPARATOR . $filename) : false;

            if (!$realBackupDir || !$filePath || !is_file($filePath) || !str_starts_with($filePath, $realBackupDir)) {
                throw new SecurityGateException(
                    'ไม่พบไฟล์สำรองข้อมูลที่ระบุในคลังสำรองข้อมูลของระบบ',
                    404,
                    'BACKUP_FILE_NOT_FOUND'
                );
            }

            $fileSize = (int) filesize($filePath);
            if ($fileSize <= 0) {
                throw new SecurityGateException('ไฟล์สำรองข้อมูลว่างเปล่า ไม่สามารถสร้างคำขอได้', 422, 'BACKUP_FILE_EMPTY');
            }

            $targetType = 'backup_archive';
            $targetId = $filename;
            $fileHash = hash_file('sha256', $filePath);
            $sanitizedPayload['filename'] = $filename;
            $sanitizedPayload['sha256'] = $fileHash;
            $sanitizedPayload['file_sha256'] = $fileHash;
            $sanitizedPayload['size_bytes'] = $fileSize;
        }

        $defaultTtl = (int) ($policy['default_ttl_seconds'] ?? config('security.dual_control.approval_ttl_seconds', 3600));
        $effectiveTtl = $ttlSeconds !== null ? max(60, min(86400, $ttlSeconds)) : $defaultTtl;
        $nowTs = time();
        $createdAt = date('Y-m-d H:i:s', $nowTs);
        $expiresAt = date('Y-m-d H:i:s', $nowTs + $effectiveTtl);
        $requestNo = sprintf('APR-%s-%s', date('Ymd-His', $nowTs), strtoupper(bin2hex(random_bytes(3))));

        $sanitizedPayload['correlation_id'] = AdminControlCenterService::correlationId();
        $sanitizedPayload['execution_state'] = 'not_started';

        $id = (int) Database::insert(
            "INSERT INTO approval_requests (
                request_no, module, action_key, target_type, target_id,
                payload_json, reason, status, requested_by, expires_at, created_at, updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)",
            [
                $requestNo,
                $policy['module'],
                $actionKey,
                $targetType,
                $targetId,
                json_encode($sanitizedPayload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $cleanReason,
                $makerUserId,
                $expiresAt,
                $createdAt,
                $createdAt,
            ]
        );

        self::logSecurityAudit('dual_control', 'request_created', $requestNo, [
            'approval_request_id' => $id,
            'module' => $policy['module'],
            'action_key' => $actionKey,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'requested_by' => $makerUserId,
            'expires_at' => $expiresAt,
        ]);

        return self::getRequestById($id);
    }

    /**
     * Approve a pending Dual-Control request (Checker action).
     *
     * Enforces:
     * - Backend Checker RBAC permission
     * - Fresh server-side Step-Up MFA
     * - Pessimistic row lock (FOR UPDATE) + atomic CAS update
     * - Maker !== Checker across ALL roles (including super_admin)
     * - Expiration check & automatic transition to 'expired'
     * - Optional target_id / action_key binding check
     */
    public static function approveRequest(
        int $requestId,
        int $checkerUserId,
        ?string $reviewNote = null,
        ?string $stepUpCode = null,
        ?string $expectedActionKey = null,
        ?string $expectedTargetId = null,
        string $ip = '127.0.0.1'
    ): array {
        if (!Auth::check() || (int) Auth::id() !== $checkerUserId) {
            throw new SecurityGateException('กรุณาเข้าสู่ระบบก่อนอนุมัติคำขอ', 401, 'UNAUTHORIZED');
        }

        $forUpdate = self::forUpdateClause();
        $expiredDetected = false;

        $result = Database::transaction(function () use (
            $requestId,
            $checkerUserId,
            $reviewNote,
            $stepUpCode,
            $expectedActionKey,
            $expectedTargetId,
            $ip,
            $forUpdate,
            &$expiredDetected
        ) {
            $row = Database::first(
                "SELECT * FROM approval_requests WHERE id = ?{$forUpdate}",
                [$requestId]
            );

            if (!$row) {
                throw new SecurityGateException('ไม่พบคำขออนุมัติที่ระบุ', 404, 'APPROVAL_NOT_FOUND');
            }

            $actionKey = (string) $row['action_key'];

            // 1. Verify Checker backend permission for this action_key FIRST
            if (!self::hasCheckerPermission($checkerUserId, $actionKey)) {
                self::logSecurityAudit('dual_control', 'checker_permission_denied', (string) $row['request_no'], [
                    'approval_request_id' => $requestId,
                    'checker_user_id' => $checkerUserId,
                    'action_key' => $actionKey,
                ]);
                throw new SecurityGateException(
                    'คุณไม่มีสิทธิ์อนุมัติคำขอประเภทนี้ (Checker Permission Denied)',
                    403,
                    'CHECKER_PERMISSION_DENIED'
                );
            }

            // 2. Ironclad Dual-Control Invariant: Maker MUST NOT be Checker (even for super_admin!)
            if ((int) $row['requested_by'] === $checkerUserId) {
                self::logSecurityAudit('dual_control', 'self_approval_blocked', (string) $row['request_no'], [
                    'approval_request_id' => $requestId,
                    'user_id' => $checkerUserId,
                    'action_key' => $actionKey,
                ]);
                throw new SecurityGateException(
                    'กฎความปลอดภัย Dual-Control ห้ามผู้สร้างคำขอ (Maker) อนุมัติคำขอของตนเอง (Checker) ในทุกกรณี รวมถึง Super Admin',
                    403,
                    'SELF_APPROVAL_FORBIDDEN'
                );
            }

            // 3. Verify Status === 'pending' (prevents Duplicate Approval / Replay / Race Condition)
            if (($row['status'] ?? '') !== 'pending' || $row['reviewed_by'] !== null) {
                self::logSecurityAudit('dual_control', 'duplicate_or_invalid_status_approval_blocked', (string) $row['request_no'], [
                    'approval_request_id' => $requestId,
                    'current_status' => $row['status'] ?? '',
                    'attempted_by' => $checkerUserId,
                ]);
                throw new SecurityGateException(
                    "ไม่สามารถอนุมัติคำขอที่มีสถานะ '{$row['status']}' ได้ (คำขอถูกดำเนินการไปแล้วหรือไม่ได้อยู่ในสถานะรออนุมัติ)",
                    409,
                    'APPROVAL_NOT_PENDING'
                );
            }

            // 4. Verify Expiration
            if (!empty($row['expires_at']) && strtotime((string) $row['expires_at']) <= time()) {
                Database::execute(
                    "UPDATE approval_requests SET status = 'expired', updated_at = ? WHERE id = ? AND status = 'pending'",
                    [date('Y-m-d H:i:s'), $requestId]
                );
                $expiredDetected = true;
                return $row;
            }

            // 5. Verify Action & Target Binding if specified by caller
            if ($expectedActionKey !== null && $expectedActionKey !== '' && $expectedActionKey !== $actionKey) {
                throw new SecurityGateException('ประเภทการกระทำ (action_key) ไม่ตรงกับคำขอที่รออนุมัติ', 409, 'ACTION_MISMATCH');
            }
            if ($expectedTargetId !== null && $expectedTargetId !== '' && $expectedTargetId !== (string) ($row['target_id'] ?? '')) {
                throw new SecurityGateException('เป้าหมาย (target_id) ไม่ตรงกับคำขอที่รออนุมัติ', 409, 'TARGET_MISMATCH');
            }

            // 6. Enforce Server-Side Step-Up MFA Freshness
            self::requireFreshStepUpMfa($checkerUserId, $stepUpCode, 'dual_control_approve:' . $actionKey, true, $ip);

            $nowStr = date('Y-m-d H:i:s');
            $cleanNote = $reviewNote !== null ? mb_substr(trim($reviewNote), 0, 1000) : 'อนุมัติตามขั้นตอน Dual-Control';

            $updated = Database::execute(
                "UPDATE approval_requests
                 SET status = 'approved',
                     reviewed_by = ?,
                     reviewed_at = ?,
                     review_note = ?,
                     updated_at = ?
                 WHERE id = ? AND status = 'pending' AND reviewed_by IS NULL",
                [$checkerUserId, $nowStr, $cleanNote, $nowStr, $requestId]
            );

            if ($updated !== 1) {
                throw new SecurityGateException(
                    'ตรวจพบการอนุมัติซ้อนพร้อมกัน (Concurrent Approval Conflict)',
                    409,
                    'CONCURRENT_APPROVAL_CONFLICT'
                );
            }

            return $row;
        });

        if ($expiredDetected) {
            self::logSecurityAudit('dual_control', 'expired_approval_blocked', (string) ($result['request_no'] ?? $requestId), [
                'approval_request_id' => $requestId,
                'expires_at' => $result['expires_at'] ?? null,
                'attempted_by' => $checkerUserId,
            ]);
            throw new SecurityGateException(
                'คำขออนุมัตินี้หมดอายุแล้ว ไม่สามารถอนุมัติได้ กรุณาสร้างคำขอใหม่',
                410,
                'APPROVAL_EXPIRED'
            );
        }

        self::logSecurityAudit('dual_control', 'request_approved', (string) $result['request_no'], [
            'approval_request_id' => $requestId,
            'action_key' => $result['action_key'],
            'target_id' => $result['target_id'],
            'requested_by' => (int) $result['requested_by'],
            'reviewed_by' => $checkerUserId,
        ]);

        return self::getRequestById($requestId);
    }

    /**
     * Reject a pending Dual-Control request (Checker action).
     */
    public static function rejectRequest(
        int $requestId,
        int $checkerUserId,
        string $reviewNote
    ): array {
        if (!Auth::check() || (int) Auth::id() !== $checkerUserId) {
            throw new SecurityGateException('กรุณาเข้าสู่ระบบก่อนดำเนินการ', 401, 'UNAUTHORIZED');
        }

        $cleanNote = trim($reviewNote);
        if (mb_strlen($cleanNote) < 5) {
            throw new SecurityGateException('กรุณาระบุเหตุผลในการปฏิเสธคำขออย่างน้อย 5 ตัวอักษร', 422, 'INVALID_REVIEW_NOTE');
        }

        $forUpdate = self::forUpdateClause();

        Database::transaction(function () use ($requestId, $checkerUserId, $cleanNote, $forUpdate) {
            $row = Database::first("SELECT * FROM approval_requests WHERE id = ?{$forUpdate}", [$requestId]);
            if (!$row) {
                throw new SecurityGateException('ไม่พบคำขออนุมัติที่ระบุ', 404, 'APPROVAL_NOT_FOUND');
            }

            if (!self::hasCheckerPermission($checkerUserId, (string) $row['action_key'])) {
                throw new SecurityGateException('คุณไม่มีสิทธิ์ตรวจสอบคำขอประเภทนี้', 403, 'CHECKER_PERMISSION_DENIED');
            }

            if (($row['status'] ?? '') !== 'pending') {
                throw new SecurityGateException("คำขอไม่ได้อยู่ในสถานะ pending (สถานะปัจจุบัน: {$row['status']})", 409, 'APPROVAL_NOT_PENDING');
            }

            $nowStr = date('Y-m-d H:i:s');
            $updated = Database::execute(
                "UPDATE approval_requests
                 SET status = 'rejected', reviewed_by = ?, reviewed_at = ?, review_note = ?, updated_at = ?
                 WHERE id = ? AND status = 'pending'",
                [$checkerUserId, $nowStr, $cleanNote, $nowStr, $requestId]
            );

            if ($updated !== 1) {
                throw new SecurityGateException('ไม่สามารถปฏิเสธคำขอได้เนื่องจากสถานะเปลี่ยนแปลงแล้ว', 409, 'CONCURRENT_STATE_CHANGE');
            }

            self::logSecurityAudit('dual_control', 'request_rejected', (string) $row['request_no'], [
                'approval_request_id' => $requestId,
                'action_key' => $row['action_key'],
                'rejected_by' => $checkerUserId,
            ]);
        });

        return self::getRequestById($requestId);
    }

    /**
     * Cancel a pending Dual-Control request by its Maker.
     */
    public static function cancelRequest(int $requestId, int $userId): array
    {
        if (!Auth::check() || (int) Auth::id() !== $userId) {
            throw new SecurityGateException('กรุณาเข้าสู่ระบบก่อนดำเนินการ', 401, 'UNAUTHORIZED');
        }

        $forUpdate = self::forUpdateClause();

        Database::transaction(function () use ($requestId, $userId, $forUpdate) {
            $row = Database::first("SELECT * FROM approval_requests WHERE id = ?{$forUpdate}", [$requestId]);
            if (!$row) {
                throw new SecurityGateException('ไม่พบคำขออนุมัติที่ระบุ', 404, 'APPROVAL_NOT_FOUND');
            }

            if ((int) $row['requested_by'] !== $userId) {
                throw new SecurityGateException('เฉพาะผู้สร้างคำขอ (Maker) เท่านั้นที่สามารถยกเลิกคำขอของตนเองได้', 403, 'NOT_REQUEST_MAKER');
            }

            if (($row['status'] ?? '') !== 'pending') {
                throw new SecurityGateException("ไม่สามารถยกเลิกคำขอที่มีสถานะ '{$row['status']}' ได้", 409, 'APPROVAL_NOT_PENDING');
            }

            $nowStr = date('Y-m-d H:i:s');
            Database::execute(
                "UPDATE approval_requests SET status = 'cancelled', updated_at = ? WHERE id = ? AND status = 'pending'",
                [$nowStr, $requestId]
            );

            self::logSecurityAudit('dual_control', 'request_cancelled', (string) $row['request_no'], [
                'approval_request_id' => $requestId,
                'cancelled_by' => $userId,
            ]);
        });

        return self::getRequestById($requestId);
    }

    /**
     * Two-Phase Non-Transactional Restore Execution Security Gate.
     *
     * Phase 1 (Inside DB Transaction + Row Lock):
     *   - Verifies Executor RBAC permission (`backup.restore.request` / `backup.restore.approve`).
     *   - Verifies fresh server-side Step-Up MFA.
     *   - Locks `approval_requests` row (`FOR UPDATE`).
     *   - Verifies durable filesystem journal (`restore_execution_journal.jsonl`) to block replay
     *     even if a previous SQL dump overwrote database rows.
     *   - Verifies `status === 'approved'`, `executed_at === null`, `requested_by !== reviewed_by`.
     *   - Verifies `action_key === 'backup.restore.request'` and `target_id === $filename`.
     *   - Verifies `expires_at` and post-approval execution window (`execution_window_seconds`).
     *   - Verifies backup file SHA-256 hash matches `payload_json['sha256']` (TOCTOU protection).
     *   - Checks `security.dual_control.backup_restore_execution_enabled` (default `false`!).
     *     If `false`, logs policy block and throws `423 RESTORE_EXECUTION_DISABLED_BY_POLICY` BEFORE mutating DB.
     *   - If `true`, atomically claims `executed_at = NOW()` and commits Phase 1 BEFORE running SQL dump.
     *
     * Phase 2 (Non-Transactional SQL Restore Job + Post-Restore Reconciliation):
     *   - Executes the restore callback/SQL, records completion in durable journal and reconciles
     *     `approval_requests.executed_at` so replay is impossible after restore.
     */
    public static function executeApprovedRestore(
        int $approvalRequestId,
        int $executorUserId,
        string $filename,
        ?string $stepUpCode = null,
        ?string $backupDir = null,
        callable|bool|null $customRestoreExecutor = null,
        string $ip = '127.0.0.1',
        ?bool $executionEnabledOverride = null
    ): array {
        if (is_bool($customRestoreExecutor)) {
            $executionEnabledOverride = $customRestoreExecutor;
            $customRestoreExecutor = null;
        }
        $correlationId = AdminControlCenterService::correlationId();

        if (!Auth::check() || (int) Auth::id() !== $executorUserId) {
            throw new SecurityGateException('กรุณาเข้าสู่ระบบก่อนดำเนินการกู้คืนข้อมูล', 401, 'UNAUTHORIZED');
        }

        // 1. Backend RBAC Permission Check
        if (!self::hasExecutePermission($executorUserId, 'backup.restore.request')) {
            self::logSecurityAudit('backup', 'restore_permission_denied', $filename, [
                'approval_request_id' => $approvalRequestId,
                'executor_user_id' => $executorUserId,
            ]);
            throw new SecurityGateException(
                'คุณไม่มีสิทธิ์ดำเนินการกู้คืนฐานข้อมูล (Restore Permission Denied)',
                403,
                'RESTORE_PERMISSION_DENIED'
            );
        }

        if ($approvalRequestId <= 0) {
            self::logSecurityAudit('backup', 'restore_missing_approval_blocked', $filename, [
                'executor_user_id' => $executorUserId,
            ]);
            throw new SecurityGateException(
                'ไม่อนุญาตให้กู้คืนข้อมูลโดยไม่มีรหัสคำขอที่ผ่านการอนุมัติแบบ Dual-Control (approval_request_id required)',
                403,
                'APPROVAL_REQUIRED'
            );
        }

        // 2. Validate backup filename and physical file before acquiring DB lock
        $cleanFilename = basename(trim($filename));
        if ($cleanFilename === '' || !preg_match('/^[a-zA-Z0-9_\-\.]+\.sql$/i', $cleanFilename)) {
            throw new SecurityGateException(
                'สามารถกู้คืนได้เฉพาะไฟล์ฐานข้อมูล (.sql) ที่อยู่ในคลังสำรองข้อมูลเท่านั้น',
                422,
                'INVALID_BACKUP_FILENAME'
            );
        }

        $resolvedBackupDir = $backupDir ?: dirname(__DIR__, 2) . '/storage/backups';
        $realBackupDir = realpath($resolvedBackupDir);
        $filePath = $realBackupDir ? realpath($realBackupDir . DIRECTORY_SEPARATOR . $cleanFilename) : false;

        if (!$realBackupDir || !$filePath || !is_file($filePath) || !str_starts_with($filePath, $realBackupDir)) {
            throw new SecurityGateException(
                'ไม่พบไฟล์สำรองข้อมูลสำหรับกู้คืนในคลังข้อมูลที่ได้รับอนุญาต',
                404,
                'BACKUP_FILE_NOT_FOUND'
            );
        }

        $currentSha256 = hash_file('sha256', $filePath);
        $forUpdate = self::forUpdateClause();
        $expiredDuringCheck = false;

        // 3. Phase 1: Transactional Verification & Atomic Claim under Row Lock
        $claimedRow = Database::transaction(function () use (
            $approvalRequestId,
            $executorUserId,
            $cleanFilename,
            $currentSha256,
            $stepUpCode,
            $ip,
            $forUpdate,
            $correlationId,
            $executionEnabledOverride,
            &$expiredDuringCheck
        ) {
            $row = Database::first(
                "SELECT * FROM approval_requests WHERE id = ?{$forUpdate}",
                [$approvalRequestId]
            );

            if (!$row) {
                throw new SecurityGateException('ไม่พบคำขออนุมัติสำหรับการกู้คืนข้อมูล', 404, 'APPROVAL_NOT_FOUND');
            }

            $requestNo = (string) $row['request_no'];

            // 3.1 Durable Journal Replay Check (protects against post-restore DB state rollback)
            if (self::isRequestConsumedInJournal($approvalRequestId, $requestNo)) {
                self::logSecurityAudit('backup', 'restore_replay_blocked_by_journal', $requestNo, [
                    'approval_request_id' => $approvalRequestId,
                    'executor_user_id' => $executorUserId,
                ]);
                throw new SecurityGateException(
                    'คำขออนุมัตินี้ถูกใช้ดำเนินการไปแล้ว (ตรวจพบใน Durable Execution Journal - Replay Blocked)',
                    409,
                    'APPROVAL_ALREADY_EXECUTED'
                );
            }

            // 3.2 Single-use DB column check
            $payload = json_decode((string) ($row['payload_json'] ?? '{}'), true);
            if (!is_array($payload)) {
                $payload = [];
            }

            if (!empty($row['executed_at']) || in_array($payload['execution_state'] ?? '', ['claimed', 'completed'], true)) {
                self::logSecurityAudit('backup', 'restore_replay_blocked', $requestNo, [
                    'approval_request_id' => $approvalRequestId,
                    'executed_at' => $row['executed_at'] ?? null,
                    'executor_user_id' => $executorUserId,
                ]);
                throw new SecurityGateException(
                    'คำขออนุมัตินี้ถูกดำเนินการไปแล้ว ไม่สามารถใช้ซ้ำได้ (Single-Use Approval Enforcement)',
                    409,
                    'APPROVAL_ALREADY_EXECUTED'
                );
            }

            // 3.3 Approval Status Check
            if (($row['status'] ?? '') !== 'approved') {
                self::logSecurityAudit('backup', 'restore_unapproved_request_blocked', $requestNo, [
                    'approval_request_id' => $approvalRequestId,
                    'status' => $row['status'] ?? '',
                    'executor_user_id' => $executorUserId,
                ]);
                throw new SecurityGateException(
                    "ไม่อนุญาตให้กู้คืนข้อมูลเนื่องจากคำขออยู่ในสถานะ '{$row['status']}' (ต้องเป็น 'approved' เท่านั้น)",
                    403,
                    'APPROVAL_NOT_APPROVED'
                );
            }

            // 3.4 Verify Maker !== Checker & Non-Null Checker
            if (empty($row['reviewed_by']) || (int) $row['requested_by'] === (int) $row['reviewed_by']) {
                throw new SecurityGateException(
                    'คำขออนุมัติไม่ผ่านเงื่อนไข Dual-Control (ผู้สร้างคำขอและผู้อนุมัติต้องเป็นคนละบัญชี)',
                    403,
                    'DUAL_CONTROL_VIOLATION'
                );
            }

            // 3.5 Verify Action & Target Binding
            if (($row['action_key'] ?? '') !== 'backup.restore.request') {
                throw new SecurityGateException(
                    'คำขออนุมัตินี้ไม่ใช่คำขอสำหรับการกู้คืนฐานข้อมูล (Action Mismatch)',
                    403,
                    'ACTION_MISMATCH'
                );
            }

            if ((string) ($row['target_id'] ?? '') !== $cleanFilename) {
                self::logSecurityAudit('backup', 'restore_target_mismatch_blocked', $requestNo, [
                    'approved_target' => $row['target_id'] ?? null,
                    'requested_target' => $cleanFilename,
                    'executor_user_id' => $executorUserId,
                ]);
                throw new SecurityGateException(
                    'ชื่อไฟล์ที่สั่งกู้คืนไม่ตรงกับไฟล์ที่ได้รับการอนุมัติในคำขอ (Target Mismatch)',
                    403,
                    'TARGET_MISMATCH'
                );
            }

            // 3.6 Verify Expiration & Post-Approval Execution Window
            $nowTs = time();
            if (!empty($row['expires_at']) && strtotime((string) $row['expires_at']) <= $nowTs) {
                Database::execute(
                    "UPDATE approval_requests SET status = 'expired', updated_at = ? WHERE id = ?",
                    [date('Y-m-d H:i:s', $nowTs), $approvalRequestId]
                );
                $expiredDuringCheck = true;
                return $row;
            }

            $execWindow = max(60, (int) config('security.dual_control.execution_window_seconds', 1800));
            if (!empty($row['reviewed_at']) && ($nowTs - strtotime((string) $row['reviewed_at'])) > $execWindow) {
                Database::execute(
                    "UPDATE approval_requests SET status = 'expired', updated_at = ? WHERE id = ?",
                    [date('Y-m-d H:i:s', $nowTs), $approvalRequestId]
                );
                $expiredDuringCheck = true;
                return $row;
            }

            // 3.7 Verify Backup File SHA-256 Integrity (prevents TOCTOU file replacement on disk)
            if (!empty($payload['sha256']) && !hash_equals((string) $payload['sha256'], (string) $currentSha256)) {
                self::logSecurityAudit('backup', 'restore_file_integrity_mismatch', $requestNo, [
                    'approval_request_id' => $approvalRequestId,
                    'filename' => $cleanFilename,
                    'expected_sha256' => $payload['sha256'],
                    'actual_sha256' => $currentSha256,
                ]);
                throw new SecurityGateException(
                    'ตรวจพบการเปลี่ยนแปลงเนื้อหาไฟล์สำรองหลังการอนุมัติ (SHA-256 Checksum Mismatch)',
                    409,
                    'BACKUP_FILE_INTEGRITY_MISMATCH'
                );
            }

            // 3.8 Require Fresh Server-Side Step-Up MFA for Restore Execution
            self::requireFreshStepUpMfa($executorUserId, $stepUpCode, 'backup_restore_execute', true, $ip);

            // 3.9 Enforce Policy Kill-Switch (Disabled until Backend Enforcement & Manual Security UAT pass)
            $executionEnabled = $executionEnabledOverride !== null
                ? $executionEnabledOverride
                : self::isRestoreExecutionEnabled();
            if (!$executionEnabled) {
                self::logSecurityAudit('backup', 'restore_blocked_by_policy_gate', $requestNo, [
                    'approval_request_id' => $approvalRequestId,
                    'filename' => $cleanFilename,
                    'executor_user_id' => $executorUserId,
                    'policy' => 'BACKUP_RESTORE_EXECUTION_ENABLED=false',
                ]);
                throw new SecurityGateException(
                    'การรันคำสั่ง Restore จริงถูกปิดไว้ตามนโยบายความปลอดภัย (ต้องผ่าน Manual Security UAT และเปิดใช้งาน BACKUP_RESTORE_EXECUTION_ENABLED ก่อน)',
                    423,
                    'RESTORE_EXECUTION_DISABLED_BY_POLICY'
                );
            }

            // 3.10 Atomically claim execution BEFORE releasing transaction lock
            $nowStr = date('Y-m-d H:i:s', $nowTs);
            $payload['execution_state'] = 'claimed';
            $payload['executed_by'] = $executorUserId;
            $payload['claimed_at_utc'] = gmdate('Y-m-d\TH:i:s\Z', $nowTs);
            $payload['execution_correlation_id'] = $correlationId;

            $claimed = Database::execute(
                "UPDATE approval_requests
                 SET executed_at = ?, payload_json = ?, updated_at = ?
                 WHERE id = ? AND status = 'approved' AND executed_at IS NULL",
                [
                    $nowStr,
                    json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    $nowStr,
                    $approvalRequestId,
                ]
            );

            if ($claimed !== 1) {
                throw new SecurityGateException(
                    'ตรวจพบการสั่งกู้คืนข้อมูลซ้อนพร้อมกัน (Concurrent Execution Conflict)',
                    409,
                    'CONCURRENT_EXECUTION_CONFLICT'
                );
            }

            // Write durable pre-execution journal record before Phase 1 commits
            self::appendJournalEntry([
                'phase' => 'CLAIMED',
                'approval_request_id' => $approvalRequestId,
                'request_no' => $requestNo,
                'filename' => $cleanFilename,
                'sha256' => $currentSha256,
                'requested_by' => (int) $row['requested_by'],
                'reviewed_by' => (int) $row['reviewed_by'],
                'executed_by' => $executorUserId,
                'correlation_id' => $correlationId,
                'timestamp_utc' => gmdate('Y-m-d\TH:i:s\Z', $nowTs),
            ]);

            $row['executed_at'] = $nowStr;
            $row['payload_parsed'] = $payload;
            return $row;
        });

        if ($expiredDuringCheck) {
            self::logSecurityAudit('backup', 'restore_expired_approval_blocked', (string) ($claimedRow['request_no'] ?? $approvalRequestId), [
                'approval_request_id' => $approvalRequestId,
                'executor_user_id' => $executorUserId,
            ]);
            throw new SecurityGateException(
                'คำขออนุมัติหมดอายุก่อนการดำเนินการกู้คืนข้อมูล กรุณาสร้างคำขอและอนุมัติใหม่',
                410,
                'APPROVAL_EXPIRED'
            );
        }

        // 4. Phase 2: Non-Transactional Restore Job Execution & Post-Restore Reconciliation
        $requestNo = (string) $claimedRow['request_no'];
        $executedAtStr = (string) $claimedRow['executed_at'];
        $payload = $claimedRow['payload_parsed'];

        try {
            $sqlContent = (string) file_get_contents($filePath);
            if (trim($sqlContent) === '') {
                throw new \RuntimeException('ไฟล์สำรองฐานข้อมูลว่างเปล่า');
            }

            if ($customRestoreExecutor !== null) {
                $customRestoreExecutor($filePath, $sqlContent);
            } else {
                $pdo = Database::connect();
                $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
                if ($driver === 'mysql') {
                    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
                    $pdo->exec('SET FOREIGN_KEY_CHECKS=0;');
                    $pdo->exec($sqlContent);
                    $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
                } else {
                    $pdo->exec($sqlContent);
                }
            }

            $payload['execution_state'] = 'completed';
            $payload['completed_at_utc'] = gmdate('Y-m-d\TH:i:s\Z');

            // Re-stamp approval_requests in case the SQL dump restored an older state of approval_requests
            try {
                Database::execute(
                    "UPDATE approval_requests
                     SET status = 'approved', executed_at = ?, payload_json = ?, updated_at = ?
                     WHERE id = ?",
                    [
                        $executedAtStr,
                        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        date('Y-m-d H:i:s'),
                        $approvalRequestId,
                    ]
                );
            } catch (\Throwable $reconcileErr) {
                Logger::error('Post-restore approval_requests reconciliation warning: ' . $reconcileErr->getMessage());
            }

            self::appendJournalEntry([
                'phase' => 'COMPLETED',
                'approval_request_id' => $approvalRequestId,
                'request_no' => $requestNo,
                'filename' => $cleanFilename,
                'sha256' => $currentSha256,
                'executed_by' => $executorUserId,
                'correlation_id' => $correlationId,
                'timestamp_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            ]);

            self::logSecurityAudit('backup', 'restore_executed_successfully', $requestNo, [
                'approval_request_id' => $approvalRequestId,
                'filename' => $cleanFilename,
                'sha256' => $currentSha256,
                'requested_by' => (int) $claimedRow['requested_by'],
                'reviewed_by' => (int) $claimedRow['reviewed_by'],
                'executed_by' => $executorUserId,
            ]);

            return [
                'executed' => true,
                'request_id' => $approvalRequestId,
                'approval_request_id' => $approvalRequestId,
                'request_no' => $requestNo,
                'filename' => $cleanFilename,
                'sha256' => $currentSha256,
                'executed_at' => $executedAtStr,
                'correlation_id' => $correlationId,
            ];
        } catch (\Throwable $e) {
            $payload['execution_state'] = 'failed';
            $payload['failed_at_utc'] = gmdate('Y-m-d\TH:i:s\Z');
            $payload['failure_reason'] = 'Restore execution failed; see server error log for correlation ID ' . $correlationId;

            try {
                Database::execute(
                    "UPDATE approval_requests SET payload_json = ?, updated_at = ? WHERE id = ?",
                    [
                        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        date('Y-m-d H:i:s'),
                        $approvalRequestId,
                    ]
                );
            } catch (\Throwable $ignore) {
            }

            self::appendJournalEntry([
                'phase' => 'FAILED',
                'approval_request_id' => $approvalRequestId,
                'request_no' => $requestNo,
                'filename' => $cleanFilename,
                'executed_by' => $executorUserId,
                'correlation_id' => $correlationId,
                'timestamp_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            ]);

            self::logSecurityAudit('backup', 'restore_execution_failed', $requestNo, [
                'approval_request_id' => $approvalRequestId,
                'filename' => $cleanFilename,
                'executor_user_id' => $executorUserId,
            ]);

            throw new SecurityGateException(
                'เกิดข้อผิดพลาดระหว่างการกู้คืนฐานข้อมูล (รหัสอ้างอิง: ' . $correlationId . ')',
                500,
                'RESTORE_EXECUTION_FAILED',
                [],
                $e
            );
        }
    }

    /**
     * Fetch a single sanitized approval request by ID.
     */
    public static function getRequestById(int $id): array
    {
        $row = Database::first(
            "SELECT ar.*,
                    u_maker.name AS requested_by_name, u_maker.username AS requested_by_username,
                    u_checker.name AS reviewed_by_name, u_checker.username AS reviewed_by_username
             FROM approval_requests ar
             LEFT JOIN users u_maker ON ar.requested_by = u_maker.id
             LEFT JOIN users u_checker ON ar.reviewed_by = u_checker.id
             WHERE ar.id = ?",
            [$id]
        );

        if (!$row) {
            throw new SecurityGateException('ไม่พบคำขออนุมัติที่ระบุ', 404, 'APPROVAL_NOT_FOUND');
        }

        return self::formatRequestRow($row);
    }

    /**
     * Check whether backup restore execution is enabled by server policy.
     * Defaults to false until backend enforcement and Manual Security UAT are signed off.
     */
    public static function isRestoreExecutionEnabled(): bool
    {
        return (bool) config('security.dual_control.backup_restore_execution_enabled', false);
    }

    /**
     * List approval requests with optional status/module filter.
     */
    public static function listRequests(array|string $filters = [], string $module = '', int $limit = 50): array
    {
        if (is_string($filters)) {
            $filters = [
                'status' => $filters,
                'module' => $module,
                'limit' => $limit,
            ];
        }
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = 'ar.status = ?';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['module']) && $filters['module'] !== 'all') {
            $where[] = 'ar.module = ?';
            $params[] = (string) $filters['module'];
        }
        if (!empty($filters['action_key'])) {
            $where[] = 'ar.action_key = ?';
            $params[] = (string) $filters['action_key'];
        }

        $limit = max(1, min(100, (int) ($filters['limit'] ?? 50)));
        $whereSql = implode(' AND ', $where);

        $rows = Database::query(
            "SELECT ar.*,
                    u_maker.name AS requested_by_name, u_maker.username AS requested_by_username,
                    u_checker.name AS reviewed_by_name, u_checker.username AS reviewed_by_username
             FROM approval_requests ar
             LEFT JOIN users u_maker ON ar.requested_by = u_maker.id
             LEFT JOIN users u_checker ON ar.reviewed_by = u_checker.id
             WHERE {$whereSql}
             ORDER BY ar.id DESC
             LIMIT {$limit}",
            $params
        );

        return array_map([self::class, 'formatRequestRow'], $rows);
    }

    /**
     * Write a redacted security/audit log entry with Correlation ID.
     */
    public static function logSecurityAudit(string $module, string $action, string $recordId, array $metadata = []): void
    {
        $metadata['correlation_id'] = AdminControlCenterService::correlationId();
        $cleanMeta = AdminControlCenterService::redactMetadata($metadata);
        try {
            AuditService::log($module, $action, $recordId, null, is_array($cleanMeta) ? $cleanMeta : []);
        } catch (\Throwable $e) {
            Logger::error('Failed to write DualControl audit log: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------------------------------

    private static function userHasAnyExplicitOrRolePermission(int $userId, array $requiredKeys): bool
    {
        $user = Database::first(
            "SELECT u.id, u.status, r.slug AS role_slug
             FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id
             WHERE u.id = ? AND u.status = 'active' AND u.deleted_at IS NULL
             LIMIT 1",
            [$userId]
        );

        if (!$user) {
            return false;
        }

        $slug = (string) ($user['role_slug'] ?? '');
        if ($slug === '' || $slug === 'member') {
            return false;
        }

        if ($slug === 'super_admin') {
            return true;
        }

        $effective = AdminControlCenterService::effectivePermissions($userId);
        foreach ($requiredKeys as $req) {
            if (in_array($req, $effective, true)) {
                return true;
            }
        }

        return false;
    }

    private static function formatRequestRow(array $row): array
    {
        $payload = json_decode((string) ($row['payload_json'] ?? '{}'), true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $payload = AdminControlCenterService::redactMetadata($payload);

        return [
            'id' => (int) $row['id'],
            'request_no' => (string) $row['request_no'],
            'module' => (string) $row['module'],
            'action_key' => (string) $row['action_key'],
            'target_type' => $row['target_type'] ?? null,
            'target_id' => $row['target_id'] ?? null,
            'payload' => $payload,
            'reason' => (string) $row['reason'],
            'status' => (string) $row['status'],
            'requested_by' => (int) $row['requested_by'],
            'requested_by_name' => $row['requested_by_name'] ?? null,
            'requested_by_username' => $row['requested_by_username'] ?? null,
            'reviewed_by' => $row['reviewed_by'] !== null ? (int) $row['reviewed_by'] : null,
            'reviewed_by_name' => $row['reviewed_by_name'] ?? null,
            'reviewed_by_username' => $row['reviewed_by_username'] ?? null,
            'reviewed_at' => $row['reviewed_at'] ?? null,
            'review_note' => $row['review_note'] ?? null,
            'expires_at' => $row['expires_at'] ?? null,
            'executed_at' => $row['executed_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private static function runInTransaction(callable $callback): mixed
    {
        if (Database::pdo()->inTransaction()) {
            return $callback();
        }
        return Database::transaction($callback);
    }

    private static function forUpdateClause(): string
    {
        try {
            $driver = (string) Database::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
            return $driver === 'mysql' ? ' FOR UPDATE' : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function appendJournalEntry(array $entry): void
    {
        $cleanEntry = AdminControlCenterService::redactMetadata($entry);
        $path = self::getJournalPath();
        $line = json_encode($cleanEntry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }

    private static function isRequestConsumedInJournal(int $approvalRequestId, string $requestNo): bool
    {
        $path = self::getJournalPath();
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return false;
        }
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }
            if (
                ((int) ($decoded['approval_request_id'] ?? 0) === $approvalRequestId || ($decoded['request_no'] ?? '') === $requestNo)
                && in_array($decoded['phase'] ?? '', ['CLAIMED', 'COMPLETED', 'FAILED'], true)
            ) {
                return true;
            }
        }
        return false;
    }
}
