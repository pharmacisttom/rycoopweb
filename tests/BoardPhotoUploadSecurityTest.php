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

function assertSecurity(bool $condition, string $message): void {
    if (!$condition) {
        echo "FAIL: {$message}\n";
        exit(1);
    }
    echo "PASS: {$message}\n";
}

echo "Running Board Photograph Upload Security & Validation Test Suite...\n";
echo "=========================================================================\n";

// 1. Ensure Term 38 exists
BoardTermService::importTerm38();
$term38 = Database::first("SELECT id FROM board_terms WHERE term_number = 38");
$term38Id = (int)$term38['id'];
$member = Database::first("SELECT id FROM board_term_members WHERE term_id = ? AND category = 'director' ORDER BY sort_order ASC", [$term38Id]);
$memberId = (int)$member['id'];

// Helper to create a test image
function createTestImage(int $width, int $height, string $format = 'jpeg'): string {
    $tempFile = tempnam(sys_get_temp_dir(), 'test_img_');
    $img = imagecreatetruecolor($width, $height);
    $bg = imagecolorallocate($img, 100, 150, 200);
    imagefill($img, 0, 0, $bg);

    if ($format === 'png') {
        imagepng($img, $tempFile);
    } elseif ($format === 'webp') {
        imagewebp($img, $tempFile);
    } else {
        imagejpeg($img, $tempFile, 90);
    }
    imagedestroy($img);
    return $tempFile;
}

// 2. Test valid JPEG upload (300x300)
$validJpeg = createTestImage(300, 300, 'jpeg');
$uploadResult = BoardTermService::uploadMemberPhoto($term38Id, $memberId, [
    'name' => 'president_portrait.jpg',
    'type' => 'image/jpeg',
    'tmp_name' => $validJpeg,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($validJpeg),
], 1);

assertSecurity($uploadResult['success'] === true, "1.1 Valid JPEG upload succeeded");
assertSecurity(str_starts_with($uploadResult['url'], '/storage/uploads/board/board_m_'), "1.2 Stored URL follows canonical pattern (/storage/uploads/board/board_m_...)");

// Check DB updated
$memberUpdated = Database::first("SELECT image_url FROM board_term_members WHERE id = ?", [$memberId]);
assertSecurity($memberUpdated['image_url'] === $uploadResult['url'], "1.3 Database image_url updated with new uploaded photo URL");

// 3. Test valid PNG upload (200x200)
$validPng = createTestImage(200, 200, 'png');
$pngResult = BoardTermService::uploadMemberPhoto($term38Id, $memberId, [
    'name' => 'president_portrait.png',
    'type' => 'image/png',
    'tmp_name' => $validPng,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($validPng),
], 1);
assertSecurity($pngResult['success'] === true, "2.1 Valid PNG upload succeeded");
assertSecurity(str_ends_with($pngResult['url'], '.png'), "2.2 PNG URL has .png extension");

// 4. Test oversize file (> 5MB)
$oversizeFile = tempnam(sys_get_temp_dir(), 'oversize_');
$oversizeCaught = false;
try {
    BoardTermService::uploadMemberPhoto($term38Id, $memberId, [
        'name' => 'huge_file.jpg',
        'type' => 'image/jpeg',
        'tmp_name' => $oversizeFile,
        'error' => UPLOAD_ERR_OK,
        'size' => (5 * 1024 * 1024) + 1024, // 5MB + 1KB
    ], 1);
} catch (InvalidArgumentException $e) {
    $oversizeCaught = true;
    assertSecurity(str_contains($e->getMessage(), '5 MB'), "3.1 Oversize file (>5MB) rejected with message: {$e->getMessage()}");
}
assertSecurity($oversizeCaught, "3.2 Oversize file was strictly rejected");
@unlink($oversizeFile);

// 5. Test disguised malicious script (fake JPEG with PHP code)
$fakeJpeg = tempnam(sys_get_temp_dir(), 'fake_');
file_put_contents($fakeJpeg, "<?php phpinfo(); ?>\nThis is not a real image");
$magicCaught = false;
try {
    BoardTermService::uploadMemberPhoto($term38Id, $memberId, [
        'name' => 'exploit.jpg',
        'type' => 'image/jpeg',
        'tmp_name' => $fakeJpeg,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($fakeJpeg),
    ], 1);
} catch (InvalidArgumentException $e) {
    $magicCaught = true;
    assertSecurity(true, "4.1 Fake JPEG script rejected with message: {$e->getMessage()}");
}
assertSecurity($magicCaught, "4.2 Magic bytes verification rejected malicious non-image file");
@unlink($fakeJpeg);

// 6. Test too small dimensions (< 100x100)
$tinyImg = createTestImage(50, 50, 'jpeg');
$tinyCaught = false;
try {
    BoardTermService::uploadMemberPhoto($term38Id, $memberId, [
        'name' => 'tiny.jpg',
        'type' => 'image/jpeg',
        'tmp_name' => $tinyImg,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tinyImg),
    ], 1);
} catch (InvalidArgumentException $e) {
    $tinyCaught = true;
    assertSecurity(str_contains($e->getMessage(), '100x100'), "5.1 Tiny image (<100x100) rejected with message: {$e->getMessage()}");
}
assertSecurity($tinyCaught, "5.2 Tiny dimensions were strictly rejected");
@unlink($tinyImg);

// 7. Test path traversal filename in upload metadata
$traversalImg = createTestImage(150, 150, 'jpeg');
$traversalResult = BoardTermService::uploadMemberPhoto($term38Id, $memberId, [
    'name' => '../../../../etc/passwd.jpg',
    'type' => 'image/jpeg',
    'tmp_name' => $traversalImg,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($traversalImg),
], 1);
assertSecurity(!str_contains($traversalResult['url'], '..'), "6.1 Path traversal filename safely neutralized into randomized filename");
assertSecurity(str_starts_with($traversalResult['url'], '/storage/uploads/board/board_m_'), "6.2 Stored in dedicated board directory");

// 8. Test mismatched term ID / member ID
$mismatchCaught = false;
try {
    BoardTermService::uploadMemberPhoto(99999, $memberId, [
        'name' => 'valid.jpg',
        'type' => 'image/jpeg',
        'tmp_name' => $validJpeg,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($validJpeg),
    ], 1);
} catch (InvalidArgumentException $e) {
    $mismatchCaught = true;
    assertSecurity(true, "7.1 Member not belonging to specified term rejected with message: {$e->getMessage()}");
}
assertSecurity($mismatchCaught, "7.2 Term/member boundary check strictly enforced");

@unlink($validJpeg);
@unlink($validPng);
@unlink($traversalImg);

// Re-sync Term 38 clean state
BoardTermService::importTerm38(['preserve_custom_photos' => false, 'preserve_admin_edits' => false]);

echo "=========================================================================\n";
echo "Board Photograph Upload Security & Validation Test Suite PASSED successfully!\n";
