<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

class DashboardController extends Controller
{
    public function index(): void
    {
        $user = Auth::user();

        // Cooperative summary - genuine database data only
        $totalMembers = Database::hasTable('members')
            ? (int)(Database::value("SELECT COUNT(*) FROM members WHERE status != 'resigned'") ?: 0)
            : 0;

        $newMembersMonth = Database::hasTable('members')
            ? (int)(Database::value("SELECT COUNT(*) FROM members WHERE status != 'resigned' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())") ?: 0)
            : 0;

        $latestStats = Database::hasTable('financial_statistics')
            ? Database::first("SELECT * FROM financial_statistics ORDER BY year DESC, month DESC LIMIT 1")
            : null;

        $coopSummary = [
            'total_members' => $totalMembers,
            'new_members_this_month' => $newMembersMonth,
            'total_deposits' => $latestStats ? (float)$latestStats['total_deposits'] : 0.0,
            'deposit_growth' => 0.0,
            'total_loans' => 0,
            'loan_amount' => $latestStats ? (float)$latestStats['total_loans'] : 0.0,
            'total_assets' => $latestStats ? (float)$latestStats['total_assets'] : 0.0,
            'reserve_fund' => $latestStats ? (float)$latestStats['reserve_fund'] : 0.0,
        ];

        $counts = [
            'published_news' => Database::hasTable('news') ? (int)(Database::value("SELECT COUNT(*) FROM news WHERE workflow_status = 'published' AND deleted_at IS NULL") ?: 0) : 0,
            'draft_news' => Database::hasTable('news') ? (int)(Database::value("SELECT COUNT(*) FROM news WHERE workflow_status IN ('draft', 'submitted', 'under_review') AND deleted_at IS NULL") ?: 0) : 0,
            'active_popups' => Database::hasTable('popups') ? (int)(Database::value("SELECT COUNT(*) FROM popups WHERE status = 'active' AND deleted_at IS NULL") ?: 0) : 0,
            'active_slides' => Database::hasTable('hero_slides') ? (int)(Database::value("SELECT COUNT(*) FROM hero_slides WHERE status = 'active' AND deleted_at IS NULL") ?: 0) : 0,
            'total_documents' => Database::hasTable('documents') ? (int)(Database::value("SELECT COUNT(*) FROM documents WHERE status = 'active' AND deleted_at IS NULL") ?: 0) : 0,
            'total_downloads' => Database::hasTable('documents') ? (int)(Database::value("SELECT SUM(download_count) FROM documents WHERE deleted_at IS NULL") ?: 0) : 0,
            'pending_complaints' => Database::hasTable('complaints') ? (int)(Database::value("SELECT COUNT(*) FROM complaints WHERE status IN ('received', 'under_review', 'assigned', 'in_progress')") ?: 0) : 0,
            'total_users' => Database::hasTable('users') ? (int)(Database::value("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL") ?: 0) : 0,
        ];

        $recentAudits = [];
        try {
            if (Database::hasTable('audit_logs') && Database::hasTable('users')) {
                $recentAudits = Database::query("SELECT a.*, u.name as user_name FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 6");
            }
        } catch (\Throwable $e) {}

        $recentComplaints = [];
        try {
            if (Database::hasTable('complaints')) {
                $recentComplaints = Database::query("SELECT * FROM complaints ORDER BY created_at DESC LIMIT 5");
            }
        } catch (\Throwable $e) {}

        $this->render('admin.dashboard', [
            'title' => 'Dashboard บริหารจัดการสหกรณ์ออมทรัพย์',
            'user' => $user,
            'coopSummary' => $coopSummary,
            'counts' => $counts,
            'recentAudits' => $recentAudits,
            'recentComplaints' => $recentComplaints,
        ], 'layouts.admin');
    }

    public function executive(): void
    {
        $statsHistory = [];
        try {
            if (Database::hasTable('financial_statistics')) {
                $statsHistory = Database::query("SELECT * FROM financial_statistics ORDER BY year ASC, month ASC");
            }
        } catch (\Throwable $e) {}
        $latest = end($statsHistory) ?: null;

        $this->render('admin.executive', [
            'title' => 'Executive Financial Dashboard (สำหรับผู้บริหาร)',
            'statsHistory' => $statsHistory,
            'latest' => $latest,
        ], 'layouts.admin');
    }
}
