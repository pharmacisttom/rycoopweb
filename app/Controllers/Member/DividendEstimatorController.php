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

        // Fetch member shares
        $shares = Database::first("SELECT * FROM member_shares WHERE member_id = ?", [$this->memberId]);
        $totalShareAmount = $shares ? (float)$shares['total_amount'] : 245000.00;

        // Fetch member annual loan interest paid
        $receiptsInterest = Database::first(
            "SELECT SUM(loan_interest) as total_interest FROM receipts WHERE member_id = ? AND billing_year = YEAR(CURDATE())",
            [$this->memberId]
        );
        $totalInterestPaid = ($receiptsInterest && $receiptsInterest['total_interest'] !== null) 
            ? (float)$receiptsInterest['total_interest'] 
            : 39000.00;

        // Get latest approved stats
        $latestStats = Database::first("SELECT * FROM financial_statistics ORDER BY year DESC, month DESC LIMIT 1");
        $defaultDividendRate = $latestStats ? (float)$latestStats['dividend_rate'] : 5.10;
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
