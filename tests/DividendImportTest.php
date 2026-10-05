<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
use App\Services\DividendImportService as Import;
function check(bool $condition, string $label): void { if (!$condition) { throw new RuntimeException($label); } echo "PASS: {$label}\n"; }
$sample = [2566, '1234567890123', '00001', 'Test member', '', 1000, 200, 0, 0, 1200, 100, 0, 0, 0, 0, 0, 100, 1100, 1100, 5.3, 8];
$result = Import::normalize([Import::HEADERS, $sample], 2566);
check(count($result['records']) === 1 && !$result['errors'], 'historical Buddhist year supported');
check($result['records'][0]['member_no'] === '00001', 'leading zero member number preserved');
$invalid = $sample; $invalid[17] = 900;
check(count(Import::normalize([Import::HEADERS, $invalid], 2566)['errors']) === 1, 'incorrect net amount rejected');
check(count(Import::normalize([Import::HEADERS, $sample, $sample], 2566)['errors']) === 1, 'duplicate identity in one year rejected');
check(count(Import::normalize([Import::HEADERS, $sample], 2565)['errors']) === 1, 'wrong selected year rejected');
$invalid = $sample; $invalid[1] = '0000000000000';
check(count(Import::normalize([Import::HEADERS, $invalid], 2566)['errors']) === 1, 'template example identity rejected');
$invalid = $sample; $invalid[5] = '#VALUE!';
check(count(Import::normalize([Import::HEADERS, $invalid], 2566)['errors']) === 1, 'formula error rejected');
$headers = Import::HEADERS; unset($headers[9]);
try { Import::normalize([$headers, $sample], 2566); check(false, 'missing header rejected'); } catch (RuntimeException $e) { check(str_contains($e->getMessage(), 'ขาดคอลัมน์'), 'missing header rejected'); }
$rows = array_reverse(Import::HEADERS); $reversed = array_reverse($sample);
check(!Import::normalize([$rows, $reversed], 2566)['errors'], 'reordered columns supported');
foreach (['xlsx', 'csv'] as $type) {
    if (file_exists(__DIR__ . '/../public/templates/member-dividends-template.' . $type)) {
        $parsed = Import::parseFile(__DIR__ . '/../public/templates/member-dividends-template.' . $type, $type, 2566);
        check(count($parsed['errors']) === 1 && str_contains($parsed['errors'][0]['message'], 'แทนที่'), "{$type} template parses and requires real identity");
    }
}
