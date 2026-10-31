<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\MemberPortalService;

class MemberPortalController extends Controller
{
    private ?array $member = null;
    private int $memberId;

    public function __construct($request = null, $response = null)
    {
        parent::__construct($request, $response);
        
        $this->member = MemberPortalService::getActiveMemberByUserId((int)Auth::id());
        if ($this->member === null) {
            $this->memberId = 0;
            Session::flash('error', 'ไม่พบข้อมูลสมาชิกที่เปิดใช้งานหรือเชื่อมโยงกับบัญชีของคุณ กรุณาติดต่อเจ้าหน้าที่');
            $this->redirect(url('login'));
            return;
        }
        $this->memberId = (int)$this->member['id'];
    }

    /**
     * Stop controller execution immediately if member is not authenticated or not linked.
     */
    private function assertMemberAuthorized(): bool
    {
        if ($this->member === null || $this->memberId <= 0) {
            if ($this->request && $this->request->isAjax()) {
                $this->response->json([
                    'success' => false,
                    'code' => 'MEMBER_NOT_LINKED',
                    'message' => 'ไม่พบข้อมูลสมาชิกที่เปิดใช้งานหรือเชื่อมโยงกับบัญชีของคุณ กรุณาติดต่อเจ้าหน้าที่'
                ], 403);
                return false;
            }
            Session::flash('error', 'ไม่พบข้อมูลสมาชิกที่เปิดใช้งานหรือเชื่อมโยงกับบัญชีของคุณ กรุณาติดต่อเจ้าหน้าที่');
            $this->redirect(url('login'));
            return false;
        }
        return true;
    }

    /**
     * 1. Member Dashboard
     */
    public function dashboard(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        $summary = MemberPortalService::getDashboardSummary($this->memberId);
        $notifications = MemberPortalService::getNotifications($this->memberId);

        $this->render('member.dashboard', [
            'title' => 'Dashboard สมาชิกสหกรณ์',
            'member' => $this->member,
            'summary' => $summary,
            'unreadCount' => $notifications['unread_count'],
        ], 'layouts.member');
    }

    /**
     * 2. Member Profile
     */
    public function profile(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        $notifications = MemberPortalService::getNotifications($this->memberId);

        $this->render('member.profile', [
            'title' => 'ข้อมูลสมาชิก (Member Profile)',
            'member' => $this->member,
            'unreadCount' => $notifications['unread_count'],
        ], 'layouts.member');
    }

    /**
     * Update Profile allowed fields (Phone, Email, Address)
     */
    public function updateProfile(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }

        $phone = trim((string)$this->request->input('phone'));
        $email = trim((string)$this->request->input('email'));
        $address = trim((string)$this->request->input('address'));

        if (empty($phone) || empty($email) || empty($address)) {
            Session::flash('error', 'กรุณากรอกข้อมูลให้ครบถ้วน');
            $this->redirect(url('member/profile'));
            return;
        }

        // Validate Thai phone number (9-10 digits, starting with 0)
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (!preg_match('/^0[0-9]{8,9}$/', $cleanPhone)) {
            Session::flash('error', 'หมายเลขโทรศัพท์ไม่ถูกต้อง (กรุณากรอกหมายเลขโทรศัพท์ 9-10 หลัก)');
            $this->redirect(url('member/profile'));
            return;
        }

        // Validate email format and length
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            Session::flash('error', 'รูปแบบอีเมลไม่ถูกต้อง');
            $this->redirect(url('member/profile'));
            return;
        }

        // Validate address length
        if (mb_strlen($address) < 5 || mb_strlen($address) > 500) {
            Session::flash('error', 'ที่อยู่ต้องมีความยาวระหว่าง 5 ถึง 500 ตัวอักษร');
            $this->redirect(url('member/profile'));
            return;
        }

        Database::execute(
            "UPDATE members SET phone = ?, email = ?, address = ?, updated_at = NOW() WHERE id = ?",
            [$cleanPhone, $email, $address, $this->memberId]
        );

        // Mask PII in audit log payload to prevent sensitive data exposure
        $maskedPhone = substr($cleanPhone, 0, 3) . 'XXXX' . substr($cleanPhone, -3);
        $emailParts = explode('@', $email);
        $maskedEmail = substr($emailParts[0], 0, 2) . '***@' . ($emailParts[1] ?? 'domain.com');

        AuditService::log('member', 'update_profile', (string)$this->memberId, null, [
            'phone' => $maskedPhone,
            'email' => $maskedEmail,
            'address_updated' => true,
        ]);

        Session::flash('success', 'บันทึกการเปลี่ยนแปลงข้อมูลส่วนตัวเรียบร้อยแล้ว');
        $this->redirect(url('member/profile'));
    }

    /**
     * 3. Shares Page
     */
    public function shares(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        $summary = MemberPortalService::getDashboardSummary($this->memberId);
        $requests = Database::query("SELECT * FROM share_change_requests WHERE member_id = ? ORDER BY created_at DESC", [$this->memberId]);
        $notifications = MemberPortalService::getNotifications($this->memberId);

        $this->render('member.shares', [
            'title' => 'หุ้นของสมาชิก (Member Shares)',
            'member' => $this->member,
            'shares' => $summary['shares'],
            'requests' => $requests,
            'unreadCount' => $notifications['unread_count'],
        ], 'layouts.member');
    }

    /**
     * Submit Share Change Request
     */
    public function submitShareChange(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        $requestedAmount = (float)$this->request->input('requested_monthly');
        $changeType = $this->request->input('change_type') ?? 'increase';
        $reason = trim((string)$this->request->input('reason'));

        if ($requestedAmount < 500) {
            Session::flash('error', 'เงินส่งค่าหุ้นรายเดือนต้องไม่ต่ำกว่า 500 บาท');
            $this->redirect(url('member/shares'));
            return;
        }

        $shares = Database::first("SELECT * FROM member_shares WHERE member_id = ?", [$this->memberId]);
        $currentMonthly = (float)($shares['monthly_share'] ?? 1000);

        $reqNo = 'REQ-SH-' . date('Ymd') . '-' . rand(100, 999);

        Database::insert(
            "INSERT INTO share_change_requests (request_no, member_id, current_monthly, requested_monthly, change_type, reason, status, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())",
            [$reqNo, $this->memberId, $currentMonthly, $requestedAmount, $changeType, $reason]
        );

        // Also add to online requests
        Database::insert(
            "INSERT INTO online_requests (request_no, member_id, request_type, title, details, status, timeline_json, created_at) VALUES (?, ?, 'share_change', ?, ?, 'submitted', ?, NOW())",
            [
                $reqNo,
                $this->memberId,
                "คำขอเปลี่ยนแปลงค่าหุ้นรายเดือนเป็น " . number_format($requestedAmount) . " บาท",
                "เหตุผล: {$reason}",
                json_encode([['status' => 'submitted', 'time' => date('d/m/Y H:i'), 'desc' => 'ยื่นคำขอเรียบร้อย']])
            ]
        );

        Session::flash('success', "ส่งคำขอเปลี่ยนแปลงค่าหุ้นสำเร็จ เลขที่คำขอ: {$reqNo}");
        $this->redirect(url('member/shares'));
    }

    /**
     * 4. Deposits Page
     */
    public function deposits(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        $accountNo = $this->request->query('account');
        $typeFilter = $this->request->query('type');
        $depositData = MemberPortalService::getDepositDetails($this->memberId, $accountNo, $typeFilter);
        $notifications = MemberPortalService::getNotifications($this->memberId);

        $this->render('member.deposits', [
            'title' => 'บัญชีเงินฝาก (Savings & Deposits)',
            'member' => $this->member,
            'accounts' => $depositData['accounts'],
            'activeAccount' => $depositData['activeAccount'],
            'transactions' => $depositData['transactions'],
            'typeFilter' => $typeFilter,
            'unreadCount' => $notifications['unread_count'],
        ], 'layouts.member');
    }

    /**
     * 5. Loans Page
     */
    public function loans(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        $contractNo = $this->request->query('contract');
        $loanData = MemberPortalService::getLoanDetails($this->memberId, $contractNo);
        $notifications = MemberPortalService::getNotifications($this->memberId);

        $this->render('member.loans', [
            'title' => 'สัญญาเงินกู้และสินเชื่อ (Loans & Credit)',
            'member' => $this->member,
            'loans' => $loanData['loans'],
            'activeLoan' => $loanData['activeLoan'],
            'schedules' => $loanData['schedules'],
            'unreadCount' => $notifications['unread_count'],
        ], 'layouts.member');
    }

    /**
     * 6. Online Loan Application Wizard
     */
    public function loanApplication(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        $eligibilityOrdinary = MemberPortalService::calculateLoanEligibility($this->memberId, 'ordinary', 35000);
        $notifications = MemberPortalService::getNotifications($this->memberId);

        $this->render('member.loan_apply', [
            'title' => 'ยื่นกู้เงินออนไลน์ (Online Loan Application)',
            'member' => $this->member,
            'eligibility' => $eligibilityOrdinary,
            'unreadCount' => $notifications['unread_count'],
        ], 'layouts.member');
    }

    /**
     * Submit Loan Application POST
     */
    public function submitLoanApplication(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        $data = $this->request->all();
        $files = $_FILES ?? [];
        $res = MemberPortalService::submitLoanApplication($this->memberId, $data, $files);

        if ($this->request->isAjax()) {
            $this->response->json([
                'success' => true,
                'message' => "ยื่นคำขอกู้เงินสำเร็จ เลขที่ {$res['application_no']}",
                'application_no' => $res['application_no'],
                'redirect' => '/member/online-services'
            ]);
            return;
        }

        Session::flash('success', "ยื่นคำขอกู้เงินสำเร็จ เลขที่ {$res['application_no']}");
        $this->redirect(url('member/online-services'));
    }

    /**
     * 7. Welfare Benefits
     */
    public function welfare(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        $types = Database::query("SELECT * FROM welfare_types WHERE status = 'active' ORDER BY id ASC");
        $applications = Database::query("SELECT a.*, t.name as welfare_name FROM welfare_applications a JOIN welfare_types t ON a.welfare_type_id = t.id WHERE a.member_id = ? ORDER BY a.created_at DESC", [$this->memberId]);
        $notifications = MemberPortalService::getNotifications($this->memberId);

        $this->render('member.welfare', [
            'title' => 'สวัสดิการสมาชิก (Member Welfare)',
            'member' => $this->member,
            'types' => $types,
            'applications' => $applications,
            'unreadCount' => $notifications['unread_count'],
        ], 'layouts.member');
    }

    /**
     * Submit Welfare Claim POST
     */
    public function submitWelfareClaim(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        $typeId = (int)$this->request->input('welfare_type_id');
        $claimAmount = (float)$this->request->input('claim_amount');
        $desc = trim((string)$this->request->input('description'));

        $appNo = 'WLF-' . date('Y') . '-' . str_pad((string)rand(100, 99999), 5, '0', STR_PAD_LEFT);

        Database::insert(
            "INSERT INTO welfare_applications (application_no, member_id, welfare_type_id, claim_amount, description, status, created_at) 
             VALUES (?, ?, ?, ?, ?, 'pending', NOW())",
            [$appNo, $this->memberId, $typeId, $claimAmount, $desc]
        );

        // Add to online requests
        Database::insert(
            "INSERT INTO online_requests (request_no, member_id, request_type, title, details, status, timeline_json, created_at) VALUES (?, ?, 'welfare', ?, ?, 'submitted', ?, NOW())",
            [
                $appNo,
                $this->memberId,
                "คำขอรับสวัสดิการเลขที่ {$appNo}",
                "รายละเอียด: {$desc} ยอดขอรับ " . number_format($claimAmount) . " บาท",
                json_encode([['status' => 'submitted', 'time' => date('d/m/Y H:i'), 'desc' => 'ยื่นคำขอรับสวัสดิการเรียบร้อย']])
            ]
        );

        Session::flash('success', "ยื่นคำขอรับสวัสดิการสำเร็จ เลขที่: {$appNo}");
        $this->redirect(url('member/welfare'));
    }

    /**
     * 8. Beneficiaries
     */
    public function beneficiaries(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        $data = MemberPortalService::getBeneficiaries($this->memberId);
        $notifications = MemberPortalService::getNotifications($this->memberId);

        $this->render('member.beneficiaries', [
            'title' => 'ผู้รับผลประโยชน์ (Beneficiaries)',
            'member' => $this->member,
            'beneficiaries' => $data['list'],
            'totalPercentage' => $data['total_percentage'],
            'isValid' => $data['is_valid'],
            'unreadCount' => $notifications['unread_count'],
        ], 'layouts.member');
    }

    public function saveBeneficiary(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        $res = MemberPortalService::saveBeneficiary($this->memberId, $this->request->all());
        Session::flash('success', $res['message']);
        $this->redirect(url('member/beneficiaries'));
    }

    /**
     * 9. Receipts
     */
    public function receipts(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        $year = (int)$this->request->query('year', (string)date('Y'));
        $month = $this->request->query('month') ? (int)$this->request->query('month') : null;

        $receipts = \App\Services\ReceiptService::getMemberReceipts($this->memberId, $year ?: null, $month);
        $notifications = MemberPortalService::getNotifications($this->memberId);

        // Calculate yearly stats
        $yearlyReceipts = \App\Services\ReceiptService::getMemberReceipts($this->memberId, $year ?: (int)date('Y'), null);
        $stats = [
            'total_paid' => array_sum(array_column($yearlyReceipts, 'total_amount')),
            'share_paid' => array_sum(array_column($yearlyReceipts, 'share_amount')),
            'loan_paid' => array_sum(array_column($yearlyReceipts, 'loan_principal')) + array_sum(array_column($yearlyReceipts, 'loan_interest')),
        ];

        $this->render('member.receipts', [
            'title' => 'ใบเสร็จรับเงินประจำเดือน (Electronic Receipts)',
            'member' => $this->member,
            'receipts' => $receipts,
            'stats' => $stats,
            'selectedYear' => $year,
            'selectedMonth' => $month,
            'unreadCount' => $notifications['unread_count'],
        ], 'layouts.member');
    }

    /**
     * Print e-Receipt (Standard A4 Page & PDF Generator)
     */
    public function printReceipt(string $receiptNo): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        $receipt = \App\Services\ReceiptService::getReceiptDetails($receiptNo);
        $user = \App\Core\Auth::user();
        $isStaffOrAdmin = $user && in_array($user['role_slug'] ?? '', ['super_admin', 'staff', 'admin']);

        if (!$receipt || (!$isStaffOrAdmin && (int)$receipt['member_id'] !== $this->memberId)) {
            \App\Core\Session::flash('error', 'ไม่พบใบเสร็จรับเงินที่ต้องการพิมพ์ หรือไม่มีสิทธิ์เข้าถึง');
            $this->redirect(url('member/receipts'));
            return;
        }

        $this->render('member.receipt_print', [
            'receipt' => $receipt,
            'title' => "ใบเสร็จรับเงิน {$receipt['receipt_no']}"
        ]);
    }

    /**
     * 10. Notifications
     */
    public function notifications(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        $notifications = MemberPortalService::getNotifications($this->memberId);

        $this->render('member.notifications', [
            'title' => 'ศูนย์การแจ้งเตือน (Notifications)',
            'member' => $this->member,
            'list' => $notifications['list'],
            'unreadCount' => $notifications['unread_count'],
        ], 'layouts.member');
    }

    public function markAllNotificationsRead(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        MemberPortalService::markNotificationRead($this->memberId);
        if ($this->request->isAjax()) {
            $this->response->json(['success' => true]);
            return;
        }
        $this->redirect(url('member/notifications'));
    }

    /**
     * 11. Online Services & Request Tracking
     */
    public function onlineServices(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        $requests = Database::query("SELECT * FROM online_requests WHERE member_id = ? ORDER BY created_at DESC", [$this->memberId]);
        $notifications = MemberPortalService::getNotifications($this->memberId);

        $this->render('member.online_services', [
            'title' => 'บริการออนไลน์และติดตามคำขอ (Online Services)',
            'member' => $this->member,
            'requests' => $requests,
            'unreadCount' => $notifications['unread_count'],
        ], 'layouts.member');
    }

    /**
     * 12. Financial Summary
     */
    public function financialSummary(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        $summary = MemberPortalService::getDashboardSummary($this->memberId);
        $notifications = MemberPortalService::getNotifications($this->memberId);

        $this->render('member.financial_summary', [
            'title' => 'ภาพรวมทางการเงินของสมาชิก (Financial Summary)',
            'member' => $this->member,
            'summary' => $summary,
            'unreadCount' => $notifications['unread_count'],
        ], 'layouts.member');
    }

    /**
     * 13. Settings & PDPA & LINE Connection
     */
    public function settings(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        $userId = (int)Auth::id();
        $loginHistory = Database::query("SELECT * FROM login_activities WHERE user_id = ? ORDER BY created_at DESC LIMIT 10", [$userId]);
        $lineConn = Database::first("SELECT * FROM line_connections WHERE member_id = ?", [$this->memberId]);
        $notifications = MemberPortalService::getNotifications($this->memberId);

        $this->render('member.settings', [
            'title' => 'ตั้งค่าบัญชี & ความปลอดภัย (Profile Settings & PDPA)',
            'member' => $this->member,
            'loginHistory' => $loginHistory,
            'lineConn' => $lineConn,
            'unreadCount' => $notifications['unread_count'],
        ], 'layouts.member');
    }

    /**
     * Change Password
     */
    public function changePassword(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        $currentPass = (string)$this->request->input('current_password');
        $newPass = (string)$this->request->input('new_password');
        $confirmPass = (string)$this->request->input('confirm_password');
        $isAjax = $this->request->isAjax() || !empty($this->request->input('ajax'));

        if (empty($newPass) || strlen($newPass) < 8) {
            if ($isAjax) {
                $this->json(['success' => false, 'message' => 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 8 ตัวอักษร'], 422);
                return;
            }
            Session::flash('error', 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 8 ตัวอักษร');
            $this->redirect(url('member/settings'));
            return;
        }

        if ($newPass !== $confirmPass) {
            if ($isAjax) {
                $this->json(['success' => false, 'message' => 'รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน'], 422);
                return;
            }
            Session::flash('error', 'รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน');
            $this->redirect(url('member/settings'));
            return;
        }

        $userId = (int)Auth::id();
        $user = Database::first("SELECT password FROM users WHERE id = ? LIMIT 1", [$userId]);

        if ($user && !empty($user['password']) && !empty($currentPass)) {
            if (!password_verify($currentPass, $user['password'])) {
                if ($isAjax) {
                    $this->json(['success' => false, 'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง'], 422);
                    return;
                }
                Session::flash('error', 'รหัสผ่านปัจจุบันไม่ถูกต้อง');
                $this->redirect(url('member/settings'));
                return;
            }
        }

        $pwConfig = config('security.password');
        $hash = password_hash($newPass, $pwConfig['algo'] ?? PASSWORD_DEFAULT, $pwConfig['options'] ?? []);
        Database::execute("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?", [$hash, $userId]);

        AuditService::log('auth', 'password_change', (string)$userId);

        if ($isAjax) {
            $this->json(['success' => true, 'message' => 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว']);
            return;
        }

        Session::flash('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
        $this->redirect(url('member/settings'));
    }

    /**
     * Toggle LINE OA Connection
     */
    public function toggleLine(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        $action = $this->request->input('action') ?? 'connect';
        if ($action === 'connect') {
            Database::execute(
                "INSERT INTO line_connections (member_id, line_user_id, display_name, status, connected_at) 
                 VALUES (?, 'U" . bin2hex(random_bytes(8)) . "', 'LINE Member', 'connected', NOW())
                 ON DUPLICATE KEY UPDATE status = 'connected', connected_at = NOW()",
                [$this->memberId]
            );
            Session::flash('success', 'เชื่อมต่อบัญชี LINE Official Account เรียบร้อยแล้ว');
        } else {
            Database::execute("UPDATE line_connections SET status = 'disconnected' WHERE member_id = ?", [$this->memberId]);
            Session::flash('info', 'ยกเลิกการเชื่อมต่อ LINE เรียบร้อยแล้ว');
        }
        $this->redirect(url('member/settings'));
    }

    /**
     * Revoke All Other Active Sessions
     */
    public function revokeSessions(): void
    {
        if (!$this->assertMemberAuthorized()) {
            return;
        }
        $userId = (int)Auth::id();
        if ($userId <= 0) {
            $this->redirect(url('login'));
            return;
        }

        Auth::revokeAllSessions($userId);

        AuditService::log('security', 'revoke_sessions', (string)$userId, null, [
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);

        Session::flash('success', 'บังคับออกจากระบบอุปกรณ์อื่นทั้งหมดเรียบร้อยแล้ว');
        $this->redirect(url('member/settings'));
    }
}
