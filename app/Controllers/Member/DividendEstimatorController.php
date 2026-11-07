<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\MemberPortalService;

class DividendEstimatorController extends Controller
{
    private ?array $member = null;
    private int $memberId = 0;

    public function __construct(Request $request, Response $response)
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
     * Member Interactive Dividend & Patronage Refund Estimator
     */
    public function index(): void
    {
        if ($this->member === null || $this->memberId <= 0) {
            $this->redirect(url('login'));
            return;
        }
        $notifications = MemberPortalService::getNotifications($this->memberId);

        // Fetch verified member shares from database
        $totalShareAmount = 0.0;
        if (Database::hasTable('member_shares')) {
            $shares = Database::first("SELECT * FROM member_shares WHERE member_id = ?", [$this->memberId]);
            if ($shares) {
                $totalShareAmount = (float)($shares['total_amount'] ?? 0.0);
            }
        }

        // Fetch member annual loan interest paid from receipts table
        $totalInterestPaid = 0.0;
        if (Database::hasTable('receipts')) {
            $receiptsInterest = Database::first(
                "SELECT SUM(loan_interest) as total_interest FROM receipts WHERE member_id = ? AND billing_year = YEAR(CURDATE())",
                [$this->memberId]
            );
            if ($receiptsInterest && $receiptsInterest['total_interest'] !== null) {
                $totalInterestPaid = (float)$receiptsInterest['total_interest'];
            }
        }

        // Get latest approved stats from official financial_statistics table
        $latestStats = null;
        $defaultDividendRate = 0.0;
        if (Database::hasTable('financial_statistics')) {
            $latestStats = Database::first("SELECT * FROM financial_statistics ORDER BY year DESC, month DESC LIMIT 1");
            if ($latestStats) {
                $defaultDividendRate = (float)$latestStats['dividend_rate'];
            }
        }
        $defaultRefundRate = 12.50; // Standard cooperative patronage refund rate

        $this->render('member.dividend_estimator', [
            'title' => 'เครื่องมือประมาณการเงินปันผลและเงินเฉลี่ยคืน',
            'member' => $this->member,
            'totalShareAmount' => $totalShareAmount,
            'totalInterestPaid' => $totalInterestPaid,
            'defaultDividendRate' => $defaultDividendRate,
            'defaultRefundRate' => $defaultRefundRate,
            'latestStats' => $latestStats,
            'unreadCount' => $notifications['unread_count'],
        ], 'layouts.member');
    }
}
