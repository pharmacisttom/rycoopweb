<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
use App\Services\DividendImportService;
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
try {
    $file = $argv[1] ?? __DIR__ . '/../storage/private/dividends.json';
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (in_array($extension, ['xlsx', 'csv'], true)) {
        $parsed = DividendImportService::parseFile($file, $extension, isset($argv[2]) ? (int)$argv[2] : null);
        if ($parsed['errors']) {
            foreach (array_slice($parsed['errors'], 0, 100) as $error) { fwrite(STDERR, "Row {$error['row']}: {$error['message']}\n"); }
            throw new RuntimeException('Validation failed; no records written.');
        }
        $records = $parsed['records'];
    } elseif ($extension === 'json') {
        $records = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $rows = [DividendImportService::HEADERS];
        foreach ($records as $record) {
            $values = ['ปีบัญชี' => $record['year'], 'เลขบัตรประชาชน' => $record['id_card'], 'เลขทะเบียนสมาชิก' => $record['member_no'], 'ชื่อนามสกุล' => $record['name'], 'สังกัด' => $record['department'], 'รวมรายการรับทั้งหมด' => $record['total_income'], 'รวมรายจ่าย' => $record['total_deductions'], 'ยอดเงินคงเหลือ' => $record['net'], 'เงินที่ได้รับ' => $record['received'], 'อัตราเงินปันผล' => $record['dividend_rate'], 'อัตราเงินเฉลี่ยคืน' => $record['refund_rate']] + $record['income'] + $record['deductions'];
            $rows[] = array_map(static fn($header) => $values[$header], DividendImportService::HEADERS);
        }
        if (DividendImportService::normalize($rows)['errors']) { throw new RuntimeException('Prepared JSON validation failed; no records written.'); }
    } else { throw new RuntimeException('Supported formats: JSON, XLSX, CSV'); }
    $count = DividendImportService::import($records);
    echo "Imported {$count} dividend records. Existing passwords preserved.\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
