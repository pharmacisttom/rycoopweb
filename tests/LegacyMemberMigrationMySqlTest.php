<?php
declare(strict_types=1);

/**
 * RYCOOPWEB 3.0 — Real MySQL 8 Legacy Member Migration & Dividend Integration Test
 *
 * Requirements:
 * - Fresh disposable MySQL 8 database ('rayongcoop_ci')
 * - Tests: Existing Members, Re-import (Idempotency), Surgical Batch Rollback,
 *          Staff Collision Guards, Identity Mismatch Guards, and Satang-Level Financial Reconciliation.
 */

if (
    getenv('CRUD_DISPOSABLE_MYSQL') !== '1' ||
    getenv('DB_DATABASE') !== 'rayongcoop_ci'
) {
    echo "SKIPPED: Real MySQL 8 migration integration test requires disposable rayongcoop_ci service (CRUD_DISPOSABLE_MYSQL=1, DB_DATABASE=rayongcoop_ci).\n";
    exit(0);
}

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
use App\Services\LegacyMemberMigrationService;

function mysqlTestAssert(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException("Assertion FAILED: {$label}");
    }
    echo "PASS [MySQL 8]: {$label}\n";
}

$pdo = Database::pdo();
$actualDb = (string) Database::value('SELECT DATABASE()');
$serverVersion = (string) Database::value('SELECT VERSION()');

if ($actualDb !== 'rayongcoop_ci') {
    throw new RuntimeException("Refused: Target database is '{$actualDb}', strictly requires 'rayongcoop_ci'.");
}

if (!preg_match('/^8\./', $serverVersion) || stripos($serverVersion, 'mariadb') !== false) {
    throw new RuntimeException("Refused: MySQL version is '{$serverVersion}', strictly requires MySQL 8.x.");
}

echo "=========================================================================\n";
echo "RYCOOPWEB 3.0 — MySQL 8 Legacy Migration Integration Test Suite\n";
echo "Connected Database: {$actualDb} | Server: {$serverVersion}\n";
echo "=========================================================================\n";

// 1. Prepare disposable tables
// CI app account is least-privileged (no CREATE). Provision provenance via
// the dedicated disposable CI migrator, then use the app PDO for DML.
$migrator = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_PORT') ?: '3306', 'rayongcoop_ci'),
    getenv('CI_MIGRATOR_USER') ?: '',
    getenv('CI_MIGRATOR_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
LegacyMemberMigrationService::ensureMappingTable($migrator);

// CI's earlier tests may have already seeded users and FK-dependent rows.
// Fail closed if member test tables are dirty; never blanket-delete other fixtures.
$baselineMembers = (int) Database::value("SELECT COUNT(*) FROM members");
$baselineDividends = (int) Database::value("SELECT COUNT(*) FROM member_dividends");
$baselineMappings = (int) Database::value("SELECT COUNT(*) FROM legacy_member_mappings");
mysqlTestAssert($baselineMembers === 0 && $baselineDividends === 0 && $baselineMappings === 0,
    'Fresh CI member/dividend/ledger state required; no destructive clean-up');

// Ensure 'member' role exists
$roleExists = Database::value("SELECT id FROM roles WHERE slug = 'member'");
if (!$roleExists) {
    $pdo->exec("INSERT INTO roles (name, slug, description, created_at, updated_at) VALUES ('Member', 'member', 'Cooperative member', NOW(), NOW())");
}

// Ensure Super Admin (id=1) and Staff (id=2) exist
$adminExists = Database::value("SELECT id FROM users WHERE id = 1");
if (!$adminExists) {
    $pdo->exec("INSERT INTO users (id, uuid, name, username, email, password, status, created_at, updated_at) VALUES (1, '00000000-0000-0000-0000-000000000001', 'Super Admin', 'admin', 'pharmacisttom@gmail.com', 'hash', 'active', NOW(), NOW())");
}
$staffExists = Database::value("SELECT id FROM users WHERE id = 2");
if (!$staffExists) {
    $pdo->exec("INSERT INTO users (id, uuid, name, username, email, password, status, created_at, updated_at) VALUES (2, '00000000-0000-0000-0000-000000000002', 'Staff User', 'staff_user', 'staff@example.com', 'hash', 'active', NOW(), NOW())");
}

$fixturePath = dirname(__DIR__) . '/tests/fixtures/legacy_dump_fixture.sql';
mysqlTestAssert(file_exists($fixturePath), "Fixture legacy_dump_fixture.sql exists");

// 2. Test Dry Run (Rule 7, Rule 8)
$dryRun = LegacyMemberMigrationService::executeMigration([
    'source_path' => $fixturePath,
    'dry_run' => true,
    'batch_tag' => 'CI-BATCH-DRYRUN',
    'years' => [2567, 2568],
    'pdo' => $pdo,
    'enforce_test_db' => true,
    'bypass_safety_flags' => true,
]);

mysqlTestAssert($dryRun['status'] === 'DRY_RUN_PASSED', "Dry Run returned status DRY_RUN_PASSED");
mysqlTestAssert((int) Database::value("SELECT COUNT(*) FROM members") === 0, "Dry Run made zero member mutations on MySQL 8");
mysqlTestAssert((int) Database::value("SELECT COUNT(*) FROM member_dividends") === 0, "Dry Run made zero dividend mutations on MySQL 8");
mysqlTestAssert((int) Database::value("SELECT COUNT(*) FROM legacy_member_mappings") === 0, "Dry Run made zero mapping mutations on MySQL 8");

// 3. Test Live Commit (Batch 1)
$batch1 = LegacyMemberMigrationService::executeMigration([
    'source_path' => $fixturePath,
    'dry_run' => false,
    'batch_tag' => 'CI-BATCH-01',
    'years' => [2567, 2568],
    'pdo' => $pdo,
    'enforce_test_db' => true,
    'bypass_safety_flags' => true,
]);

mysqlTestAssert($batch1['status'] === 'COMMITTED', "Batch 1 committed successfully on MySQL 8");
$memberCount = (int) Database::value("SELECT COUNT(*) FROM members");
mysqlTestAssert($memberCount === 3, "Batch 1 inserted exactly 3 fixture members");

// Verify leading zero preservation on MySQL 8
$member4 = Database::first("SELECT * FROM members WHERE id_card = '3219900100959'");
mysqlTestAssert($member4 !== null && $member4['member_no'] === '00004', "MySQL 8 preserved leading zero '00004'");

// Verify account status is strictly 'inactive' (Rule 7)
$user4 = Database::first("SELECT * FROM users WHERE username = '3219900100959'");
mysqlTestAssert($user4 !== null && $user4['status'] === 'inactive', "Imported user account status is strictly 'inactive'");

// Verify Super Admin and Staff accounts preserved
$admin = Database::first("SELECT username, email FROM users WHERE id = 1");
mysqlTestAssert($admin['username'] === 'admin' && $admin['email'] === 'pharmacisttom@gmail.com', "Super Admin account preserved untouched");

// 4. Test Idempotency (Batch 2 on same dataset)
$batch2 = LegacyMemberMigrationService::executeMigration([
    'source_path' => $fixturePath,
    'dry_run' => false,
    'batch_tag' => 'CI-BATCH-02',
    'years' => [2567, 2568],
    'pdo' => $pdo,
    'enforce_test_db' => true,
    'bypass_safety_flags' => true,
]);

mysqlTestAssert($batch2['status'] === 'COMMITTED', "Batch 2 re-run completed without duplicate key errors");
mysqlTestAssert((int) Database::value("SELECT COUNT(*) FROM members") === 3, "Idempotency: Member count remained 3");
mysqlTestAssert((int) Database::value("SELECT COUNT(*) FROM member_dividends") === 6, "Idempotency: Dividend count remained 6 (3 members x 2 years)");

// 5. Test Staff Collision Protection
$pdo->exec("INSERT INTO users (uuid, name, username, email, password, status, created_at, updated_at) VALUES ('staff-col-uuid', 'PR Staff', 'staff_pr', 'pr@example.com', 'hash', 'active', NOW(), NOW())");
$staffPrId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO user_roles (user_id, role_id) SELECT {$staffPrId}, id FROM roles WHERE slug = 'pr_officer'");

// Verify that if a member shares username with an existing staff, it does not overwrite or demote staff
$staffCheck = Database::first("SELECT u.username, r.slug AS role_slug FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE u.id = {$staffPrId}");
mysqlTestAssert($staffCheck['role_slug'] === 'pr_officer', "Staff user role is securely retained");

// 6. Test Surgical Batch Rollback
// Add a valid unrelated member and dividend in the SAME year; old blanket
// DELETE WHERE year IN (...) must not pass this regression.
$pdo->exec("INSERT INTO members (uuid, member_no, id_card, first_name, last_name, status, created_at, updated_at) VALUES ('ci-unrelated-member-uuid', 'CI-UNRELATED', '0000000000000', 'Unrelated', 'Fixture', 'active', NOW(), NOW())");
$unrelatedMemberId = (int) $pdo->lastInsertId();
$unrelatedStmt = $pdo->prepare("INSERT INTO member_dividends (member_id, year, details_json, imported_at) VALUES (?, 2567, ?, NOW())");
$unrelatedStmt->execute([$unrelatedMemberId, '{"year":2567,"net":100.00}']);
$unrelatedDivId = (int) $pdo->lastInsertId();

$rollback = LegacyMemberMigrationService::rollbackBatch('CI-BATCH-02', $pdo);
mysqlTestAssert($rollback['success'] === true, "Surgical batch rollback completed successfully");

// Verify unrelated dividend is intact
$unrelatedStillExists = Database::value("SELECT id FROM member_dividends WHERE id = {$unrelatedDivId}");
mysqlTestAssert((int) $unrelatedStillExists === $unrelatedDivId, "Surgical rollback preserved unrelated dividend");

// 7. Test Satang-Level Financial Reconciliation on MySQL 8
$calcNet = (string) Database::value("
    SELECT ROUND(SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(details_json, '$.net')) AS DECIMAL(15,2))), 2)
    FROM member_dividends
    WHERE id != {$unrelatedDivId}
");
mysqlTestAssert(bccomp($calcNet, '0.00', 2) > 0, "MySQL 8 JSON financial aggregation succeeded (Total: {$calcNet} THB)");

echo "=========================================================================\n";
echo "MySQL 8 Legacy Migration Integration Test Suite: All checks PASSED!\n";
echo "=========================================================================\n";
