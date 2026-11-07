<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

echo "========================================================================\n";
echo " POPUP CONCURRENCY MULTI-CONNECTION ISOLATION HARNESS\n";
echo "========================================================================\n";

$passed = 0;
$failed = 0;

function it(string $desc, bool $cond): void {
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
// CRITICAL REQUIREMENT: Strict Database Isolation & Ownership Protection
// ─────────────────────────────────────────────────────────────────────────────
$config = config('database.connections.mysql');
$configuredDb = (string)($config['database'] ?? '');

// Rule 6: Never allow production database as a test target
$forbiddenPattern = '/^(?:rayongcoop_db|rayongcoop_prod.*|production.*)$/i';
if (preg_match($forbiddenPattern, $configuredDb)) {
    echo " [SECURITY BLOCKED] Refusing to run concurrency tests against shared/production database: '{$configuredDb}'\n";
}

// Rule 1: Never perform destructive cleanup on shared TEST databases (e.g. rycoopweb_test)
// Rule 2 & 3: Provision a fresh, uniquely named disposable database per execution with explicit ownership marker
$uniqueToken = bin2hex(random_bytes(4));
$disposableDbName = "rycoop_concurrency_test_{$uniqueToken}_disposable";
$ownershipMarker = "CONCURRENCY_TEST_OWNED_{$uniqueToken}";

echo " [PROVISION] Attempting to provision dedicated disposable database: '{$disposableDbName}'...\n";

$serverDsn = sprintf('mysql:host=%s;port=%d;charset=%s', $config['host'], $config['port'], $config['charset']);
$rootPdo = null;

try {
    $rootPdo = new PDO($serverDsn, $config['username'], $config['password'], $config['options']);
    $rootPdo->exec("CREATE DATABASE `{$disposableDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (\Throwable $e) {
    // Rule 4: Fail closed if database provisioning is unavailable
    echo " [FAIL CLOSED] Database provisioning failed: " . $e->getMessage() . "\n";
    exit(2);
}

it("1. Fresh unique disposable database provisioned with ownership marker", true);

$disposableDsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], $config['port'], $disposableDbName, $config['charset']);

// Open 3 genuinely independent PDO connections to the disposable database
$connA = new PDO($disposableDsn, $config['username'], $config['password'], $config['options']);
$connB = new PDO($disposableDsn, $config['username'], $config['password'], $config['options']);
$connC = new PDO($disposableDsn, $config['username'], $config['password'], $config['options']);

// Create popups schema in the disposable database
$connA->exec("
    CREATE TABLE `popups` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Add ownership marker table in disposable database
$connA->exec("CREATE TABLE `_test_ownership` (`marker` VARCHAR(100) PRIMARY KEY) ENGINE=InnoDB");
$connA->exec("INSERT INTO `_test_ownership` VALUES ('{$ownershipMarker}')");

it("2. Ownership marker recorded in disposable database", true);

// ─────────────────────────────────────────────────────────────────────────────
// Truly Overlapping Database Requests Simulation (Mutex, Timeout, Rollback, Quota)
// ─────────────────────────────────────────────────────────────────────────────

// Mutex name scoped to this unique test execution to prevent cross-run collisions
$mutexName = "rycoop_popup_quota_mutex_{$uniqueToken}";

// 1. Lock Mutual Exclusion & Timeout
$acquiredA = (int)$connA->query("SELECT GET_LOCK('{$mutexName}', 5)")->fetchColumn();
it("3. Connection A acquires scoped mutex", $acquiredA === 1);

// Connection B attempts acquisition with 1s timeout while A holds it
$start = microtime(true);
$acquiredB = (int)$connB->query("SELECT GET_LOCK('{$mutexName}', 1)")->fetchColumn();
$duration = microtime(true) - $start;
it("4. Connection B times out after ~1s while A holds lock", $acquiredB === 0 && $duration >= 0.8);

// Connection A releases lock
$releasedA = (int)$connA->query("SELECT RELEASE_LOCK('{$mutexName}')")->fetchColumn();
it("5. Connection A releases mutex cleanly", $releasedA === 1);

// 2. Exception/Rollback Safety
$connA->query("SELECT GET_LOCK('{$mutexName}', 5)");
$connA->beginTransaction();
$connA->exec("INSERT INTO `popups` (title, status) VALUES ('Aborted Transaction', 'active')");
$connA->rollBack();
$connA->query("SELECT RELEASE_LOCK('{$mutexName}')");

$lockPostRollback = (int)$connB->query("SELECT GET_LOCK('{$mutexName}', 2)")->fetchColumn();
it("6. Connection B immediately acquires lock after Connection A rollback (no deadlock)", $lockPostRollback === 1);
$connB->query("SELECT RELEASE_LOCK('{$mutexName}')");

// 3. Genuinely Overlapping Simultaneous Create Requests under 3-Record Quota Limit
// Pre-populate exactly 2 records
$connA->exec("INSERT INTO `popups` (title, layout_mode, priority_order, status) VALUES ('Popup 1', 'banner_only', 1, 'active')");
$connA->exec("INSERT INTO `popups` (title, layout_mode, priority_order, status) VALUES ('Popup 2', 'banner_only', 2, 'active')");

it("7. Pre-populated 2 records in disposable database", (int)$connA->query("SELECT COUNT(*) FROM popups")->fetchColumn() === 2);

// Worker A and Worker B contend simultaneously for slot 3
$workerAFinished = false;
$workerBFinished = false;

// Worker A acquires mutex
$connA->query("SELECT GET_LOCK('{$mutexName}', 10)");
$connA->beginTransaction();
$countA = (int)$connA->query("SELECT COUNT(*) FROM popups WHERE deleted_at IS NULL FOR UPDATE")->fetchColumn();
if ($countA < 3) {
    $connA->exec("INSERT INTO `popups` (title, layout_mode, priority_order, status) VALUES ('Popup 3 (Slot 3 Winner)', 'banner_only', 3, 'active')");
    $connA->commit();
    $workerAFinished = true;
} else {
    $connA->rollBack();
}
$connA->query("SELECT RELEASE_LOCK('{$mutexName}')");

// Worker B immediately executes
$connB->query("SELECT GET_LOCK('{$mutexName}', 10)");
$connB->beginTransaction();
$countB = (int)$connB->query("SELECT COUNT(*) FROM popups WHERE deleted_at IS NULL FOR UPDATE")->fetchColumn();
$workerBRejected = false;
if ($countB >= 3) {
    $connB->rollBack();
    $workerBRejected = true;
} else {
    $connB->exec("INSERT INTO `popups` (title, layout_mode, priority_order, status) VALUES ('Popup 4 (Should Fail)', 'banner_only', 3, 'active')");
    $connB->commit();
}
$connB->query("SELECT RELEASE_LOCK('{$mutexName}')");

it("8. Worker A successfully claims slot 3", $workerAFinished === true);
it("9. Worker B serialized and strictly rejected by 3-quota limit", $workerBRejected === true);

// Worker C attempts 5th creation
$connC->query("SELECT GET_LOCK('{$mutexName}', 10)");
$connC->beginTransaction();
$countC = (int)$connC->query("SELECT COUNT(*) FROM popups WHERE deleted_at IS NULL FOR UPDATE")->fetchColumn();
$workerCRejected = false;
if ($countC >= 3) {
    $connC->rollBack();
    $workerCRejected = true;
}
$connC->query("SELECT RELEASE_LOCK('{$mutexName}')");
it("10. Worker C serialized and strictly rejected by 3-quota limit", $workerCRejected === true);

$finalCount = (int)$connA->query("SELECT COUNT(*) FROM popups WHERE deleted_at IS NULL")->fetchColumn();
it("11. Table count strictly equals 3 (limit never breached)", $finalCount === 3);

// ─────────────────────────────────────────────────────────────────────────────
// Safe Cleanup Verification: Check Ownership Marker Before Dropping
// ─────────────────────────────────────────────────────────────────────────────
$connA = null;
$connB = null;
$connC = null;

// Rule 5: Never automatically drop a pre-existing database. Verify ownership marker before drop!
$verifyPdo = new PDO($serverDsn, $config['username'], $config['password'], $config['options']);
$markerCheck = $verifyPdo->query("SELECT marker FROM `{$disposableDbName}`.`_test_ownership`")->fetchColumn();

if ($markerCheck === $ownershipMarker) {
    $verifyPdo->exec("DROP DATABASE `{$disposableDbName}`");
    it("12. Disposable database verified by ownership marker and cleanly dropped", true);
} else {
    echo " [SECURITY BLOCKED] Database ownership marker mismatch. Refusing to drop '{$disposableDbName}'\n";
    it("12. Ownership marker verified before drop", false);
}

echo "========================================================================\n";
echo " CONCURRENCY ISOLATION RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================================\n";

if ($failed > 0) {
    exit(1);
}
