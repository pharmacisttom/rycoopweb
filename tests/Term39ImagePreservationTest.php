<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_WARNING);

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Logger.php';
require_once __DIR__ . '/../app/Core/Helpers.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Services/AccessService.php';
require_once __DIR__ . '/../app/Services/AuditService.php';
require_once __DIR__ . '/../app/Services/PublicContentService.php';
require_once __DIR__ . '/../app/Services/BoardTermService.php';

use App\Core\Database;
use App\Services\BoardTermService;

function assertTerm39Img(bool $condition, string $message): void {
    if (!$condition) {
        echo "FAIL: {$message}\n";
        exit(1);
    }
    echo "PASS: {$message}\n";
}

echo "Running Term 39 Image Transfer & Isolation Test Suite...\n";
echo "=========================================================================\n";

// 1. Ensure clean Term 38 and clean Term 39
BoardTermService::importTerm38(['preserve_custom_photos' => false, 'preserve_admin_edits' => false]);
$term38 = Database::first("SELECT id FROM board_terms WHERE term_number = 38");
$term38Id = (int)$term38['id'];

$existing39 = Database::first("SELECT id FROM board_terms WHERE term_number = 39");
if ($existing39) {
    Database::execute("DELETE FROM board_terms WHERE id = ?", [$existing39['id']]);
}

// 2. Select Term 38 President
$president38 = Database::first("SELECT * FROM board_term_members WHERE term_id = ? AND role_type = 'president'", [$term38Id]);
assertTerm39Img($president38 !== null, "1.1 Term 38 president exists");
assertTerm39Img(!empty($president38['image_url']), "1.2 Term 38 president has non-empty image_url");
$originalTerm38Photo = $president38['image_url'];

// 3. Selectively transfer president to Term 39
$transferResult = BoardTermService::selectiveTransfer(
    $term38Id,
    39,
    [(int)$president38['id']],
    ['term_name' => 'ชุดที่ 39']
);
assertTerm39Img($transferResult['success'] === true, "2.1 Transfer executed successfully");
$term39Id = (int)$transferResult['target_term_id'];

// 4. Verify Term 39 member inherits image_url
$president39 = Database::first("SELECT * FROM board_term_members WHERE term_id = ? AND source_member_id = ?", [$term39Id, $president38['id']]);
assertTerm39Img($president39 !== null, "3.1 Term 39 member exists with source_member_id linking to Term 38 president");
assertTerm39Img($president39['image_url'] === $originalTerm38Photo, "3.2 Term 39 member inherits identical image_url from Term 38");

// 5. Update Term 39 member's photo independently
$newTerm39Photo = '/storage/uploads/board/board_m_39_new_photo_test.webp';
Database::execute(
    "UPDATE board_term_members SET image_url = ? WHERE id = ?",
    [$newTerm39Photo, $president39['id']]
);

// 6. Verify Term 38 photo is STRICTLY UNCHANGED
$president38After = Database::first("SELECT * FROM board_term_members WHERE id = ?", [$president38['id']]);
assertTerm39Img($president38After['image_url'] === $originalTerm38Photo, "4.1 Term 38 president photo remains 100% UNCHANGED after Term 39 photo update");

// 7. Verify Term 39 photo reflects the new update
$president39After = Database::first("SELECT * FROM board_term_members WHERE id = ?", [$president39['id']]);
assertTerm39Img($president39After['image_url'] === $newTerm39Photo, "4.2 Term 39 member photo updated to new photo");

// Clean up Term 39 draft
Database::execute("DELETE FROM board_terms WHERE id = ?", [$term39Id]);

echo "=========================================================================\n";
echo "Term 39 Image Transfer & Isolation Test Suite PASSED successfully!\n";
