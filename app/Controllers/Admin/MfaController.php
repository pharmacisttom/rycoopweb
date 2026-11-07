<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\MfaRateLimiter;
use App\Services\TwoFactorService;

class MfaController extends Controller
{
    private function challenge(bool $enrollment): ?array
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, private');
        }
        $user = Auth::user();
        $valid = $user && (int) Session::get('session_version', 1) === (int) ($user['session_version'] ?? 1)
            && time() - (int) Session::get('mfa_started_at', 0) <= 600;
        if (!$valid && Auth::id()) Auth::logout();
        $valid = $valid && ($enrollment ? Auth::checkPendingMfaEnrollment() && empty($user['two_factor_enabled']) : Auth::checkPending2FA() && !empty($user['two_factor_enabled']));
        if (!$valid) { $this->response->json(['success'=>false,'message'=>'เซสชันยืนยันตัวตนหมดอายุหรือไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่'],401); return null; }
        return $user;
    }

    /**
     * Get MFA status for currently authenticated or pending-2FA user.
     * GET /api/auth/mfa/status
     */
    public function status(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, private');
        }
        if (!Auth::check() && !$this->challenge(Auth::checkPendingMfaEnrollment())) return;
        $userId = Auth::id();
        if (!$userId) {
            $this->response->json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $user = Database::first(
            "SELECT id, name, email, two_factor_enabled, two_factor_confirmed_at
             FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1",
            [$userId]
        );

        if (!$user) {
            $this->response->json(['success' => false, 'message' => 'User not found'], 404);
            return;
        }

        $hasMfa = (int) ($user['two_factor_enabled'] ?? 0) === 1 && !empty($user['two_factor_confirmed_at']);
        $remainingCodes = $hasMfa ? TwoFactorService::getRecoveryCodesRemaining((int) $user['id']) : 0;
        $isPendingEnrollment = Auth::checkPendingMfaEnrollment();
        $isPendingVerification = Auth::checkPending2FA();

        $now = time();
        $startedAt = (int) Session::get('mfa_started_at', 0);
        $expiresAt = ($isPendingEnrollment || $isPendingVerification) && $startedAt > 0
            ? $startedAt + 600
            : null;
        $remainingSeconds = $expiresAt !== null ? max(0, $expiresAt - $now) : null;

        $this->response->json([
            'success' => true,
            'enrolled' => $hasMfa,
            'confirmed_at' => $user['two_factor_confirmed_at'],
            'recovery_codes_remaining' => $remainingCodes,
            'mandatory' => \App\Services\PrOfficerPolicy::requiresMfa((string) (Auth::user()['role_slug'] ?? '')),
            'pending_enrollment' => $isPendingEnrollment,
            'pending_verification' => $isPendingVerification,
            'challenge_started_at' => $startedAt > 0 ? $startedAt : null,
            'challenge_expires_at' => $expiresAt,
            'remaining_seconds' => $remainingSeconds,
            'server_time' => $now,
            'user' => [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
            ],
        ]);
    }

    /**
     * Initiate TOTP MFA Enrollment.
     * Generates a new secret, stores in session, renders local SVG QR code.
     * POST /api/auth/mfa/enroll
     */
    public function enroll(): void
    {
        if (!$this->challenge(true)) return;
        $userId = Auth::id();
        if (!$userId) {
            $this->response->json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $user = Database::first("SELECT id, name, email FROM users WHERE id = ? LIMIT 1", [$userId]);
        if (!$user) {
            $this->response->json(['success' => false, 'message' => 'User not found'], 404);
            return;
        }

        Session::start();
        $secret = TwoFactorService::generateSecret(32);
        Session::set('mfa_enroll_secret', $secret);
        Session::set('mfa_enroll_expires', time() + 600);

        $issuer = (string) config('security.two_factor.issuer', 'RayongCoop');
        $qrDataUri = TwoFactorService::getQrCodeDataUri($user['email'], $secret, $issuer, 320);
        $formattedSecret = TwoFactorService::formatSecret($secret);

        $this->response->json([
            'success' => true,
            'secret' => $secret,
            'formatted_secret' => $formattedSecret,
            'qr_code_uri' => $qrDataUri,
            'issuer' => $issuer,
            'account' => $user['email'],
        ]);
    }

    /**
     * Confirm TOTP MFA Enrollment with the first valid code.
     * Saves encrypted secret, activates MFA, generates single-use recovery codes.
     * POST /api/auth/mfa/confirm
     */
    public function confirm(): void
    {
        if (!$this->challenge(true)) return;
        $userId = Auth::id();
        if (!$userId) {
            $this->response->json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        Session::start();
        $secret = (string) Session::get('mfa_enroll_secret');
        if (empty($secret) || time() > (int) Session::get('mfa_enroll_expires', 0)) {
            $this->response->json([
                'success' => false,
                'message' => 'กรุณากดเริ่มต้นลงทะเบียน 2FA ใหม่',
            ], 400);
            return;
        }

        if ($this->isRateLimited($userId, $this->request->ip())) { $this->response->json(['success'=>false,'message'=>'กรุณารอ 5 นาที'],429); return; }
        $code = trim((string) $this->request->input('code'));
        if (!preg_match('/^\d{6}$/', $code)) {
            $this->response->json([
                'success' => false,
                'message' => 'กรุณากรอกรหัสความปลอดภัย 6 หลักที่เป็นตัวเลขเท่านั้น',
            ], 422);
            return;
        }

        $matchedSlice = null;
        if (!TwoFactorService::verifyCode($secret, $code, 1, null, $matchedSlice)) {
            $this->recordFailedAttempt($userId, (string) Auth::user()['email'], 'Invalid enrollment code', $this->request->ip(), $this->request->userAgent());
            \App\Services\SecurityAuditService::recordAdmin2faFailed($userId, (string)(Auth::user()['role_slug'] ?? 'staff'), 'Invalid enrollment code');
            $this->response->json([
                'success' => false,
                'message' => 'รหัสความปลอดภัย 6 หลักไม่ถูกต้อง กรุณาตรวจสอบเวลาบนสมาร์ตโฟนและลองใหม่อีกครั้ง',
            ], 400);
            return;
        }

        // Code is verified! Encrypt secret and activate MFA
        $encryptedSecret = TwoFactorService::encryptSecret($secret);
        Database::beginTransaction();
        try {
        $activated = Database::execute(
            "UPDATE users
             SET two_factor_secret = ?, two_factor_enabled = 1, two_factor_confirmed_at = NOW(),
                 two_factor_last_used_slice = ?, updated_at = NOW()
             WHERE id = ? AND two_factor_enabled = 0",
            [$encryptedSecret, $matchedSlice, $userId]
        );

        if ($activated !== 1) throw new \RuntimeException('MFA enrollment changed.');

        // Generate 8 single-use recovery codes
        $recoveryCodes = TwoFactorService::generateRecoveryCodes($userId, 8);
        Database::commit();
        } catch (\Throwable $e) { Database::rollBack(); throw $e; }
        Session::remove('mfa_enroll_secret');

        // Mark 2FA verified in active session
        Auth::verify2FA();
        Session::regenerate();
        $this->clearRateLimit($userId, $this->request->ip());

        AuditService::log('mfa', 'enroll_confirmed', (string) $userId, null, [
            'method' => 'totp',
            'recovery_codes_issued' => 8,
        ]);

        $roleSlug = (string) (Auth::user()['role_slug'] ?? 'staff');
        \App\Services\SecurityAuditService::recordAdmin2faSuccess($userId, $roleSlug, 'totp_enrollment');
        $redirectUrl = match ($roleSlug) {
            'super_admin' => '/admin/dashboard',
            'member_admin' => '/admin/members/dashboard',
            default => '/admin/workspace'
        };

        $this->response->json([
            'success' => true,
            'message' => 'เปิดใช้งานการยืนยันตัวตนสองขั้นตอน (2FA) สำเร็จเรียบร้อยแล้ว',
            'recovery_codes' => $recoveryCodes,
            'redirect' => $redirectUrl,
        ]);
    }

    /**
     * Verify 6-digit TOTP code during login.
     * POST /api/auth/mfa/verify
     */
    public function verify(): void
    {
        if (!$this->challenge(false)) return;
        $userId = Auth::id();
        if (!$userId) {
            $this->response->json(['success' => false, 'message' => 'เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่'], 401);
            return;
        }

        $code = trim((string) $this->request->input('code'));
        if (!preg_match('/^\d{6}$/', $code)) {
            $this->response->json([
                'success' => false,
                'message' => 'กรุณากรอกรหัสความปลอดภัย 6 หลักให้ครบถ้วน',
            ], 422);
            return;
        }

        $ip = $this->request->ip();
        $ua = $this->request->userAgent();

        // Check Rate Limiting (max 5 failed attempts per 5 minutes)
        if ($this->isRateLimited($userId, $ip)) {
            $this->response->json([
                'success' => false,
                'message' => 'คุณกรอกรหัสผิดเกินจำนวนที่กำหนด กรุณารอสักครู่ (5 นาที) ก่อนลองใหม่',
            ], 429);
            return;
        }

        $user = Database::first(
            "SELECT u.*, r.slug as role_slug
             FROM users u
             LEFT JOIN user_roles ur ON u.id = ur.user_id
             LEFT JOIN roles r ON ur.role_id = r.id
             WHERE u.id = ? AND u.deleted_at IS NULL AND u.status = 'active'
             LIMIT 1",
            [$userId]
        );

        if (!$user || empty($user['two_factor_secret'])) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูล 2FA สำหรับบัญชีนี้'], 400);
            return;
        }

        $matchedSlice = null;
        $lastUsed = !empty($user['two_factor_last_used_slice']) ? (int) $user['two_factor_last_used_slice'] : null;

        $isValid = TwoFactorService::verifyCode(
            $user['two_factor_secret'],
            $code,
            1,
            $lastUsed,
            $matchedSlice
        );

        if (!$isValid) {
            $this->recordFailedAttempt($userId, $user['email'], 'รหัส 2FA ไม่ถูกต้องหรือถูกใช้ไปแล้ว', $ip, $ua);
            \App\Services\SecurityAuditService::recordAdmin2faFailed($userId, (string)($user['role_slug'] ?? 'staff'), 'รหัส 2FA ไม่ถูกต้องหรือถูกใช้ไปแล้ว');
            $this->response->json([
                'success' => false,
                'message' => 'รหัสความปลอดภัย 6 หลักไม่ถูกต้องหรือหมดอายุ',
            ], 400);
            return;
        }

        // Success: update replay slice, verify 2FA, regenerate session
        $used = Database::execute(
            "UPDATE users SET two_factor_last_used_slice = ?, last_login_at = NOW(), last_login_ip = ? WHERE id = ? AND (two_factor_last_used_slice IS NULL OR two_factor_last_used_slice < ?)",
            [$matchedSlice, $ip, $userId, $matchedSlice]
        );
        if ($used !== 1) { $this->response->json(['success'=>false,'message'=>'รหัสถูกใช้แล้ว กรุณารอรหัสถัดไป'],409); return; }

        Auth::verify2FA();
        Session::regenerate();

        $this->clearRateLimit($userId, $ip);

        AuditService::log('auth', '2fa_verified', (string) $userId, null, [
            'method' => 'totp',
            'status' => 'success',
        ]);

        $roleSlug = (string) ($user['role_slug'] ?? 'staff');
        \App\Services\SecurityAuditService::recordAdmin2faSuccess($userId, $roleSlug, 'totp');
        $redirectUrl = match ($roleSlug) {
            'super_admin' => '/admin/dashboard',
            'member_admin' => '/admin/members/dashboard',
            default => '/admin/workspace'
        };

        $this->response->json([
            'success' => true,
            'message' => 'ยืนยันตัวตนสำเร็จ กำลังเข้าสู่ระบบ...',
            'redirect' => $redirectUrl,
            'user' => [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $roleSlug,
            ],
        ]);
    }

    /**
     * Authenticate using a single-use recovery code.
     * POST /api/auth/mfa/recovery
     */
    public function recovery(): void
    {
        if (!$this->challenge(false)) return;
        $userId = Auth::id();
        if (!$userId) {
            $this->response->json(['success' => false, 'message' => 'เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่'], 401);
            return;
        }

        $code = trim((string) $this->request->input('recovery_code'));
        if ($code === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกรหัสสำรอง (Recovery Code)'], 422);
            return;
        }

        $ip = $this->request->ip();
        $ua = $this->request->userAgent();

        if ($this->isRateLimited($userId, $ip)) {
            $this->response->json([
                'success' => false,
                'message' => 'คุณพยายามยืนยันตัวตนผิดเกินจำนวนที่กำหนด กรุณารอ 5 นาทีก่อนลองใหม่',
            ], 429);
            return;
        }

        $user = Database::first(
            "SELECT u.*, r.slug as role_slug
             FROM users u
             LEFT JOIN user_roles ur ON u.id = ur.user_id
             LEFT JOIN roles r ON ur.role_id = r.id
             WHERE u.id = ? AND u.deleted_at IS NULL AND u.status = 'active'
             LIMIT 1",
            [$userId]
        );

        if (!$user) {
            $this->response->json(['success' => false, 'message' => 'User not found'], 404);
            return;
        }

        $isValid = TwoFactorService::verifyRecoveryCode($userId, $code);
        if (!$isValid) {
            $this->recordFailedAttempt($userId, $user['email'], 'รหัสกู้คืนไม่ถูกต้องหรือถูกใช้งานไปแล้ว', $ip, $ua);
            \App\Services\SecurityAuditService::recordAdmin2faFailed($userId, (string)($user['role_slug'] ?? 'staff'), 'รหัสกู้คืนไม่ถูกต้องหรือถูกใช้งานไปแล้ว');
            $this->response->json([
                'success' => false,
                'message' => 'รหัสกู้คืนไม่ถูกต้องหรือถูกใช้งานไปแล้ว',
            ], 400);
            return;
        }

        // Success
        Auth::verify2FA();
        Session::regenerate();
        $this->clearRateLimit($userId, $ip);

        $remaining = TwoFactorService::getRecoveryCodesRemaining($userId);

        AuditService::log('auth', 'recovery_code_used', (string) $userId, null, [
            'remaining_codes' => $remaining,
        ]);

        $roleSlug = (string) ($user['role_slug'] ?? 'staff');
        \App\Services\SecurityAuditService::recordAdmin2faSuccess($userId, $roleSlug, 'recovery_code');
        $redirectUrl = match ($roleSlug) {
            'super_admin' => '/admin/dashboard',
            'member_admin' => '/admin/members/dashboard',
            default => '/admin/workspace'
        };

        $this->response->json([
            'success' => true,
            'message' => "ยืนยันตัวตนด้วยรหัสกู้คืนสำเร็จ (เหลือรหัสสำรองอีก {$remaining} รหัส)",
            'remaining_codes' => $remaining,
            'redirect' => $redirectUrl,
            'user' => [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $roleSlug,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Rate Limiting Helpers
    // -------------------------------------------------------------------------

    private function isRateLimited(int $userId, string $ip): bool
    {
        return MfaRateLimiter::isRateLimited($userId, $ip);
    }

    private function recordFailedAttempt(int $userId, string $email, string $reason, string $ip, string $ua): void
    {
        MfaRateLimiter::recordFailedAttempt($userId, $ip);

        try {
            Database::execute(
                "INSERT INTO login_logs (user_id, email, status, failure_reason, ip_address, user_agent, created_at)
                 VALUES (?, ?, '2fa_failed', ?, ?, ?, NOW())",
                [$userId, $email, $reason, $ip, $ua]
            );
        } catch (\Throwable $e) {}
    }

    private function clearRateLimit(int $userId, string $ip): void
    {
        MfaRateLimiter::clear($userId, $ip);
    }

    /**
     * Server-Side Step-Up MFA Verification for critical administrative actions.
     * Verifies a fresh 6-digit TOTP code against the active authenticated session
     * and records freshness in server session (never relies solely on login-time MFA).
     * POST /api/auth/mfa/step-up
     */
    public function stepUp(): void
    {
        $correlationId = \App\Services\AdminControlCenterService::correlationId();
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

        $userId = (int) Auth::id();
        $code = (string) ($this->request->input('code') ?? $this->request->input('totp_code') ?? '');
        $scope = trim((string) $this->request->input('scope', 'general')) ?: 'general';

        try {
            $result = \App\Services\DualControlSecurityService::verifyAndRecordStepUpMfa(
                $userId,
                $code,
                $scope,
                $this->request->ip()
            );

            $this->response->json([
                'success' => true,
                'code' => 'STEP_UP_MFA_VERIFIED',
                'correlation_id' => $correlationId,
                'message' => 'ยืนยันตัวตนขั้นสูง (Step-Up MFA) สำเร็จ',
                'data' => $result,
            ], 200);
        } catch (\App\Exceptions\SecurityGateException $e) {
            $this->response->json([
                'success' => false,
                'code' => $e->getErrorCode(),
                'correlation_id' => $correlationId,
                'message' => $e->getMessage(),
                'context' => $e->getContext(),
            ], $e->getHttpStatus());
        } catch (\Throwable $e) {
            Logger::error('Step-Up MFA Error [' . $correlationId . ']: ' . $e->getMessage());
            $this->response->json([
                'success' => false,
                'code' => 'INTERNAL_ERROR',
                'correlation_id' => $correlationId,
                'message' => 'ไม่สามารถตรวจสอบรหัส Step-Up MFA ได้ในขณะนี้',
            ], 500);
        }
    }

    /**
     * Start secure Authenticator device replacement (re-enrollment) for an existing administrator.
     * Requires an authenticated session, fresh password re-verification, and generates a new secret
     * while keeping the old secret active in the database until confirmation.
     * POST /api/auth/mfa/re-enroll/start
     */
    public function reEnrollStart(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, private');
        }

        if (!Auth::check() || !Auth::user()) {
            $this->response->json([
                'success' => false,
                'message' => 'เซสชันหมดอายุหรือไม่ได้รับอนุญาต กรุณาเข้าสู่ระบบใหม่',
            ], 401);
            return;
        }

        $userId = (int) Auth::id();
        $ip = $this->request->ip();
        $ua = $this->request->userAgent();

        if ($this->isRateLimited($userId, $ip)) {
            $this->response->json([
                'success' => false,
                'message' => 'คุณพยายามยืนยันตัวตนผิดเกินจำนวนที่กำหนด กรุณารอ 5 นาที',
            ], 429);
            return;
        }

        $password = (string) ($this->request->input('password') ?? '');
        if ($password === '') {
            $this->response->json([
                'success' => false,
                'message' => 'กรุณากรอกรหัสผ่านปัจจุบันเพื่อยืนยันตัวตนก่อนเริ่มต้นเปลี่ยนอุปกรณ์',
            ], 422);
            return;
        }

        $user = Database::first(
            "SELECT u.* FROM users u WHERE u.id = ? AND u.deleted_at IS NULL AND u.status = 'active' LIMIT 1",
            [$userId]
        );

        if (!$user) {
            $this->response->json(['success' => false, 'message' => 'User not found'], 404);
            return;
        }

        $hash = (string) ($user['password'] ?? $user['password_hash'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            $this->recordFailedAttempt($userId, (string) $user['email'], 'Failed password confirmation for MFA re-enrollment start', $ip, $ua);
            $roleSlug = (string) (Auth::user()['role_slug'] ?? 'staff');
            \App\Services\SecurityAuditService::recordAdmin2faFailed($userId, $roleSlug, 'Failed password confirmation during MFA device replacement request');
            $this->response->json([
                'success' => false,
                'message' => 'รหัสผ่านไม่ถูกต้อง ไม่สามารถเริ่มต้นเปลี่ยนอุปกรณ์ Authenticator ได้',
            ], 401);
            return;
        }

        if ((int) ($user['two_factor_enabled'] ?? 0) !== 1) {
            $this->response->json([
                'success' => false,
                'message' => 'บัญชียังไม่ได้ลงทะเบียน MFA กรุณาลงทะเบียนตามขั้นตอนปกติ',
            ], 400);
            return;
        }

        Session::start();
        $newSecret = TwoFactorService::generateSecret(32);
        Session::set('mfa_re_enroll_secret', $newSecret);
        Session::set('mfa_re_enroll_expires', time() + 600);
        Session::set('mfa_re_enroll_user_id', $userId);

        $issuer = (string) config('security.two_factor.issuer', 'RayongCoop');
        $qrDataUri = TwoFactorService::getQrCodeDataUri($user['email'], $newSecret, $issuer, 320);
        $formattedSecret = TwoFactorService::formatSecret($newSecret);

        AuditService::log('mfa', 're_enroll_started', (string) $userId, null, [
            'method' => 'totp_device_replacement',
            'expires_in' => 600,
        ]);

        $this->response->json([
            'success' => true,
            'message' => 'ยืนยันตัวตนสำเร็จ กรุณาสแกน QR Code ด้วยอุปกรณ์ใหม่',
            'formatted_secret' => $formattedSecret,
            'qr_code_uri' => $qrDataUri,
            'issuer' => $issuer,
            'account' => $user['email'],
            'expires_in' => 600,
        ]);
    }

    /**
     * Confirm TOTP MFA re-enrollment with the first valid code from the new device.
     * Atomically replaces the encrypted secret, invalidates old recovery codes, issues new ones,
     * bumps session_version to revoke other sessions, and logs the security audit event.
     * POST /api/auth/mfa/re-enroll/confirm
     */
    public function reEnrollConfirm(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, private');
        }

        if (!Auth::check() || !Auth::user()) {
            $this->response->json([
                'success' => false,
                'message' => 'เซสชันหมดอายุหรือไม่ได้รับอนุญาต กรุณาเข้าสู่ระบบใหม่',
            ], 401);
            return;
        }

        $userId = (int) Auth::id();
        $ip = $this->request->ip();
        $ua = $this->request->userAgent();

        Session::start();
        $secret = (string) Session::get('mfa_re_enroll_secret');
        $expires = (int) Session::get('mfa_re_enroll_expires', 0);
        $challengeUserId = (int) Session::get('mfa_re_enroll_user_id', 0);

        if (empty($secret) || time() > $expires || $challengeUserId !== $userId) {
            $this->response->json([
                'success' => false,
                'message' => 'คำขอเปลี่ยนอุปกรณ์หมดอายุหรือไม่ถูกต้อง กรุณาเริ่มต้นใหม่อีกครั้ง',
            ], 400);
            return;
        }

        if ($this->isRateLimited($userId, $ip)) {
            $this->response->json([
                'success' => false,
                'message' => 'คุณพยายามยืนยันตัวตนผิดเกินจำนวนที่กำหนด กรุณารอ 5 นาที',
            ], 429);
            return;
        }

        $code = trim((string) $this->request->input('code'));
        if (!preg_match('/^\d{6}$/', $code)) {
            $this->response->json([
                'success' => false,
                'message' => 'กรุณากรอกรหัสความปลอดภัย 6 หลักที่เป็นตัวเลขเท่านั้น',
            ], 422);
            return;
        }

        $matchedSlice = null;
        if (!TwoFactorService::verifyCode($secret, $code, 1, null, $matchedSlice)) {
            $this->recordFailedAttempt($userId, (string) Auth::user()['email'], 'Invalid TOTP code during MFA re-enrollment confirmation', $ip, $ua);
            $roleSlug = (string) (Auth::user()['role_slug'] ?? 'staff');
            \App\Services\SecurityAuditService::recordAdmin2faFailed($userId, $roleSlug, 'Invalid TOTP code during MFA device replacement confirmation');
            $this->response->json([
                'success' => false,
                'message' => 'รหัสความปลอดภัย 6 หลักไม่ถูกต้องหรือหมดอายุ กรุณาตรวจสอบเวลาบนอุปกรณ์ใหม่แล้วลองใหม่อีกครั้ง',
            ], 400);
            return;
        }

        // Code verified! Atomically replace secret, revoke old recovery codes, and revoke other sessions
        $encryptedSecret = TwoFactorService::encryptSecret($secret);
        Database::beginTransaction();
        try {
            $updated = Database::execute(
                "UPDATE users
                 SET two_factor_secret = ?,
                     two_factor_enabled = 1,
                     two_factor_confirmed_at = NOW(),
                     two_factor_last_used_slice = ?,
                     session_version = session_version + 1,
                     updated_at = NOW()
                 WHERE id = ? AND two_factor_enabled = 1",
                [$encryptedSecret, $matchedSlice, $userId]
            );

            if ($updated !== 1) {
                throw new \RuntimeException('Failed to update MFA credentials.');
            }

            // Invalidate old recovery codes and generate 8 new single-use recovery codes
            $recoveryCodes = TwoFactorService::generateRecoveryCodes($userId, 8);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            Logger::error('Failed to confirm MFA re-enrollment: ' . $e->getMessage());
            $this->response->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง',
            ], 500);
            return;
        }

        // Update active session version so this session stays active while concurrent sessions are revoked
        $newSessionVersion = (int) Database::value("SELECT session_version FROM users WHERE id = ?", [$userId]);
        Session::set('session_version', $newSessionVersion);

        // Clear temporary re-enrollment challenge session
        Session::remove('mfa_re_enroll_secret');
        Session::remove('mfa_re_enroll_expires');
        Session::remove('mfa_re_enroll_user_id');
        $this->clearRateLimit($userId, $ip);

        // Audit Logging
        AuditService::log('mfa', 'device_replaced', (string) $userId, null, [
            'method' => 'totp_device_replacement',
            'recovery_codes_issued' => 8,
            'other_sessions_revoked' => true,
        ]);
        $roleSlug = (string) (Auth::user()['role_slug'] ?? 'staff');
        \App\Services\SecurityAuditService::recordAdmin2faSuccess($userId, $roleSlug, 'totp_device_replacement');

        $this->response->json([
            'success' => true,
            'message' => 'เปลี่ยนอุปกรณ์ Authenticator และสร้างรหัสสำรองชุดใหม่สำเร็จเรียบร้อยแล้ว',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Cancel an in-progress MFA re-enrollment challenge.
     * The existing TOTP secret remains active in the database.
     * POST /api/auth/mfa/re-enroll/cancel
     */
    public function reEnrollCancel(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, private');
        }

        if (!Auth::check() || !Auth::user()) {
            $this->response->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
            return;
        }

        $userId = (int) Auth::id();
        Session::start();
        Session::remove('mfa_re_enroll_secret');
        Session::remove('mfa_re_enroll_expires');
        Session::remove('mfa_re_enroll_user_id');

        AuditService::log('mfa', 're_enroll_cancelled', (string) $userId, null, [
            'action' => 'totp_device_replacement_cancelled',
        ]);

        $this->response->json([
            'success' => true,
            'message' => 'ยกเลิกการเปลี่ยนอุปกรณ์ Authenticator เรียบร้อยแล้ว (อุปกรณ์เดิมยังคงมีผลใช้งาน)',
        ]);
    }
}
