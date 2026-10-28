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
            $heroSlides = Database::query("SELECT * FROM hero_slides WHERE status = 'active' AND deleted_at IS NULL AND (start_at IS NULL OR start_at <= NOW()) AND (end_at IS NULL OR end_at >= NOW()) ORDER BY priority DESC, sort_order ASC");
            $heroSlides = array_map(fn(array $slide): array => $this->withResolvedMediaUrls($slide, ['image_url', 'desktop_image', 'mobile_image']), $heroSlides);
        } catch (\Throwable $e) {}

        try {
            $depositRates = Database::query("SELECT id, product_name, product_type, rate, min_amount, condition_note AS description, NULL AS icon, sort_order FROM interest_rates WHERE product_type = 'deposit' AND status = 'active' ORDER BY sort_order ASC LIMIT 4");
            $loanRates = Database::query("SELECT id, product_name, product_type, rate, min_amount, max_amount, condition_note AS description, NULL AS icon, sort_order FROM interest_rates WHERE product_type = 'loan' AND status = 'active' ORDER BY sort_order ASC");
        } catch (\Throwable $e) {}

        try {
            $latestNews = Database::query("SELECT n.id, n.title, n.slug, n.summary AS excerpt, n.content, n.cover_image, n.publish_at, n.is_pinned, c.name as category_name FROM news n JOIN news_categories c ON n.category_id = c.id WHERE n.deleted_at IS NULL AND n.workflow_status = 'published' AND (n.publish_at IS NULL OR n.publish_at <= NOW()) ORDER BY n.is_pinned DESC, n.publish_at DESC LIMIT 6");
            $latestNews = array_map($this->withPublicNewsImageUrl(...), $latestNews);
        } catch (\Throwable $e) {}

        try {
            $featuredDeposits = Database::query("SELECT id, name, slug, short_description, min_deposit AS min_amount, interest_rate, NULL AS icon, NULL AS badge FROM deposit_products WHERE is_featured = 1 AND status = 'active' ORDER BY sort_order ASC LIMIT 3");
            $featuredLoans = Database::query("SELECT id, name, slug, short_description, max_loan_limit AS max_amount, interest_rate, max_term_months, NULL AS icon, NULL AS badge FROM loan_products WHERE is_featured = 1 AND status = 'active' ORDER BY sort_order ASC LIMIT 3");
            $calcLoanProducts = Database::query("SELECT id, name, slug, interest_rate, max_loan_limit AS max_amount, max_term_months FROM loan_products WHERE is_calculator_enabled = 1 AND status = 'active' ORDER BY sort_order ASC");
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

        try {
            $catalog=\App\Services\PublicContentService::catalog();$rates=\App\Services\PublicContentService::apiRates($catalog);
            $depositRates=$rates['deposit'];$loanRates=$rates['loan'];$featuredLoans=$calcLoanProducts=\App\Services\PublicContentService::apiProducts($catalog);
        }catch(\Throwable $e){$depositRates=[];$loanRates=[];$featuredLoans=[];$calcLoanProducts=[];}

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
        try {$profile=\App\Services\PublicContentService::catalog()['coopInfo'];}
        catch(\Throwable $e){$this->json(['success'=>false,'message'=>'โหลดข้อมูลเผยแพร่ไม่ได้'],503);return;}
        $coopConfig=['full_name_th'=>$profile['nameTh'],'full_name_en'=>$profile['nameEn'],'short_name'=>$profile['shortName'],'phone'=>$profile['phone'],'fax'=>$profile['fax'],'email'=>$profile['email'],'address'=>$profile['address'],'office_hours'=>$profile['workHours']];
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
        try {$catalog=\App\Services\PublicContentService::catalog();$this->json(['success'=>true,'data'=>\App\Services\PublicContentService::apiRates($catalog)]);}
        catch(\Throwable $e){$this->json(['success'=>false,'message'=>'โหลดข้อมูลเผยแพร่ไม่ได้'],503);}
    }
    public function loanProducts(): void
    {
        try {$catalog=\App\Services\PublicContentService::catalog();$this->json(['success'=>true,'data'=>\App\Services\PublicContentService::apiProducts($catalog)]);}
        catch(\Throwable $e){$this->json(['success'=>false,'message'=>'โหลดข้อมูลเผยแพร่ไม่ได้'],503);}
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
            $news = Database::query("SELECT n.id, n.title, n.slug, n.summary AS excerpt, n.content, n.cover_image, n.publish_at, n.is_pinned, c.name as category_name FROM news n JOIN news_categories c ON n.category_id = c.id WHERE n.deleted_at IS NULL AND n.workflow_status = 'published' AND (n.publish_at IS NULL OR n.publish_at <= NOW()) ORDER BY n.is_pinned DESC, n.publish_at DESC LIMIT {$perPage} OFFSET {$offset}");
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
            $docs = array_map(fn(array $doc): array => $this->withResolvedMediaUrls($doc, ['thumbnail', 'thumbnail_url', 'image_url']), $docs);
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
        try {$catalog=\App\Services\PublicContentService::catalog();$members=[];foreach(['boardMembers'=>'board','staffMembers'=>'staff'] as $key=>$group)foreach($catalog[$key] as $index=>$person)$members[]=[...$person,'image_url'=>$person['image']??null,'group_name'=>$group,'term_number'=>$person['term']??null,'sort_order'=>$index];$this->json(['success'=>true,'data'=>$members]);}
        catch(\Throwable $e){$this->json(['success'=>false,'message'=>'โหลดข้อมูลเผยแพร่ไม่ได้'],503);}
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

    /**
     * POST /api/visits/record
     * Logs page visit with PDPA masked IP and increments counters.
     */
    public function recordVisit(): void
    {
        $payload = $this->request->all();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $rawIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (strpos($rawIp, ',') !== false) {
            $rawIp = trim(explode(',', $rawIp)[0]);
        }

        $res = \App\Services\VisitorCounterService::recordVisit($payload, $userAgent, $rawIp);
        $this->json($res);
    }

    /**
     * GET /api/visits/stats
     * Returns public summary counter metrics for header/footer/badges.
     */
    public function visitorStats(): void
    {
        $stats = \App\Services\VisitorCounterService::getPublicStats();
        $this->json(['success' => true, 'data' => $stats]);
    }

    /**
     * GET /api/visits/analytics
     * Returns detailed analytics (30-day timeline, device breakdown, top pages, recent visits).
     */
    public function visitorAnalytics(): void
    {
        $analytics = \App\Services\VisitorCounterService::getDetailedAnalytics();
        $this->json(['success' => true, 'data' => $analytics]);
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
        header('Cache-Control: no-store, private');
        if (!Auth::check()) {
            if (Auth::checkPendingMfaEnrollment()) {
                $this->json([
                    'success' => false,
                    'authenticated' => false,
                    'code' => 'MFA_ENROLLMENT_REQUIRED',
                    'requiresMfaEnrollment' => true,
                    'redirect' => '/admin/mfa/enroll'
                ], 403);
                return;
            }
            if (Auth::checkPending2FA()) {
                $this->json([
                    'success' => false,
                    'authenticated' => false,
                    'code' => 'TWO_FACTOR_REQUIRED',
                    'requiresTwoFactor' => true,
                    'redirect' => '/admin/mfa/verify'
                ], 403);
                return;
            }
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

        $googleIdentity = Database::first(
            "SELECT google_email, google_sub, linked_at FROM user_google_identities WHERE user_id = ? LIMIT 1",
            [$user['id']]
        );

        $hasMfa = (int) ($user['two_factor_enabled'] ?? 0) === 1 && !empty($user['two_factor_confirmed_at']);

        $userData = [
            'id' => $user['id'] ?? null,
            'username' => $user['username'] ?? '',
            'name' => $user['name'] ?? 'ผู้ใช้งาน',
            'email' => $user['email'] ?? '',
            'role' => $roleSlug,
            'role_slug' => $roleSlug,
            'department' => $user['department'] ?? '',
            'permissions' => \App\Services\AccessService::permissions((int)$user['id']),
            'effective_permissions' => \App\Services\AdminControlCenterService::effectivePermissions((int)$user['id']),
            'role_name' => $user['role_name'] ?? match($roleSlug) {
                'super_admin' => 'ผู้ดูแลระบบสูงสุด',
                'staff' => 'เจ้าหน้าที่สินเชื่อ/การเงิน',
                'auditor' => 'ผู้ตรวจสอบกิจการ / ผู้จัดการ',
                default => 'สมาชิกสหกรณ์'
            },
            'mfa' => [
                'enrolled' => $hasMfa,
                'confirmed_at' => $user['two_factor_confirmed_at'] ?? null,
            ],
            'google' => [
                'linked' => !empty($googleIdentity),
                'google_email' => $googleIdentity['google_email'] ?? null,
                'linked_at' => $googleIdentity['linked_at'] ?? null,
            ],
        ];

        if ($member) {
            $userData['member_no'] = $member['member_no'] ?? '';
            $userData['phone'] = $member['phone'] ?? '';
            $userData['department'] = $member['org_name'] ?? ($userData['department'] ?? '');
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
     * GET /api/admin/rates
     * Returns all interest rates (deposits, loans), loan products, and audit history.
     */
    public function adminRates(): void
    {
        if (!Auth::check()) {
            $this->json(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อน'], 401);
            return;
        }

        $depositRates = [];
        $loanRates = [];
        $loanProducts = [];
        $history = [];

        try {
            $depositRates = Database::query("SELECT id, product_type, product_name, rate, min_amount, max_amount, condition_note, effective_date, status, sort_order, updated_at FROM interest_rates WHERE product_type = 'deposit' AND deleted_at IS NULL ORDER BY sort_order ASC, id ASC");
            $loanRates = Database::query("SELECT id, product_type, product_name, rate, min_amount, max_amount, condition_note, effective_date, status, sort_order, updated_at FROM interest_rates WHERE product_type = 'loan' AND deleted_at IS NULL ORDER BY sort_order ASC, id ASC");
            $loanProducts = Database::query("SELECT id, name, slug, short_description, interest_rate, max_loan_limit, max_term_months, calculation_type, guarantor_requirement, is_calculator_enabled, status, sort_order FROM loan_products WHERE deleted_at IS NULL ORDER BY sort_order ASC, id ASC");
            $history = Database::query("SELECT h.id, h.interest_rate_id, h.product_name, h.old_rate, h.new_rate, h.effective_date, h.note, h.created_at, u.name as changed_by_name FROM interest_rate_history h LEFT JOIN users u ON h.changed_by = u.id ORDER BY h.created_at DESC LIMIT 30");
        } catch (\Throwable $e) {}

        $this->json([
            'success' => true,
            'data' => [
                'deposit' => $depositRates,
                'loan' => $loanRates,
                'loanProducts' => $loanProducts,
                'history' => $history,
            ]
        ]);
    }

    /**
     * POST /api/admin/rates/{id}
     * Updates an interest rate, records history, and synchronizes product tables.
     */
    public function updateAdminRate(string $id): void
    {
        try {
            $existing=Database::first('SELECT * FROM interest_rates WHERE id=?',[(int)$id]);
            if(!$existing)throw new \RuntimeException('ไม่พบอัตราดอกเบี้ย',404);
            $section=$existing['product_type']==='deposit'?'deposits':'loan_rates';
            $current=\App\Services\PublicContentService::section($section);$value=$current['value'];$found=false;
            foreach($value as &$rate)if($rate['type']===$existing['product_name']){$rate['rate']=(string)$this->request->input('rate').'%';$found=true;}
            unset($rate);if(!$found)throw new \RuntimeException('ไม่พบรายการในข้อมูลกลาง กรุณาแก้ไขผ่านหน้าข้อมูลพื้นฐาน',409);
            $draft=\App\Services\PublicContentService::saveDraft($section,['value'=>$value,'version'=>$current['version'],'source_url'=>(string)$this->request->input('source_url','https://rayongcoop.com/'),'effective_on'=>(string)$this->request->input('effective_date',date('Y-m-d')),'note'=>(string)$this->request->input('note','ปรับดอกเบี้ยจากหน้าจัดการเดิม')]);
            $this->json(['success'=>true,'id'=>$draft,'message'=>'บันทึกเป็นร่างแล้ว กรุณาตรวจสอบและเผยแพร่ที่หน้าข้อมูลพื้นฐาน','redirect'=>'/admin/public-content']);
        } catch(\Throwable $e){$status=$e instanceof \InvalidArgumentException?422:(in_array($e->getCode(),[401,403,404,409],true)?$e->getCode():500);$this->json(['success'=>false,'message'=>$status===500?'บันทึกร่างไม่สำเร็จ':$e->getMessage()],$status);}
    }

    /**
     * POST /api/admin/loan-products/{id}
     * Updates loan product limits and conditions.
     */
    public function updateAdminLoanProduct(string $id): void
    {
        try {
            $existing=Database::first('SELECT name FROM loan_products WHERE id=?',[(int)$id]);
            if(!$existing)throw new \RuntimeException('ไม่พบผลิตภัณฑ์',404);
            $current=\App\Services\PublicContentService::section('products');$value=$current['value'];$found=false;
            foreach($value as &$product)if($product['title']===$existing['name']){
                if($this->request->input('max_loan_limit')!==null)$product['maxLimit']=$this->request->input('max_loan_limit');
                if($this->request->input('max_term_months')!==null)$product['maxTerm']=$this->request->input('max_term_months');
                if($this->request->input('short_description')!==null)$product['subtitle']=$this->request->input('short_description');
                $found=true;
            }unset($product);
            if(!$found)throw new \RuntimeException('ไม่พบผลิตภัณฑ์ในข้อมูลกลาง กรุณาแก้ไขผ่านหน้าข้อมูลพื้นฐาน',409);
            $id=\App\Services\PublicContentService::saveDraft('products',['value'=>$value,'version'=>$current['version'],'source_url'=>(string)$this->request->input('source_url','https://rayongcoop.com/'),'effective_on'=>date('Y-m-d'),'note'=>(string)$this->request->input('note','ปรับผลิตภัณฑ์จากหน้าจัดการเดิม')]);
            $this->json(['success'=>true,'id'=>$id,'message'=>'บันทึกเป็นร่างแล้ว กรุณาตรวจสอบและเผยแพร่ที่หน้าข้อมูลพื้นฐาน','redirect'=>'/admin/public-content']);
        }catch(\Throwable $e){$status=$e instanceof \InvalidArgumentException?422:(in_array($e->getCode(),[401,403,404,409],true)?$e->getCode():500);$this->json(['success'=>false,'message'=>$status===500?'บันทึกร่างไม่สำเร็จ':$e->getMessage()],$status);}
    }

    public function adminNews(): void
    {
        header('Cache-Control: no-store, private');
        try {$this->json(['success'=>true,'data'=>\App\Services\NewsWorkflowService::listing()]);}
        catch (\Throwable $e) {$this->json(['success'=>false,'message'=>'โหลดข่าวไม่สำเร็จ'],in_array($e->getCode(),[401,403],true)?$e->getCode():500);}
    }
    public function updateAdminNews(string $id): void { $this->createAdminNews($id); }
    public function createAdminNews(?string $editingId = null): void
    {
        try {
            $image=$this->storeNewsCoverImage($this->request->input('image'));
            $item=\App\Services\NewsWorkflowService::save($this->request->all(),$image,$editingId===null?null:(int)$editingId);
            $this->json(['success'=>true,'data'=>$item,'message'=>'บันทึกร่าง / ส่งตรวจแล้ว ยังไม่เผยแพร่'], $editingId===null?201:200);
        } catch (\Throwable $e) {
            $status=$e instanceof \InvalidArgumentException?422:(in_array($e->getCode(),[401,403,404,409],true)?$e->getCode():500);
            $this->json(['success'=>false,'message'=>$status===500?'ไม่สามารถบันทึกข่าวได้':$e->getMessage()],$status);
        }
    }

    /** POST /api/admin/news/{id}/delete */
    public function updateAdminFaq(string $id): void { $this->createAdminFaq($id); }

    public function createAdminFaq(?string $editingId = null): void
    {
        $question=trim((string)$this->request->input('question',''));
        $answer=\App\Services\RichTextService::clean((string)$this->request->input('answer',''));
        if($question==='' || mb_strlen($question)>255 || \App\Services\RichTextService::text($answer)==='' || strlen($answer)>60000){
            $this->json(['success'=>false,'message'=>'กรุณากรอกคำถามและคำตอบให้ครบถ้วน'],422);return;
        }
        try {
            if($editingId!==null){
                $old=Database::first("SELECT id,question FROM faqs WHERE id=? AND status='active'",[(int)$editingId]);
                if(!$old){$this->json(['success'=>false,'message'=>'ไม่พบคำถาม'],404);return;}
                Database::execute('UPDATE faqs SET question=?,answer=?,updated_at=NOW() WHERE id=?',[$question,$answer,(int)$editingId]);$id=(int)$editingId;
            }else{$id=Database::insert("INSERT INTO faqs (category,question,answer,status,created_at) VALUES ('general',?,?,'active',NOW())",[$question,$answer]);}
            AuditService::log('faqs',$editingId===null?'create':'update',(string)$id,$old??null,['question'=>$question]);
            $this->json(['success'=>true,'data'=>['id'=>$id,'question'=>$question,'answer'=>$answer]],201);
        } catch(\Throwable $e){$this->json(['success'=>false,'message'=>'บันทึกคำถามไม่สำเร็จ'],500);}
    }

    public function deleteAdminFaq(string $id): void
    {
        $faq=Database::first('SELECT id,question FROM faqs WHERE id=?',[(int)$id]);
        if(!$faq){$this->json(['success'=>false,'message'=>'ไม่พบคำถาม'],404);return;}
        Database::execute("UPDATE faqs SET status='inactive' WHERE id=?",[(int)$id]);
        AuditService::log('faqs','delete',(string)$id,$faq,null);
        $this->json(['success'=>true]);
    }

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

        $directory = storage_upload_path('news');
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

        $item[$key] = resolve_media_url($image);

        if (array_key_exists('gallery_images', $item)) {
            $gallery = is_string($item['gallery_images'])
                ? json_decode($item['gallery_images'], true)
                : $item['gallery_images'];
            if (is_array($gallery)) {
                $item['gallery_images'] = array_values(array_filter(array_map(
                    static fn (mixed $value): ?string => resolve_media_url($value),
                    $gallery
                )));
            }
        }

        return $item;
    }

    /** Resolve known media fields while leaving non-media values untouched. */
    private function withResolvedMediaUrls(array $item, array $fields): array
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $item)) {
                $item[$field] = resolve_media_url($item[$field]);
            }
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
    public function memberGetRequests(): void
    {
        $member = $this->getAuthenticatedMember();
        if (!$member) return;
        if (($member['status'] ?? '') !== 'active' || (Auth::user()['role_slug'] ?? '') !== 'member') {
            $this->json(['success'=>false,'message'=>'ไม่มีสิทธิ์ใช้งาน'],403);
            return;
        }
        header('Cache-Control: no-store, private');
        try {
            $this->json(['success'=>true,'data'=>\App\Services\MemberRequestService::getMemberRequests((int)$member['id'])]);
        } catch (\Throwable $error) {
            $this->json(['success'=>false,'message'=>'ไม่สามารถโหลดคำร้องได้'],503);
        }
    }

    public function memberSubmitProfileRequest(): void
    {
        $member = $this->getAuthenticatedMember();
        if (!$member) return;
        if (($member['status'] ?? '') !== 'active' || (Auth::user()['role_slug'] ?? '') !== 'member') {
            $this->json(['success'=>false,'message'=>'ไม่มีสิทธิ์ใช้งาน'],403);
            return;
        }
        header('Cache-Control: no-store, private');
        $changes=[];
        foreach (['phone'=>30,'email'=>190,'address'=>500,'note'=>1000] as $field=>$limit) {
            $value=$this->request->input($field);
            if ($value===null) continue;
            if (!is_string($value) || mb_strlen($value)>$limit) {
                $this->json(['success'=>false,'message'=>'รูปแบบข้อมูลไม่ถูกต้อง'],422);
                return;
            }
            $changes[$field]=trim($value);
        }
        if (!empty($changes['email']) && !filter_var($changes['email'],FILTER_VALIDATE_EMAIL)) {
            $this->json(['success'=>false,'message'=>'อีเมลไม่ถูกต้อง'],422);
            return;
        }
        try {
            $data=\App\Services\MemberRequestService::createProfileRequest((int)$member['id'],$changes);
            $this->json(['success'=>true,'data'=>$data]);
        } catch (\InvalidArgumentException $error) {
            $this->json(['success'=>false,'message'=>$error->getMessage()],422);
        } catch (\Throwable $error) {
            $this->json(['success'=>false,'message'=>'ระบบบันทึกคำร้องยังไม่พร้อมใช้งาน'],503);
        }
    }

    private function sanitizeMember(array $member): array
    {
        // Provide PDPA compliant masked identity before unsetting
        $rawIdCard = $member['id_card'] ?? '';
        $rawName = trim(($member['prefix'] ?? '') . ' ' . ($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
        if ($rawName === '') {
            $rawName = $member['name'] ?? '';
        }

        $member['id_card_pdpa'] = \App\Services\PdpaService::maskIdCard($rawIdCard);
        $member['name_pdpa'] = \App\Services\PdpaService::maskName($rawName);

        // Remove PII that should not be sent in raw form
        unset($member['id_card']);
        unset($member['password']);
        if (array_key_exists('avatar', $member)) {
            $member['avatar'] = resolve_media_url($member['avatar']);
        }
        return $member;
    }
}
