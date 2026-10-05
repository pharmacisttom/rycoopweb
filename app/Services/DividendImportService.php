<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use RuntimeException;

class DividendImportService
{
    public const INCOME = ['เงินปันผล', 'เงินเฉลี่ยคืน', 'เงินของชำร่วย', 'เงินรางวัลสมาชิก'];
    public const DEDUCTIONS = ['สสธท.', 'กสธท.2', 'กสธท.3', 'กสธท.4', 'สส.ชสอ', 'กรมบังคับคดี'];
    public const HEADERS = ['ปีบัญชี', 'เลขบัตรประชาชน', 'เลขทะเบียนสมาชิก', 'ชื่อนามสกุล', 'สังกัด', 'เงินปันผล', 'เงินเฉลี่ยคืน', 'เงินของชำร่วย', 'เงินรางวัลสมาชิก', 'รวมรายการรับทั้งหมด', 'สสธท.', 'กสธท.2', 'กสธท.3', 'กสธท.4', 'สส.ชสอ', 'กรมบังคับคดี', 'รวมรายจ่าย', 'ยอดเงินคงเหลือ', 'เงินที่ได้รับ', 'อัตราเงินปันผล', 'อัตราเงินเฉลี่ยคืน'];

    public static function parseFile(string $path, string $extension, ?int $defaultYear = null): array
    {
        $rows = $extension === 'xlsx' ? self::xlsxRows($path) : self::csvRows($path);
        return self::normalize($rows, $defaultYear);
    }

    private static function csvRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) { throw new RuntimeException('ไม่สามารถอ่านไฟล์ได้'); }
        $rows = [];
        try {
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if (count($rows) >= 20000) { throw new RuntimeException('ไฟล์เกิน 20,000 แถว'); }
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0] ?? '');
                $rows[] = $row;
            }
        } finally { fclose($handle); }
        return $rows;
    }

    private static function xlsxRows(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) { throw new RuntimeException('เซิร์ฟเวอร์ต้องเปิด PHP zip หรือใช้ไฟล์ CSV UTF-8'); }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) { throw new RuntimeException('ไฟล์ XLSX ไม่ถูกต้อง'); }
        try {
            $size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $size += $zip->statIndex($i)['size'];
                if ($size > 50000000 || $zip->numFiles > 500) { throw new RuntimeException('ไฟล์ Excel มีขนาดใหญ่เกินกำหนด'); }
            }
            $read = static function (string $entry) use ($zip): \SimpleXMLElement {
                $text = $zip->getFromName($entry);
                if ($text === false || stripos($text, '<!DOCTYPE') !== false || stripos($text, '<!ENTITY') !== false) { throw new RuntimeException('โครงสร้าง Excel ไม่ถูกต้อง'); }
                $xml = @simplexml_load_string($text, \SimpleXMLElement::class, LIBXML_NONET);
                if ($xml === false) { throw new RuntimeException('ไม่สามารถอ่าน XML ใน Excel ได้'); }
                return $xml;
            };
            $strings = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                foreach ($read('xl/sharedStrings.xml')->xpath('//*[local-name()="si"]') as $item) {
                    $strings[] = implode('', array_map('strval', $item->xpath('.//*[local-name()="t"]')));
                }
            }
            $book = $read('xl/workbook.xml');
            $sheets = $book->xpath('//*[local-name()="sheet"]');
            if (!$sheets) { throw new RuntimeException('Excel ไม่มีชีตข้อมูล'); }
            $relation = (string)$sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $target = null;
            foreach ($read('xl/_rels/workbook.xml.rels')->xpath('//*[local-name()="Relationship"]') as $r) {
                if ((string)$r['Id'] === $relation && (string)$r['TargetMode'] !== 'External') { $target = (string)$r['Target']; }
            }
            if (!$target || str_contains($target, '..')) { throw new RuntimeException('ไม่พบชีตข้อมูล'); }
            $entry = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
            $rows = [];
            foreach ($read($entry)->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                if (count($rows) >= 20000) { throw new RuntimeException('ไฟล์เกิน 20,000 แถว'); }
                $values = [];
                foreach ($row->xpath('./*[local-name()="c"]') as $cell) {
                    preg_match('/^([A-Z]+)\d+$/', (string)$cell['r'], $match);
                    if (!$match) { throw new RuntimeException('ตำแหน่งเซลล์ไม่ถูกต้อง'); }
                    $column = 0;
                    foreach (str_split($match[1]) as $letter) { $column = $column * 26 + ord($letter) - 64; }
                    if ($column > 100) { throw new RuntimeException('ไฟล์มีคอลัมน์เกินกำหนด'); }
                    $v = $cell->xpath('./*[local-name()="v"]');
                    $value = isset($v[0]) ? (string)$v[0] : '';
                    if ((string)$cell['t'] === 's') { $value = $strings[(int)$value] ?? ''; }
                    if ((string)$cell['t'] === 'inlineStr') { $value = implode('', array_map('strval', $cell->xpath('.//*[local-name()="t"]'))); }
                    if ($cell->xpath('./*[local-name()="f"]') && $value === '') { throw new RuntimeException('มีสูตรที่ยังไม่ได้คำนวณ กรุณาเปิดและบันทึกไฟล์ใน Excel ก่อน'); }
                    $values[$column - 1] = $value;
                }
                $rows[] = $values;
            }
            return $rows;
        } finally { $zip->close(); }
    }

    public static function normalize(array $rows, ?int $defaultYear = null): array
    {
        $headers = null; $records = []; $errors = []; $seen = []; $identities = []; $memberNos = [];
        foreach ($rows as $i => $values) {
            if (!$headers) {
                $candidate = array_map(static fn($v) => trim((string)$v), $values);
                if (!in_array('เลขบัตรประชาชน', $candidate, true)) { continue; }
                $headers = $candidate;
                $required = array_diff(self::HEADERS, ['ปีบัญชี', 'สังกัด']);
                $missing = array_diff($required, $headers);
                if ($missing) { throw new RuntimeException('ขาดคอลัมน์: ' . implode(', ', $missing)); }
                if (count(array_filter($headers)) !== count(array_unique(array_filter($headers)))) { throw new RuntimeException('ชื่อคอลัมน์ซ้ำ'); }
                continue;
            }
            if (!array_filter($values, static fn($v) => trim((string)$v) !== '')) { continue; }
            $row = [];
            foreach ($headers as $column => $label) { $row[$label] = trim((string)($values[$column] ?? '')); }
            try {
                $id = $row['เลขบัตรประชาชน'];
                if (!preg_match('/^\d{13}$/D', $id) || $id === '0000000000000') { throw new RuntimeException('เลขบัตรประชาชนต้องเป็นข้อความ 13 หลัก และต้องแทนที่เลขตัวอย่างด้วยข้อมูลจริง'); }
                $memberNo = $row['เลขทะเบียนสมาชิก'];
                if (!preg_match('/^\d{1,20}$/D', $memberNo)) { throw new RuntimeException('เลขทะเบียนสมาชิกไม่ถูกต้อง'); }
                $memberNo = str_pad($memberNo, 5, '0', STR_PAD_LEFT);
                $year = $row['ปีบัญชี'] ?? (string)$defaultYear;
                if (!preg_match('/^\d{4}$/D', $year) || (int)$year < 2400 || (int)$year > 2800) { throw new RuntimeException('ปีบัญชีต้องเป็นปี พ.ศ. 4 หลัก'); }
                if ($defaultYear && (int)$year !== $defaultYear) { throw new RuntimeException('ปีบัญชีในไฟล์ไม่ตรงกับปีที่เลือก'); }
                $name = $row['ชื่อนามสกุล'];
                if ($name === '' || mb_strlen($name) > 100) { throw new RuntimeException('ชื่อนามสกุลต้องมีข้อมูลและไม่เกิน 100 ตัวอักษร'); }
                if (isset($seen[$year . ':' . $id])) { throw new RuntimeException('เลขบัตรประชาชนซ้ำในปีเดียวกัน'); }
                if (isset($identities[$id]) && $identities[$id] !== $memberNo) { throw new RuntimeException('เลขบัตรประชาชนเดียวกันมีเลขสมาชิกต่างกัน'); }
                if (isset($memberNos[$memberNo]) && $memberNos[$memberNo] !== $id) { throw new RuntimeException('เลขสมาชิกเดียวกันมีเลขบัตรประชาชนต่างกัน'); }
                $money = static function (string $label) use ($row): string {
                    $value = $row[$label] ?? '';
                    if ($value === '') { $value = '0'; }
                    if (!is_numeric($value) || !is_finite((float)$value) || abs((float)$value) > 99999999999) { throw new RuntimeException('จำนวนเงินไม่ถูกต้องในคอลัมน์ ' . $label); }
                    return number_format((float)$value, 2, '.', '');
                };
                $income = []; $deductions = [];
                foreach (self::INCOME as $label) { $income[$label] = $money($label); }
                foreach (self::DEDUCTIONS as $label) { $deductions[$label] = $money($label); }
                $total = $money('รวมรายการรับทั้งหมด'); $expense = $money('รวมรายจ่าย'); $net = $money('ยอดเงินคงเหลือ');
                $cents = static fn($v) => (int)round((float)$v * 100);
                if (abs(array_sum(array_map($cents, $income)) - $cents($total)) > 2 || abs(array_sum(array_map($cents, $deductions)) - $cents($expense)) > 2 || abs($cents($total) - $cents($expense) - $cents($net)) > 2) { throw new RuntimeException('ยอดรวมรายรับ รายหัก หรือยอดสุทธิไม่ตรง'); }
                $records[] = ['year' => (int)$year, 'id_card' => $id, 'member_no' => $memberNo, 'name' => $name, 'department' => $row['สังกัด'] ?? '', 'income' => $income, 'deductions' => $deductions, 'total_income' => $total, 'total_deductions' => $expense, 'net' => $net, 'received' => $money('เงินที่ได้รับ'), 'dividend_rate' => $money('อัตราเงินปันผล'), 'refund_rate' => $money('อัตราเงินเฉลี่ยคืน')];
                $seen[$year . ':' . $id] = true; $identities[$id] = $memberNo; $memberNos[$memberNo] = $id;
            } catch (RuntimeException $e) { $errors[] = ['row' => $i + 1, 'message' => $e->getMessage()]; }
        }
        if (!$headers) { throw new RuntimeException('ไม่พบหัวตารางข้อมูล'); }
        if (!$records && !$errors) { throw new RuntimeException('ไฟล์ไม่มีรายการข้อมูล'); }
        return ['records' => $records, 'errors' => $errors];
    }

    public static function import(array $records): int
    {
        $pdo = Database::connect(); $pdo->beginTransaction();
        try {
            $role = Database::first("SELECT id FROM roles WHERE slug = 'member'");
            if (!$role) { throw new RuntimeException('ไม่พบสิทธิ์สมาชิก'); }
            foreach ($records as $record) {
                $members = Database::query('SELECT * FROM members WHERE id_card = ? OR member_no = ? FOR UPDATE', [$record['id_card'], $record['member_no']]);
                if (count($members) > 1) { throw new RuntimeException('ข้อมูลสมาชิกในฐานข้อมูลซ้ำ นำเข้าไม่สำเร็จ'); }
                $member = $members[0] ?? null;
                if ($member && ($member['id_card'] !== $record['id_card'] || $member['member_no'] !== $record['member_no'])) { throw new RuntimeException('เลขบัตรประชาชนและเลขสมาชิกไม่ตรงกับฐานข้อมูล'); }
                $userId = $member['user_id'] ?? null;
                $uuid = static function () { $h = bin2hex(random_bytes(16)); return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-a'.substr($h,17,3).'-'.substr($h,20,12); };
                if (!$userId) {
                    if (Database::first('SELECT id FROM users WHERE username = ?', [$record['id_card']])) { throw new RuntimeException('ชื่อผู้ใช้ซ้ำกับบัญชีอื่น'); }
                    $userId = Database::insert('INSERT INTO users (uuid, name, username, email, password) VALUES (?, ?, ?, ?, ?)', [$uuid(), $record['name'], $record['id_card'], 'member-' . $record['member_no'] . '@members.invalid', password_hash($record['member_no'], PASSWORD_DEFAULT)]);
                    Database::execute('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, $role['id']]);
                } elseif (!Database::first('SELECT id FROM user_roles WHERE user_id = ? AND role_id = ?', [$userId, $role['id']])) { throw new RuntimeException('บัญชีที่ผูกกับสมาชิกไม่มีสิทธิ์สมาชิก'); }
                if (!$member) {
                    $memberId = Database::insert('INSERT INTO members (uuid, user_id, member_no, id_card, first_name, last_name, department) VALUES (?, ?, ?, ?, ?, ?, ?)', [$uuid(), $userId, $record['member_no'], $record['id_card'], $record['name'], '', $record['department']]);
                } else { $memberId = $member['id']; Database::execute('UPDATE members SET user_id = ? WHERE id = ?', [$userId, $memberId]); }
                Database::execute('INSERT INTO member_dividends (member_id, year, details_json) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE details_json = VALUES(details_json), imported_at = NOW()', [$memberId, $record['year'], json_encode($record, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
            }
            $pdo->commit(); return count($records);
        } catch (\Throwable $e) { $pdo->rollBack(); throw $e; }
    }
}
