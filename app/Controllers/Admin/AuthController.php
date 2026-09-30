<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\TwoFactorService;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            $roleSlug = (string) (Auth::user()['role_slug'] ?? '');
            if ($roleSlug === '' || ($roleSlug === 'member' && !config('features.member_login', false))) {
                Auth::logout();
                Session::flash('error', 'บัญชีนี้ไม่ได้รับอนุญาตให้เข้าสู่ระบบเจ้าหน้าที่');
                $this->redirect(url('admin/login'));
                return;
            }
            if ($roleSlug === 'member') {
                $this->redirect(url('member/dashboard'));
            } elseif ($roleSlug === 'super_admin') {
                $this->redirect(url('admin/dashboard'));
            } else {
                $this->redirect(url('staff/dashboard'));
            }
            return;
        }

        $this->render('auth.login', [
            'title' => 'เข้าสู่ระบบ — ระบบบริหารจัดการสหกรณ์ออมทรัพย์',
        ], 'layouts.auth');
    }

    public function login(): void
    {
        $inputUsername = trim((string)($this->request->input('username') ?? $this->request->input('email') ?? ''));
        $password = (string)($this->request->input('password') ?? '');
        $isAjax = $this->request->isAjax() || $this->request->header('Accept') === 'application/json' || $this->request->input('ajax') === '1';

        if (empty($inputUsername) || empty($password)) {
            $errorMsg = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
            if ($isAjax) {
                $this->response->json(['success' => false, 'message' => $errorMsg], 422);
                return;
            }
            Session::flash('error', $errorMsg);
            $this->redirect(url('login'));
            return;
        }

        $ip = $this->request->ip();
        $ua = $this->request->userAgent();

        // 1. Check user in database
        $user = null;
        try {
            $cleanInput = str_replace(['-', ' '], '', $inputUsername);
            $sql = "SELECT u.*, r.slug as role_slug, r.name as role_name 
                    FROM users u
                    LEFT JOIN user_roles ur ON u.id = ur.user_id
                    LEFT JOIN roles r ON ur.role_id = r.id
                    LEFT JOIN members m ON u.id = m.user_id
                    WHERE (u.username = ? OR u.username = ? OR u.email = ? OR m.member_no = ? OR REPLACE(m.member_no, '-', '') = ? OR m.id_card = ?) AND u.deleted_at IS NULL
                    LIMIT 1";
            $user = Database::first($sql, [$inputUsername, $cleanInput, $inputUsername, $inputUsername, $cleanInput, $cleanInput]);
        } catch (\Throwable $e) {
            Logger::error("Database user query error: " . $e->getMessage());
        }

        $isValid = false;

        // Check against DB hash
        if ($user && !empty($user['password']) && password_verify($password, $user['password'])) {
            $isValid = true;
        }

        if (!$isValid) {
            // Log failed attempt
            try {
                Database::execute("INSERT INTO login_logs (email, status, failure_reason, ip_address, user_agent, created_at) VALUES (?, 'failed', 'รหัสผ่านไม่ถูกต้อง หรือไม่พบบัญชีผู้ใช้', ?, ?, NOW())", [$inputUsername, $ip, $ua]);
            } catch (\Throwable $e) {}

            Logger::auth("Failed login attempt for: {$inputUsername} from IP: {$ip}");

            $errorMsg = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง';
            if ($isAjax) {
                $this->response->json(['success' => false, 'message' => $errorMsg], 401);
                return;
            }

            Session::flash('error', $errorMsg);
            $this->redirect(url('login'));
            return;
        }

        if (($user['status'] ?? 'active') !== 'active') {
            try {
                Database::execute("INSERT INTO login_logs (user_id, email, status, failure_reason, ip_address, user_agent, created_at) VALUES (?, ?, 'locked_out', 'บัญชีถูกระงับการใช้งาน', ?, ?, NOW())", [$user['id'], $inputUsername, $ip, $ua]);
            } catch (\Throwable $e) {}

            $errorMsg = 'บัญชีผู้ใช้งานนี้ถูกระงับ กรุณาติดต่อผู้ดูแลระบบ';
            if ($isAjax) {
                $this->response->json(['success' => false, 'message' => $errorMsg], 403);
                return;
            }

            Session::flash('error', $errorMsg);
            $this->redirect(url('login'));
            return;
        }

        $roleSlug = (string) ($user['role_slug'] ?? '');
        if ($roleSlug === '' || ($roleSlug === 'member' && !config('features.member_login', false))) {
            $reason = $roleSlug === '' ? 'Account has no assigned role' : 'Member portal disabled';
            try {
                Database::execute("INSERT INTO login_logs (user_id, email, status, failure_reason, ip_address, user_agent, created_at) VALUES (?, ?, 'locked_out', ?, ?, ?, NOW())", [$user['id'], $inputUsername, $reason, $ip, $ua]);
            } catch (\Throwable $e) {}

            $message = $roleSlug === ''
                ? 'บัญชีนี้ยังไม่ได้รับการกำหนดสิทธิ์ใช้งาน'
                : 'ระบบสมาชิกออนไลน์ยังไม่เปิดให้บริการ';
            if ($isAjax) {
                $this->response->json(['success' => false, 'message' => $message, 'errors' => []], 403);
                return;
            }
            Session::flash('error', $message);
            $this->redirect(url($roleSlug === 'member' ? 'service-unavailable' : 'admin/login'));
            return;
        }

        // Check if 2FA is enabled.
        if ((int)($user['two_factor_enabled'] ?? 0) === 1 && !empty($user['two_factor_secret'])) {
            Auth::login($user, false);
            if ($isAjax) {
                $this->response->json(['success' => true, 'redirect' => '/admin/2fa']);
                return;
            }
            $this->redirect(url('admin/2fa'));
            return;
        }

        // Login success
        Auth::login($user, true);

        try {
            Database::execute("UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?", [$ip, $user['id']]);
            Database::execute("INSERT INTO login_logs (user_id, email, status, ip_address, user_agent, created_at) VALUES (?, ?, 'success', ?, ?, NOW())", [$user['id'], $inputUsername, $ip, $ua]);
            AuditService::log('auth', 'login', (string)$user['id'], null, ['status' => 'success']);
        } catch (\Throwable $e) {}

        Session::flash('success', 'เข้าสู่ระบบสำเร็จ ยินดีต้อนรับ ' . ($user['name'] ?? 'ผู้ใช้งาน'));

        $targetPath = match($roleSlug) {
            'member' => '/member/dashboard',
            'super_admin' => '/admin/dashboard',
            default => '/staff/dashboard'
        };

        if ($isAjax) {
            $this->response->json([
                'success' => true,
                'message' => 'เข้าสู่ระบบสำเร็จ กำลังพาไปยัง Dashboard...',
                'redirect' => $targetPath,
                'user' => [
                    'id' => $user['id'] ?? null,
                    'username' => $user['username'] ?? '',
                    'name' => $user['name'] ?? 'ผู้ใช้งาน',
                    'email' => $user['email'] ?? '',
                    'role' => $roleSlug,
                    'role_slug' => $roleSlug,
                    'role_name' => $user['role_name'] ?? match($roleSlug) {
                        'super_admin' => 'ผู้ดูแลระบบสูงสุด',
                        'staff' => 'เจ้าหน้าที่สินเชื่อ/การเงิน',
                        'auditor' => 'ผู้ตรวจสอบกิจการ / ผู้จัดการ',
                        default => 'เจ้าหน้าที่สหกรณ์'
                    },
                ]
            ]);
            return;
        }

        $this->redirect(url(ltrim($targetPath, '/')));
    }

    public function showTwoFactor(): void
    {
        if (!Auth::checkPending2FA()) {
            $this->redirect(url('login'));
            return;
        }

        $this->render('auth.two_factor', [
            'title' => 'ยืนยันรหัสความปลอดภัย 2FA',
        ], 'layouts.auth');
    }

    public function verifyTwoFactor(): void
    {
        if (!Auth::checkPending2FA()) {
            $this->redirect(url('login'));
            return;
        }

        $code = trim((string)$this->request->input('code'));
        $userId = Auth::id();
        $user = Database::first(
            "SELECT u.*, r.slug AS role_slug, r.name AS role_name
             FROM users u
             LEFT JOIN user_roles ur ON u.id = ur.user_id
             LEFT JOIN roles r ON ur.role_id = r.id
             WHERE u.id = ? AND u.deleted_at IS NULL AND u.status = 'active'
             LIMIT 1",
            [$userId]
        );

        if (!$user || !TwoFactorService::verifyCode($user['two_factor_secret'], $code)) {
            try {
                Database::execute("INSERT INTO login_logs (user_id, email, status, failure_reason, ip_address, user_agent, created_at) VALUES (?, ?, '2fa_failed', 'รหัส 2FA ไม่ถูกต้อง', ?, ?, NOW())", [$user['id'], $user['email'], $this->request->ip(), $this->request->userAgent()]);
            } catch (\Throwable $e) {}
            Session::flash('error', 'รหัส 2FA 6 หลักไม่ถูกต้องหรือหมดอายุ');
            $this->redirect(url('admin/2fa'));
            return;
        }

        // 2FA Success
        Auth::verify2FA();
        try {
            Database::execute("UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?", [$this->request->ip(), $user['id']]);
            Database::execute("INSERT INTO login_logs (user_id, email, status, ip_address, user_agent, created_at) VALUES (?, ?, 'success', ?, ?, NOW())", [$user['id'], $user['email'], $this->request->ip(), $this->request->userAgent()]);
            AuditService::log('auth', '2fa_verified', (string)$user['id'], null, ['status' => '2fa_success']);
        } catch (\Throwable $e) {}

        Session::flash('success', 'ยืนยันตัวตนสำเร็จ');

        $roleSlug = (string) ($user['role_slug'] ?? '');
        if ($roleSlug === '' || ($roleSlug === 'member' && !config('features.member_login', false))) {
            Auth::logout();
            Session::flash('error', $roleSlug === ''
                ? 'บัญชีนี้ยังไม่ได้รับการกำหนดสิทธิ์ใช้งาน'
                : 'ระบบสมาชิกออนไลน์ยังไม่เปิดให้บริการ');
            $this->redirect(url($roleSlug === 'member' ? 'service-unavailable' : 'admin/login'));
            return;
        }
        $targetUrl = match($roleSlug) {
            'member' => url('member/dashboard'),
            'super_admin' => url('admin/dashboard'),
            default => url('staff/dashboard')
        };

        $this->redirect($targetUrl);
    }

    public function changePassword(): void
    {
        $inputUsername = trim((string)($this->request->input('username') ?? ''));
        $userId = $this->request->input('user_id') ? (int)$this->request->input('user_id') : (Auth::id() ?? null);
        $currentPass = (string)$this->request->input('current_password');
        $newPass = (string)$this->request->input('new_password');
        $confirmPass = (string)$this->request->input('confirm_password');
        $isAjax = $this->request->isAjax() || !empty($this->request->input('ajax'));

        if (empty($newPass) || strlen($newPass) < 8) {
            $msg = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
            if ($isAjax) {
                $this->response->json(['success' => false, 'message' => $msg], 400);
                return;
            }
            Session::flash('error', $msg);
            $this->redirect($this->safeRefererUrl());
            return;
        }

        if ($newPass !== $confirmPass) {
            $msg = 'รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน';
            if ($isAjax) {
                $this->response->json(['success' => false, 'message' => $msg], 400);
                return;
            }
            Session::flash('error', $msg);
            $this->redirect($this->safeRefererUrl());
            return;
        }

        // Find user by ID or username
        $user = null;
        if ($userId) {
            $user = Database::first("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1", [$userId]);
        }
        if (!$user && !empty($inputUsername)) {
            $cleanInput = str_replace(['-', ' '], '', $inputUsername);
            $sql = "SELECT u.* FROM users u
                    LEFT JOIN members m ON u.id = m.user_id
                    WHERE (u.username = ? OR u.username = ? OR u.email = ? OR m.member_no = ? OR REPLACE(m.member_no, '-', '') = ?)
                    AND u.deleted_at IS NULL LIMIT 1";
            $user = Database::first($sql, [$inputUsername, $cleanInput, $inputUsername, $inputUsername, $cleanInput]);
        }

        if (!$user) {
            $msg = 'ไม่พบข้อมูลบัญชีผู้ใช้งานในระบบ';
            if ($isAjax) {
                $this->response->json(['success' => false, 'message' => $msg], 404);
                return;
            }
            Session::flash('error', $msg);
            $this->redirect($this->safeRefererUrl());
            return;
        }

        // Check current password against DB hash — never compare plaintext
        if (!empty($user['password'])) {
            if (!password_verify($currentPass, $user['password'])) {
                $msg = 'รหัสผ่านปัจจุบันไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง';
                if ($isAjax) {
                    $this->response->json(['success' => false, 'message' => $msg], 400);
                    return;
                }
                Session::flash('error', $msg);
                $this->redirect($this->safeRefererUrl());
                return;
            }
        }

        // Update password in DB using the configured hashing algorithm
        $pwConfig = config('security.password');
        $hash = password_hash($newPass, $pwConfig['algo'] ?? PASSWORD_DEFAULT, $pwConfig['options'] ?? []);
        Database::execute("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?", [$hash, $user['id']]);

        try {
            AuditService::log('auth', 'password_change', (string)$user['id'], null, ['username' => $user['username']]);
        } catch (\Throwable $e) {}

        $msg = 'เปลี่ยนรหัสผ่านสำเร็จเรียบร้อยแล้ว ท่านสามารถเข้าสู่ระบบด้วยรหัสผ่านใหม่ได้ทันที';
        if ($isAjax) {
            $this->response->json(['success' => true, 'message' => $msg]);
            return;
        }

        Session::flash('success', $msg);
        $this->redirect($this->safeRefererUrl());
    }

    public function logout(): void
    {
        $isAjax = $this->request->isAjax() || $this->request->header('Accept') === 'application/json' || $this->request->input('ajax') === '1';
        if (Auth::id()) {
            try {
                AuditService::log('auth', 'logout', (string)Auth::id());
            } catch (\Throwable $e) {}
        }
        Auth::logout();
        if ($isAjax) {
            $this->response->json(['success' => true, 'message' => 'ออกจากระบบเรียบร้อยแล้ว']);
            return;
        }

        Session::flash('info', 'ออกจากระบบเรียบร้อยแล้ว');
        $this->redirect(url('login'));
    }

    private function safeRefererUrl(): string
    {
        $fallback = url('/');
        $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
        if ($referer === '') {
            return $fallback;
        }

        $target = parse_url($referer);
        $origin = parse_url($fallback);
        if ($target === false || $origin === false) {
            return $fallback;
        }

        $scheme = strtolower((string) ($target['scheme'] ?? ''));
        $sameOrigin = in_array($scheme, ['http', 'https'], true)
            && strtolower((string) ($target['host'] ?? '')) === strtolower((string) ($origin['host'] ?? ''))
            && ($target['port'] ?? null) === ($origin['port'] ?? null);

        return $sameOrigin ? $referer : $fallback;
    }
}
