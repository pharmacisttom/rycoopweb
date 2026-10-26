<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\GoogleAuthService;
use App\Services\StaffInvitationService;

class GoogleAuthController extends Controller
{
    private GoogleAuthService $googleAuth;

    public function __construct($request = null, $response = null)
    {
        parent::__construct($request, $response);
        $this->googleAuth = new GoogleAuthService();
    }

    /**
     * Start the Google OAuth 2.0 / OpenID Connect authorization code flow.
     * GET /auth/google/start
     */
    public function start(): void
    {
        if (!$this->googleAuth->isEnabled()) {
            Session::flash('error', 'ระบบเข้าสู่ระบบด้วย Google ปิดการใช้งานชั่วคราว');
            $this->redirect(url('admin/login'));
            return;
        }

        if (!$this->googleAuth->isConfigured()) {
            Session::flash('error', 'Google OAuth ยังไม่ได้ตั้งค่า Client ID / Secret ในระบบ');
            $this->redirect(url('admin/login'));
            return;
        }

        Session::start();

        // Generate PKCE code_verifier and code_challenge (S256)
        $codeVerifier = GoogleAuthService::generateCodeVerifier();
        $codeChallenge = GoogleAuthService::generateCodeChallenge($codeVerifier);

        // Cryptographically secure state and nonce
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));

        // Store in session for callback verification
        Session::set('oauth2_state', $state);
        Session::set('oauth2_nonce', $nonce);
        Session::set('oauth2_code_verifier', $codeVerifier);

        // If staff arrived with an invitation token, preserve it in session
        $invitationToken = trim((string) ($this->request->query('invitation') ?? ''));
        if ($invitationToken !== '') {
            Session::set('oauth2_invitation_token', $invitationToken);
        }

        // If an already-authenticated user is linking their Google account
        $linkingUserId = $this->request->query('link_account') ? Auth::id() : null;
        if ($linkingUserId) {
            Session::set('oauth2_linking_user_id', $linkingUserId);
        }

        // Build Google Authorization URL and redirect
        $authUrl = $this->googleAuth->buildAuthorizationUrl($state, $nonce, $codeChallenge);
        $this->redirect($authUrl);
    }

    /**
     * Handle the OAuth 2.0 callback from Google.
     * GET /auth/google/callback
     */
    public function callback(): void
    {
        Session::start();

        // 1. Check for error returned by Google (e.g. user cancelled)
        $error = $this->request->query('error');
        if ($error) {
            $desc = $this->request->query('error_description') ?? 'ผู้ใช้งานยกเลิกการเข้าสู่ระบบผ่าน Google';
            Logger::auth("Google OAuth callback returned error: {$error} ({$desc})");
            Session::flash('error', 'การเข้าสู่ระบบผ่าน Google ถูกยกเลิก: ' . $desc);
            $this->redirect(url('admin/login'));
            return;
        }

        // 2. Validate state parameter (CSRF protection)
        $returnedState = (string) $this->request->query('state');
        $savedState = (string) Session::get('oauth2_state');
        Session::remove('oauth2_state');

        if (empty($returnedState) || empty($savedState) || !hash_equals($savedState, $returnedState)) {
            Logger::auth('Google OAuth callback state mismatch or expired.');
            Session::flash('error', 'เซสชันการยืนยันตัวตนหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง');
            $this->redirect(url('admin/login'));
            return;
        }

        // 3. Retrieve code and PKCE code_verifier
        $code = (string) $this->request->query('code');
        $codeVerifier = (string) Session::get('oauth2_code_verifier');
        $expectedNonce = (string) Session::get('oauth2_nonce');
        Session::remove('oauth2_code_verifier');
        Session::remove('oauth2_nonce');

        if (empty($code) || empty($codeVerifier) || empty($expectedNonce)) {
            Session::flash('error', 'ข้อมูลการยืนยันตัวตนไม่ครบถ้วน กรุณาลองใหม่อีกครั้ง');
            $this->redirect(url('admin/login'));
            return;
        }

        // 4. Exchange authorization code for tokens
        try {
            $tokenData = $this->googleAuth->exchangeCode($code, $codeVerifier);
        } catch (\Throwable $e) {
            Logger::error('Google token exchange exception: ' . $e->getMessage());
            Session::flash('error', 'ไม่สามารถแลกเปลี่ยนโทเคนกับ Google ได้: ' . $e->getMessage());
            $this->redirect(url('admin/login'));
            return;
        }

        // 5. Cryptographically verify OpenID Connect ID Token
        $idToken = (string) ($tokenData['id_token'] ?? '');
        try {
            $googleUser = $this->googleAuth->verifyIdToken($idToken, $expectedNonce);
        } catch (\Throwable $e) {
            Logger::error('Google ID token verification failed: ' . $e->getMessage());
            Session::flash('error', 'การตรวจสอบความปลอดภัยของ Google ID Token ไม่ผ่าน: ' . $e->getMessage());
            $this->redirect(url('admin/login'));
            return;
        }

        $sub = $googleUser['sub'];
        $email = $googleUser['email'];
        $name = $googleUser['name'];
        $ip = $this->request->ip();
        $ua = $this->request->userAgent();

        // 6. Handle linking to an existing active session (Account Linking Flow)
        $linkingUserId = (int) Session::get('oauth2_linking_user_id', 0);
        Session::remove('oauth2_linking_user_id');

        if ($linkingUserId > 0) {
            if ($linkingUserId !== (int) Auth::id()) {
                Session::flash('error', 'เซสชันไม่ถูกต้อง ไม่สามารถเชื่อมต่อบัญชี Google ได้');
                $this->redirect(url('admin/workspace'));
                return;
            }

            try {
                $this->googleAuth->linkUser($linkingUserId, $sub, $email);
                Session::flash('success', "เชื่อมต่อบัญชี Google ({$email}) สำเร็จเรียบร้อยแล้ว");
                $this->redirect(url('admin/workspace'));
                return;
            } catch (\Throwable $e) {
                Session::flash('error', 'ไม่สามารถเชื่อมต่อบัญชี Google ได้: ' . $e->getMessage());
                $this->redirect(url('admin/workspace'));
                return;
            }
        }

        // 7. Find existing linked Google identity
        $identity = $this->googleAuth->findIdentityBySub($sub);

        if ($identity) {
            // Linked identity found
            $userId = (int) $identity['user_id'];
            $userStatus = (string) ($identity['user_status'] ?? 'active');
            $roleSlug = (string) ($identity['role_slug'] ?? '');

            if ($userStatus !== 'active') {
                $this->logLoginAttempt($userId, $email, 'locked_out', 'บัญชีถูกระงับการใช้งาน', $ip, $ua, $sub);
                Session::flash('error', 'บัญชีเจ้าหน้าที่นี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ');
                $this->redirect(url('admin/login'));
                return;
            }

            if ($roleSlug === '' || $roleSlug === 'member') {
                $this->logLoginAttempt($userId, $email, 'locked_out', 'บัญชีนี้ไม่มีสิทธิ์เข้าใช้งานระบบเจ้าหน้าที่', $ip, $ua, $sub);
                Session::flash('error', 'บัญชีนี้ไม่ได้รับสิทธิ์เข้าใช้งานระบบจัดการเจ้าหน้าที่');
                $this->redirect(url('admin/login'));
                return;
            }

            // User is approved and active
            $this->googleAuth->recordLogin($sub);
            $this->handleUserSessionAndMfa($identity, $ip, $ua, $sub);
            return;
        }

        // 8. Identity is not yet linked. Check Staff Invitation or Primary Super Admin Bootstrap
        $invitationToken = (string) Session::get('oauth2_invitation_token', '');
        Session::remove('oauth2_invitation_token');

        // Check if matching pending invitation exists (by token or by verified email)
        $invitation = null;
        if ($invitationToken !== '') {
            $invitation = StaffInvitationService::findValidByToken($invitationToken);
        }
        if (!$invitation) {
            $invitation = StaffInvitationService::findValidByEmail($email);
        }

        if ($invitation) {
            // Valid invitation found for this verified Google user!
            try {
                $accepted = StaffInvitationService::acceptInvitation((int) $invitation['id'], $sub, $email, $name);
                $identity = $this->googleAuth->findIdentityBySub($sub);
                if ($identity) {
                    $this->logLoginAttempt((int) $identity['user_id'], $email, 'success', null, $ip, $ua, $sub);
                    Session::flash('success', "ยินดีต้อนรับ {$name} เปิดใช้งานบัญชีเจ้าหน้าที่เรียบร้อยแล้ว");
                    $this->handleUserSessionAndMfa($identity, $ip, $ua, $sub);
                    return;
                }
            } catch (\Throwable $e) {
                Logger::error('Failed to accept staff invitation: ' . $e->getMessage());
                Session::flash('error', 'ไม่สามารถเปิดใช้งานบัญชีจากคำเชิญได้: ' . $e->getMessage());
                $this->redirect(url('admin/login'));
                return;
            }
        }

        // 9. Check Primary Super Admin Bootstrap
        // The designated PRIMARY_SUPERADMIN_EMAIL (pharmacisttom@gmail.com) can be linked
        // to the primary Super Admin if the authorized bootstrap procedure has been initiated.
        if ($email === $this->googleAuth->getPrimarySuperAdminEmail()) {
            $linked = $this->handlePrimarySuperAdminBootstrap($sub, $email, $name, $ip, $ua);
            if ($linked) {
                return;
            }
        }

        // 10. Not authorized: No invitation, not linked, no open public registration
        $this->logLoginAttempt(null, $email, 'failed', 'บัญชี Google ไม่ได้รับอนุมัติหรือยังไม่ได้รับคำเชิญ', $ip, $ua, $sub);
        Logger::auth("Unauthorized Google login attempt for email: {$email} (sub: {$sub}) from IP: {$ip}");

        Session::flash('error', "บัญชี Google ({$email}) ยังไม่ได้รับการอนุมัติให้เข้าสู่ระบบเจ้าหน้าที่ กรุณาติดต่อผู้ดูแลระบบเพื่อขอรับคำเชิญ");
        $this->redirect(url('admin/login'));
    }

    /**
     * Unlink Google account (requires Super Admin or authenticated confirmation).
     * POST /api/auth/google/unlink
     */
    public function unlink(): void
    {
        if (!Auth::check()) {
            $this->response->json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $targetUserId = (int) ($this->request->input('user_id') ?: Auth::id());
        $currentUserId = (int) Auth::id();

        // Only super_admin can unlink other users
        if ($targetUserId !== $currentUserId && !Auth::hasRole('super_admin')) {
            $this->response->json(['success' => false, 'message' => 'ไม่มีสิทธิ์ยกเลิกการเชื่อมต่อบัญชีผู้อื่น'], 403);
            return;
        }

        $this->googleAuth->unlinkUser($targetUserId);
        $this->response->json(['success' => true, 'message' => 'ยกเลิกการเชื่อมต่อบัญชี Google เรียบร้อยแล้ว']);
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Handle session creation and mandatory MFA routing.
     */
    private function handleUserSessionAndMfa(array $identity, string $ip, string $ua, string $sub): void
    {
        $userId = (int) $identity['user_id'];
        $roleSlug = (string) ($identity['role_slug'] ?? 'staff');
        $hasMfa = (int) ($identity['two_factor_enabled'] ?? 0) === 1 && !empty($identity['two_factor_confirmed_at']);
        $mfaRequired = (bool) config('google.staff_mfa_required', true);

        // Update login stats
        Database::execute("UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?", [$ip, $userId]);
        $this->logLoginAttempt($userId, $identity['google_email'], 'success', null, $ip, $ua, $sub);

        if ($hasMfa) {
            // User has already enrolled in MFA: require TOTP verification
            Auth::login($identity, false, false);
            $this->redirect(url('admin/mfa/verify'));
            return;
        }

        if ($mfaRequired) {
            // Mandatory MFA policy: user must enroll in MFA before gaining full access
            Auth::login($identity, false, true);
            Session::flash('info', 'สหกรณ์กำหนดให้เจ้าหน้าที่ทุกคนต้องเปิดใช้งานการยืนยันตัวตนสองขั้นตอน (2FA)');
            $this->redirect(url('admin/mfa/enroll'));
            return;
        }

        // If MFA is not enforced (e.g. testing)
        Auth::login($identity, true, false);
        $targetPath = match ($roleSlug) {
            'super_admin' => 'admin/dashboard',
            'member_admin' => 'admin/members/dashboard',
            default => 'admin/workspace'
        };
        $this->redirect(url($targetPath));
    }

    /**
     * Handle bootstrap linking for the designated PRIMARY_SUPERADMIN_EMAIL.
     */
    private function handlePrimarySuperAdminBootstrap(string $sub, string $email, string $name, string $ip, string $ua): bool
    {
        // 1. Check if the local super admin account exists
        $superAdmin = Database::first(
            "SELECT u.*, r.slug as role_slug 
             FROM users u
             JOIN user_roles ur ON u.id = ur.user_id
             JOIN roles r ON ur.role_id = r.id
             WHERE r.slug = 'super_admin' AND u.deleted_at IS NULL
             ORDER BY u.id ASC
             LIMIT 1"
        );

        if (!$superAdmin) {
            Logger::error("Primary Super Admin bootstrap failed: No local super_admin account exists.");
            return false;
        }

        $userId = (int) $superAdmin['id'];

        // 2. Check if already linked to another sub
        $existingLink = $this->googleAuth->findIdentityByUserId($userId);
        if ($existingLink) {
            Logger::auth("Super Admin account is already linked to Google sub: {$existingLink['google_sub']}");
            return false;
        }

        // 3. Check bootstrap authorization flag or setting in DB
        // A bootstrap record in site_settings table or env allows initial binding
        $bootstrapAllowed = (bool) Database::value(
            "SELECT `value` FROM site_settings WHERE `key` = 'superadmin_bootstrap_enabled' LIMIT 1"
        ) || config('app.env') === 'local' || config('app.debug') === true;

        // If not explicitly opened or in local, check if this is the initial linking attempt for pharmacisttom@gmail.com
        // when superadmin has never linked before
        $hasAnyGoogleLinks = (int) Database::value("SELECT COUNT(*) FROM user_google_identities") === 0;

        if ($bootstrapAllowed || $hasAnyGoogleLinks) {
            // Execute bootstrap linking
            $this->googleAuth->linkUser($userId, $sub, $email);

            // Update user email and name if designated
            Database::execute("UPDATE users SET email = ?, updated_at = NOW() WHERE id = ?", [$email, $userId]);

            // Disable one-time bootstrap flag
            Database::execute("DELETE FROM site_settings WHERE `key` = 'superadmin_bootstrap_enabled'");

            AuditService::log('google_auth', 'bootstrap_superadmin', (string) $userId, null, [
                'email' => $email,
                'google_sub' => $sub,
                'status' => 'bootstrapped_successfully',
            ]);

            Logger::auth("Primary Super Admin bootstrap completed for {$email} (sub: {$sub}, local user ID: {$userId})");

            $identity = $this->googleAuth->findIdentityBySub($sub);
            if ($identity) {
                $this->handleUserSessionAndMfa($identity, $ip, $ua, $sub);
                return true;
            }
        }

        return false;
    }

    private function logLoginAttempt(
        ?int $userId,
        string $email,
        string $status,
        ?string $failureReason,
        string $ip,
        string $ua,
        ?string $googleSub = null
    ): void {
        try {
            Database::execute(
                "INSERT INTO login_logs (user_id, email, status, auth_provider, google_sub, failure_reason, ip_address, user_agent, created_at)
                 VALUES (?, ?, ?, 'google', ?, ?, ?, ?, NOW())",
                [$userId, $email, $status, $googleSub, $failureReason, $ip, $ua]
            );
        } catch (\Throwable $e) {}
    }
}
