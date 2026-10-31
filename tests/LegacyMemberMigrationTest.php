<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    @session_start();
}

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\LegacyMemberMigrationService;

echo "=========================================================================\n";
echo "RYCOOPWEB 3.0 — Legacy Member Migration & Dividend Recovery Test Suite\n";
echo "=========================================================================\n";

$passCount = 0;
function testAssert(bool $condition, string $description): void
{
    global $passCount;
    if (!$condition) {
        echo "FAIL: {$description}\n";
        exit(1);
    }
    $passCount++;
    echo "PASS [{$passCount}]: {$description}\n";
}

$dumpPath = 'C:/Users/ACER/Downloads/rayongcoop_db.sql';

// 1. File verification
testAssert(file_exists($dumpPath) && is_readable($dumpPath), "Legacy SQL dump file exists and is readable at {$dumpPath}");
testAssert(filesize($dumpPath) > 5000000, "Legacy SQL dump file size is valid (> 5 MB, actual: " . number_format(filesize($dumpPath)) . " bytes)");

// 2. Parse dump
$parsed = LegacyMemberMigrationService::parseDump($dumpPath);
testAssert(count($parsed['members']) === 2508, "Extracted exactly 2,508 member records from dump");
testAssert(count($parsed['users']) === 2508, "Extracted exactly 2,508 member user accounts from dump (excluding administrative accounts)");
testAssert(count($parsed['dividends']) === 4855, "Extracted exactly 4,855 dividend records from dump");

// 3. Administrative exclusion
testAssert(isset($parsed['excluded_users'][1]), "Super Admin (ID 1 / pharmacisttom@gmail.com) successfully excluded from member migration");
testAssert(isset($parsed['excluded_users'][2510]), "Member Admin (ID 2510 / memberadmin) successfully excluded from member migration");
testAssert(!isset($parsed['users'][1]), "Target user list does NOT contain User ID 1");
testAssert(!isset($parsed['users'][2510]), "Target user list does NOT contain User ID 2510");

// 4. Leading zero preservation in member_no
$firstMember = $parsed['members'][1] ?? null;
testAssert($firstMember !== null, "Member #1 parsed successfully");
testAssert($firstMember['member_no'] === '00004', "Member #1 member_no strictly preserves leading zero ('00004')");

$sampleMembers = [
    2 => '00006',
    3 => '00008',
    4 => '00015',
    5 => '00017',
    6 => '00019',
    7 => '00021',
    8 => '00022',
    9 => '00024'
];
foreach ($sampleMembers as $id => $expectedNo) {
    testAssert(($parsed['members'][$id]['member_no'] ?? '') === $expectedNo, "Member #{$id} member_no strictly preserves leading zeros ('{$expectedNo}')");
}

// 5. National ID card format verification
testAssert(empty($parsed['anomalies']['invalid_id_cards']), "All 2,508 members have strictly valid 13-digit national ID cards");
testAssert(empty($parsed['anomalies']['duplicate_member_nos']), "Zero duplicate member numbers found in legacy dataset");
testAssert(empty($parsed['anomalies']['duplicate_id_cards']), "Zero duplicate national ID cards found in legacy dataset");

// 6. Password hash security
testAssert(empty($parsed['anomalies']['unsupported_passwords']), "All 2,508 user password hashes use standard bcrypt (\$2y\$10\$)");
testAssert(str_starts_with($parsed['users'][2]['password'], '$2y$10$'), "Sample user password starts with bcrypt signature \$2y\$10\$");

// 7. Dividend reconciliation math
$reconciliation = LegacyMemberMigrationService::reconcileDividends($parsed['dividends']);
testAssert(isset($reconciliation[2567]) && isset($reconciliation[2568]), "Reconciliation covers both Year 2567 and Year 2568");

// Year 2567 checks
$r2567 = $reconciliation[2567];
testAssert($r2567['record_count'] === 2429, "Year 2567 record count is exactly 2,429");
testAssert($r2567['sum_dividend'] === '78356989.62', "Year 2567 sum_dividend is 78,356,989.62 THB");
testAssert($r2567['sum_refund'] === '7017501.18', "Year 2567 sum_refund is 7,017,501.18 THB");
testAssert($r2567['sum_total_income'] === '86785490.80', "Year 2567 sum_total_income is 86,785,490.80 THB");
testAssert($r2567['sum_total_deductions'] === '15766193.35', "Year 2567 sum_total_deductions is 15,766,193.35 THB");
testAssert($r2567['sum_net'] === '71019297.45', "Year 2567 sum_net is 71,019,297.45 THB");
testAssert($r2567['sum_received'] === '72093125.97', "Year 2567 sum_received is 72,093,125.97 THB");

// Year 2568 checks
$r2568 = $reconciliation[2568];
testAssert($r2568['record_count'] === 2426, "Year 2568 record count is exactly 2,426");
testAssert($r2568['sum_dividend'] === '82204037.08', "Year 2568 sum_dividend is 82,204,037.08 THB");
testAssert($r2568['sum_refund'] === '6883432.49', "Year 2568 sum_refund is 6,883,432.49 THB");
testAssert($r2568['sum_total_income'] === '90495969.57', "Year 2568 sum_total_income is 90,495,969.57 THB");
testAssert($r2568['sum_total_deductions'] === '17860236.17', "Year 2568 sum_total_deductions is 17,860,236.17 THB");
testAssert($r2568['sum_net'] === '72635733.40', "Year 2568 sum_net is 72,635,733.40 THB");
testAssert($r2568['sum_received'] === '74083019.77', "Year 2568 sum_received is 74,083,019.77 THB");

// 8. Isolated Target Simulation with SQLite PDO mimicking target schema
$mockTargetPdo = new PDO('sqlite::memory:');
$mockTargetPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$mockTargetPdo->exec("
    CREATE TABLE roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT UNIQUE,
        name TEXT
    );
    INSERT INTO roles (id, slug, name) VALUES (1, 'super_admin', 'Super Admin');
    INSERT INTO roles (id, slug, name) VALUES (2, 'staff', 'Staff');
    INSERT INTO roles (id, slug, name) VALUES (12, 'member', 'Member');

    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid TEXT UNIQUE,
        name TEXT,
        department TEXT,
        username TEXT UNIQUE,
        email TEXT UNIQUE,
        password TEXT,
        status TEXT,
        session_version INTEGER DEFAULT 1,
        created_at TEXT,
        updated_at TEXT
    );
    -- Existing Super Admin (ID 1)
    INSERT INTO users (id, uuid, name, username, email, password, status)
    VALUES (1, 'admin-uuid', 'Super Admin User', 'admin', 'pharmacisttom@gmail.com', 'hash', 'active');

    CREATE TABLE user_roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        role_id INTEGER,
        created_at TEXT
    );
    INSERT INTO user_roles (user_id, role_id) VALUES (1, 1);

    CREATE TABLE members (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid TEXT UNIQUE,
        user_id INTEGER,
        member_no TEXT UNIQUE,
        id_card TEXT,
        prefix TEXT,
        first_name TEXT,
        last_name TEXT,
        department TEXT,
        status TEXT,
        created_at TEXT,
        updated_at TEXT
    );

    CREATE TABLE member_dividends (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        member_id INTEGER,
        year INTEGER,
        details_json TEXT,
        imported_at TEXT,
        UNIQUE(member_id, year)
    );

    CREATE TABLE member_import_runs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        year INTEGER,
        source_name TEXT,
        record_count INTEGER,
        created_count INTEGER,
        updated_count INTEGER,
        unchanged_count INTEGER,
        net_total NUMERIC,
        created_at TEXT
    );
");

// 9. Dry Run Verification
$dryRunResult = LegacyMemberMigrationService::executeMigration([
    'source_path' => $dumpPath,
    'dry_run' => true,
    'batch_tag' => 'MIG-TEST-DRYRUN',
    'years' => [2567, 2568],
    'pdo' => $mockTargetPdo,
]);

testAssert($dryRunResult['status'] === 'DRY_RUN_PASSED', "Dry Run executed and reported DRY_RUN_PASSED");
testAssert($dryRunResult['dry_run'] === true, "Dry Run flag is true");

// Verify zero database mutation after dry-run
$userCount = (int) $mockTargetPdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$memberCount = (int) $mockTargetPdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
$divCount = (int) $mockTargetPdo->query("SELECT COUNT(*) FROM member_dividends")->fetchColumn();
$runCount = (int) $mockTargetPdo->query("SELECT COUNT(*) FROM member_import_runs")->fetchColumn();

testAssert($userCount === 1, "Dry Run preserved exact user count (only initial Super Admin exists)");
testAssert($memberCount === 0, "Dry Run wrote zero members to database");
testAssert($divCount === 0, "Dry Run wrote zero dividends to database");
testAssert($runCount === 0, "Dry Run wrote zero import runs to database");

// 10. Live Commit in Isolated Test DB Verification
$commitResult = LegacyMemberMigrationService::executeMigration([
    'source_path' => $dumpPath,
    'dry_run' => false,
    'batch_tag' => 'MIG-TEST-COMMIT-01',
    'years' => [2567, 2568],
    'pdo' => $mockTargetPdo,
]);

testAssert($commitResult['status'] === 'COMMITTED', "Live migration committed successfully in test database");

$newUserCount = (int) $mockTargetPdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$newMemberCount = (int) $mockTargetPdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
$newDivCount = (int) $mockTargetPdo->query("SELECT COUNT(*) FROM member_dividends")->fetchColumn();
$newRunCount = (int) $mockTargetPdo->query("SELECT COUNT(*) FROM member_import_runs")->fetchColumn();

testAssert($newUserCount === 2509, "Target users contains 2,508 migrated members + 1 existing Super Admin (Total 2,509)");
testAssert($newMemberCount === 2508, "Target members contains exactly 2,508 records");
testAssert($newDivCount === 4855, "Target member_dividends contains exactly 4,855 records (2,429 for 2567 + 2,426 for 2568)");
testAssert($newRunCount === 2, "Target member_import_runs contains 2 batch run entries (2567 and 2568)");

// Check existing Super Admin was NOT overwritten
$adminUser = $mockTargetPdo->query("SELECT id, username, email FROM users WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
testAssert($adminUser['username'] === 'admin' && $adminUser['email'] === 'pharmacisttom@gmail.com', "Existing Super Admin account was preserved untouched");

// Check ID mapping: first member got ID > 1 (not overwriting admin)
$migratedUser = $mockTargetPdo->query("SELECT id, username, email FROM users WHERE username = '3219900100959'")->fetch(PDO::FETCH_ASSOC);
testAssert((int)$migratedUser['id'] > 1, "Migrated member received safely allocated target ID ({$migratedUser['id']}) without colliding with Super Admin (ID 1)");

// Check leading zero preserved in target DB
$migratedMember = $mockTargetPdo->query("SELECT id, member_no, id_card FROM members WHERE id_card = '3219900100959'")->fetch(PDO::FETCH_ASSOC);
testAssert($migratedMember['member_no'] === '00004', "Target database members table holds exact leading zero '00004'");

// 11. Idempotency Verification
$idempotentResult = LegacyMemberMigrationService::executeMigration([
    'source_path' => $dumpPath,
    'dry_run' => false,
    'batch_tag' => 'MIG-TEST-COMMIT-02',
    'years' => [2567, 2568],
    'pdo' => $mockTargetPdo,
]);
testAssert($idempotentResult['status'] === 'COMMITTED', "Idempotent re-run completed without duplicate key errors");
$idempotentMemberCount = (int) $mockTargetPdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
$idempotentDivCount = (int) $mockTargetPdo->query("SELECT COUNT(*) FROM member_dividends")->fetchColumn();
testAssert($idempotentMemberCount === 2508, "Idempotent re-run did not duplicate member rows (still exactly 2,508)");
testAssert($idempotentDivCount === 4855, "Idempotent re-run did not duplicate dividend rows (still exactly 4,855)");

// 12. Batch Rollback Verification
$rollbackResult = LegacyMemberMigrationService::rollbackBatch('MIG-TEST-COMMIT-02', $mockTargetPdo);
testAssert($rollbackResult['success'] === true, "Batch rollback executed successfully");
testAssert($rollbackResult['runs_deleted'] === 2, "Batch rollback deleted 2 import run entries for the batch");

echo "=========================================================================\n";
echo "Legacy Member Migration & Dividend Recovery Suite: All {$passCount} checks PASSED!\n";
echo "=========================================================================\n";
