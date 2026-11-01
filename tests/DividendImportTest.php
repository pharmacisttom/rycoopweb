<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
use App\Services\DividendImportService as Import;
function check(bool $condition, string $label): void { if (!$condition) { throw new RuntimeException($label); } echo "PASS: {$label}\n"; }
$sample = [2566, '1234567890123', '00001', 'Test member', '', 1000, 200, 0, 0, 1200, 100, 0, 0, 0, 0, 0, 100, 1100, 1100, 5.3, 8];
$result = Import::normalize([Import::HEADERS, $sample], 2566);
check(count($result['records']) === 1 && !$result['errors'], 'historical Buddhist year supported');
check($result['records'][0]['member_no'] === '00001', 'leading zero member number preserved');
$reordered=$result['records'][0];$reordered['income']=array_reverse($reordered['income'],true);$reordered['deductions']=array_reverse($reordered['deductions'],true);
check(Import::sameFinancialRecord($result['records'][0],$reordered),'MySQL JSON object key ordering does not mark unchanged amounts as updated');
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
$legacyHeaders = Import::HEADERS; $legacyRow = $sample; unset($legacyHeaders[0], $legacyRow[0]);
$legacy=Import::normalize([['Annual dividends report'], $legacyHeaders, $legacyRow],2566);
check(!$legacy['errors'] && $legacy['records'][0]['source_row']===3, 'legacy title row and selected year supported with source row');
$duplicate=Import::normalize([Import::HEADERS,$sample,$sample],2566)['errors'][0];
check($duplicate['id_card']===$sample[1] && str_contains($duplicate['message'],'2'), 'duplicate report identifies national ID and original row');

// Legacy annual spreadsheets may have an extra index column (2567) or no index (2568).
// Header-name mapping must keep members and currency aligned regardless of position.
$year2567 = array_merge(['ลำดับ'], Import::HEADERS);
$row2567 = array_merge(['1'], $sample);
$row2567[1] = 2567;
$annual2567 = Import::normalize([$year2567, $row2567], 2567);
check(count($annual2567['records']) === 1 && !$annual2567['errors'], '2567 extra index column is ignored');
check($annual2567['records'][0]['member_no'] === '00001' && $annual2567['records'][0]['net'] === '1100.00', '2567 member and net amount remain aligned');

$row2568 = $sample;
$row2568[0] = 2568;
$annual2568 = Import::normalize([Import::HEADERS, $row2568], 2568);
check(count($annual2568['records']) === 1 && !$annual2568['errors'], '2568 standard header positions accepted');
check($annual2568['records'][0]['member_no'] === '00001' && $annual2568['records'][0]['net'] === '1100.00', '2568 values remain aligned');

foreach (['xlsx', 'csv'] as $type) {
    if (file_exists(__DIR__ . '/../public/templates/member-dividends-template.' . $type)) {
        try {
            $template = Import::parseFile(__DIR__ . '/../public/templates/member-dividends-template.' . $type, $type, 2566);
            check(!empty($template['errors']), "$type example template requires real member data");
        } catch (\App\Services\MemberDataException $e) {
            check(str_contains($e->getMessage(), 'ไฟล์ไม่มีรายการข้อมูล'), "$type blank staff template is rejected before import");
        }
    }
}

$source = file_get_contents(__DIR__ . '/../app/Services/DividendImportService.php');
$importMethod = substr($source, strpos($source, 'public static function import('));
check($importMethod !== false && $importMethod !== '', 'Annual importer source available for security regression');
check(!preg_match('/INSERT\\s+INTO\\s+(?:users|members|user_roles)\\b/i', $importMethod), 'Annual financial importer cannot provision user/member/role records');
check(str_contains($importMethod, 'ไม่พบสมาชิกที่ผูกบัญชีแล้ว'), 'Commit rejects absent Member Master mapping');
check(str_contains($source, 'ไม่พบทะเบียนสมาชิก กรุณานำเข้าทะเบียนสมาชิกหลัก'), 'Preview rejects absent Member Master');
check(!preg_match('/UPDATE\\s+(?:users|members|user_roles)\\b/i', $importMethod), 'Annual financial import cannot update user or member profile records');
