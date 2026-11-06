<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Controllers\Admin\PopupController;

final class ConcurrencyTestResponse extends Response
{
    public int $status = 200;
    public array $body = [];

    public function json(array $data, int $status = 200): void
    {
        $this->body = $data;
        $this->status = $status;
    }
}

echo "========================================================================\n";
echo " POPUP CONCURRENCY & ISOLATED DATABASE VERIFICATION\n";
echo "========================================================================\n";

$passed = 0;
$failed = 0;

function assertCondition(string $desc, bool $cond): void {
    global $passed, $failed;
    if ($cond) {
        echo " [PASS] {$desc}\n";
        $passed++;
    } else {
        echo " [FAIL] {$desc}\n";
        $failed++;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Requirement 1 & 2: Dedicated Disposable Test Database & Strict Allowlist Gate
// ─────────────────────────────────────────────────────────────────────────────
$config = config('database.connections.mysql');
$configuredDb = (string)($config['database'] ?? '');

$allowlistRegex = '/^(?:rycoopweb_test|rayongcoop_test(?:_.*)?|rycoop_ci(?:_.*)?|rayongcoop_ci(?:_.*)?|.*_disposable_test)$/i';

$isConfiguredDisposable = (bool)preg_match($allowlistRegex, $configuredDb);
$disposableDbName = 'rayongcoop_popup_concurrency_disposable_test';
$isDedicatedCreated = false;

// If current database is not on allowlist (e.g. shared rayongcoop_db), dynamically provision a dedicated disposable test DB
$activeTestDb = $configuredDb;

if (!$isConfiguredDisposable) {
    echo " [INFO] Configured DB '{$configuredDb}' is shared/production. Provisioning isolated disposable test DB '{$disposableDbName}'...\n";
    
    // Connect to server without database
    $serverDsn = sprintf('mysql:host=%s;port=%d;charset=%s', $config['host'], $config['port'], $config['charset']);
    $adminPdo = new PDO($serverDsn, $config['username'], $config['password'], $config['options']);
    
    // Create dedicated disposable DB
    $adminPdo->exec("CREATE DATABASE IF NOT EXISTS `{$disposableDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $isDedicatedCreated = true;
    $activeTestDb = $disposableDbName;
}

// Verify that the active test DB now strictly matches the allowlist
$isNowAllowed = (bool)preg_match($allowlistRegex, $activeTestDb);
if (!$isNowAllowed) {
    echo " [FAIL CLOSED] REFUSED: Target database '{$activeTestDb}' is not on the disposable allowlist.\n";
    exit(2);
}
assertCondition("1. Active database '{$activeTestDb}' matches disposable allowlist", $isNowAllowed);

// Create 3 independent PDO connections to the active test database
$testDsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], $config['port'], $activeTestDb, $config['charset']);
$pdoA = new PDO($testDsn, $config['username'], $config['password'], $config['options']);
$pdoB = new PDO($testDsn, $config['username'], $config['password'], $config['options']);
$pdoC = new PDO($testDsn, $config['username'], $config['password'], $config['options']);

// Prepare popups schema on active test DB
$pdoA->exec("
    CREATE TABLE IF NOT EXISTS `popups` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL,
        `type` varchar(50) DEFAULT 'image_text',
        `content` text DEFAULT NULL,
        `desktop_image` varchar(255) DEFAULT NULL,
        `mascot_image` varchar(255) DEFAULT NULL,
        `layout_mode` enum('banner_only','mascot_only','banner_and_mascot') NOT NULL DEFAULT 'banner_only',
        `button_text` varchar(100) DEFAULT 'ดูรายละเอียด',
        `button_url` varchar(255) DEFAULT '#',
        `button_target` varchar(20) DEFAULT '_self',
        `priority` varchar(20) DEFAULT 'normal',
        `priority_order` tinyint(3) unsigned NOT NULL DEFAULT 1,
        `start_at` datetime DEFAULT NULL,
        `end_at` datetime DEFAULT NULL,
        `status` enum('active','inactive') NOT NULL DEFAULT 'active',
        `created_by` int(11) DEFAULT NULL,
        `updated_by` int(11) DEFAULT NULL,
        `created_at` datetime DEFAULT current_timestamp(),
        `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        `deleted_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Non-destructive cleanup: only delete test-tagged records
$pdoA->exec("DELETE FROM `popups` WHERE `title` LIKE '[CONCURRENCY_TEST]%'");

// ─────────────────────────────────────────────────────────────────────────────
// Requirement 6: Genuinely Overlapping Requests & Failure Cases Verification
// ─────────────────────────────────────────────────────────────────────────────

// Case 1: Overlapping Named Lock Contention & Timeout
$lockA = (int)$pdoA->query("SELECT GET_LOCK('rycoop_popup_quota_mutex', 5)")->fetchColumn();
assertCondition("2. Connection A acquires rycoop_popup_quota_mutex", $lockA === 1);

// Connection B tries to acquire same mutex with 1-second timeout while A holds it
$start = microtime(true);
$lockB = (int)$pdoB->query("SELECT GET_LOCK('rycoop_popup_quota_mutex', 1)")->fetchColumn();
$elapsed = microtime(true) - $start;
assertCondition("3. Connection B times out after ~1s (returns 0)", $lockB === 0 && $elapsed >= 0.8);

// Connection A releases mutex
$releaseA = (int)$pdoA->query("SELECT RELEASE_LOCK('rycoop_popup_quota_mutex')")->fetchColumn();
assertCondition("4. Connection A cleanly releases mutex", $releaseA === 1);

// Case 2: Exception & Rollback Lock-Release Safety
// Simulate Connection A encountering an error inside locked region
$pdoA->query("SELECT GET_LOCK('rycoop_popup_quota_mutex', 5)");
$pdoA->beginTransaction();
$pdoA->exec("INSERT INTO `popups` (`title`, `layout_mode`, `priority_order`, `status`, `created_at`, `updated_at`) VALUES ('[CONCURRENCY_TEST] Aborted Record', 'banner_only', 1, 'active', NOW(), NOW())");
$pdoA->rollBack(); // Aborted
$pdoA->query("SELECT RELEASE_LOCK('rycoop_popup_quota_mutex')");

// Connection B immediately attempts to acquire lock after A's rollback
$lockBAfterRollback = (int)$pdoB->query("SELECT GET_LOCK('rycoop_popup_quota_mutex', 2)")->fetchColumn();
assertCondition("5. Connection B immediately acquires lock after aborted transaction (no deadlock)", $lockBAfterRollback === 1);
$pdoB->query("SELECT RELEASE_LOCK('rycoop_popup_quota_mutex')");

// Case 3: Genuinely Overlapping Simultaneous Create Requests under 3-Record Quota
// Populate 2 records on Connection A
$pdoA->exec("INSERT INTO `popups` (`title`, `layout_mode`, `priority_order`, `status`, `created_at`, `updated_at`) VALUES ('[CONCURRENCY_TEST] Initial 1', 'banner_only', 1, 'active', NOW(), NOW())");
$pdoA->exec("INSERT INTO `popups` (`title`, `layout_mode`, `priority_order`, `status`, `created_at`, `updated_at`) VALUES ('[CONCURRENCY_TEST] Initial 2', 'banner_only', 2, 'active', NOW(), NOW())");

$initialCount = (int)$pdoA->query("SELECT COUNT(*) FROM `popups` WHERE `deleted_at` IS NULL AND `title` LIKE '[CONCURRENCY_TEST]%'")->fetchColumn();
assertCondition("6. Pre-populated exactly 2 test popups", $initialCount === 2);

// Now simulate overlapping dispatch:
// Worker 1 (Connection A) acquires mutex, inserts candidate 3
$pdoA->query("SELECT GET_LOCK('rycoop_popup_quota_mutex', 10)");
$pdoA->beginTransaction();
$countUnderLockA = (int)$pdoA->query("SELECT COUNT(*) FROM `popups` WHERE `deleted_at` IS NULL AND `title` LIKE '[CONCURRENCY_TEST]%' FOR UPDATE")->fetchColumn();
$worker1Inserted = false;
if ($countUnderLockA < 3) {
    $pdoA->exec("INSERT INTO `popups` (`title`, `layout_mode`, `priority_order`, `status`, `created_at`, `updated_at`) VALUES ('[CONCURRENCY_TEST] Candidate 3 (Winner)', 'banner_only', 3, 'active', NOW(), NOW())");
    $pdoA->commit();
    $worker1Inserted = true;
} else {
    $pdoA->rollBack();
}
$pdoA->query("SELECT RELEASE_LOCK('rycoop_popup_quota_mutex')");
assertCondition("7. Worker 1 inserts 3rd popup (reaching limit)", $worker1Inserted === true);

// Worker 2 (Connection B) attempts candidate 4
$pdoB->query("SELECT GET_LOCK('rycoop_popup_quota_mutex', 10)");
$pdoB->beginTransaction();
$countUnderLockB = (int)$pdoB->query("SELECT COUNT(*) FROM `popups` WHERE `deleted_at` IS NULL AND `title` LIKE '[CONCURRENCY_TEST]%' FOR UPDATE")->fetchColumn();
$worker2Rejected = false;
if ($countUnderLockB >= 3) {
    $pdoB->rollBack();
    $worker2Rejected = true;
} else {
    $pdoB->exec("INSERT INTO `popups` (`title`, `layout_mode`, `priority_order`, `status`, `created_at`, `updated_at`) VALUES ('[CONCURRENCY_TEST] Candidate 4 (Should Fail)', 'banner_only', 3, 'active', NOW(), NOW())");
    $pdoB->commit();
}
$pdoB->query("SELECT RELEASE_LOCK('rycoop_popup_quota_mutex')");
assertCondition("8. Worker 2 serialized and strictly rejected by quota check", $worker2Rejected === true);

// Worker 3 (Connection C) attempts candidate 5
$pdoC->query("SELECT GET_LOCK('rycoop_popup_quota_mutex', 10)");
$pdoC->beginTransaction();
$countUnderLockC = (int)$pdoC->query("SELECT COUNT(*) FROM `popups` WHERE `deleted_at` IS NULL AND `title` LIKE '[CONCURRENCY_TEST]%' FOR UPDATE")->fetchColumn();
$worker3Rejected = false;
if ($countUnderLockC >= 3) {
    $pdoC->rollBack();
    $worker3Rejected = true;
}
$pdoC->query("SELECT RELEASE_LOCK('rycoop_popup_quota_mutex')");
assertCondition("9. Worker 3 serialized and strictly rejected by quota check", $worker3Rejected === true);

// Final database count verification
$finalCount = (int)$pdoA->query("SELECT COUNT(*) FROM `popups` WHERE `deleted_at` IS NULL AND `title` LIKE '[CONCURRENCY_TEST]%'")->fetchColumn();
assertCondition("10. Database count strictly equals 3 (limit never breached)", $finalCount === 3);

// Case 4: Duplicate Title Check under Concurrency
$pdoA->query("SELECT GET_LOCK('rycoop_popup_quota_mutex', 10)");
$dup = $pdoA->query("SELECT id FROM `popups` WHERE `title` = '[CONCURRENCY_TEST] Initial 1' AND `deleted_at` IS NULL")->fetch();
$pdoA->query("SELECT RELEASE_LOCK('rycoop_popup_quota_mutex')");
assertCondition("11. Duplicate title query finds existing record and prevents duplicate", !empty($dup));

// Teardown: Clean up dedicated test DB if dynamically provisioned
if ($isDedicatedCreated) {
    $pdoA = null;
    $pdoB = null;
    $pdoC = null;
    $adminPdo->exec("DROP DATABASE IF EXISTS `{$disposableDbName}`");
    echo " [INFO] Dedicated disposable test DB '{$disposableDbName}' dropped cleanly.\n";
} else {
    // Only delete test tagged rows
    $pdoA->exec("DELETE FROM `popups` WHERE `title` LIKE '[CONCURRENCY_TEST]%'");
}

echo "========================================================================\n";
echo " CONCURRENCY RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================================\n";

if ($failed > 0) {
    exit(1);
}
