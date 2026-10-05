<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
use App\Core\Database;
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') !== '--apply') { fwrite(STDERR, "Explicit bulk reset: php bin/reset-member-passwords.php --apply\n"); exit(1); }
$pdo = Database::connect();
try {
    $members = Database::query("SELECT m.user_id, m.member_no, m.id_card FROM members m JOIN users u ON u.id=m.user_id WHERE u.deleted_at IS NULL AND EXISTS (SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=u.id AND r.slug='member') AND NOT EXISTS (SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=u.id AND r.slug<>'member') ORDER BY m.id");
    $seenUsers = []; $seenIds = []; $updates = [];
    foreach ($members as $member) {
        if (!preg_match('/^\d{13}$/D', $member['id_card']) || !preg_match('/^\d{1,20}$/D', $member['member_no']) || isset($seenUsers[$member['user_id']]) || isset($seenIds[$member['id_card']])) { throw new RuntimeException('Invalid or duplicated member identity; no changes applied.'); }
        $seenUsers[$member['user_id']] = true; $seenIds[$member['id_card']] = true;
        $updates[] = ['id' => $member['user_id'], 'password' => password_hash($member['member_no'], PASSWORD_DEFAULT)];
        if (count($updates) % 250 === 0) { echo 'Prepared ' . count($updates) . '/' . count($members) . " passwords\n"; }
    }
    $pdo->beginTransaction();
    foreach ($updates as $update) { Database::execute('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?', [$update['password'], $update['id']]); }
    $pdo->commit();
    echo 'Reset ' . count($updates) . " member passwords to exact member numbers. Staff accounts excluded.\n";
} catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
