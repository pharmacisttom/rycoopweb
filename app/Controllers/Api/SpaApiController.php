<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Services\MemberPortalService;
use App\Services\AuditService;

/**
 * REST API Controller — serves JSON data for the React SPA frontend.
 *
 * All public endpoints require no authentication.
 * Member/Admin endpoints require a valid PHP session (AuthMiddleware).
 */
class SpaApiController extends Controller
{
    // ──────────────────────────────────────────────
    // PUBLIC DATA ENDPOINTS (no auth required)
    // ──────────────────────────────────────────────

    /**
     * GET /api/public/home
     * Returns all data needed for the homepage.
     */
    public function home(): void
    {
        $heroSlides = [];
        $depositRates = [];
        $loanRates = [];
        $latestNews = [];
        $featuredDeposits = [];
        $featuredLoans = [];
        $calcLoanProducts = [];
        $latestStats = null;
        $eservices = [];
        $importantAnnouncements = [];
        $upcomingEvents = [];

        try {
            $heroSlides = Database::query("SELECT id, title, subtitle, image_url, cta_text, cta_url, bg_gradient, priority, sort_order FROM hero_slides WHERE status = 'active' AND (start_at IS NULL OR start_at <= NOW()) AND (end_at IS NULL OR end_at >= NOW()) ORDER BY priority DESC, sort_order ASC");
        } catch (\Throwable $e) {}

        try {
            $depositRates = Database::query("SELECT id, product_name, product_type, rate, min_amount, description, icon, sort_order FROM interest_rates WHERE product_type = 'deposit' AND status = 'active' ORDER BY sort_order ASC LIMIT 4");
            $loanRates = Database::query("SELECT id, product_name, product_type, rate, min_amount, max_amount, description, icon, sort_order FROM interest_rates WHERE product_type = 'loan' AND status = 'active' ORDER BY sort_order ASC");
        } catch (\Throwable $e) {}

        try {
            $latestNews = Database::query("SELECT n.id, n.title, n.slug, n.summary AS excerpt, n.cover_image, n.publish_at, n.is_pinned, c.name as category_name FROM news n JOIN news_categories c ON n.category_id = c.id WHERE n.deleted_at IS NULL AND n.workflow_status = 'published' AND (n.publish_at IS NULL OR n.publish_at <= NOW()) ORDER BY n.is_pinned DESC, n.publish_at DESC LIMIT 6");
            $latestNews = array_map($this->withPublicNewsImageUrl(...), $latestNews);
        } catch (\Throwable $e) {}

        try {
            $featuredDeposits = Database::query("SELECT id, name, slug, short_description, min_amount, interest_rate, icon, badge FROM deposit_products WHERE is_featured = 1 AND status = 'active' ORDER BY sort_order ASC LIMIT 3");
            $featuredLoans = Database::query("SELECT id, name, slug, short_description, max_amount, interest_rate, max_term_months, icon, badge FROM loan_products WHERE is_featured = 1 AND status = 'active' ORDER BY sort_order ASC LIMIT 3");
            $calcLoanProducts = Database::query("SELECT id, name, slug, interest_rate, max_amount, max_term_months FROM loan_products WHERE is_calculator_enabled = 1 AND status = 'active' ORDER BY sort_order ASC");
        } catch (\Throwable $e) {}

        try {
            $latestStats = Database::first("SELECT total_members, total_assets, total_shares, total_deposits, total_loans, dividend_rate, loan_refund_rate, year, month FROM financial_statistics ORDER BY year DESC, month DESC LIMIT 1");
        } catch (\Throwable $e) {}

        try {
            $eservices = Database::query("SELECT id, title, description, url, icon, badge FROM eservice_links WHERE status = 'active' ORDER BY sort_order ASC LIMIT 6");
        } catch (\Throwable $e) {}

        try {
            $importantAnnouncements = Database::query("SELECT id, title, slug, content, priority, is_pinned, publication_date, badge_text, badge_color FROM important_announcements WHERE status = 'published' AND publication_date <= CURDATE() AND (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY is_pinned DESC, priority DESC, publication_date DESC LIMIT 3");
        } catch (\Throwable $e) {}

        try {
            $upcomingEvents = Database::query("SELECT id, title, description, start_date, start_time, end_date, location, event_type FROM events WHERE status = 'upcoming' AND deleted_at IS NULL AND start_date >= CURDATE() ORDER BY start_date ASC, start_time ASC LIMIT 4");
        } catch (\Throwable $e) {}

        $this->json([
            'success' => true,
            'data' => [
                'heroSlides' => $heroSlides,
                'depositRates' => $depositRates,
                'loanRates' => $loanRates,
                'latestNews' => $latestNews,
                'featuredDeposits' => $featuredDeposits,
                'featuredLoans' => $featuredLoans,
                'calcLoanProducts' => $calcLoanProducts,
                'latestStats' => $latestStats,
                'eservices' => $eservices,
                'importantAnnouncements' => $importantAnnouncements,
                'upcomingEvents' => $upcomingEvents,
            ],
        ]);
    }

    /**
     * GET /api/public/coop-info
     * Returns cooperative information and general config.
     */
    public function coopInfo(): void
    {
        $coopConfig = config('app.coop');
        $stats = null;
        try {
            $stats = Database::first("SELECT total_members, total_assets, total_shares, total_deposits, total_loans, dividend_rate, loan_refund_rate, year, month FROM financial_statistics ORDER BY year DESC, month DESC LIMIT 1");
        } catch (\Throwable $e) {}

        $this->json([
            'success' => true,
            'data' => [
                'name_th' => $coopConfig['full_name_th'] ?? '',
                'name_en' => $coopConfig['full_name_en'] ?? '',
                'short_name' => $coopConfig['short_name'] ?? '',
                'phone' => $coopConfig['phone'] ?? '',
                'fax' => $coopConfig['fax'] ?? '',
                'email' => $coopConfig['email'] ?? '',
                'address' => $coopConfig['address'] ?? '',
                'office_hours' => $coopConfig['office_hours'] ?? '',
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * GET /api/public/interest-rates
     */
    public function interestRates(): void
    {
        $deposit = [];
        $loan = [];
        try {
            $deposit = Database::query("SELECT id, product_name, product_type, rate, min_amount, description, icon, sort_order FROM interest_rates WHERE product_type = 'deposit' AND status = 'active' ORDER BY sort_order ASC");
            $loan = Database::query("SELECT id, product_name, product_type, rate, min_amount, max_amount, description, icon, sort_order FROM interest_rates WHERE product_type = 'loan' AND status = 'active' ORDER BY sort_order ASC");
        } catch (\Throwable $e) {}

        $this->json(['success' => true, 'data' => ['deposit' => $deposit, 'loan' => $loan]]);
    }

    /**
     * GET /api/public/loan-products
     */
    public function loanProducts(): void
    {
        $products = [];
        try {
            $products = Database::query("SELECT id, name, slug, short_description, description, interest_rate, max_amount, max_term_months, min_membership_months, required_guarantors, is_featured, is_calculator_enabled, icon, badge, sort_order FROM loan_products WHERE status = 'active' ORDER BY sort_order ASC");
        } catch (\Throwable $e) {}

        $this->json(['success' => true, 'data' => $products]);
    }

    /**
     * GET /api/public/news
     */
    public function news(): void
    {
        $page = max(1, (int)($this->request->query('page') ?? 1));
        $perPage = 12;
        $offset = ($page - 1) * $perPage;
        $news = [];
        $total = 0;
        try {
            $news = Database::query("SELECT n.id, n.title, n.slug, n.summary AS excerpt, n.cover_image, n.publish_at, n.is_pinned, c.name as category_name FROM news n JOIN news_categories c ON n.category_id = c.id WHERE n.deleted_at IS NULL AND n.workflow_status = 'published' AND (n.publish_at IS NULL OR n.publish_at <= NOW()) ORDER BY n.is_pinned DESC, n.publish_at DESC LIMIT {$perPage} OFFSET {$offset}");
            $news = array_map($this->withPublicNewsImageUrl(...), $news);
            $total = (int)Database::value("SELECT COUNT(*) FROM news WHERE deleted_at IS NULL AND workflow_status = 'published' AND (publish_at IS NULL OR publish_at <= NOW())");
        } catch (\Throwable $e) {}

        $this->json(['success' => true, 'data' => $news, 'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total]]);
    }

    /**
     * GET /api/public/announcements
     */
    public function announcements(): void
    {
        $items = [];
        try {
            $items = Database::query("SELECT id, title, slug, content, priority, is_pinned, publication_date, badge_text, badge_color FROM important_announcements WHERE status = 'published' AND publication_date <= CURDATE() AND (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY is_pinned DESC, priority DESC, publication_date DESC LIMIT 20");
        } catch (\Throwable $e) {}

        $this->json(['success' => true, 'data' => $items]);
    }

    /**
     * GET /api/public/documents
     */
    public function documents(): void
    {
        $docs = [];
        try {
            $docs = Database::query("SELECT id, title, category, file_path, file_size, download_count, created_at FROM documents WHERE status = 'published' ORDER BY created_at DESC");
        } catch (\Throwable $e) {}

        $this->json(['success' => true, 'data' => $docs]);
    }

    /**
     * GET /api/public/welfare
     */
    public function welfare(): void
    {
        $types = [];
        try {
            $types = Database::query("SELECT id, name, description, max_amount, icon, requirements FROM welfare_types WHERE status = 'active' ORDER BY id ASC");
        } catch (\Throwable $e) {}

        $this->json(['success' => true, 'data' => $types]);
    }

    /**
     * GET /api/public/faqs
     */
    public function faqs(): void
    {
        $items = [];
        try {
            $items = Database::query("SELECT id, question, answer, category, sort_order FROM faqs WHERE status = 'active' ORDER BY sort_order ASC");
        } catch (\Throwable $e) {}

        $this->json(['success' => true, 'data' => $items]);
    }

    /**
     * GET /api/public/board-members
     */
    public function boardMembers(): void
    {
        $members = [];
        try {
            $members = Database::query("SELECT id, name, position, image_url, group_name, sort_order FROM board_staff WHERE status = 'active' ORDER BY group_name ASC, sort_order ASC");
        } catch (\Throwable $e) {}

        $this->json(['success' => true, 'data' => $members]);
    }

    /**
     * GET /api/public/statistics
     */
    public function statistics(): void
    {
        $stats = [];
        try {
            $stats = Database::query("SELECT year, month, total_members, total_assets, total_shares, total_deposits, total_loans, dividend_rate, loan_refund_rate FROM financial_statistics ORDER BY year DESC, month DESC LIMIT 24");
        } catch (\Throwable $e) {}

        $this->json(['success' => true, 'data' => $stats]);
    }

    // ──────────────────────────────────────────────
    // MEMBER DATA ENDPOINTS (auth required)
    // ──────────────────────────────────────────────

    /**
     * GET /api/member/dashboard
     * Returns member dashboard summary data.
     */
    public function memberDashboard(): void
    {
        $member = $this->getAuthenticatedMember();
        if (!$member) return;

        $summary = MemberPortalService::getDashboardSummary((int)$member['id']);
        $notifications = MemberPortalService::getNotifications((int)$member['id']);

        $this->json([
            'success' => true,
            'data' => [
                'member' => $this->sanitizeMember($member),
                'summary' => $summary,
                'unreadCount' => $notifications['unread_count'],
            ],
        ]);
    }

    /**
     * GET /api/member/profile
     */
    public function memberProfile(): void
    {
        $member = $this->getAuthenticatedMember();
        if (!$member) return;

        $this->json([
            'success' => true,
            'data' => [
                'member' => $this->sanitizeMember($member),
            ],
        ]);
    }

    /**
     * GET /api/member/shares
     */
    public function memberShares(): void
    {
        $member = $this->getAuthenticatedMember();
        if (!$member) return;

        $memberId = (int)$member['id'];
        $summary = MemberPortalService::getDashboardSummary($memberId);
        $requests = Database::query("SELECT * FROM share_change_requests WHERE member_id = ? ORDER BY created_at DESC", [$memberId]);

        $this->json([
            'success' => true,
            'data' => [
                'shares' => $summary['shares'],
                'requests' => $requests,
            ],
        ]);
    }

    /**
     * GET /api/member/deposits
     */
    public function memberDeposits(): void
    {
        $member = $this->getAuthenticatedMember();
        if (!$member) return;

        $accountNo = $this->request->query('account');
        $typeFilter = $this->request->query('type');
        $depositData = MemberPortalService::getDepositDetails((int)$member['id'], $accountNo, $typeFilter);

        $this->json(['success' => true, 'data' => $depositData]);
    }

    /**
     * GET /api/member/loans
     */
    public function memberLoans(): void
    {
        $member = $this->getAuthenticatedMember();
        if (!$member) return;

        $contractNo = $this->request->query('contract');
        $loanData = MemberPortalService::getLoanDetails((int)$member['id'], $contractNo);

        $this->json(['success' => true, 'data' => $loanData]);
    }

    /**
     * GET /api/member/notifications
     */
    public function memberNotifications(): void
    {
        $member = $this->getAuthenticatedMember();
        if (!$member) return;

        $notifications = MemberPortalService::getNotifications((int)$member['id']);

        $this->json(['success' => true, 'data' => $notifications]);
    }

    /**
     * GET /api/member/receipts
     */
    public function memberReceipts(): void
    {
        $member = $this->getAuthenticatedMember();
        if (!$member) return;

        $memberId = (int)$member['id'];
        $year = (int)($this->request->query('year') ?? date('Y'));
        $month = $this->request->query('month') ? (int)$this->request->query('month') : null;

        $receipts = \App\Services\ReceiptService::getMemberReceipts($memberId, $year ?: null, $month);

        $yearlyReceipts = \App\Services\ReceiptService::getMemberReceipts($memberId, $year ?: (int)date('Y'), null);
        $stats = [
            'total_paid' => array_sum(array_column($yearlyReceipts, 'total_amount')),
            'share_paid' => array_sum(array_column($yearlyReceipts, 'share_amount')),
            'loan_paid' => array_sum(array_column($yearlyReceipts, 'loan_principal')) + array_sum(array_column($yearlyReceipts, 'loan_interest')),
        ];

        $this->json([
            'success' => true,
            'data' => [
                'receipts' => $receipts,
                'stats' => $stats,
                'selectedYear' => $year,
                'selectedMonth' => $month,
            ],
        ]);
    }

    /**
     * GET /api/member/online-services
     */
    public function memberOnlineServices(): void
    {
        $member = $this->getAuthenticatedMember();
        if (!$member) return;

        $requests = Database::query("SELECT * FROM online_requests WHERE member_id = ? ORDER BY created_at DESC", [(int)$member['id']]);

        $this->json(['success' => true, 'data' => $requests]);
    }

    /**
     * GET /api/member/financial-summary
     */
    public function memberFinancialSummary(): void
    {
        $member = $this->getAuthenticatedMember();
        if (!$member) return;

        $summary = MemberPortalService::getDashboardSummary((int)$member['id']);

        $this->json(['success' => true, 'data' => $summary]);
    }

    /**
     * GET /api/member/beneficiaries
     */
    public function memberBeneficiaries(): void
    {
        $member = $this->getAuthenticatedMember();
        if (!$member) return;

        $data = MemberPortalService::getBeneficiaries((int)$member['id']);

        $this->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/member/welfare
     */
    public function memberWelfare(): void
    {
        $member = $this->getAuthenticatedMember();
        if (!$member) return;

        $memberId = (int)$member['id'];
        $types = Database::query("SELECT * FROM welfare_types WHERE status = 'active' ORDER BY id ASC");
        $applications = Database::query("SELECT a.*, t.name as welfare_name FROM welfare_applications a JOIN welfare_types t ON a.welfare_type_id = t.id WHERE a.member_id = ? ORDER BY a.created_at DESC", [$memberId]);

        $this->json([
            'success' => true,
            'data' => [
                'types' => $types,
                'applications' => $applications,
            ],
        ]);
    }

    /**
     * GET /api/auth/me
     * Returns the current authenticated user info (used by React to check session).
     */
    public function me(): void
    {
        if (!Auth::check()) {
            $this->json(['success' => false, 'authenticated' => false], 401);
            return;
        }

        $user = Auth::user();
        $member = MemberPortalService::getMemberByUserId(Auth::id());
        $roleSlug = (string) ($user['role_slug'] ?? '');
        if ($roleSlug === '') {
            Auth::logout();
            $this->json(['success' => false, 'authenticated' => false], 403);
            return;
        }

        $userData = [
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
                default => 'สมาชิกสหกรณ์'
            },
        ];

        if ($member) {
            $userData['member_no'] = $member['member_no'] ?? '';
            $userData['phone'] = $member['phone'] ?? '';
            $userData['department'] = $member['org_name'] ?? '';
            $userData['position'] = $member['position'] ?? '';
        }

        $this->json([
            'success' => true,
            'authenticated' => true,
            'user' => $userData,
        ]);
    }

    // ──────────────────────────────────────────────
    // ADMIN DATA ENDPOINTS (auth + super_admin required)
    // ──────────────────────────────────────────────

    /**
     * GET /api/admin/dashboard
     */
    public function adminDashboard(): void
    {
        if (!Auth::check()) {
            $this->json(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อน'], 401);
            return;
        }

        $totalUsers = 0;
        $totalMembers = 0;
        $totalNews = 0;
        $recentLogs = [];
        $complaints = [];
        $financialStats = null;

        try {
            $totalUsers = (int)Database::value("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL");
            $totalMembers = (int)Database::value("SELECT COUNT(*) FROM members WHERE status = 'active'");
            $totalNews = (int)Database::value("SELECT COUNT(*) FROM news WHERE workflow_status = 'published'");
            $financialStats = Database::first("SELECT total_members, total_assets, total_shares, total_deposits, total_loans, dividend_rate, loan_refund_rate, year, month FROM financial_statistics ORDER BY year DESC, month DESC LIMIT 1");
            $recentLogs = Database::query("SELECT id, module, action, record_id, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 10");
            $complaints = Database::query("SELECT id, tracking_no, topic, complainant_name, status, created_at FROM complaints ORDER BY created_at DESC LIMIT 10");
        } catch (\Throwable $e) {}

        $this->json([
            'success' => true,
            'data' => [
                'totalUsers' => $totalUsers,
                'totalMembers' => $totalMembers,
                'totalNews' => $totalNews,
                'financialStats' => $financialStats,
                'recentLogs' => $recentLogs,
                'recentComplaints' => $complaints,
            ]
        ]);
    }

    /**
     * GET /api/admin/users
     */
    public function adminUsers(): void
    {
        if (!Auth::check()) {
            $this->json(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อน'], 401);
            return;
        }

        $users = [];
        $roles = [];
        try {
            $users = Database::query("SELECT u.id, u.name, u.username, u.email, u.status, u.created_at, u.last_login_at, r.name as role_name, r.slug as role_slug FROM users u LEFT JOIN user_roles ur ON u.id = ur.user_id LEFT JOIN roles r ON ur.role_id = r.id WHERE u.deleted_at IS NULL ORDER BY u.created_at ASC");
            $roles = Database::query("SELECT id, name, slug FROM roles ORDER BY id ASC");
        } catch (\Throwable $e) {}

        $this->json([
            'success' => true,
            'data' => [
                'users' => $users,
                'roles' => $roles,
            ]
        ]);
    }

    /**
     * GET /api/admin/complaints
     */
    public function adminComplaints(): void
    {
        if (!Auth::check()) {
            $this->json(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อน'], 401);
            return;
        }

        $complaints = [];
        try {
            $complaints = Database::query("SELECT * FROM complaints ORDER BY created_at DESC");
        } catch (\Throwable $e) {}

        $this->json(['success' => true, 'data' => $complaints]);
    }

    /**
     * GET /api/admin/audit-logs
     */
    public function adminAuditLogs(): void
    {
        if (!Auth::check()) {
            $this->json(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อน'], 401);
            return;
        }

        $logs = [];
        try {
            $logs = Database::query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 50");
        } catch (\Throwable $e) {}

        $this->json(['success' => true, 'data' => $logs]);
    }

    /**
     * GET /api/admin/news
     * Returns the same database-backed news records that the public website reads.
     */
    public function adminNews(): void
    {
        $news = [];
        try {
            $news = Database::query("SELECT n.id, n.title, n.summary AS excerpt, n.cover_image AS image, n.publish_at AS date, n.views_count AS views, c.name AS category
                FROM news n
                JOIN news_categories c ON n.category_id = c.id
                WHERE n.deleted_at IS NULL
                ORDER BY n.created_at DESC");
            $news = array_map($this->withPublicNewsImageUrl(...), $news);
        } catch (\Throwable $e) {}

        $this->json(['success' => true, 'data' => $news]);
    }

    /**
     * POST /api/admin/news
     * Creates a published article for the React administrator dashboard.
     */
    public function createAdminNews(): void
    {
        $title = trim((string) $this->request->input('title', ''));
        $categoryName = trim((string) $this->request->input('category', ''));
        $excerpt = trim((string) $this->request->input('excerpt', ''));

        if (mb_strlen($title) < 5 || mb_strlen($title) > 255 || $categoryName === '' || $excerpt === '') {
            $this->json(['success' => false, 'message' => 'กรุณากรอกหัวข้อ หมวดหมู่ และสรุปข่าวให้ครบถ้วน'], 422);
            return;
        }

        $category = Database::first('SELECT id FROM news_categories WHERE name = ? AND status = \'active\' LIMIT 1', [$categoryName]);
        if (!$category) {
            $this->json(['success' => false, 'message' => 'ไม่พบหมวดหมู่ข่าวที่เลือก'], 422);
            return;
        }

        try {
            $coverImage = $this->storeNewsCoverImage($this->request->input('image'));
            $authorId = Auth::id();
            $id = Database::insert(
                'INSERT INTO news (category_id, title, slug, summary, content, cover_image, workflow_status, author_id, created_by, publish_at, created_at) VALUES (?, ?, ?, ?, ?, ?, \'published\', ?, ?, NOW(), NOW())',
                [(int) $category['id'], $title, str_slug($title) . '-' . time(), $excerpt, $excerpt, $coverImage, $authorId, $authorId]
            );
            AuditService::log('news', 'create', (string) $id, null, ['title' => $title, 'status' => 'published']);
            $item = Database::first("SELECT n.id, n.title, n.summary AS excerpt, n.cover_image AS image, n.publish_at AS date, n.views_count AS views, c.name AS category
                FROM news n JOIN news_categories c ON n.category_id = c.id WHERE n.id = ? LIMIT 1", [(int) $id]);
            $item = $item ? $this->withPublicNewsImageUrl($item) : null;
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 422);
            return;
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'ไม่สามารถบันทึกข่าวสารได้'], 500);
            return;
        }

        $this->json(['success' => true, 'data' => $item, 'message' => 'เผยแพร่ข่าวสารเรียบร้อยแล้ว'], 201);
    }

    /** POST /api/admin/news/{id}/delete */
    public function deleteAdminNews(string $id): void
    {
        $news = Database::first('SELECT id, title FROM news WHERE id = ? AND deleted_at IS NULL LIMIT 1', [(int) $id]);
        if (!$news) {
            $this->json(['success' => false, 'message' => 'ไม่พบข่าวสารที่ต้องการลบ'], 404);
            return;
        }

        try {
            Database::execute('UPDATE news SET deleted_at = NOW(), updated_by = ? WHERE id = ?', [Auth::id(), (int) $id]);
            AuditService::log('news', 'delete', (string) $id, $news, ['deleted_at' => date('Y-m-d H:i:s')]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'ไม่สามารถลบข่าวสารได้'], 500);
            return;
        }

        $this->json(['success' => true, 'message' => 'ลบข่าวสารเรียบร้อยแล้ว']);
    }

    private function storeNewsCoverImage(mixed $image): ?string
    {
        if (!is_string($image) || $image === '') {
            return null;
        }

        if (!preg_match('/^data:image\/(png|jpe?g|webp);base64,([A-Za-z0-9+\/=\s]+)$/i', $image, $matches)) {
            throw new \InvalidArgumentException('รูปภาพต้องเป็นไฟล์ PNG, JPEG หรือ WEBP ที่ถูกต้อง');
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false || strlen($binary) > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException('รูปภาพต้องมีขนาดไม่เกิน 5 MB');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) {
            throw new \InvalidArgumentException('รองรับเฉพาะรูปภาพ PNG, JPEG และ WEBP');
        }

        $directory = dirname(__DIR__, 3) . '/public/storage/uploads/news';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('ไม่สามารถเตรียมพื้นที่จัดเก็บรูปภาพได้');
        }

        $filename = 'news-' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        if (file_put_contents($directory . '/' . $filename, $binary) === false) {
            throw new \RuntimeException('ไม่สามารถบันทึกรูปภาพได้');
        }

        return '/storage/uploads/news/' . $filename;
    }

    /** Convert a stored relative image path into the public application URL. */
    private function withPublicNewsImageUrl(array $item): array
    {
        $key = array_key_exists('image', $item) ? 'image' : 'cover_image';
        $image = $item[$key] ?? null;

        if (is_string($image) && $image !== '' && !preg_match('#^https?://#i', $image)) {
            $relativePath = preg_replace('#^/?storage/uploads/#', '', $image);
            $item[$key] = storage_url(ltrim($relativePath, '/'));
        }

        return $item;
    }

    // ──────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────

    /**
     * Get the authenticated member or send 401.
     */
    private function getAuthenticatedMember(): ?array
    {
        if (!Auth::check()) {
            $this->json(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อน'], 401);
            return null;
        }

        $member = MemberPortalService::getMemberByUserId(Auth::id());
        if (!$member) {
            $this->json(['success' => false, 'message' => 'ไม่พบข้อมูลสมาชิก'], 404);
            return null;
        }

        return $member;
    }

    /**
     * Remove sensitive fields from member data before sending to client.
     */
    private function sanitizeMember(array $member): array
    {
        // Remove PII that should not be sent in raw form
        unset($member['id_card']);
        unset($member['password']);
        return $member;
    }
}
