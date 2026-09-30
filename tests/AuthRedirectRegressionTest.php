<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$auth = file_get_contents($root . '/app/Controllers/Admin/AuthController.php');
$migration = file_get_contents($root . '/database/migrations/012_repair_members_auth_columns.sql');
$failures = [];

foreach (["'super_admin' => '/admin/dashboard'", "default => '/staff/dashboard'", "'redirect' => '/admin/2fa'"] as $expected) {
    if (!str_contains($auth, $expected)) $failures[] = "Missing relative auth redirect: {$expected}";
}
if (str_contains($auth, "'redirect' => url('admin/2fa')")) $failures[] = '2FA JSON redirect is still absolute.';
foreach (['m.user_id', 'm.member_no', 'm.id_card'] as $field) {
    if (!str_contains($auth, $field)) $failures[] = "Authentication lookup does not include {$field}.";
}
foreach (['user_id', 'id_card', 'idx_members_user', 'idx_members_id_card', 'fk_members_user'] as $schemaItem) {
    if (!str_contains($migration, $schemaItem)) $failures[] = "Repair migration does not include {$schemaItem}.";
}

if ($failures) {
    foreach ($failures as $failure) echo "FAIL: {$failure}\n";
    exit(1);
}
echo "Authentication redirect and schema regression tests passed.\n";
