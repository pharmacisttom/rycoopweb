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

use App\Services\BoardTermService;

function assertPreview(bool $condition, string $message): void {
    if (!$condition) {
        echo "FAIL: {$message}\n";
        exit(1);
    }
    echo "PASS: {$message}\n";
}

echo "Running Term 38 Import Preview Test Suite...\n";
echo "=========================================================================\n";

// 1. Test getTerm38SourceData()
$sourceData = BoardTermService::getTerm38SourceData();
assertPreview(is_array($sourceData), "1.1 getTerm38SourceData() returns an array");
assertPreview(($sourceData['term_number'] ?? null) === 38, "1.2 Source term number is 38");
assertPreview(isset($sourceData['boardMembers']) && count($sourceData['boardMembers']) === 15, "1.3 Source has exactly 15 directors");
assertPreview(isset($sourceData['advisors']) && count($sourceData['advisors']) === 3, "1.4 Source has exactly 3 advisors");
assertPreview(isset($sourceData['auditors']) && count($sourceData['auditors']) === 3, "1.5 Source has exactly 3 auditors");
assertPreview(isset($sourceData['boardGroups']) && count($sourceData['boardGroups']) === 3, "1.6 Source has exactly 3 subcommittees");

// 2. Test getTerm38ImportPreview()
$preview = BoardTermService::getTerm38ImportPreview();
assertPreview(is_array($preview), "2.1 getTerm38ImportPreview() returns preview array");
assertPreview(isset($preview['source_summary']), "2.2 Preview includes source_summary");
assertPreview($preview['source_summary']['directors_count'] === 15, "2.3 Source summary directors count is 15");
assertPreview($preview['source_summary']['advisors_count'] === 3, "2.4 Source summary advisors count is 3");
assertPreview($preview['source_summary']['auditors_count'] === 3, "2.5 Source summary auditors count is 3");
assertPreview($preview['source_summary']['groups_count'] === 3, "2.6 Source summary groups count is 3");

assertPreview(isset($preview['existing_summary']), "2.7 Preview includes existing_summary");
assertPreview(isset($preview['director_comparisons']) && count($preview['director_comparisons']) === 15, "2.8 Director comparisons has 15 items");
assertPreview($preview['duplicates_detected'] === 0, "2.9 Duplicates detected is strictly 0");
assertPreview($preview['can_preserve_custom_photos'] === true, "2.10 can_preserve_custom_photos is supported");
assertPreview($preview['can_preserve_admin_edits'] === true, "2.11 can_preserve_admin_edits is supported");

// 3. Verify director comparison structure
$firstDir = $preview['director_comparisons'][0];
assertPreview(isset($firstDir['person_id']) && $firstDir['person_id'] === 1, "3.1 First director person_id is 1");
assertPreview(str_contains($firstDir['name'], 'อุษา'), "3.2 First director name matches president");
assertPreview(str_contains($firstDir['position'], 'ประธาน'), "3.3 First director position matches president");
assertPreview(array_key_exists('existing', $firstDir), "3.4 First director contains existing flag");
assertPreview(array_key_exists('has_custom_photo', $firstDir), "3.5 First director contains has_custom_photo flag");

echo "=========================================================================\n";
echo "Term 38 Import Preview Test Suite PASSED successfully!\n";
