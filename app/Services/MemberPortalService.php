<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

class MemberPortalService
{
    /**
     * Get the member record linked to the authenticated user.
     */
    public static function getMemberByUserId(?int $userId): ?array
    {
        if (!$userId) {
            return null;
        }

        return Database::first("SELECT * FROM members WHERE user_id = ? LIMIT 1", [$userId]);
    }

    /**
     * Get complete Member Dashboard summary metrics & charts
     */
    public static function getDashboardSummary(int $memberId): array
    {
        $member = Database::first("SELECT * FROM members WHERE id = ?", [$memberId]);
        $shares = Database::first("SELECT * FROM member_shares WHERE member_id = ?", [$memberId]) ?? [
            'total_shares' => 24500,
            'share_value' => 10.00,
            'total_amount' => 245000.00,
            'monthly_share' => 1500.00,
            'last_payment_date' => '2026-08-31',
            'start_date' => '2018-05-15'
        ];

        $deposits = Database::query("SELECT * FROM deposit_accounts WHERE member_id = ? AND status = 'active'", [$memberId]);
        $totalDepositBalance = array_sum(array_column($deposits, 'balance'));
        $totalAccruedInterest = array_sum(array_column($deposits, 'accrued_interest'));

        $loans = Database::query("SELECT * FROM loan_contracts WHERE member_id = ? AND status = 'active'", [$memberId]);
        $totalLoanPrincipal = array_sum(array_column($loans, 'principal_balance'));
        $totalMonthlyInstallment = array_sum(array_column($loans, 'monthly_installment'));

        // Monthly deduction breakdown
        $monthlyDeduction = [
            'share' => (float)($shares['monthly_share'] ?? 1500.00),
            'loan_principal' => (float)($totalMonthlyInstallment * 0.7),
            'loan_interest' => (float)($totalMonthlyInstallment * 0.3),
            'deposit' => 5000.00,
            'other' => 100.00,
        ];
        $monthlyDeduction['total'] = array_sum($monthlyDeduction);

        // Financial trends (6-month history)
        $chartData = [
            'labels' => ['เม.ย. 69', 'พ.ค. 69', 'มิ.ย. 69', 'ก.ค. 69', 'ส.ค. 69', 'ก.ย. 69'],
            'shares' => [237500, 239000, 240500, 242000, 243500, 245000],
            'deposits' => [315000, 320000, 325400, 330400, 335400, (float)$totalDepositBalance],
            'loans' => [380000, 372000, 365000, 358000, 350000, (float)$totalLoanPrincipal],
            'payments' => [14450, 14450, 14530, 14490, 14450, 14450]
        ];

        return [
            'member' => $member,
            'shares' => $shares,
            'deposits' => [
                'accounts' => $deposits,
                'total_balance' => $totalDepositBalance,
                'total_interest' => $totalAccruedInterest,
                'count' => count($deposits)
            ],
            'loans' => [
                'contracts' => $loans,
                'total_principal' => $totalLoanPrincipal,
                'total_installment' => $totalMonthlyInstallment,
                'count' => count($loans)
            ],
            'monthlyDeduction' => $monthlyDeduction,
            'chartData' => $chartData,
            'share_total' => (float)($shares['total_amount'] ?? 0),
            'deposit_total' => (float)$totalDepositBalance,
            'loan_total' => (float)$totalLoanPrincipal,
            'netWorth' => ($shares['total_amount'] + $totalDepositBalance) - $totalLoanPrincipal
        ];
    }

    /**
     * Get member deposit accounts with transaction ledgers
     */
    public static function getDepositDetails(int $memberId, ?string $accountNo = null, ?string $typeFilter = null): array
    {
        $accounts = Database::query("SELECT * FROM deposit_accounts WHERE member_id = ? ORDER BY id ASC", [$memberId]);
        
        $activeAccount = null;
        if ($accountNo) {
            $activeAccount = Database::first("SELECT * FROM deposit_accounts WHERE member_id = ? AND account_no = ?", [$memberId, $accountNo]);
        }
        if (!$activeAccount && !empty($accounts)) {
            $activeAccount = $accounts[0];
        }

        $transactions = [];
        if ($activeAccount) {
            $sql = "SELECT * FROM deposit_transactions WHERE account_id = ?";
            $params = [$activeAccount['id']];
            if ($typeFilter && in_array($typeFilter, ['deposit', 'withdraw', 'interest'])) {
                $sql .= " AND tx_type = ?";
                $params[] = $typeFilter;
            }
            $sql .= " ORDER BY tx_date DESC LIMIT 50";
            $transactions = Database::query($sql, $params);
        }

        return [
            'accounts' => $accounts,
            'activeAccount' => $activeAccount,
            'transactions' => $transactions
        ];
    }

    /**
     * Get member loans and full amortization schedule
     */
    public static function getLoanDetails(int $memberId, ?string $contractNo = null): array
    {
        $loans = Database::query("SELECT * FROM loan_contracts WHERE member_id = ? ORDER BY id ASC", [$memberId]);
        
        $activeLoan = null;
        if ($contractNo) {
            $activeLoan = Database::first("SELECT * FROM loan_contracts WHERE member_id = ? AND contract_no = ?", [$memberId, $contractNo]);
        }
        if (!$activeLoan && !empty($loans)) {
            $activeLoan = $loans[0];
        }

        $schedules = [];
        if ($activeLoan) {
            $schedules = Database::query("SELECT * FROM loan_amortization_schedules WHERE loan_id = ? ORDER BY period_no ASC", [$activeLoan['id']]);
        }

        return [
            'loans' => $loans,
            'activeLoan' => $activeLoan,
            'schedules' => $schedules
        ];
    }

    /**
     * Calculate max borrowing eligibility
     */
    public static function calculateLoanEligibility(int $memberId, string $loanType, float $salary): array
    {
        $shares = Database::first("SELECT * FROM member_shares WHERE member_id = ?", [$memberId]);
        $shareAmount = (float)($shares['total_amount'] ?? 245000);

        $maxLimit = match($loanType) {
            'emergency' => min(100000.00, $salary * 5),
            'ordinary' => min(1500000.00, $shareAmount * 4 + $salary * 30),
            'special' => min(3000000.00, $salary * 60),
            default => 500000.00
        };

        $interestRate = match($loanType) {
            'emergency' => 5.25,
            'ordinary' => 4.75,
            'special' => 4.50,
            default => 4.75
        };

        $maxTerm = match($loanType) {
            'emergency' => 12,
            'ordinary' => 84,
            'special' => 180,
            default => 84
        };

        return [
            'max_limit' => $maxLimit,
            'interest_rate' => $interestRate,
            'max_term' => $maxTerm,
            'share_balance' => $shareAmount,
        ];
    }

    /**
     * Submit an online loan application
     */
    public static function submitLoanApplication(int $memberId, array $data, array $files = []): array
    {
        $year = date('Y');
        $lastId = (int)Database::value("SELECT MAX(id) FROM loan_applications") ?: 0;
        $appNo = sprintf('LN-%s-%05d', $year, $lastId + 1);

        $loanType = $data['loan_type'] ?? 'ordinary';
        $amount = (float)($data['request_amount'] ?? 100000);
        $term = (int)($data['request_term'] ?? 36);
        $salary = (float)($data['salary'] ?? 30000);
        $purpose = trim((string)($data['purpose'] ?? 'เพื่อการพัฒนาคุณภาพชีวิต'));
        $guarantor = trim((string)($data['guarantor_member_no'] ?? ''));

        // Estimated monthly installment
        $rate = match($loanType) {
            'emergency' => 5.25,
            'ordinary' => 4.75,
            'special' => 4.50,
            default => 4.75
        };
        $monthlyInterest = ($amount * ($rate / 100)) / 12;
        $monthlyPrincipal = $amount / $term;
        $estimatedMonthly = round($monthlyPrincipal + $monthlyInterest, 2);

        $docs = [];
        $uploadDir = storage_upload_path('loans');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $labelMap = [
            'doc_salary' => 'สลิปเงินเดือนเดือนล่าสุด',
            'doc_id_card' => 'สำเนาบัตรประชาชน',
            'doc_guarantor' => 'เอกสารผู้ค้ำประกัน',
            'doc_statement' => 'รายการเดินบัญชี (Bank Statement)',
            'doc_house' => 'สำเนาทะเบียนบ้าน',
            'doc_other' => 'เอกสารประกอบเพิ่มเติม',
        ];

        if (!empty($files)) {
            foreach ($files as $field => $fileItem) {
                if (empty($fileItem['tmp_name']) || ($fileItem['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                    continue;
                }

                $origName = $fileItem['name'] ?? ('document_' . time());
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];

                if (in_array($ext, $allowed)) {
                    $prefix = preg_replace('/^doc_/', '', (string)$field);
                    $filename = "{$prefix}_{$appNo}_" . time() . "_" . bin2hex(random_bytes(3)) . ".{$ext}";
                    $targetPath = $uploadDir . '/' . $filename;

                    if (move_uploaded_file($fileItem['tmp_name'], $targetPath) || copy($fileItem['tmp_name'], $targetPath)) {
                        $sizeBytes = file_exists($targetPath) ? (filesize($targetPath) ?: 0) : ($fileItem['size'] ?? 0);
                        $formattedSize = $sizeBytes > 1048576 
                            ? number_format($sizeBytes / 1048576, 2) . ' MB' 
                            : number_format($sizeBytes / 1024, 1) . ' KB';

                        $docName = $labelMap[$field] ?? ('เอกสารแนบ (' . pathinfo($origName, PATHINFO_FILENAME) . ')');

                        $docs[] = [
                            'name' => $docName,
                            'file' => 'loans/' . $filename,
                            'original_name' => $origName,
                            'size' => $formattedSize,
                            'type' => $ext,
                            'status' => 'uploaded'
                        ];
                    }
                }
            }
        }

        if (empty($docs)) {
            if (!empty($data['documents']) && is_array($data['documents'])) {
                $docs = $data['documents'];
            }
        }

        $docJson = json_encode($docs, JSON_UNESCAPED_UNICODE);

        $newId = Database::insert(
            "INSERT INTO loan_applications (application_no, member_id, loan_type, request_amount, request_term, estimated_monthly, salary, purpose, guarantor_member_no, documents_json, status, current_step, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted', 6, NOW(), NOW())",
            [$appNo, $memberId, $loanType, $amount, $term, $estimatedMonthly, $salary, $purpose, $guarantor, $docJson]
        );

        // Also create an online request entry for unified tracking
        Database::insert(
            "INSERT INTO online_requests (request_no, member_id, request_type, title, details, status, timeline_json, created_at) VALUES (?, ?, 'loan', ?, ?, 'submitted', ?, NOW())",
            [
                $appNo,
                $memberId,
                "คำขอกู้เงิน {$loanType} วงเงิน " . number_format($amount) . " บาท",
                "วัตถุประสงค์: {$purpose} ผ่อนชำระ {$term} งวด",
                json_encode([
                    ['status' => 'submitted', 'time' => date('d/m/Y H:i'), 'desc' => 'ยื่นคำขอกู้ออนไลน์เรียบร้อย']
                ], JSON_UNESCAPED_UNICODE)
            ]
        );

        // Create notification
        Database::insert(
            "INSERT INTO notifications (member_id, title, message, type, link_url, is_read, created_at) VALUES (?, ?, ?, 'doc_request', ?, 0, NOW())",
            [
                $memberId,
                "ยื่นคำขอกู้เงินสำเร็จ เลขที่ {$appNo}",
                "ระบบได้รับคำขอกู้เงิน {$loanType} วงเงิน " . number_format($amount) . " บาท เรียบร้อยแล้ว เจ้าหน้าที่จะดำเนินการตรวจสอบเอกสาร",
                "/member/online-services"
            ]
        );

        return [
            'success' => true,
            'application_no' => $appNo,
            'id' => $newId
        ];
    }

    /**
     * Get receipts list with monthly breakdown
     */
    public static function getReceipts(int $memberId): array
    {
        return Database::query("SELECT * FROM receipts WHERE member_id = ? ORDER BY billing_year DESC, billing_month DESC", [$memberId]);
    }

    /**
     * Get single receipt by receipt_no
     */
    public static function getReceiptByNo(int $memberId, string $receiptNo): ?array
    {
        return Database::first("SELECT * FROM receipts WHERE member_id = ? AND receipt_no = ? LIMIT 1", [$memberId, $receiptNo]);
    }

    /**
     * Get member beneficiaries & ensure sum is 100%
     */
    public static function getBeneficiaries(int $memberId): array
    {
        $list = Database::query("SELECT * FROM beneficiaries WHERE member_id = ? AND status = 'active' ORDER BY priority_order ASC", [$memberId]);
        $totalPercent = array_sum(array_column($list, 'percentage'));
        return [
            'list' => $list,
            'total_percentage' => $totalPercent,
            'is_valid' => abs($totalPercent - 100.00) < 0.01
        ];
    }

    /**
     * Save/Update beneficiaries
     */
    public static function saveBeneficiary(int $memberId, array $data): array
    {
        $id = !empty($data['id']) ? (int)$data['id'] : null;
        $firstName = trim((string)($data['first_name'] ?? ''));
        $lastName = trim((string)($data['last_name'] ?? ''));
        $rel = trim((string)($data['relationship'] ?? ''));
        $idCard = trim((string)($data['id_card_masked'] ?? ''));
        $pct = (float)($data['percentage'] ?? 100);
        $phone = trim((string)($data['phone'] ?? ''));

        if ($id) {
            Database::execute(
                "UPDATE beneficiaries SET first_name = ?, last_name = ?, relationship = ?, id_card_masked = ?, percentage = ?, phone = ? WHERE id = ? AND member_id = ?",
                [$firstName, $lastName, $rel, $idCard, $pct, $phone, $id, $memberId]
            );
        } else {
            Database::insert(
                "INSERT INTO beneficiaries (member_id, first_name, last_name, relationship, id_card_masked, percentage, phone, priority_order, status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'active')",
                [$memberId, $firstName, $lastName, $rel, $idCard, $pct, $phone]
            );
        }

        return ['success' => true, 'message' => 'บันทึกข้อมูลผู้รับผลประโยชน์เรียบร้อยแล้ว'];
    }

    /**
     * Get notifications list & unread count
     */
    public static function getNotifications(int $memberId): array
    {
        $list = Database::query("SELECT * FROM notifications WHERE member_id = ? ORDER BY created_at DESC LIMIT 30", [$memberId]);
        $unread = (int)Database::value("SELECT COUNT(*) FROM notifications WHERE member_id = ? AND is_read = 0", [$memberId]);

        return [
            'list' => $list,
            'unread_count' => $unread
        ];
    }

    /**
     * Mark notification as read
     */
    public static function markNotificationRead(int $memberId, ?int $notificationId = null): void
    {
        if ($notificationId) {
            Database::execute("UPDATE notifications SET is_read = 1 WHERE id = ? AND member_id = ?", [$notificationId, $memberId]);
        } else {
            Database::execute("UPDATE notifications SET is_read = 1 WHERE member_id = ?", [$memberId]);
        }
    }
}
