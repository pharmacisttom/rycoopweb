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
    public static function pseudonymizeIdentifier(string $identifier): string
    {
        $trimmed = trim($identifier);
        $cleanDigits = preg_replace('/\D/', '', $trimmed);

        // 13-digit Thai Citizen ID (raw or formatted)
        if (strlen($cleanDigits) === 13) {
            return substr($cleanDigits, 0, 3) . 'XXXXXX' . substr($cleanDigits, -4);
        }

        // Email address
        if (filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $trimmed, 2);
            $name = $parts[0];
            $domain = $parts[1] ?? '';
            $maskedName = strlen($name) <= 2 ? substr($name, 0, 1) . '***' : substr($name, 0, 2) . '***' . substr($name, -1);
            return $maskedName . '@' . $domain;
        }

        // If identifier contains any sequence of 13 digits, mask it
        if (preg_match('/\d{13}/', $trimmed)) {
            return (string)preg_replace_callback('/\d{13}/', function ($matches) {
                return substr($matches[0], 0, 3) . 'XXXXXX' . substr($matches[0], -4);
            }, $trimmed);
        }

        return $trimmed;
    }

    public function options(): void
    {
        $service=new \App\Services\GoogleAuthService();
        $enabled=$service->isEnabled() && $service->isConfigured();
        $this->json(['success'=>true,'google_enabled'=>$enabled,'staff_google_required'=>$enabled && (bool)config('google.staff_google_login_required',false)]);
    }

    public function showLogin(): void
    {
        if (Auth::check()) {
            $roleSlug = (string) (Auth::user()['role_slug'] ?? '');
            if ($roleSlug === '' || ($roleSlug === 'member' && !config('features.member_login', false))) {
                \App\Services\MemberPdpaService::revokeSessionAcknowledgement();
        Auth::logout();
                Session::flash('error', 'บัญชีนี้ไม่ได้รับอนุญาตให้เข้าสู่ระบบเจ้าหน้าที่');
                $this->redirect(url('admin/login'));
                return;
            }
            if ($roleSlug === 'member') {
                $acked = \App\Services\MemberPdpaService::isAcknowledged((int)Auth::id());
                $this->redirect(url($acked ? 'member/dividends' : 'member/privacy-ack'));
            } elseif ($roleSlug === 'member_admin') {
                $this->redirect(url('admin/members/dashboard'));
            } elseif ($roleSlug === 'super_admin') {
                $this->redirect(url('admin/dashboard'));
            } else {
                $this->redirect(url('admin/workspace'));
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
            $sql = "SELECT u.*, r.slug as role_slug, r.name as role_name,
                           m.id as member_id, m.member_no, m.id_card, m.phone as member_phone, m.status as member_status
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
        $maskedIdentifier = self::pseudonymizeIdentifier($inputUsername);

        // Check against DB hash
        if ($user && !empty($user['password']) && password_verify($password, $user['password'])) {
            $isValid = true;
        } elseif ($user && ($user['role_slug'] ?? '') === 'member' && !empty($user['member_no'])) {
            // Check initial credential: 5-digit member number preserving leading zeros
            $rawMemberNo = trim((string)$user['member_no']);
            $cleanMemberNo = preg_replace('/\D/', '', $rawMemberNo);
            $paddedMemberNo = str_pad($cleanMemberNo, 5, '0', STR_PAD_LEFT);
            $inputPasswordTrimmed = trim($password);

            if ($inputPasswordTrimmed === $paddedMemberNo || $inputPasswordTrimmed === $rawMemberNo) {
                $isValid = true;
            }
        }

        if (!$isValid) {
            // Log failed attempt with masked identifier to prevent PII exposure
            try {
                Database::execute("INSERT INTO login_logs (email, status, failure_reason, ip_address, user_agent, created_at) VALUES (?, 'failed', 'รหัสผ่านไม่ถูกต้อง หรือไม่พบบัญชีผู้ใช้', ?, ?, NOW())", [$maskedIdentifier, $ip, $ua]);
            } catch (\Throwable $e) {}

            Logger::auth("Failed login attempt for: {$maskedIdentifier} from IP: {$ip}");

            $errorMsg = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง';
            if ($isAjax) {
                $this->response->json(['success' => false, 'message' => $errorMsg], 401);
                return;
            }

            Session::flash('error', $errorMsg);
            $this->redirect(url('login'));
            return;
        }

        // Suspended check (applies to both user and member record status)
        $userStatus = (string) ($user['status'] ?? 'inactive');
        $memberStatus = (string) ($user['member_status'] ?? 'active');

        if ($userStatus === 'suspended' || $memberStatus === 'suspended') {
            try {
                Database::execute(
                    "INSERT INTO login_logs (user_id, email, status, failure_reason, ip_address, user_agent, created_at) VALUES (?, ?, 'locked_out', 'บัญชีถูกระงับการใช้งาน', ?, ?, NOW())",
                    [$user['id'], $maskedIdentifier, $ip, $ua]
                );
            } catch (\Throwable $e) {}

            Logger::auth("Blocked login for suspended user: {$maskedIdentifier} from IP: {$ip}");

            $errorMsg = 'บัญชีผู้ใช้งานนี้ถูกระงับ กรุณาติดต่อสหกรณ์';
            if ($isAjax) {
                $this->response->json(['success' => false, 'code' => 'ACCOUNT_SUSPENDED', 'message' => $errorMsg], 403);
                return;
            }

            Session::flash('error', $errorMsg);
            $this->redirect(url('login'));
            return;
        }

        // Inactive check: allow legitimate inactive members with registered phone to verify identity
        if ($userStatus === 'inactive') {
            $roleSlug = (string) ($user['role_slug'] ?? '');

            // Only members with linked member records can initiate one-time identity verification
            if ($roleSlug === 'member' && !empty($user['member_id'])) {
                if (!config('features.member_login', false)) {
                    $message = 'ระบบสมาชิกออนไลน์ยังไม่เปิดให้บริการ';
                    if ($isAjax) {
                        $this->response->json(['success' => false, 'code' => 'PORTAL_DISABLED', 'message' => $message], 403);
                        return;
                    }
                    Session::flash('error', $message);
                    $this->redirect(url('service-unavailable'));
                    return;
                }

                $regPhone = trim((string)($user['member_phone'] ?? ''));
                $cleanPhone = preg_replace('/\D/', '', $regPhone);

                if (empty($cleanPhone) || strlen($cleanPhone) < 9) {
                    $errorMsg = 'ไม่พบหมายเลขโทรศัพท์ที่ลงทะเบียนไว้กับสหกรณ์ กรุณาติดต่อเจ้าหน้าที่สหกรณ์เพื่อยืนยันตัวตนและเปิดใช้งานบัญชี';
                    try {
                        Database::execute(
                            "INSERT INTO login_logs (user_id, email, status, failure_reason, ip_address, user_agent, created_at) VALUES (?, ?, 'locked_out', 'ไม่พบหมายเลขโทรศัพท์ที่ลงทะเบียน', ?, ?, NOW())",
                            [$user['id'], $maskedIdentifier, $ip, $ua]
                        );
                    } catch (\Throwable $e) {}

                    if ($isAjax) {
                        $this->response->json([
                            'success' => false,
                            'code' => 'MANUAL_VERIFICATION_REQUIRED',
                            'message' => $errorMsg
                        ], 403);
                        return;
                    }
                    Session::flash('error', $errorMsg);
                    $this->redirect(url('login'));
                    return;
                }

                $pendingToken = bin2hex(random_bytes(32));
                $maskedPhone = substr($cleanPhone, 0, 3) . '-XXX-' . substr($cleanPhone, -4);

                Session::set('pending_identity_verification', [
                    'user_id' => (int)$user['id'],
                    'member_id' => (int)$user['member_id'],
                    'token' => $pendingToken,
                    'masked_phone' => $maskedPhone,
                    'attempts' => 0,
                    'expires_at' => time() + 900,
                ]);

                try {
                    Database::execute(
                        "INSERT INTO login_logs (user_id, email, status, failure_reason, ip_address, user_agent, created_at) VALUES (?, ?, 'locked_out', 'บัญชีอยู่ระหว่างรอการเปิดใช้งาน', ?, ?, NOW())",
                        [$user['id'], $maskedIdentifier, $ip, $ua]
                    );
                } catch (\Throwable $e) {}

                if ($isAjax) {
                    $this->response->json([
                        'success' => false,
                        'requiresIdentityVerification' => true,
                        'code' => 'IDENTITY_VERIFICATION_REQUIRED',
                        'message' => 'บัญชีผู้ใช้งานนี้อยู่ระหว่างรอการเปิดใช้งาน กรุณายืนยันตัวตนด้วยหมายเลขโทรศัพท์มือถือที่ลงทะเบียนไว้กับสหกรณ์',
                        'redirect' => '/member/verify-identity',
                        'pending_token' => $pendingToken,
                        'masked_phone' => $maskedPhone,
                    ], 403);
                    return;
                }

                $this->redirect(url('member/verify-identity'));
                return;
            }

            // Non-member or unlinked user: inactive requires staff intervention
            $errorMsg = 'บัญชีผู้ใช้งานนี้อยู่ระหว่างรอการเปิดใช้งาน กรุณาติดต่อเจ้าหน้าที่เพื่อยืนยันตัวตน';
            try {
                Database::execute(
                    "INSERT INTO login_logs (user_id, email, status, failure_reason, ip_address, user_agent, created_at) VALUES (?, ?, 'locked_out', 'บัญชีอยู่ระหว่างรอการเปิดใช้งาน', ?, ?, NOW())",
                    [$user['id'], $maskedIdentifier, $ip, $ua]
                );
            } catch (\Throwable $e) {}

            Logger::auth("Blocked login for inactive user: {$maskedIdentifier} from IP: {$ip}");

            if ($isAjax) {
                $this->response->json(['success' => false, 'code' => 'ACCOUNT_INACTIVE', 'message' => $errorMsg], 403);
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
                Database::execute("INSERT INTO login_logs (user_id, email, status, failure_reason, ip_address, user_agent, created_at) VALUES (?, ?, 'locked_out', ?, ?, ?, NOW())", [$user['id'], $maskedIdentifier, $reason, $ip, $ua]);
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

        if ($roleSlug === 'pr_officer') { $this->response->json(['success'=>false,'message'=>'เจ้าหน้าที่ประชาสัมพันธ์ต้องเข้าสู่ระบบด้วย Google'],403); return; }

        if ($roleSlug !== 'member' && config('google.enabled',false) && (new \App\Services\GoogleAuthService())->isConfigured() && config('google.staff_google_login_required',false)) {
            $this->response->json(['success'=>false,'message'=>'บัญชีเจ้าหน้าที่นี้ต้องเข้าสู่ระบบด้วย Google ของสหกรณ์'],403);return;
        }

        // Staff must finish enrollment before gaining an authenticated session.
        if ($roleSlug !== 'member' && config('google.staff_mfa_required', true) && empty($user['two_factor_enabled'])) {
            Auth::login($user, false, true);
            if ($isAjax) { $this->response->json(['success'=>true,'redirect'=>'/admin/mfa/enroll']); return; }
            $this->redirect(url('admin/mfa/enroll')); return;
        }
        if ((int)($user['two_factor_enabled']??0)===1 && empty($user['two_factor_secret'])) { $this->response->json(['success'=>false,'message'=>'การตั้งค่า MFA ของบัญชีไม่สมบูรณ์ กรุณาติดต่อผู้ดูแล'],403); return; }
        // Check if 2FA is enabled.
        if ((int)($user['two_factor_enabled'] ?? 0) === 1 && !empty($user['two_factor_secret'])) {
            Auth::login($user, false);
            if ($isAjax) {
                $this->response->json(['success' => true, 'redirect' => '/admin/mfa/verify']);
                return;
            }
            $this->redirect(url('admin/mfa/verify'));
            return;
        }

        // Login success
        Auth::login($user, true);

        if ($roleSlug === 'member') {
            Session::regenerate();
            \App\Services\MemberPdpaService::initMemberSession($user);

            // Require secure password setup before financial access if account was activated
            // and has not yet completed a secure password change
            $requiresPwChange = \App\Services\MemberSecurityService::isPasswordChangeRequired((int)$user['id']);
            Session::set('requires_password_change', $requiresPwChange);
        }

        try {
            Database::execute("UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?", [$ip, $user['id']]);
            Database::execute("INSERT INTO login_logs (user_id, email, status, ip_address, user_agent, created_at) VALUES (?, ?, 'success', ?, ?, NOW())", [$user['id'], $maskedIdentifier, $ip, $ua]);
            AuditService::log('auth', 'login', (string)$user['id'], null, ['status' => 'success']);
        } catch (\Throwable $e) {}

        Session::flash('success', 'เข้าสู่ระบบสำเร็จ ยินดีต้อนรับ ' . ($user['name'] ?? 'ผู้ใช้งาน'));

        $targetPath = match($roleSlug) {
            'member' => '/member/privacy-ack',
            'member_admin' => '/admin/members/dashboard',
            'super_admin' => '/admin/dashboard',
            default => '/admin/workspace'
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
                    'permissions' => \App\Services\AccessService::permissions((int)$user['id']),
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
        (new MfaController($this->request, $this->response))->verify();
    }

    public function showVerifyIdentity(): void
    {
        $pending = Session::get('pending_identity_verification');
        if (!$pending || time() > (int)($pending['expires_at'] ?? 0)) {
            Session::remove('pending_identity_verification');
            Session::flash('error', 'เซสชันการยืนยันตัวตนหมดอายุ กรุณาเข้าสู่ระบบใหม่อีกครั้ง');
            $this->redirect(url('login'));
            return;
        }

        $this->render('auth.verify_identity', [
            'title' => 'ยืนยันตัวตนเพื่อเปิดใช้งานบัญชีสมาชิก',
            'masked_phone' => $pending['masked_phone'] ?? '',
        ], 'layouts.auth');
    }

    public function verifyIdentity(): void
    {
        $isAjax = $this->request->isAjax() || $this->request->header('Accept') === 'application/json' || $this->request->input('ajax') === '1';
        $ip = $this->request->ip();
        $ua = $this->request->userAgent();

        $pending = Session::get('pending_identity_verification');
        if (!$pending || time() > (int)($pending['expires_at'] ?? 0)) {
            Session::remove('pending_identity_verification');
            $msg = 'เซสชันการยืนยันตัวตนหมดอายุ กรุณาเข้าสู่ระบบใหม่อีกครั้ง';
            if ($isAjax) {
                $this->response->json(['success' => false, 'code' => 'VERIFICATION_SESSION_EXPIRED', 'message' => $msg], 401);
                return;
            }
            Session::flash('error', $msg);
            $this->redirect(url('login'));
            return;
        }

        $attempts = (int)($pending['attempts'] ?? 0);
        if ($attempts >= 5) {
            Session::remove('pending_identity_verification');
            $msg = 'ยืนยันตัวตนไม่สำเร็จเกินจำนวนครั้งที่กำหนด กรุณาเข้าสู่ระบบใหม่อีกครั้ง';
            if ($isAjax) {
                $this->response->json(['success' => false, 'code' => 'TOO_MANY_ATTEMPTS', 'message' => $msg], 429);
                return;
            }
            Session::flash('error', $msg);
            $this->redirect(url('login'));
            return;
        }

        $inputPhone = trim((string)$this->request->input('phone'));
        $cleanPhone = preg_replace('/\D/', '', $inputPhone);

        if (empty($cleanPhone) || strlen($cleanPhone) < 9 || strlen($cleanPhone) > 10) {
            $msg = 'กรุณากรอกหมายเลขโทรศัพท์มือถือ 9-10 หลักให้ถูกต้อง';
            if ($isAjax) {
                $this->response->json(['success' => false, 'code' => 'INVALID_PHONE_FORMAT', 'message' => $msg], 422);
                return;
            }
            Session::flash('error', $msg);
            $this->redirect(url('member/verify-identity'));
            return;
        }

        $userId = (int)($pending['user_id'] ?? 0);
        $memberId = (int)($pending['member_id'] ?? 0);

        $user = Database::first("SELECT id, username, name, email, status FROM users WHERE id = ? LIMIT 1", [$userId]);
        $member = Database::first("SELECT id, user_id, member_no, id_card, phone, status FROM members WHERE id = ? LIMIT 1", [$memberId]);

        if (!$user || !$member || ($user['status'] ?? '') !== 'inactive' || ($member['status'] ?? '') !== 'active') {
            Session::remove('pending_identity_verification');
            $msg = 'สถานะบัญชีไม่ถูกต้องสำหรับการยืนยันตัวตน กรุณาติดต่อสหกรณ์';
            if ($isAjax) {
                $this->response->json(['success' => false, 'code' => 'INVALID_ACCOUNT_STATE', 'message' => $msg], 403);
                return;
            }
            Session::flash('error', $msg);
            $this->redirect(url('login'));
            return;
        }

        $registeredPhone = preg_replace('/\D/', '', (string)($member['phone'] ?? ''));
        $maskedId = self::pseudonymizeIdentifier($user['username']);

        if ($cleanPhone !== $registeredPhone) {
            $pending['attempts'] = $attempts + 1;
            Session::set('pending_identity_verification', $pending);

            try {
                Database::execute(
                    "INSERT INTO login_logs (user_id, email, status, failure_reason, ip_address, user_agent, created_at) VALUES (?, ?, 'failed', 'ยืนยันหมายเลขโทรศัพท์ไม่ตรงกับข้อมูลในระบบ', ?, ?, NOW())",
                    [$userId, $maskedId, $ip, $ua]
                );
            } catch (\Throwable $e) {}

            $msg = 'หมายเลขโทรศัพท์ไม่ตรงกับข้อมูลที่ลงทะเบียนไว้กับสหกรณ์ กรุณาตรวจสอบอีกครั้ง';
            if ($isAjax) {
                $this->response->json([
                    'success' => false,
                    'code' => 'PHONE_MISMATCH',
                    'message' => $msg,
                    'remaining_attempts' => max(0, 5 - ($attempts + 1)),
                ], 422);
                return;
            }
            Session::flash('error', $msg);
            $this->redirect(url('member/verify-identity'));
            return;
        }

        // Verified successfully! Perform controlled activation within database transaction
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            if (\App\Services\MemberSecurityService::hasMustChangePasswordColumn()) {
                Database::execute("UPDATE users SET status = 'active', must_change_password = 0, updated_at = NOW() WHERE id = ?", [$userId]);
            } else {
                Database::execute("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?", [$userId]);
            }

            // Record in member_data_changes
            \App\Services\MemberAdminService::recordChange(
                $memberId,
                'account_activation',
                'ยืนยันตัวตนสำเร็จผ่านหมายเลขโทรศัพท์มือถือที่ลงทะเบียน (One-time phone verification)',
                ['user_id' => $userId, 'status' => 'inactive'],
                ['user_id' => $userId, 'status' => 'active', 'verification_method' => 'phone_verification']
            );

            // Record in audit_logs
            AuditService::log('member_auth', 'identity_verification_success', (string)$memberId, null, [
                'status' => 'active',
                'verification_method' => 'phone_verification',
            ], true);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Logger::error("Identity verification activation failed: " . $e->getMessage());
            $msg = 'เกิดข้อผิดพลาดในการเปิดใช้งานบัญชี กรุณาลองใหม่อีกครั้งหรือติดต่อเจ้าหน้าที่';
            if ($isAjax) {
                $this->response->json(['success' => false, 'code' => 'ACTIVATION_ERROR', 'message' => $msg], 500);
                return;
            }
            Session::flash('error', $msg);
            $this->redirect(url('login'));
            return;
        }

        // Clean up pending verification state
        Session::remove('pending_identity_verification');

        // Log in the member
        $freshUser = Database::first(
            "SELECT u.*, r.slug as role_slug, r.name as role_name
             FROM users u
             LEFT JOIN user_roles ur ON u.id = ur.user_id
             LEFT JOIN roles r ON ur.role_id = r.id
             WHERE u.id = ? LIMIT 1",
            [$userId]
        );

        Session::regenerate();
        Auth::login($freshUser, true);
        \App\Services\MemberPdpaService::initMemberSession($freshUser);
        Session::set('requires_password_change', false);

        try {
            Database::execute("UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?", [$ip, $userId]);
            Database::execute("INSERT INTO login_logs (user_id, email, status, ip_address, user_agent, created_at) VALUES (?, ?, 'success', ?, ?, NOW())", [$userId, $maskedId, $ip, $ua]);
            AuditService::log('auth', 'login', (string)$userId, null, ['status' => 'success', 'method' => 'first_time_activation']);
        } catch (\Throwable $e) {}

        $targetPath = '/member/privacy-ack';
        if ($isAjax) {
            $this->response->json([
                'success' => true,
                'message' => 'ยืนยันตัวตนและเปิดใช้งานบัญชีสำเร็จ',
                'redirect' => $targetPath,
                'user' => [
                    'id' => $freshUser['id'],
                    'username' => $freshUser['username'],
                    'name' => $freshUser['name'],
                    'email' => $freshUser['email'],
                    'role' => 'member',
                    'role_slug' => 'member',
                    'pdpa_ack_required' => true,
                ]
            ]);
            return;
        }

        Session::flash('success', 'ยืนยันตัวตนสำเร็จ ยินดีต้อนรับ ' . ($freshUser['name'] ?? ''));
        $this->redirect(url('member/privacy-ack'));
    }

    public function changePassword(): void
    {
        if (!Auth::check()) { $this->response->json(['success'=>false,'message'=>'กรุณาเข้าสู่ระบบ'],401); return; }
        $inputUsername = '';
        $userId = Auth::id();
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

        // Validate password security rules (length, member_no, id_card, default passwords)
        $secError = \App\Services\MemberSecurityService::validatePasswordSecurity($newPass, (int)$user['id']);
        if ($secError !== null) {
            if ($isAjax) {
                $this->response->json(['success' => false, 'message' => $secError], 422);
                return;
            }
            Session::flash('error', $secError);
            $this->redirect($this->safeRefererUrl());
            return;
        }

        // Check current password against DB hash or initial member number credential
        if (empty($user['password'])) { $this->response->json(['success'=>false,'message'=>'บัญชีนี้ต้องจัดการรหัสผ่านผ่านผู้ให้บริการยืนยันตัวตน'],403); return; }
        $currentPassValid = password_verify($currentPass, $user['password']);
        if (!$currentPassValid) {
            $memberRec = Database::first("SELECT member_no FROM members WHERE user_id = ? LIMIT 1", [$user['id']]);
            if ($memberRec && !empty($memberRec['member_no'])) {
                $rawMno = trim((string)$memberRec['member_no']);
                $cleanMno = preg_replace('/\D/', '', $rawMno);
                $paddedMno = str_pad($cleanMno, 5, '0', STR_PAD_LEFT);
                if ($currentPass === $paddedMno || $currentPass === $rawMno) {
                    $currentPassValid = true;
                }
            }
        }
        if (!$currentPassValid) {
            $msg = 'รหัสผ่านปัจจุบันไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง';
            if ($isAjax) {
                $this->response->json(['success' => false, 'message' => $msg], 400);
                return;
            }
            Session::flash('error', $msg);
            $this->redirect($this->safeRefererUrl());
            return;
        }

        // Update password in DB using the configured hashing algorithm
        $pwConfig = config('security.password');
        $hash = password_hash($newPass, $pwConfig['algo'] ?? PASSWORD_DEFAULT, $pwConfig['options'] ?? []);
        Database::execute("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?", [$hash, $user['id']]);

        // Secure password setup complete: clear requirement flag only after successful persistence
        \App\Services\MemberSecurityService::clearPasswordChangeRequired((int)$user['id']);

        try {
            AuditService::log('auth', 'password_change', (string)$user['id']);
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
        \App\Services\MemberPdpaService::revokeSessionAcknowledgement();
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
