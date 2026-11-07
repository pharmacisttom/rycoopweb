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

function assertPreserve(bool $condition, string $message): void {
    if (!$condition) {
        echo "FAIL: {$message}\n";
        exit(1);
    }
    echo "PASS: {$message}\n";
}

echo "Running Term 38 Image & Admin Edit Preservation Test Suite...\n";
echo "=========================================================================\n";

// 1. Ensure Term 38 exists
BoardTermService::importTerm38();
$term38 = Database::first("SELECT id FROM board_terms WHERE term_number = 38");
$termId = (int)$term38['id'];

// 2. Select member 1 (President) and apply a custom uploaded photo and custom workplace
$customPhotoUrl = '/storage/uploads/board/board_m_38_1_custom_test.jpg';
$customWorkplace = 'สำนักงานสาธารณสุขจังหวัดระยอง (ข้อมูลแก้ไขโดยเจ้าหน้าที่)';
$customBio = 'ประวัติส่วนตัวเพิ่มเติมที่แอดมินบันทึก';

Database::execute(
    "UPDATE board_term_members SET image_url = ?, workplace = ?, bio = ? WHERE term_id = ? AND person_id = 1",
    [$customPhotoUrl, $customWorkplace, $customBio, $termId]
);

$memberBefore = Database::first("SELECT * FROM board_term_members WHERE term_id = ? AND person_id = 1", [$termId]);
assertPreserve($memberBefore['image_url'] === $customPhotoUrl, "1.1 Custom photo saved before import");
assertPreserve($memberBefore['workplace'] === $customWorkplace, "1.2 Custom workplace saved before import");
assertPreserve($memberBefore['bio'] === $customBio, "1.3 Custom bio saved before import");

// 3. Run importTerm38 with preserve_custom_photos => true and preserve_admin_edits => true
$importResult = BoardTermService::importTerm38([
    'preserve_custom_photos' => true,
    'preserve_admin_edits' => true,
]);

assertPreserve($importResult['status'] === 'success', "2.1 Import succeeded");
assertPreserve($importResult['preserved_photos'] >= 1, "2.2 At least 1 custom photo reported as preserved");
assertPreserve($importResult['preserved_edits'] >= 1, "2.3 At least 1 admin edit reported as preserved");

$memberAfter = Database::first("SELECT * FROM board_term_members WHERE term_id = ? AND person_id = 1", [$termId]);
assertPreserve($memberAfter['image_url'] === $customPhotoUrl, "2.4 Custom uploaded photo was strictly PRESERVED during import");
assertPreserve($memberAfter['workplace'] === $customWorkplace, "2.5 Custom workplace was strictly PRESERVED during import");
assertPreserve($memberAfter['bio'] === $customBio, "2.6 Custom bio was strictly PRESERVED during import");

// 4. Test explicit overwrite when preserve_custom_photos => false
BoardTermService::importTerm38([
    'preserve_custom_photos' => false,
    'preserve_admin_edits' => false,
]);

$memberReset = Database::first("SELECT * FROM board_term_members WHERE term_id = ? AND person_id = 1", [$termId]);
assertPreserve(str_contains($memberReset['image_url'], 'committee-01-president'), "3.1 Photo reset to legacy reference when preservation is explicitly disabled");

// Restore clean state
BoardTermService::importTerm38();

echo "=========================================================================\n";
echo "Term 38 Image & Admin Edit Preservation Test Suite PASSED successfully!\n";
