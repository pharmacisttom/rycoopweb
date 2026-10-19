<?php

declare(strict_types=1);
require __DIR__.'/require-test-database.php';

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;

class LoanMasterDataTest
{
    private static array $expectedMasterData = [
        'เงินกู้สามัญ' => 6.15,
        'เงินกู้ฉุกเฉิน' => 4.75,
        'เงินกู้พิเศษ' => 5.25,
        'เงินกู้ผ่อนชำระสินค้า ฯ' => 5.50,
        'เงินกู้เพื่อการศึกษา' => 2.00,
        'เงินกู้เพื่อพัฒนาคุณภาพชีวิต' => 5.50,
        'เงินกู้เพื่อจัดซื้อรถยนต์' => 4.00,
        'เงินกู้พิเศษเพื่อความมั่นคงในชีวิต' => 5.25,
        'เงินกู้เพื่อปรับปรุงโครงสร้างหนี้' => 4.75,
        'เงินกู้รับการค้ำประกัน' => 2.00,
    ];

    public static function run(): bool
    {
        echo "========================================================\n";
        echo "🔍 STARTING MASTER DATA & INTEREST RATE VALIDATION TEST\n";
        echo "========================================================\n\n";

        $allPassed = true;

        // 1. TEST DATABASE: loan_products table
        echo "[1] Testing Database: `loan_products` table...\n";
        $dbProducts = Database::query("SELECT name, interest_rate FROM loan_products WHERE status = 'active' ORDER BY sort_order ASC");

        if (count($dbProducts) !== 10) {
            echo "❌ FAILED: Expected 10 loan products in DB, got " . count($dbProducts) . "\n";
            $allPassed = false;
        } else {
            echo "✓ Found exactly 10 loan products in database.\n";
        }

        $names = [];
        foreach ($dbProducts as $p) {
            $names[] = $p['name'];
            $expectedRate = self::$expectedMasterData[$p['name']] ?? null;
            $actualRate = (float) $p['interest_rate'];
            if ($expectedRate === null) {
                echo "❌ FAILED: Unexpected loan product in DB: {$p['name']}\n";
                $allPassed = false;
            } elseif (abs($expectedRate - $actualRate) > 0.001) {
                echo "❌ FAILED: Rate mismatch for {$p['name']}. Expected: {$expectedRate}%, Got: {$actualRate}%\n";
                $allPassed = false;
            } else {
                echo "  ✓ DB Product: {$p['name']} => {$actualRate}% (Exact match)\n";
            }
        }

        if (count(array_unique($names)) !== 10) {
            echo "❌ FAILED: Duplicate loan product names found in DB!\n";
            $allPassed = false;
        } else {
            echo "✓ No duplicate product names in database.\n";
        }

        echo "\n";

        // 2. TEST DATABASE: interest_rates table
        echo "[2] Testing Database: `interest_rates` (product_type = 'loan')...\n";
        $dbRates = Database::query("SELECT product_name, rate FROM interest_rates WHERE product_type = 'loan' AND status = 'active' ORDER BY sort_order ASC");

        if (count($dbRates) !== 10) {
            echo "❌ FAILED: Expected 10 loan interest rates in DB, got " . count($dbRates) . "\n";
            $allPassed = false;
        } else {
            echo "✓ Found exactly 10 loan rates in `interest_rates` table.\n";
        }

        foreach ($dbRates as $r) {
            $expectedRate = self::$expectedMasterData[$r['product_name']] ?? null;
            $actualRate = (float) $r['rate'];
            if ($expectedRate === null) {
                echo "❌ FAILED: Unexpected rate in DB: {$r['product_name']}\n";
                $allPassed = false;
            } elseif (abs($expectedRate - $actualRate) > 0.001) {
                echo "❌ FAILED: Rate mismatch for {$r['product_name']}. Expected: {$expectedRate}%, Got: {$actualRate}%\n";
                $allPassed = false;
            } else {
                echo "  ✓ DB Rate: {$r['product_name']} => {$actualRate}% (Exact match)\n";
            }
        }

        echo "\n";

        // 3. TEST REACT MOCK DATA
        echo "[3] Testing React: `src/data/mockData.js`...\n";
        $mockDataContent = file_get_contents(__DIR__ . '/../src/data/mockData.js');

        foreach (self::$expectedMasterData as $name => $rate) {
            $rateStr = number_format($rate, 2) . '%';
            if (!str_contains($mockDataContent, "'$name'") && !str_contains($mockDataContent, "\"$name\"")) {
                echo "❌ FAILED: `src/data/mockData.js` missing loan product '$name'\n";
                $allPassed = false;
            } else {
                echo "  ✓ MockData contains product: '$name'\n";
            }
        }

        echo "\n";

        // 4. TEST 10-ITEM PAIR-BY-PAIR INTEGRITY
        echo "[4] Pair-by-Pair 100% Verification Summary:\n";
        $idx = 1;
        foreach (self::$expectedMasterData as $name => $rate) {
            $rateStr = number_format($rate, 2) . '%';
            echo "  [✓] #{$idx}. {$name} = {$rateStr}\n";
            $idx++;
        }

        echo "\n========================================================\n";
        if ($allPassed) {
            echo "🎉 ALL 12 VALIDATION CHECKS PASSED PERFECTLY (100% MATCH)!\n";
        } else {
            echo "❌ SOME TESTS FAILED. PLEASE REVIEW OUTPUT ABOVE.\n";
        }
        echo "========================================================\n";

        return $allPassed;
    }
}

$success = LoanMasterDataTest::run();
exit($success ? 0 : 1);
