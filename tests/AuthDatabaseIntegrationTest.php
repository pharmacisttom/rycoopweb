<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;

$pdo = Database::connect();
$database = (string) Database::value('SELECT DATABASE()');
$required = ['user_id', 'member_no', 'id_card'];
$columns = $pdo->prepare(
    'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
);
$columns->execute([$database, 'members']);
$available = $columns->fetchAll(PDO::FETCH_COLUMN);

foreach ($required as $column) {
    if (!in_array($column, $available, true)) {
        fwrite(STDERR, "Missing members.{$column}\n");
        exit(1);
    }
}

$user = Database::first(
    "SELECT u.id, r.slug AS role_slug
     FROM users u
     LEFT JOIN user_roles ur ON u.id = ur.user_id
     LEFT JOIN roles r ON ur.role_id = r.id
     LEFT JOIN members m ON u.id = m.user_id
     WHERE (u.username = ? OR u.email = ? OR m.member_no = ? OR m.id_card = ?)
       AND u.deleted_at IS NULL
     LIMIT 1",
    ['test_admin', 'test_admin@example.invalid', 'test_admin', 'test_admin']
);

if (!$user || ($user['role_slug'] ?? '') !== 'super_admin') {
    fwrite(STDERR, "Admin authentication lookup failed.\n");
    exit(1);
}

echo "Authentication database integration test passed on {$database}.\n";
