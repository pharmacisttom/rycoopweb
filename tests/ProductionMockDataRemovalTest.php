<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Services\MemberPortalService;
use App\Services\TaxCertificateService;

echo "========================================================================" . PHP_EOL;
echo " RYCOOPWEB — PRODUCTION MOCK DATA REMOVAL VERIFICATION TEST" . PHP_EOL;
echo "========================================================================" . PHP_EOL;

$passed = 0;
$failed = 0;

function it(string $description, bool $condition): void {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$description}" . PHP_EOL;
        $passed++;
    } else {
        echo " [FAIL] {$description}" . PHP_EOL;
        $failed++;
    }
}

// 1. Verify MemberPortalService does not return fake 245,000 shares when no shares exist
try {
    // Member ID 999999 does not exist
    $summary = MemberPortalService::getDashboardSummary(999999);

    it("1. Shares total is 0.0 or DATA_NOT_AVAILABLE when not in DB (never 245000)",
        $summary['shares']['total_amount'] === 0.0 && $summary['share_total'] === 0.0);

    it("2. Shares status is DATA_NOT_AVAILABLE when not in DB",
        ($summary['shares']['status'] ?? '') === 'DATA_NOT_AVAILABLE');

    it("3. Shares message contains 'ยังไม่มีข้อมูลที่ได้รับการบันทึกในระบบ'",
        str_contains($summary['shares']['message'] ?? '', 'ยังไม่มีข้อมูลที่ได้รับการบันทึกในระบบ'));

    it("4. Deposit total is 0.0 when not in DB (never 315000 or fabricated)",
        $summary['deposits']['total_balance'] === 0.0 && $summary['deposit_total'] === 0.0);

    it("5. Loan total is 0.0 when not in DB (never 380000 or fabricated)",
        $summary['loans']['total_principal'] === 0.0 && $summary['loan_total'] === 0.0);

    it("6. Monthly deduction is DATA_NOT_AVAILABLE (never fake 1500 / 5000 / 100)",
        ($summary['monthlyDeduction']['status'] ?? '') === 'DATA_NOT_AVAILABLE' && $summary['monthlyDeduction']['total'] === 0.0);

    it("7. Chart data is DATA_NOT_AVAILABLE and has empty labels/series (never fake 6-month)",
        ($summary['chartData']['status'] ?? '') === 'DATA_NOT_AVAILABLE' && empty($summary['chartData']['labels']) && empty($summary['chartData']['shares']));

    it("8. Chart data contains 'ยังไม่มีข้อมูลที่ได้รับการบันทึกในระบบ'",
        str_contains($summary['chartData']['message'] ?? '', 'ยังไม่มีข้อมูลที่ได้รับการบันทึกในระบบ'));

} catch (\Throwable $e) {
    it("1-8. MemberPortalService::getDashboardSummary does not throw: " . $e->getMessage(), false);
}

// 2. Verify calculateLoanEligibility does not fallback to 245,000
try {
    $eligibility = MemberPortalService::calculateLoanEligibility(999999, 'ordinary', 30000.0);
    it("9. calculateLoanEligibility share_balance is 0.0 (never 245000)",
        $eligibility['share_balance'] === 0.0);
} catch (\Throwable $e) {
    it("9. calculateLoanEligibility does not throw: " . $e->getMessage(), false);
}

// 3. Verify getDepositDetails returns DATA_NOT_AVAILABLE without HTTP 500
try {
    $depositDetails = MemberPortalService::getDepositDetails(999999);
    it("10. getDepositDetails returns DATA_NOT_AVAILABLE when no table/record exists",
        $depositDetails['status'] === 'DATA_NOT_AVAILABLE' && empty($depositDetails['accounts']));
} catch (\Throwable $e) {
    it("10. getDepositDetails does not throw: " . $e->getMessage(), false);
}

// 4. Verify getLoanDetails returns DATA_NOT_AVAILABLE without HTTP 500
try {
    $loanDetails = MemberPortalService::getLoanDetails(999999);
    it("11. getLoanDetails returns DATA_NOT_AVAILABLE when no table/record exists",
        $loanDetails['status'] === 'DATA_NOT_AVAILABLE' && empty($loanDetails['loans']));
} catch (\Throwable $e) {
    it("11. getLoanDetails does not throw: " . $e->getMessage(), false);
}

// 5. Verify TaxCertificateService returns empty array and does NOT auto-generate fake certificates
try {
    $certs = TaxCertificateService::getMemberTaxCertificates(999999);
    it("12. TaxCertificateService does not auto-generate fake certificates with LN-HSG-2567-0014",
        empty($certs));
} catch (\Throwable $e) {
    it("12. TaxCertificateService does not throw: " . $e->getMessage(), false);
}

// 6. Verify Database::hasTable correctly identifies existing and non-existing tables
try {
    it("13. Database::hasTable returns true for 'members'", Database::hasTable('members'));
    it("14. Database::hasTable returns true for 'member_dividends'", Database::hasTable('member_dividends'));
    it("15. Database::hasTable returns false for non-existent 'member_shares'", !Database::hasTable('member_shares'));
    it("16. Database::hasTable returns false for non-existent 'deposit_accounts'", !Database::hasTable('deposit_accounts'));
    it("17. Database::hasTable returns false for non-existent 'loan_contracts'", !Database::hasTable('loan_contracts'));
} catch (\Throwable $e) {
    it("13-17. Database::hasTable check does not throw: " . $e->getMessage(), false);
}

// 7. Verify Genuine Members and Dividends remain completely intact in DB
try {
    $memberCount = (int)Database::value("SELECT COUNT(*) FROM members");
    $userCount = (int)Database::value("SELECT COUNT(*) FROM users");
    $dividendCount = (int)Database::value("SELECT COUNT(*) FROM member_dividends");

    it("18. Members count is preserved (2508)", $memberCount === 2508);
    it("19. Users count is preserved (2510)", $userCount === 2510);
    it("20. Member Dividends count is preserved (4855)", $dividendCount === 4855);
} catch (\Throwable $e) {
    it("18-20. Integrity check failed: " . $e->getMessage(), false);
}

echo "========================================================================" . PHP_EOL;
echo " RESULTS: {$passed} PASSED, {$failed} FAILED" . PHP_EOL;
echo "========================================================================" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}
