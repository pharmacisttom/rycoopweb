<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Services\AuditService;
use App\Services\LedBatchService;
use App\Services\LedMatcherService;
use App\Services\LedSchemaService;
use App\Services\LedService;

class LedApiController extends Controller
{
    protected LedService $ledService;
    protected LedMatcherService $matcherService;
    protected LedSchemaService $schemaService;
    protected LedBatchService $batchService;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->ledService = new LedService();
        $this->matcherService = new LedMatcherService();
        $this->schemaService = new LedSchemaService($this->ledService);
        $this->batchService = new LedBatchService($this->ledService, $this->matcherService);
    }

    /**
     * Check if the current authenticated user has at least one of the given roles
     */
    protected function authorizeRoles(array $allowedRoles): bool
    {
        $user = Auth::user();
        if (!$user) {
            $this->json(['success' => false, 'code' => 'UNAUTHORIZED', 'message' => 'กรุณาเข้าสู่ระบบก่อนดำเนินการ'], 401);
            return false;
        }

        $roleSlug = $user['role_slug'] ?? '';
        if ($roleSlug === 'super_admin') {
            return true;
        }

        // Specifically within the LED Member Check module, Staff has full decision-making and view rights like Admin
        if ($roleSlug === 'staff') {
            return true;
        }

        // Aliases for compatibility
        $normalizedRoles = $allowedRoles;
        if (in_array('admin', $allowedRoles, true)) {
            $normalizedRoles[] = 'it_admin';
            $normalizedRoles[] = 'manager';
            $normalizedRoles[] = 'staff';
        }
        if (in_array('legal_reviewer', $allowedRoles, true)) {
            $normalizedRoles[] = 'staff';
        }
        if (in_array('coop_officer', $allowedRoles, true)) {
            $normalizedRoles[] = 'staff';
            $normalizedRoles[] = 'loan_officer';
            $normalizedRoles[] = 'welfare_officer';
        }
        if (in_array('executive_viewer', $allowedRoles, true)) {
            $normalizedRoles[] = 'executive';
            $normalizedRoles[] = 'manager';
        }

        if (!in_array($roleSlug, $normalizedRoles, true)) {
            $this->json(['success' => false, 'code' => 'FORBIDDEN', 'message' => 'คุณไม่มีสิทธิ์เข้าถึงข้อมูลหรือดำเนินการส่วนนี้ (Role Forbidden)'], 403);
            return false;
        }

        return true;
    }

    /**
     * GET /api/admin/led/dashboard
     */
    public function dashboard(): void
    {
        if (!$this->authorizeRoles(['super_admin', 'admin', 'legal_reviewer', 'coop_officer', 'auditor', 'executive_viewer'])) {
            return;
        }

        try {
            $totalMembers = (int) Database::value("SELECT COUNT(*) FROM members WHERE status = 'active'");

            // Today's checks
            $todayStats = Database::first(
                "SELECT
                    COUNT(*) as checked_today,
                    SUM(CASE WHEN status = 'NOT_FOUND' THEN 1 ELSE 0 END) as not_found,
                    SUM(CASE WHEN status = 'POSSIBLE_MATCH' THEN 1 ELSE 0 END) as possible_match,
                    SUM(CASE WHEN status IN ('REVIEW_REQUIRED', 'MULTIPLE_MATCH') THEN 1 ELSE 0 END) as review_required,
                    SUM(CASE WHEN status = 'VERIFIED_MATCH' THEN 1 ELSE 0 END) as verified_match,
                    SUM(CASE WHEN status = 'FALSE_MATCH' THEN 1 ELSE 0 END) as false_match,
                    SUM(CASE WHEN status = 'API_ERROR' THEN 1 ELSE 0 END) as api_error
                 FROM led_check_results
                 WHERE DATE(checked_at) = CURDATE()"
            );

            // Cumulative summary
            $allStats = Database::first(
                "SELECT
                    COUNT(*) as total_checked,
                    SUM(CASE WHEN status = 'NOT_FOUND' THEN 1 ELSE 0 END) as not_found,
                    SUM(CASE WHEN status = 'POSSIBLE_MATCH' THEN 1 ELSE 0 END) as possible_match,
                    SUM(CASE WHEN status IN ('REVIEW_REQUIRED', 'MULTIPLE_MATCH') THEN 1 ELSE 0 END) as review_required,
                    SUM(CASE WHEN status = 'VERIFIED_MATCH' THEN 1 ELSE 0 END) as verified_match,
                    SUM(CASE WHEN status = 'FALSE_MATCH' THEN 1 ELSE 0 END) as false_match,
                    SUM(CASE WHEN status = 'API_ERROR' THEN 1 ELSE 0 END) as api_error
                 FROM led_check_results"
            );

            // Pending reviews
            $pendingReviewsCount = (int) Database::value(
                "SELECT COUNT(*) FROM led_check_results WHERE status IN ('POSSIBLE_MATCH', 'MULTIPLE_MATCH', 'REVIEW_REQUIRED') AND reviewed_at IS NULL"
            );

            // Recent Batch Runs
            $recentRuns = Database::query(
                "SELECT id, run_type, started_at, completed_at, total_members, checked_members,
                        possible_match_count, review_required_count, verified_match_count, false_match_count,
                        api_error_count, status, created_at
                 FROM led_check_runs
                 ORDER BY id DESC
                 LIMIT 5"
            );

            // Recent Review Queue items
            $recentReviewQueue = Database::query(
                "SELECT r.id, r.run_id, r.member_id, r.query_value, r.checked_at, r.match_score, r.status,
                        m.member_no, m.prefix, m.first_name, m.last_name, m.department
                 FROM led_check_results r
                 JOIN members m ON r.member_id = m.id
                 WHERE r.status IN ('POSSIBLE_MATCH', 'MULTIPLE_MATCH', 'REVIEW_REQUIRED') AND r.reviewed_at IS NULL
                 ORDER BY r.match_score DESC, r.id DESC
                 LIMIT 5"
            );

            // API Health Status
            $health = $this->ledService->healthCheck();

            $totalRequests = (int) ($allStats['total_checked'] ?? 0);
            $totalErrors = (int) ($allStats['api_error'] ?? 0);
            $successRate = $totalRequests > 0 ? round((($totalRequests - $totalErrors) / $totalRequests) * 100, 1) : 100.0;

            $this->json([
                'success' => true,
                'data' => [
                    'kpis' => [
                        'total_members' => $totalMembers,
                        'checked_today' => (int) ($todayStats['checked_today'] ?? 0),
                        'not_found_today' => (int) ($todayStats['not_found'] ?? 0),
                        'possible_match_today' => (int) ($todayStats['possible_match'] ?? 0),
                        'review_required_today' => (int) ($todayStats['review_required'] ?? 0),
                        'verified_match_today' => (int) ($todayStats['verified_match'] ?? 0),
                        'false_match_today' => (int) ($todayStats['false_match'] ?? 0),
                        'api_error_today' => (int) ($todayStats['api_error'] ?? 0),
                        'pending_reviews_total' => $pendingReviewsCount,
                        'total_checked_all_time' => $totalRequests,
                        'api_success_rate' => $successRate,
                    ],
                    'api_health' => $health,
                    'recent_runs' => $recentRuns,
                    'recent_reviews' => $recentReviewQueue,
                ],
            ]);
        } catch (\Throwable $e) {
            Logger::error("LED Dashboard error: " . $e->getMessage());
            $this->json(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการโหลดข้อมูลแดชบอร์ด'], 500);
        }
    }

    /**
     * GET /api/admin/led/schema
     */
    public function schema(): void
    {
        if (!$this->authorizeRoles(['super_admin', 'admin', 'auditor'])) {
            return;
        }

        try {
            $schema = $this->schemaService->getSchema(false);
            AuditService::log('led', 'view_schema', null, null, ['status' => 'success']);

            $this->json([
                'success' => true,
                'data' => $schema,
            ]);
        } catch (\Throwable $e) {
            Logger::error("LED Schema view error: " . $e->getMessage());
            $this->json(['success' => false, 'message' => 'ไม่สามารถดึงข้อมูล Schema ได้'], 500);
        }
    }

    /**
     * POST /api/admin/led/schema/refresh
     */
    public function refreshSchema(): void
    {
        if (!$this->authorizeRoles(['super_admin', 'admin'])) {
            return;
        }

        try {
            $schema = $this->schemaService->refreshSchema();
            AuditService::log('led', 'refresh_schema', null, null, [
                'total_fields' => $schema['total_fields'] ?? 0,
                'duration_ms' => $schema['duration_ms'] ?? 0,
            ]);

            $this->json([
                'success' => true,
                'message' => 'รีเฟรชข้อมูล Schema จากกรมบังคับคดีเรียบร้อยแล้ว',
                'data' => $schema,
            ]);
        } catch (\Throwable $e) {
            Logger::error("LED Schema refresh error: " . $e->getMessage());
            $this->json(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการรีเฟรช Schema'], 500);
        }
    }

    /**
     * GET /api/admin/led/members/search
     */
    public function searchMembers(): void
    {
        if (!$this->authorizeRoles(['super_admin', 'admin', 'coop_officer', 'legal_reviewer'])) {
            return;
        }

        $query = trim((string) $this->request->query('query', ''));
        if ($query === '' || mb_strlen($query) < 2) {
            $this->json(['success' => true, 'data' => []]);
            return;
        }

        try {
            $normalizedQuery = $this->matcherService->normalizeName($query);
            $searchPattern = '%' . $query . '%';
            $normPattern = '%' . $normalizedQuery . '%';

            $sql = "SELECT m.id, m.member_no, m.prefix, m.first_name, m.last_name, m.department, m.id_card,
                           r.status as last_status, r.match_score as last_score, r.checked_at as last_checked_at
                    FROM members m
                    LEFT JOIN (
                        SELECT r1.*
                        FROM led_check_results r1
                        JOIN (
                            SELECT member_id, MAX(id) as max_id
                            FROM led_check_results
                            GROUP BY member_id
                        ) r2 ON r1.id = r2.max_id
                    ) r ON r.member_id = m.id
                    WHERE m.member_no LIKE ? OR m.first_name LIKE ? OR m.last_name LIKE ? OR CONCAT(m.first_name, ' ', m.last_name) LIKE ? OR m.first_name LIKE ? OR m.last_name LIKE ?
                    LIMIT 20";

            $rows = Database::query($sql, [
                $searchPattern, $searchPattern, $searchPattern, $searchPattern, $normPattern, $normPattern
            ]);

            $results = array_map(function ($row) {
                return [
                    'id' => (int) $row['id'],
                    'member_no' => $row['member_no'],
                    'prefix' => $row['prefix'],
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'full_name' => trim("{$row['prefix']} {$row['first_name']} {$row['last_name']}"),
                    'name_pdpa' => \App\Services\PdpaService::maskName(trim("{$row['prefix']} {$row['first_name']} {$row['last_name']}")),
                    'department' => $row['department'],
                    'id_card_masked' => $this->maskCitizenId($row['id_card'] ?? ''),
                    'id_card_pdpa' => \App\Services\PdpaService::maskIdCard($row['id_card'] ?? ''),
                    'last_status' => $row['last_status'] ?? null,
                    'last_score' => $row['last_score'] !== null ? (float) $row['last_score'] : null,
                    'last_checked_at' => $row['last_checked_at'] ?? null,
                ];
            }, $rows);

            AuditService::log('led', 'search_members', null, null, [
                'query_length' => mb_strlen($query),
                'results_count' => count($results),
            ]);

            $this->json(['success' => true, 'data' => $results]);
        } catch (\Throwable $e) {
            Logger::error("Search members error: " . $e->getMessage());
            $this->json(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการค้นหาสมาชิก'], 500);
        }
    }

    /**
     * POST /api/admin/led/check-member
     */
    public function checkMember(): void
    {
        if (!$this->authorizeRoles(['super_admin', 'admin', 'coop_officer'])) {
            return;
        }

        $memberId = (int) $this->request->input('member_id');
        if ($memberId <= 0) {
            $this->json(['success' => false, 'message' => 'กรุณาระบุรหัสสมาชิกที่ถูกต้อง'], 422);
            return;
        }

        try {
            $result = $this->batchService->checkMember($memberId);
            if (!$result['success']) {
                $this->json($result, 400);
                return;
            }

            AuditService::log('led', 'check_member', (string) $memberId, null, [
                'result_id' => $result['result_id'],
                'status' => $result['evaluation']['status'] ?? null,
                'score' => $result['evaluation']['match_score'] ?? null,
            ]);

            $this->json([
                'success' => true,
                'message' => 'ตรวจสอบข้อมูลกับกรมบังคับคดีเรียบร้อยแล้ว',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Logger::error("Check member error for ID {$memberId}: " . $e->getMessage());
            $this->json(['success' => false, 'message' => 'ไม่สามารถตรวจสอบข้อมูลได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง'], 500);
        }
    }

    /**
     * GET /api/admin/led/review
     */
    public function reviewQueue(): void
    {
        if (!$this->authorizeRoles(['super_admin', 'admin', 'legal_reviewer', 'coop_officer', 'auditor'])) {
            return;
        }

        $statusFilter = trim((string) $this->request->query('status', 'PENDING'));
        $page = max(1, (int) $this->request->query('page', 1));
        $limit = max(1, min(100, (int) $this->request->query('limit', 20)));
        $offset = ($page - 1) * $limit;

        $where = ["1=1"];
        $params = [];

        if ($statusFilter === 'PENDING') {
            $where[] = "r.status IN ('POSSIBLE_MATCH', 'MULTIPLE_MATCH', 'REVIEW_REQUIRED') AND r.reviewed_at IS NULL";
        } elseif ($statusFilter !== 'ALL' && $statusFilter !== '') {
            $where[] = "r.status = ?";
            $params[] = $statusFilter;
        }

        $whereClause = implode(' AND ', $where);

        try {
            $totalCount = (int) Database::value("SELECT COUNT(*) FROM led_check_results r WHERE {$whereClause}", $params);

            $sql = "SELECT r.id, r.run_id, r.member_id, r.query_value, r.checked_at, r.api_total,
                           r.match_score, r.status, r.reviewed_by, r.reviewed_at, r.review_note,
                           m.member_no, m.prefix, m.first_name, m.last_name, m.department, m.id_card,
                           u.name as reviewer_name
                    FROM led_check_results r
                    JOIN members m ON r.member_id = m.id
                    LEFT JOIN users u ON r.reviewed_by = u.id
                    WHERE {$whereClause}
                    ORDER BY (r.reviewed_at IS NULL) DESC, r.match_score DESC, r.id DESC
                    LIMIT {$limit} OFFSET {$offset}";

            $rows = Database::query($sql, $params);

            $data = array_map(function ($row) {
                return [
                    'id' => (int) $row['id'],
                    'run_id' => (int) $row['run_id'],
                    'member_id' => (int) $row['member_id'],
                    'member_no' => $row['member_no'],
                    'member_name' => trim("{$row['prefix']} {$row['first_name']} {$row['last_name']}"),
                    'department' => $row['department'],
                    'id_card_masked' => $this->maskCitizenId($row['id_card'] ?? ''),
                    'query_value' => $row['query_value'],
                    'api_total' => (int) $row['api_total'],
                    'match_score' => $row['match_score'] !== null ? (float) $row['match_score'] : null,
                    'status' => $row['status'],
                    'checked_at' => $row['checked_at'],
                    'reviewed_by' => $row['reviewed_by'] ? (int) $row['reviewed_by'] : null,
                    'reviewer_name' => $row['reviewer_name'] ?? null,
                    'reviewed_at' => $row['reviewed_at'],
                    'review_note' => $row['review_note'],
                ];
            }, $rows);

            $this->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalCount,
                    'total_pages' => (int) ceil($totalCount / $limit),
                ],
            ]);
        } catch (\Throwable $e) {
            Logger::error("Review queue error: " . $e->getMessage());
            $this->json(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการโหลดรายการตรวจสอบ'], 500);
        }
    }

    /**
     * GET /api/admin/led/review/{id}
     */
    public function reviewDetail(string|int $id): void
    {
        if (!$this->authorizeRoles(['super_admin', 'admin', 'legal_reviewer', 'coop_officer', 'auditor'])) {
            return;
        }

        $resultId = (int) $id;
        try {
            $sql = "SELECT r.*, m.member_no, m.prefix, m.first_name, m.last_name, m.department, m.position,
                           m.phone, m.email, m.join_date, m.id_card, u.name as reviewer_name
                    FROM led_check_results r
                    JOIN members m ON r.member_id = m.id
                    LEFT JOIN users u ON r.reviewed_by = u.id
                    WHERE r.id = ?
                    LIMIT 1";

            $result = Database::first($sql, [$resultId]);
            if (!$result) {
                $this->json(['success' => false, 'message' => 'ไม่พบข้อมูลการตรวจสอบ'], 404);
                return;
            }

            // Fetch candidate records
            $candidates = Database::query(
                "SELECT id, external_record_id, external_data_json, normalized_name, match_score, match_reason, created_at
                 FROM led_match_records
                 WHERE check_result_id = ?
                 ORDER BY match_score DESC, id ASC",
                [$resultId]
            );

            $formattedCandidates = array_map(function ($c) {
                $raw = json_decode((string) $c['external_data_json'], true) ?: [];
                return [
                    'id' => (int) $c['id'],
                    'external_record_id' => $c['external_record_id'],
                    'normalized_name' => $c['normalized_name'],
                    'match_score' => $c['match_score'] !== null ? (float) $c['match_score'] : null,
                    'match_reason' => $c['match_reason'],
                    'court_name' => $raw['COURT_NAME'] ?? '-',
                    'dept_name' => $raw['DEPT_NAME'] ?? '-',
                    'black_case' => trim(($raw['PREFIX_BLACK_CASE'] ?? '') . ' ' . ($raw['BLACK_CASE'] ?? '') . '/' . ($raw['BLACK_YY'] ?? '')),
                    'red_case' => trim(($raw['PREFIX_RED_CASE'] ?? '') . ' ' . ($raw['RED_CASE'] ?? '') . '/' . ($raw['RED_YY'] ?? '')),
                    'court_date' => $raw['COURT_DATE'] ?? '-',
                    'capital_amount' => !empty($raw['CAPITAL_AMOUNT']) ? number_format((float) $raw['CAPITAL_AMOUNT'], 2) . ' บาท' : '-',
                    'raw_data' => $raw,
                ];
            }, $candidates);

            AuditService::log('led', 'view_review_detail', (string) $resultId);

            $this->json([
                'success' => true,
                'data' => [
                    'result' => [
                        'id' => (int) $result['id'],
                        'run_id' => (int) $result['run_id'],
                        'status' => $result['status'],
                        'match_score' => $result['match_score'] !== null ? (float) $result['match_score'] : null,
                        'query_value' => $result['query_value'],
                        'query_type' => $result['query_type'],
                        'checked_at' => $result['checked_at'],
                        'reviewed_by' => $result['reviewed_by'] ? (int) $result['reviewed_by'] : null,
                        'reviewer_name' => $result['reviewer_name'] ?? null,
                        'reviewed_at' => $result['reviewed_at'],
                        'review_note' => $result['review_note'],
                    ],
                    'member' => [
                        'id' => (int) $result['member_id'],
                        'member_no' => $result['member_no'],
                        'prefix' => $result['prefix'],
                        'first_name' => $result['first_name'],
                        'last_name' => $result['last_name'],
                        'full_name' => trim("{$result['prefix']} {$result['first_name']} {$result['last_name']}"),
                        'name_pdpa' => \App\Services\PdpaService::maskName(trim("{$result['prefix']} {$result['first_name']} {$result['last_name']}")),
                        'department' => $result['department'],
                        'position' => $result['position'],
                        'join_date' => $result['join_date'],
                        'phone_masked' => $this->maskPhone($result['phone'] ?? ''),
                        'id_card_masked' => $this->maskCitizenId($result['id_card'] ?? ''),
                        'id_card_pdpa' => \App\Services\PdpaService::maskIdCard($result['id_card'] ?? ''),
                    ],
                    'candidates' => $formattedCandidates,
                ],
            ]);
        } catch (\Throwable $e) {
            Logger::error("Review detail error: " . $e->getMessage());
            $this->json(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการโหลดรายละเอียดผลตรวจ'], 500);
        }
    }

    /**
     * POST /api/admin/led/review/{id}
     * Human verification action - only legal_reviewer, admin, or super_admin
     */
    public function submitReview(string|int $id): void
    {
        if (!$this->authorizeRoles(['super_admin', 'admin', 'legal_reviewer'])) {
            return;
        }

        $resultId = (int) $id;
        $decision = trim((string) $this->request->input('decision', ''));
        $note = trim((string) $this->request->input('review_note', ''));

        $allowedDecisions = ['VERIFIED_MATCH', 'FALSE_MATCH', 'REVIEW_REQUIRED'];
        if (!in_array($decision, $allowedDecisions, true)) {
            $this->json(['success' => false, 'message' => 'สถานะการตัดสินไม่ถูกต้อง (ต้องเป็น VERIFIED_MATCH, FALSE_MATCH หรือ REVIEW_REQUIRED)'], 422);
            return;
        }

        try {
            $current = Database::first("SELECT id, run_id, status FROM led_check_results WHERE id = ? LIMIT 1", [$resultId]);
            if (!$current) {
                $this->json(['success' => false, 'message' => 'ไม่พบข้อมูลการตรวจสอบ'], 404);
                return;
            }

            $userId = Auth::id();
            $oldStatus = $current['status'];

            Database::execute(
                "UPDATE led_check_results SET
                    status = ?,
                    reviewed_by = ?,
                    reviewed_at = NOW(),
                    review_note = ?,
                    updated_at = NOW()
                 WHERE id = ?",
                [$decision, $userId, $note, $resultId]
            );

            // Update parent run counts if applicable
            $runId = (int) $current['run_id'];
            if ($runId > 0) {
                $stats = Database::first(
                    "SELECT
                        SUM(CASE WHEN status = 'VERIFIED_MATCH' THEN 1 ELSE 0 END) as verified_count,
                        SUM(CASE WHEN status = 'FALSE_MATCH' THEN 1 ELSE 0 END) as false_count,
                        SUM(CASE WHEN status = 'POSSIBLE_MATCH' THEN 1 ELSE 0 END) as possible_count,
                        SUM(CASE WHEN status IN ('REVIEW_REQUIRED', 'MULTIPLE_MATCH') THEN 1 ELSE 0 END) as review_count
                     FROM led_check_results WHERE run_id = ?",
                    [$runId]
                );

                Database::execute(
                    "UPDATE led_check_runs SET
                        verified_match_count = ?,
                        false_match_count = ?,
                        possible_match_count = ?,
                        review_required_count = ?,
                        updated_at = NOW()
                     WHERE id = ?",
                    [
                        (int) ($stats['verified_count'] ?? 0),
                        (int) ($stats['false_count'] ?? 0),
                        (int) ($stats['possible_count'] ?? 0),
                        (int) ($stats['review_count'] ?? 0),
                        $runId
                    ]
                );
            }

            AuditService::log('led', 'human_review_decision', (string) $resultId, ['old_status' => $oldStatus], [
                'new_status' => $decision,
                'reviewed_by' => $userId,
                'note' => $note,
            ]);

            $this->json([
                'success' => true,
                'message' => 'บันทึกผลการตรวจสอบโดยเจ้าหน้าที่เรียบร้อยแล้ว',
                'data' => [
                    'id' => $resultId,
                    'status' => $decision,
                    'reviewed_at' => date('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Throwable $e) {
            Logger::error("Submit review error: " . $e->getMessage());
            $this->json(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกผลการตรวจสอบ'], 500);
        }
    }

    /**
     * POST /api/admin/led/batch
     */
    public function startBatch(): void
    {
        if (!$this->authorizeRoles(['super_admin', 'admin'])) {
            return;
        }

        $department = trim((string) $this->request->input('department', ''));
        $startNo = trim((string) $this->request->input('member_no_start', ''));
        $endNo = trim((string) $this->request->input('member_no_end', ''));
        $maxMembers = (int) $this->request->input('max_members', 0);

        $filters = [];
        if ($department !== '') $filters['department'] = $department;
        if ($startNo !== '') $filters['member_no_start'] = $startNo;
        if ($endNo !== '') $filters['member_no_end'] = $endNo;

        try {
            $created = $this->batchService->createBatchRun($filters, 'BATCH', Auth::id());
            if (!$created['success']) {
                $this->json($created, 400);
                return;
            }

            $runId = (int) $created['run_id'];

            // Process first slice synchronously or limited count, so user sees instant progress
            $sliceLimit = $maxMembers > 0 ? min($maxMembers, 10) : 5;
            $execution = $this->batchService->executeRun($runId, $filters, $sliceLimit);

            AuditService::log('led', 'start_batch_run', (string) $runId, null, [
                'total_members' => $created['total_members'],
                'filters' => $filters,
            ]);

            $this->json([
                'success' => true,
                'message' => 'เริ่มรอบการตรวจสอบข้อมูลสมาชิกเรียบร้อยแล้ว',
                'data' => array_merge($created, $execution),
            ]);
        } catch (\Throwable $e) {
            Logger::error("Start batch error: " . $e->getMessage());
            $this->json(['success' => false, 'message' => 'ไม่สามารถเริ่มการตรวจสอบแบบกลุ่มได้'], 500);
        }
    }

    /**
     * GET /api/admin/led/runs
     */
    public function runs(): void
    {
        if (!$this->authorizeRoles(['super_admin', 'admin', 'auditor'])) {
            return;
        }

        $page = max(1, (int) $this->request->query('page', 1));
        $limit = max(1, min(50, (int) $this->request->query('limit', 20)));
        $offset = ($page - 1) * $limit;

        try {
            $total = (int) Database::value("SELECT COUNT(*) FROM led_check_runs");
            $rows = Database::query(
                "SELECT r.*, u.name as initiator_name
                 FROM led_check_runs r
                 LEFT JOIN users u ON r.initiated_by = u.id
                 ORDER BY r.id DESC
                 LIMIT {$limit} OFFSET {$offset}"
            );

            $this->json([
                'success' => true,
                'data' => $rows,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => (int) ceil($total / $limit),
                ],
            ]);
        } catch (\Throwable $e) {
            Logger::error("Get runs error: " . $e->getMessage());
            $this->json(['success' => false, 'message' => 'ไม่สามารถโหลดประวัติการตรวจสอบได้'], 500);
        }
    }

    /**
     * GET /api/admin/led/runs/{id}
     */
    public function runDetail(string|int $id): void
    {
        if (!$this->authorizeRoles(['super_admin', 'admin', 'auditor'])) {
            return;
        }

        $runId = (int) $id;
        try {
            $run = Database::first(
                "SELECT r.*, u.name as initiator_name
                 FROM led_check_runs r
                 LEFT JOIN users u ON r.initiated_by = u.id
                 WHERE r.id = ?
                 LIMIT 1",
                [$runId]
            );

            if (!$run) {
                $this->json(['success' => false, 'message' => 'ไม่พบข้อมูลรอบการตรวจสอบ'], 404);
                return;
            }

            // Recent results from this run
            $results = Database::query(
                "SELECT res.id, res.member_id, res.query_value, res.match_score, res.status, res.checked_at,
                        m.member_no, m.prefix, m.first_name, m.last_name, m.department
                 FROM led_check_results res
                 JOIN members m ON res.member_id = m.id
                 WHERE res.run_id = ?
                 ORDER BY res.id DESC
                 LIMIT 50",
                [$runId]
            );

            $this->json([
                'success' => true,
                'data' => [
                    'run' => $run,
                    'results' => $results,
                ],
            ]);
        } catch (\Throwable $e) {
            Logger::error("Run detail error: " . $e->getMessage());
            $this->json(['success' => false, 'message' => 'ไม่สามารถโหลดรายละเอียดรอบการตรวจสอบได้'], 500);
        }
    }

    /**
     * Mask citizen ID / id_card for PDPA compliance (192.xxxxxx171)
     */
    protected function maskCitizenId(string $idCard): string
    {
        return \App\Services\PdpaService::maskIdCard($idCard);
    }

    /**
     * Mask phone number for PDPA compliance (081-xxx-x234)
     */
    protected function maskPhone(string $phone): string
    {
        return \App\Services\PdpaService::maskPhone($phone);
    }
}
