<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AuditService;
use App\Core\Database;
use App\Core\Logger;
use PDO;
use RuntimeException;
use InvalidArgumentException;

/**
 * RYCOOPWEB 3.0 — Legacy Member Migration & Dividend Recovery Service
 *
 * Implements:
 * - Isolation from direct dump imports into rycoopweb_test.
 * - Relative path resolution for dump files across Windows, Linux VPS and CI.
 * - Parsing & validating legacy members, users, and member_dividends from MariaDB 10.4 dump.
 * - Strict Target Database verification (must be rycoopweb_test or rayongcoop_ci in TEST mode).
 * - Safety flags verification (member_portal, member_login, backup_restore_execution_enabled must be false).
 * - Identity Discrepancy Gate: strictly validates member_no and id_card consistency, aborts on mis-linking.
 * - Collision Guard: checks username, email, uuid, and staff roles before user creation.
 * - Inactive Account Status: default member user status to 'inactive' pending identity verification.
 * - Provenance Tracking: records legacy_to_target mapping and before_json in legacy_member_mappings table.
 * - Surgical Batch Rollback: deletes ONLY records created by the specific batch and restores updated records.
 * - Dry-run mode, idempotence, atomic transactions, and audit logging.
 */
class LegacyMemberMigrationService
{
    public const BATCH_PREFIX = 'MIG-LEGACY-';
    public const ALLOWED_TEST_DATABASES = ['rycoopweb_test', 'rayongcoop_ci'];

    /**
     * Resolve legacy dump path with relative fallback cascade.
     */
    public static function resolveDumpPath(?string $customPath = null): string
    {
        if ($customPath !== null) {
            if (!is_file($customPath) || !is_readable($customPath)) {
                throw new InvalidArgumentException('Explicit legacy dump source is missing or unreadable; no fallback allowed.');
            }
            return $customPath;
        }

        $baseDir = dirname(__DIR__, 2);
        $candidates = [
            getenv('LEGACY_DUMP_PATH') ?: '',
            $baseDir . '/storage/staging/rayongcoop_db.sql',
            $baseDir . '/storage/legacy/rayongcoop_db.sql',
            $baseDir . '/storage/backups/rayongcoop_db.sql',
            // Never silently import a small test fixture on a TEST or production VPS.
            ...(getenv('CRUD_DISPOSABLE_MYSQL') === '1' ? [$baseDir . '/tests/fixtures/legacy_dump_fixture.sql'] : []),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && file_exists($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        throw new InvalidArgumentException(
            "Legacy SQL dump file not found. Provide --source, set LEGACY_DUMP_PATH, or place dump at storage/staging/rayongcoop_db.sql."
        );
    }

    /**
     * Ensure provenance mapping table exists in target database.
     */
    public static function ensureMappingTable(PDO $pdo): void
    {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS legacy_member_mappings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    batch_tag TEXT NOT NULL,
                    entity_type TEXT NOT NULL,
                    legacy_id INTEGER NOT NULL,
                    target_id INTEGER NOT NULL,
                    action TEXT NOT NULL,
                    before_json TEXT NULL,
                    created_at TEXT NOT NULL,
                    UNIQUE(batch_tag, entity_type, legacy_id)
                );
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS legacy_member_mappings (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    batch_tag VARCHAR(64) NOT NULL,
                    entity_type VARCHAR(32) NOT NULL,
                    legacy_id BIGINT UNSIGNED NOT NULL,
                    target_id BIGINT UNSIGNED NOT NULL,
                    action VARCHAR(20) NOT NULL,
                    before_json LONGTEXT NULL,
                    created_at DATETIME NOT NULL,
                    KEY idx_batch_entity (batch_tag, entity_type),
                    KEY idx_target_entity (entity_type, target_id),
                    UNIQUE KEY uq_batch_legacy (batch_tag, entity_type, legacy_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        }
    }

    /**
     * Parse legacy SQL dump file into structured in-memory datasets.
     */
    public static function parseDump(string $dumpPath): array
    {
        if (!file_exists($dumpPath) || !is_readable($dumpPath)) {
            throw new InvalidArgumentException("Legacy SQL dump file not found or unreadable at: {$dumpPath}");
        }

        $handle = fopen($dumpPath, 'r');
        if (!$handle) {
            throw new RuntimeException("Failed to open legacy SQL dump file: {$dumpPath}");
        }

        $users = [];
        $members = [];
        $dividends = [];
        $excludedUsers = [];
        $anomalies = [
            'invalid_id_cards' => [],
            'duplicate_member_nos' => [],
            'duplicate_id_cards' => [],
            'json_parse_errors' => [],
            'unsupported_passwords' => []
        ];

        $seenMemberNos = [];
        $seenIdCards = [];

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                // 1. Parse USERS
                if (str_starts_with($line, 'INSERT INTO `users` VALUES (')) {
                    if (preg_match('/^INSERT INTO `users` VALUES \((\d+),\s*\'([^\']+)\',\s*\'([^\']+)\',\s*(NULL|\'[^\']*\'),\s*\'([^\']+)\',\s*\'([^\']+)\',\s*\'([^\']+)\',/u', $line, $m)) {
                        $legacyUserId = (int) $m[1];
                        $uuid = $m[2];
                        $name = $m[3];
                        $department = $m[4] === 'NULL' ? null : trim($m[4], "'");
                        $username = $m[5];
                        $email = $m[6];
                        $password = $m[7];

                        // Rule 8: Exclude legacy Super Admin (ID 1) and Member Admin (ID 2510)
                        if ($legacyUserId === 1 || $username === 'admin' || $username === 'memberadmin' || str_contains($email, 'pharmacisttom@gmail.com') || str_contains($email, 'member-admin.invalid')) {
                            $excludedUsers[$legacyUserId] = "Administrative user ({$username} / {$email}) excluded from member migration";
                            continue;
                        }

                        // Rule 9: Check password algorithm (bcrypt only)
                        if (!preg_match('/^\$2[ayb]\$10\$.{53}$/', $password)) {
                            $anomalies['unsupported_passwords'][] = ['user_id' => $legacyUserId, 'username' => $username];
                        }

                        $users[$legacyUserId] = [
                            'legacy_id' => $legacyUserId,
                            'uuid' => $uuid,
                            'name' => $name,
                            'department' => $department,
                            'username' => $username,
                            'email' => $email,
                            'password' => $password,
                        ];
                    }
                }

                // 2. Parse MEMBERS
                elseif (str_starts_with($line, 'INSERT INTO `members` VALUES (')) {
                    if (preg_match('/^INSERT INTO `members` VALUES \((\d+),\s*\'([^\']+)\',\s*(\d+|NULL),\s*\'([^\']+)\',\s*\'([^\']+)\',\s*(NULL|\'[^\']*\'),\s*\'([^\']*)\',\s*\'([^\']*)\',\s*(NULL|\'[^\']*\'),\s*(NULL|\'[^\']*\'),\s*(NULL|\'[^\']*\'),\s*(NULL|\'[^\']*\'),\s*(NULL|\'[^\']*\'),\s*(NULL|\'[^\']*\'),\s*(NULL|\'[^\']*\'),\s*\'([^\']+)\'/u', $line, $m)) {
                        $legacyMemberId = (int) $m[1];
                        $uuid = $m[2];
                        $legacyUserId = $m[3] === 'NULL' ? null : (int) $m[3];
                        $memberNo = $m[4];
                        $idCard = $m[5];
                        $prefix = $m[6] === 'NULL' ? null : trim($m[6], "'");
                        $firstName = $m[7];
                        $lastName = $m[8];
                        $department = $m[9] === 'NULL' ? null : trim($m[9], "'");
                        $status = $m[16];

                        if (!preg_match('/^\d{13}$/D', $idCard)) {
                            $anomalies['invalid_id_cards'][] = ['id' => $legacyMemberId, 'member_no' => $memberNo, 'id_card' => $idCard];
                        }

                        if (isset($seenMemberNos[$memberNo])) {
                            $anomalies['duplicate_member_nos'][] = ['member_no' => $memberNo, 'first_id' => $seenMemberNos[$memberNo], 'second_id' => $legacyMemberId];
                        } else {
                            $seenMemberNos[$memberNo] = $legacyMemberId;
                        }

                        if (isset($seenIdCards[$idCard])) {
                            $anomalies['duplicate_id_cards'][] = ['id_card' => $idCard, 'first_id' => $seenIdCards[$idCard], 'second_id' => $legacyMemberId];
                        } else {
                            $seenIdCards[$idCard] = $legacyMemberId;
                        }

                        $members[$legacyMemberId] = [
                            'legacy_id' => $legacyMemberId,
                            'uuid' => $uuid,
                            'legacy_user_id' => $legacyUserId,
                            'member_no' => $memberNo,
                            'id_card' => $idCard,
                            'prefix' => $prefix,
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'department' => $department,
                            'status' => $status,
                        ];
                    }
                }

                // 3. Parse MEMBER_DIVIDENDS
                elseif (str_starts_with($line, 'INSERT INTO `member_dividends` VALUES (')) {
                    if (preg_match('/^INSERT INTO `member_dividends` VALUES \((\d+),\s*(\d+),\s*(\d+),\s*\'(.+)\',\s*\'([^\']+)\'\);$/u', $line, $m)) {
                        $legacyDivId = (int) $m[1];
                        $legacyMemberId = (int) $m[2];
                        $year = (int) $m[3];
                        $rawJson = $m[4];
                        $importedAt = $m[5];

                        $rawJson = str_replace(['\\\\"', '\"', '\\\\'], ['"', '"', '\\'], $rawJson);

                        try {
                            $details = json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);
                            $dividends[$legacyDivId] = [
                                'legacy_id' => $legacyDivId,
                                'legacy_member_id' => $legacyMemberId,
                                'year' => $year,
                                'details' => $details,
                                'imported_at' => $importedAt,
                            ];
                        } catch (\Throwable $e) {
                            $anomalies['json_parse_errors'][] = [
                                'dividend_id' => $legacyDivId,
                                'member_id' => $legacyMemberId,
                                'year' => $year,
                                'error' => $e->getMessage()
                            ];
                        }
                    }
                }
            }
        } finally {
            fclose($handle);
        }

        return [
            'metadata' => [
                'dump_path' => $dumpPath,
                'dump_size_bytes' => filesize($dumpPath),
                'parsed_at' => date('Y-m-d H:i:s'),
                'total_users_parsed' => count($users),
                'total_members_parsed' => count($members),
                'total_dividends_parsed' => count($dividends),
                'total_excluded_admins' => count($excludedUsers),
            ],
            'users' => $users,
            'members' => $members,
            'dividends' => $dividends,
            'excluded_users' => $excludedUsers,
            'anomalies' => $anomalies,
        ];
    }

    /**
     * Compute financial reconciliation aggregates from dividend records.
     */
    public static function reconcileDividends(array $dividends): array
    {
        $years = [];

        foreach ($dividends as $div) {
            $y = (int) $div['year'];
            if (!isset($years[$y])) {
                $years[$y] = [
                    'year' => $y,
                    'record_count' => 0,
                    'sum_dividend' => '0.00',
                    'sum_refund' => '0.00',
                    'sum_total_income' => '0.00',
                    'sum_total_deductions' => '0.00',
                    'sum_net' => '0.00',
                    'sum_received' => '0.00',
                ];
            }

            $d = $div['details'];
            $years[$y]['record_count']++;

            $divAmount = (float) ($d['income']['เงินปันผล'] ?? 0);
            $refAmount = (float) ($d['income']['เงินเฉลี่ยคืน'] ?? 0);
            $income = (float) ($d['total_income'] ?? 0);
            $deductions = (float) ($d['total_deductions'] ?? 0);
            $net = (float) ($d['net'] ?? 0);
            $received = (float) ($d['received'] ?? 0);

            $years[$y]['sum_dividend'] = bcadd((string)$years[$y]['sum_dividend'], sprintf('%.2f', $divAmount), 2);
            $years[$y]['sum_refund'] = bcadd((string)$years[$y]['sum_refund'], sprintf('%.2f', $refAmount), 2);
            $years[$y]['sum_total_income'] = bcadd((string)$years[$y]['sum_total_income'], sprintf('%.2f', $income), 2);
            $years[$y]['sum_total_deductions'] = bcadd((string)$years[$y]['sum_total_deductions'], sprintf('%.2f', $deductions), 2);
            $years[$y]['sum_net'] = bcadd((string)$years[$y]['sum_net'], sprintf('%.2f', $net), 2);
            $years[$y]['sum_received'] = bcadd((string)$years[$y]['sum_received'], sprintf('%.2f', $received), 2);
        }

        ksort($years);
        return $years;
    }

    /**
     * Execute or Dry-Run the Legacy Migration.
     */
    public static function executeMigration(array $options = []): array
    {
        $sourcePath = self::resolveDumpPath($options['source_path'] ?? null);
        $dryRun = (bool) ($options['dry_run'] ?? true);
        $batchTag = (string) ($options['batch_tag'] ?? (self::BATCH_PREFIX . date('Ymd-His')));
        $targetYears = $options['years'] ?? [2567, 2568];
        $targetPdo = $options['pdo'] ?? null;
        $operatorId = $options['operator_id'] ?? null;
        $enforceTestDb = true; // fail closed: no caller may disable target DB guard

        // 1. Connect to target database
        $pdo = $targetPdo instanceof PDO ? $targetPdo : Database::connect();
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // Rule 3: Strict Target Database Verification in TEST mode
        $activeDb = 'mock_target_db';
        if ($driver === 'mysql') {
            $stmt = $pdo->query('SELECT DATABASE()');
            $activeDb = (string) ($stmt ? $stmt->fetchColumn() : '');

            $allowed = $activeDb === 'rycoopweb_test' ||
                ($activeDb === 'rayongcoop_ci' && getenv('CRUD_DISPOSABLE_MYSQL') === '1');
            if (!$allowed) {
                throw new RuntimeException(
                    "Safety violation: Target database must be strictly 'rycoopweb_test' in TEST mode. Connected database is '{$activeDb}'. Migration halted immediately."
                );
            }
        }

        // Safety bypass is permitted only for explicitly isolated test harnesses.
        $bypass = (bool) ($options['bypass_safety_flags'] ?? false);
        if ($bypass && !($driver === 'sqlite' && $targetPdo instanceof PDO)
            && !(getenv('CRUD_DISPOSABLE_MYSQL') === '1' && $activeDb === 'rayongcoop_ci')) {
            throw new RuntimeException('Safety flag bypass refused outside disposable tests.');
        }
        // Rule 4: Verify Safety Flags from Configuration
        if (!$bypass) {
            $memberPortalEnabled = config('features.member_portal', false);
            $memberLoginEnabled = config('features.member_login', false);
            $backupRestoreEnabled = config('security.backup_restore_execution_enabled', false);

            if ($memberPortalEnabled === true) {
                throw new RuntimeException("Safety violation: features.member_portal must be false during migration.");
            }
            if ($memberLoginEnabled === true) {
                throw new RuntimeException("Safety violation: features.member_login must be false during migration.");
            }
            if ($backupRestoreEnabled === true) {
                throw new RuntimeException("Safety violation: features.backup_restore_execution_enabled must be false during migration.");
            }
        }

        // Never issue DDL as part of --dry-run or application-level migration.
        // The mapping table must be provisioned separately by an approved DBA step.
        $pdo->query('SELECT 1 FROM legacy_member_mappings LIMIT 0');

        // 2. Parse dump safely in memory
        $parsed = self::parseDump($sourcePath);

        // Refuse partial/ambiguous parsing before any transaction can write data.
        foreach ($parsed['anomalies'] as $kind => $rows) {
            if ($rows !== []) {
                throw new RuntimeException("Unsafe legacy source: {$kind} has " . count($rows) . " anomalies.");
            }
        }
        if (count($parsed['users']) !== count($parsed['members'])) {
            throw new RuntimeException('Legacy user/member count mismatch; manual identity review required.');
        }
        foreach ($parsed['members'] as $member) {
            if (!isset($parsed['users'][$member['legacy_user_id'] ?? -1])) {
                throw new RuntimeException('Legacy member has no permitted linked user; review required.');
            }
        }

        // 3. Compute source reconciliation figures
        $sourceReconciliation = self::reconcileDividends($parsed['dividends']);

        // 4. Verify target role for 'member'
        $memberRoleStmt = $pdo->prepare("SELECT id FROM roles WHERE slug = 'member' LIMIT 1");
        $memberRoleStmt->execute();
        $memberRoleId = (int) $memberRoleStmt->fetchColumn();
        if ($memberRoleId <= 0) {
            throw new RuntimeException("Target database missing required role 'member' (slug: member).");
        }

        // 5. Rule 6: Pre-scan target database for staff/admin collision (Username, Email, Role)
        $staffStmt = $pdo->query("
            SELECT u.id, u.username, u.email, u.uuid, r.slug as role_slug
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON r.id = ur.role_id
            WHERE r.slug != 'member'
        ");
        $existingStaffUsers = [];
        $existingStaffEmails = [];
        $existingStaffUuids = [];
        while ($row = $staffStmt->fetch(PDO::FETCH_ASSOC)) {
            $existingStaffUsers[strtolower($row['username'])] = $row;
            $existingStaffEmails[strtolower($row['email'])] = $row;
            $existingStaffUuids[strtolower($row['uuid'])] = $row;
        }

        foreach ($parsed['users'] as $u) {
            $uName = strtolower($u['username']);
            $uEmail = strtolower($u['email']);
            $uUuid = strtolower($u['uuid']);

            if (isset($existingStaffUsers[$uName])) {
                throw new RuntimeException(
                    "Critical security collision: Legacy member account matches existing Staff username '{$u['username']}' with role '{$existingStaffUsers[$uName]['role_slug']}'. Overwrite blocked."
                );
            }
            if (isset($existingStaffEmails[$uEmail])) {
                throw new RuntimeException(
                    "Critical security collision: Legacy member account matches existing Staff email '{$u['email']}' with role '{$existingStaffEmails[$uEmail]['role_slug']}'. Overwrite blocked."
                );
            }
            if (isset($existingStaffUuids[$uUuid])) {
                throw new RuntimeException(
                    "Critical security collision: Legacy member UUID '{$u['uuid']}' matches existing Staff UUID. Overwrite blocked."
                );
            }
        }

        // Mapping tables for this run
        $userMapping = [];
        $memberMapping = [];

        $insertedUsers = 0;
        $updatedUsers = 0;
        $insertedMembers = 0;
        $updatedMembers = 0;
        $insertedDividends = 0;
        $updatedDividends = 0;

        $now = date('Y-m-d H:i:s');

        // 6. Begin Transaction
        $pdo->beginTransaction();

        try {
            // Prepared statements
            $findUserStmt = $pdo->prepare("SELECT id, uuid, name, department, email, password, status FROM users WHERE username = ? LIMIT 1");
            $findUserByEmailStmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ? LIMIT 1");
            $findUserByUuidStmt = $pdo->prepare("SELECT id FROM users WHERE uuid = ? LIMIT 1");
            $insertUserStmt = $pdo->prepare("
                INSERT INTO users (uuid, name, department, username, email, password, status, session_version, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 'inactive', 1, ?, ?)
            ");
            $updateUserStmt = $pdo->prepare("
                UPDATE users SET name = ?, department = ?, email = ?, updated_at = ? WHERE id = ?
            ");

            $findUserRoleStmt = $pdo->prepare("SELECT id FROM user_roles WHERE user_id = ? AND role_id = ? LIMIT 1");
            $insertUserRoleStmt = $pdo->prepare("
                INSERT INTO user_roles (user_id, role_id, created_at)
                VALUES (?, ?, ?)
            ");

            $findMemberByNoStmt = $pdo->prepare("SELECT id, user_id, member_no, id_card, prefix, first_name, last_name, department, status FROM members WHERE member_no = ? LIMIT 1");
            $findMemberByIdCardStmt = $pdo->prepare("SELECT id, user_id, member_no, id_card, prefix, first_name, last_name, department, status FROM members WHERE id_card = ? LIMIT 1");
            $insertMemberStmt = $pdo->prepare("
                INSERT INTO members (uuid, user_id, member_no, id_card, prefix, first_name, last_name, department, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $updateMemberStmt = $pdo->prepare("
                UPDATE members SET user_id = ?, first_name = ?, last_name = ?, department = ?, status = ?, updated_at = ?
                WHERE id = ?
            ");

            $findDivStmt = $pdo->prepare("SELECT id, details_json, imported_at FROM member_dividends WHERE member_id = ? AND year = ? LIMIT 1");
            $insertDivStmt = $pdo->prepare("
                INSERT INTO member_dividends (member_id, year, details_json, imported_at)
                VALUES (?, ?, ?, ?)
            ");
            $updateDivStmt = $pdo->prepare("
                UPDATE member_dividends SET details_json = ?, imported_at = ?
                WHERE id = ?
            ");

            $insertMappingStmt = $pdo->prepare("
                INSERT INTO legacy_member_mappings (batch_tag, entity_type, legacy_id, target_id, action, before_json, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $insertRunStmt = $pdo->prepare("
                INSERT INTO member_import_runs (user_id, year, source_name, record_count, created_count, updated_count, unchanged_count, net_total, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)
            ");

            // A. Process USERS & USER_ROLES (Rule 7: status = 'inactive')
            foreach ($parsed['users'] as $legacyUserId => $u) {
                $findUserStmt->execute([$u['username']]);
                $existingUser = $findUserStmt->fetch(PDO::FETCH_ASSOC);

                if ($existingUser) {
                    // Username alone never authorizes adoption of an existing account.
                    if (!hash_equals(strtolower((string)$existingUser['uuid']), strtolower((string)$u['uuid']))
                        || strcasecmp((string)$existingUser['email'], (string)$u['email']) !== 0) {
                        throw new RuntimeException('Existing username has a different UUID/email identity. Manual mapping required.');
                    }
                    $targetUserId = (int) $existingUser['id'];
                    $beforeJson = json_encode($existingUser, JSON_UNESCAPED_UNICODE);
                    $updateUserStmt->execute([$u['name'], $u['department'], $u['email'], $now, $targetUserId]);
                    $action = 'updated';
                    $updatedUsers++;
                } else {
                    // Check email collision
                    $findUserByEmailStmt->execute([$u['email']]);
                    if ($findUserByEmailStmt->fetchColumn()) {
                        throw new RuntimeException("Email collision: '{$u['email']}' already registered in target users.");
                    }

                    // Check uuid uniqueness
                    $targetUuid = $u['uuid'];
                    $findUserByUuidStmt->execute([$targetUuid]);
                    if ($findUserByUuidStmt->fetchColumn()) {
                        $targetUuid = bin2hex(random_bytes(16));
                    }

                    $insertUserStmt->execute([
                        $targetUuid,
                        $u['name'],
                        $u['department'],
                        $u['username'],
                        $u['email'],
                        $u['password'],
                        $now,
                        $now
                    ]);
                    $targetUserId = (int) $pdo->lastInsertId();
                    $beforeJson = null;
                    $action = 'created';
                    $insertedUsers++;
                }

                $userMapping[$legacyUserId] = $targetUserId;

                // Record provenance
                $insertMappingStmt->execute([
                    $batchTag,
                    'user',
                    $legacyUserId,
                    $targetUserId,
                    $action,
                    $beforeJson,
                    $now
                ]);

                // Ensure member role is assigned
                $findUserRoleStmt->execute([$targetUserId, $memberRoleId]);
                if (!$findUserRoleStmt->fetchColumn()) {
                    $insertUserRoleStmt->execute([$targetUserId, $memberRoleId, $now]);
                    $insertMappingStmt->execute([
                        $batchTag, 'user_role', $legacyUserId,
                        (int) $pdo->lastInsertId(), 'created', null, $now
                    ]);
                }
            }

            // B. Process MEMBERS (Rule 5: Stricter member_no & id_card consistency check)
            foreach ($parsed['members'] as $legacyMemberId => $m) {
                $targetUserId = isset($m['legacy_user_id']) ? ($userMapping[$m['legacy_user_id']] ?? null) : null;

                $findMemberByNoStmt->execute([$m['member_no']]);
                $mByNo = $findMemberByNoStmt->fetch(PDO::FETCH_ASSOC);

                $findMemberByIdCardStmt->execute([$m['id_card']]);
                $mById = $findMemberByIdCardStmt->fetch(PDO::FETCH_ASSOC);

                if ($mByNo && $mById && (int)$mByNo['id'] !== (int)$mById['id']) {
                    throw new RuntimeException(
                        "Identity integrity violation: member_no '{$m['member_no']}' belongs to Member #{$mByNo['id']}, but id_card '{$m['id_card']}' belongs to Member #{$mById['id']}. Mis-linking prevented."
                    );
                }
                if ($mByNo && !$mById && $mByNo['id_card'] !== $m['id_card']) {
                    throw new RuntimeException(
                        "Identity mismatch: Existing Member #{$mByNo['id']} has member_no '{$m['member_no']}' but id_card '{$mByNo['id_card']}' does not match incoming '{$m['id_card']}'. Update rejected."
                    );
                }
                if ($mById && !$mByNo && $mById['member_no'] !== $m['member_no']) {
                    throw new RuntimeException(
                        "Identity mismatch: Existing Member #{$mById['id']} has id_card '{$m['id_card']}' but member_no '{$mById['member_no']}' does not match incoming '{$m['member_no']}'. Update rejected."
                    );
                }

                $existing = $mByNo ?: $mById;

                if ($existing) {
                    if ($existing['user_id'] !== null && (int)$existing['user_id'] !== (int)$targetUserId) {
                        throw new RuntimeException('Existing member is linked to a different user account. Manual identity review required.');
                    }
                    $targetMemberId = (int) $existing['id'];
                    $beforeJson = json_encode($existing, JSON_UNESCAPED_UNICODE);
                    $updateMemberStmt->execute([
                        $targetUserId,
                        $m['first_name'],
                        $m['last_name'],
                        $m['department'],
                        $m['status'],
                        $now,
                        $targetMemberId
                    ]);
                    $action = 'updated';
                    $updatedMembers++;
                } else {
                    $insertMemberStmt->execute([
                        $m['uuid'],
                        $targetUserId,
                        $m['member_no'], // Leading zero strictly preserved!
                        $m['id_card'],
                        $m['prefix'],
                        $m['first_name'],
                        $m['last_name'],
                        $m['department'],
                        $m['status'],
                        $now,
                        $now
                    ]);
                    $targetMemberId = (int) $pdo->lastInsertId();
                    $beforeJson = null;
                    $action = 'created';
                    $insertedMembers++;
                }

                $memberMapping[$legacyMemberId] = $targetMemberId;

                // Record provenance
                $insertMappingStmt->execute([
                    $batchTag,
                    'member',
                    $legacyMemberId,
                    $targetMemberId,
                    $action,
                    $beforeJson,
                    $now
                ]);
            }

            // C. Process MEMBER_DIVIDENDS
            $dividendsByYear = [];
            foreach ($parsed['dividends'] as $legacyDivId => $div) {
                $year = (int) $div['year'];
                if (!in_array($year, $targetYears, true)) {
                    continue;
                }

                $targetMemberId = $memberMapping[$div['legacy_member_id']] ?? null;
                if (!$targetMemberId) {
                    throw new RuntimeException(
                        "Data integrity error: Dividend record {$legacyDivId} references unmapped legacy member {$div['legacy_member_id']}"
                    );
                }

                $detailsJson = json_encode($div['details'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

                $findDivStmt->execute([$targetMemberId, $year]);
                $existingDiv = $findDivStmt->fetch(PDO::FETCH_ASSOC);

                if ($existingDiv) {
                    $targetDivId = (int) $existingDiv['id'];
                    $beforeJson = json_encode([
                        'details_json' => $existingDiv['details_json'],
                        'imported_at' => $existingDiv['imported_at'],
                    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                    $updateDivStmt->execute([$detailsJson, $now, $targetDivId]);
                    $action = 'updated';
                    $updatedDividends++;
                } else {
                    $insertDivStmt->execute([$targetMemberId, $year, $detailsJson, $now]);
                    $targetDivId = (int) $pdo->lastInsertId();
                    $beforeJson = null;
                    $action = 'created';
                    $insertedDividends++;
                }

                // Record provenance
                $insertMappingStmt->execute([
                    $batchTag,
                    'dividend',
                    $legacyDivId,
                    $targetDivId,
                    $action,
                    $beforeJson,
                    $now
                ]);

                if (!isset($dividendsByYear[$year])) {
                    $dividendsByYear[$year] = ['count' => 0, 'net_total' => '0.00'];
                }
                $dividendsByYear[$year]['count']++;
                $dividendsByYear[$year]['net_total'] = bcadd(
                    $dividendsByYear[$year]['net_total'],
                    sprintf('%.2f', (float)($div['details']['net'] ?? 0)),
                    2
                );
            }

            // D. Record in member_import_runs for each year
            foreach ($dividendsByYear as $year => $stat) {
                $sourceName = "rayongcoop_db.sql (Legacy MariaDB 10.4) [Batch: {$batchTag}]";
                $insertRunStmt->execute([
                    $operatorId,
                    $year,
                    $sourceName,
                    $stat['count'],
                    $stat['count'],
                    $stat['net_total'],
                    $now
                ]);
            }

            // Audit log
            if (class_exists(\App\Services\AuditService::class)) {
                \App\Services\AuditService::log('member_import', 'legacy_migration', $batchTag, null, [
                    'batch_tag' => $batchTag,
                    'dry_run' => $dryRun,
                    'users_inserted' => $insertedUsers,
                    'members_inserted' => $insertedMembers,
                    'dividends_inserted' => $insertedDividends,
                    'years' => $targetYears,
                ]);
            }

            // 7. Commit or Rollback based on Dry-Run flag
            if ($dryRun) {
                $pdo->rollBack();
                $executionStatus = 'DRY_RUN_PASSED';
            } else {
                $pdo->commit();
                $executionStatus = 'COMMITTED';
            }

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException("Migration transaction aborted: " . $e->getMessage(), (int)$e->getCode(), $e);
        }

        return [
            'status' => $executionStatus,
            'dry_run' => $dryRun,
            'batch_tag' => $batchTag,
            'database' => $activeDb,
            'source_reconciliation' => $sourceReconciliation,
            'migration_summary' => [
                'users' => [
                    'source_total' => count($parsed['users']),
                    'inserted' => $insertedUsers,
                    'updated' => $updatedUsers,
                ],
                'members' => [
                    'source_total' => count($parsed['members']),
                    'inserted' => $insertedMembers,
                    'updated' => $updatedMembers,
                ],
                'dividends' => [
                    'source_total' => count($parsed['dividends']),
                    'inserted' => $insertedDividends,
                    'updated' => $updatedDividends,
                    'by_year' => $dividendsByYear,
                ],
            ],
            'mappings_count' => [
                'users' => count($userMapping),
                'members' => count($memberMapping),
            ],
        ];
    }

    /**
     * Surgical Batch Rollback: Deletes ONLY records created by the batch,
     * restores records updated by the batch to their exact prior state.
     */
    public static function rollbackBatch(string $batchTag, ?PDO $targetPdo = null): array
    {
        $pdo = $targetPdo instanceof PDO ? $targetPdo : Database::connect();

        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
            if ($dbName !== 'rycoopweb_test' && !($dbName === 'rayongcoop_ci' && getenv('CRUD_DISPOSABLE_MYSQL') === '1')) {
                throw new RuntimeException('Rollback refused outside approved TEST or disposable CI database.');
            }
        }
        $pdo->query('SELECT 1 FROM legacy_member_mappings LIMIT 0');

        // Fetch mappings for this batch
        $fetchStmt = $pdo->prepare("
            SELECT id, entity_type, legacy_id, target_id, action, before_json
            FROM legacy_member_mappings
            WHERE batch_tag = ?
            ORDER BY id DESC
        ");
        $fetchStmt->execute([$batchTag]);
        $mappings = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($mappings)) {
            return [
                'success' => false,
                'message' => "No migration records found in legacy_member_mappings for batch '{$batchTag}'"
            ];
        }

        $deletedDividends = 0;
        $restoredDividends = 0;
        $deletedMembers = 0;
        $restoredMembers = 0;
        $deletedUsers = 0;
        $restoredUsers = 0;

        // Refuse out-of-order rollback: a later batch may have edited the same target rows.
        // Roll back subsequent batches first to avoid clobbering newer changes.
        $laterStmt = $pdo->prepare("
            SELECT COUNT(*) FROM legacy_member_mappings newer
            JOIN legacy_member_mappings current
              ON newer.entity_type = current.entity_type AND newer.target_id = current.target_id
            WHERE current.batch_tag = ? AND newer.id > current.id AND newer.batch_tag <> current.batch_tag
        ");
        $laterStmt->execute([$batchTag]);
        if ((int) $laterStmt->fetchColumn() > 0) {
            throw new RuntimeException('Rollback refused: newer batches have modified the same targets. Roll back newer batches first.');
        }

        $pdo->beginTransaction();

        try {
            // Prepared statements for rollback
            $deleteDivStmt = $pdo->prepare("DELETE FROM member_dividends WHERE id = ?");
            $restoreDivStmt = $pdo->prepare("UPDATE member_dividends SET details_json = ?, imported_at = ? WHERE id = ?");

            $deleteMemberStmt = $pdo->prepare("DELETE FROM members WHERE id = ?");
            $restoreMemberStmt = $pdo->prepare("
                UPDATE members
                SET user_id = ?, first_name = ?, last_name = ?, department = ?, status = ?
                WHERE id = ?
            ");

            $deleteUserRoleStmt = $pdo->prepare("DELETE FROM user_roles WHERE id = ?");
            $deleteUserStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $restoreUserStmt = $pdo->prepare("
                UPDATE users SET name = ?, department = ?, email = ? WHERE id = ?
            ");

            // Process mappings in reverse order (dividends -> members -> users)
            foreach ($mappings as $m) {
                $type = $m['entity_type'];
                $targetId = (int) $m['target_id'];
                $action = $m['action'];
                $before = !empty($m['before_json']) ? json_decode($m['before_json'], true) : null;

                if ($type === 'dividend') {
                    if ($action === 'created') {
                        $deleteDivStmt->execute([$targetId]);
                        $deletedDividends++;
                    } elseif ($action === 'updated' && $m['before_json'] !== null) {
                        $restoreDivStmt->execute([
                            $before['details_json'] ?? '',
                            $before['imported_at'] ?? null,
                            $targetId
                        ]);
                        $restoredDividends++;
                    }
                } elseif ($type === 'member') {
                    if ($action === 'created') {
                        $deleteMemberStmt->execute([$targetId]);
                        $deletedMembers++;
                    } elseif ($action === 'updated' && is_array($before)) {
                        $restoreMemberStmt->execute([
                            $before['user_id'] ?? null,
                            $before['first_name'] ?? '',
                            $before['last_name'] ?? '',
                            $before['department'] ?? null,
                            $before['status'] ?? 'active',
                            $targetId
                        ]);
                        $restoredMembers++;
                    }
                } elseif ($type === 'user_role') {
                    if ($action === 'created') {
                        $deleteUserRoleStmt->execute([$targetId]);
                    }
                } elseif ($type === 'user') {
                    if ($action === 'created') {
                        $deleteUserStmt->execute([$targetId]);
                        $deletedUsers++;
                    } elseif ($action === 'updated' && is_array($before)) {
                        $restoreUserStmt->execute([
                            $before['name'] ?? '',
                            $before['department'] ?? null,
                            $before['email'] ?? '',
                            $targetId
                        ]);
                        $restoredUsers++;
                    }
                }
            }

            // Delete associated import runs
            $sourcePattern = "%[Batch: {$batchTag}]%";
            $deleteRunsStmt = $pdo->prepare("DELETE FROM member_import_runs WHERE source_name LIKE ?");
            $deleteRunsStmt->execute([$sourcePattern]);
            $deletedRuns = $deleteRunsStmt->rowCount();

            // Delete mappings for this batch
            $deleteMappingsStmt = $pdo->prepare("DELETE FROM legacy_member_mappings WHERE batch_tag = ?");
            $deleteMappingsStmt->execute([$batchTag]);

            // Audit log
            if (class_exists(\App\Services\AuditService::class)) {
                \App\Services\AuditService::log('member_import', 'legacy_rollback', $batchTag, null, [
                    'batch_tag' => $batchTag,
                    'deleted_dividends' => $deletedDividends,
                    'restored_dividends' => $restoredDividends,
                    'deleted_members' => $deletedMembers,
                    'restored_members' => $restoredMembers,
                    'deleted_users' => $deletedUsers,
                    'restored_users' => $restoredUsers,
                    'deleted_runs' => $deletedRuns,
                ]);
            }

            $pdo->commit();

            return [
                'success' => true,
                'batch_tag' => $batchTag,
                'deleted_dividends' => $deletedDividends,
                'restored_dividends' => $restoredDividends,
                'deleted_members' => $deletedMembers,
                'restored_members' => $restoredMembers,
                'deleted_users' => $deletedUsers,
                'restored_users' => $restoredUsers,
                'deleted_runs' => $deletedRuns,
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException("Surgical rollback transaction failed: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }
}
