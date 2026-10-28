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
        header('Cache-Control: no-store, private');
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
        header('Cache-Control: no-store, private');
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

        $this->response->json([
            'success' => true,
            'enrolled' => $hasMfa,
            'confirmed_at' => $user['two_factor_confirmed_at'],
            'recovery_codes_remaining' => $remainingCodes,
            'mandatory' => (bool) config('google.staff_mfa_required', true),
            'pending_enrollment' => $isPendingEnrollment,
            'pending_verification' => $isPendingVerification,
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
}
