<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Services\MemberDataException as RuntimeException;

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
                $rowNumber=(int)$row['r'];
                if($rowNumber<1 || $rowNumber>20000 || isset($rows[$rowNumber-1])) { throw new RuntimeException('เลขแถว Excel ไม่ถูกต้องหรือเกิน 20,000 แถว'); }
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
                $rows[$rowNumber-1] = $values;
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
                if (isset($seen[$year . ':' . $id])) { throw new RuntimeException('เลขบัตรประชาชนซ้ำในปีเดียวกันกับแถว ' . $seen[$year . ':' . $id]); }
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
                $records[count($records)-1]['source_row'] = $i + 1;
                if (mb_strlen($row['สังกัด'] ?? '') > 150) { array_pop($records); throw new RuntimeException('สังกัดต้องไม่เกิน 150 ตัวอักษร'); }
                $seen[$year . ':' . $id] = $i + 1; $identities[$id] = $memberNo; $memberNos[$memberNo] = $id;
            } catch (RuntimeException $e) { $errors[] = ['row' => $i + 1, 'id_card' => $row['เลขบัตรประชาชน'] ?? '', 'member_no' => $row['เลขทะเบียนสมาชิก'] ?? '', 'message' => $e->getMessage()]; }
        }
        if (!$headers) { throw new RuntimeException('ไม่พบหัวตารางข้อมูล'); }
        if (!$records && !$errors) { throw new RuntimeException('ไฟล์ไม่มีรายการข้อมูล'); }
        return ['records' => $records, 'errors' => $errors];
    }

    public static function snapshot(array $record): array
    {
        $members=Database::query('SELECT id,user_id,member_no,id_card,prefix,first_name,last_name,department,phone,email,address,status FROM members WHERE id_card=? OR member_no=?',[$record['id_card'],$record['member_no']]);
        if(count($members)>1) throw new RuntimeException('สมาชิกในฐานข้อมูลซ้ำ ต้องแก้ไขข้อมูลก่อนนำเข้า');
        $member=$members[0]??null;
        if($member && ($member['id_card']!==$record['id_card'] || $member['member_no']!==$record['member_no'])) throw new RuntimeException('เลขบัตรประชาชนและเลขสมาชิกไม่ตรงกับฐานข้อมูล');
        if($member && $member['user_id'] && !Database::first("SELECT ur.id FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.slug='member'",[$member['user_id']])) throw new RuntimeException('บัญชีที่ผูกกับสมาชิกไม่มีสิทธิ์สมาชิก');
        if((!$member || !$member['user_id']) && Database::first('SELECT id FROM users WHERE username=?',[$record['id_card']])) throw new RuntimeException('ชื่อผู้ใช้ซ้ำกับบัญชีอื่น');
        $row=$member?Database::first('SELECT details_json FROM member_dividends WHERE member_id=? AND year=?',[$member['id'],$record['year']]):null;
        return ['member'=>$member,'record'=>$row?json_decode($row['details_json'],true,512,JSON_THROW_ON_ERROR):null];
    }
    public static function databasePreview(array $records, bool $updateProfiles = false): array
    {
        $counts=['created'=>0,'updated'=>0,'unchanged'=>0,'new_members'=>0,'profile_updates'=>0];$snapshots=[];$errors=[];$sample=[];
        foreach($records as $i=>$record) {
            try {
                $snapshot=self::snapshot($record);$old=$snapshot['record'];
                if($updateProfiles && $snapshot['member']) {
                    $member=$snapshot['member'];
                    if(Database::first("SELECT ur.id FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.slug<>'member'",[$member['user_id']]))throw new RuntimeException('ไม่สามารถแก้ชื่อบัญชีเจ้าหน้าที่ผ่านไฟล์สมาชิก');
                    if(($member['prefix']??'')!=='' || $member['first_name']!==$record['name'] || ($member['last_name']??'')!=='' || ($member['department']??'')!==$record['department'])$counts['profile_updates']++;
                }
                $action=$old===null?'created':(self::sameFinancialRecord($old,$record)?'unchanged':'updated');
                $counts[$action]++;if(!$snapshot['member'])$counts['new_members']++;
                $snapshots[$record['year'].':'.$record['id_card']]=MemberAdminService::version($snapshot);
                if(count($sample)<20)$sample[]=['member_no'=>$record['member_no'],'name'=>$record['name'],'year'=>$record['year'],'net'=>$record['net'],'previous_net'=>$old['net']??null,'action'=>$action];
            } catch(RuntimeException $e) {$errors[]=['row'=>$record['source_row']??$i+1,'id_card'=>$record['id_card'],'member_no'=>$record['member_no'],'message'=>$e->getMessage()];}
        }
        return compact('counts','snapshots','errors','sample');
    }
    public static function sameFinancialRecord(array $a,array $b): bool
    {
        foreach(['income','deductions'] as $field) {
            $left=$a[$field]??null;$right=$b[$field]??null;
            if(!is_array($left)||!is_array($right))return false;
            ksort($left);ksort($right);if($left!==$right)return false;
        }
        foreach(['total_income','total_deductions','net','received','dividend_rate','refund_rate'] as $field)if(($a[$field]??null)!==($b[$field]??null))return false;
        return true;
    }
    public static function import(array $records, array $options = []): int
    {
        $pdo = Database::connect(); $pdo->beginTransaction();
        try {
            $role = Database::first("SELECT id FROM roles WHERE slug = 'member'");
            if (!$role) { throw new RuntimeException('ไม่พบสิทธิ์สมาชิก'); }
            $counts=['created'=>0,'updated'=>0,'unchanged'=>0];$netTotal=0;
            foreach ($records as $record) {
                $members = Database::query('SELECT * FROM members WHERE id_card = ? OR member_no = ? FOR UPDATE', [$record['id_card'], $record['member_no']]);
                if (count($members) > 1) { throw new RuntimeException('ข้อมูลสมาชิกในฐานข้อมูลซ้ำ นำเข้าไม่สำเร็จ'); }
                $member = $members[0] ?? null;
                if ($member && ($member['id_card'] !== $record['id_card'] || $member['member_no'] !== $record['member_no'])) { throw new RuntimeException('เลขบัตรประชาชนและเลขสมาชิกไม่ตรงกับฐานข้อมูล'); }
                $oldRow=$member?Database::first('SELECT details_json FROM member_dividends WHERE member_id=? AND year=? FOR UPDATE',[$member['id'],$record['year']]):null;
                $oldRecord=$oldRow?json_decode($oldRow['details_json'],true,512,JSON_THROW_ON_ERROR):null;
                if(isset($options['snapshots'])) {
                    $snapshot=['member'=>$member?array_intersect_key($member,array_flip(['id','member_no','id_card','user_id'])):null,'record'=>$oldRecord];
                    // Keep snapshot field order deterministic, matching the preview query.
                    if($member)$snapshot['member']=array_combine(['id','user_id','member_no','id_card','prefix','first_name','last_name','department','phone','email','address','status'],array_map(static fn($key)=>$member[$key],['id','user_id','member_no','id_card','prefix','first_name','last_name','department','phone','email','address','status']));
                    $expected=$options['snapshots'][$record['year'].':'.$record['id_card']]??'';
                    if(!hash_equals($expected,MemberAdminService::version($snapshot)))throw new RuntimeException('ข้อมูลเปลี่ยนหลังตรวจสอบไฟล์ กรุณาตรวจสอบใหม่ก่อนนำเข้า');
                }
                $userId = $member['user_id'] ?? null;
                $uuid = static function () { $h = bin2hex(random_bytes(16)); return substr($h,0,8).'-'.substr($h,8,4).'-4'.substr($h,13,3).'-a'.substr($h,17,3).'-'.substr($h,20,12); };
                if (!$userId) {
                    if (Database::first('SELECT id FROM users WHERE username = ?', [$record['id_card']])) { throw new RuntimeException('ชื่อผู้ใช้ซ้ำกับบัญชีอื่น'); }
                    $accountUuid = $uuid();
                    $userId = Database::insert('INSERT INTO users (uuid, name, username, email, password, status) VALUES (?, ?, ?, ?, ?, ?)', [$accountUuid, $record['name'], $record['id_card'], 'member-' . $accountUuid . '@members.invalid', password_hash($record['member_no'], PASSWORD_DEFAULT), !$member || $member['status']==='active' ? 'active' : 'suspended']);
                    Database::execute('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, $role['id']]);
                } elseif (!Database::first('SELECT id FROM user_roles WHERE user_id = ? AND role_id = ?', [$userId, $role['id']])) { throw new RuntimeException('บัญชีที่ผูกกับสมาชิกไม่มีสิทธิ์สมาชิก'); }
                if (!$member) {
                    $memberId = Database::insert('INSERT INTO members (uuid, user_id, member_no, id_card, first_name, last_name, department) VALUES (?, ?, ?, ?, ?, ?, ?)', [$uuid(), $userId, $record['member_no'], $record['id_card'], $record['name'], '', $record['department']]);
                } else { $memberId = $member['id']; Database::execute('UPDATE members SET user_id = ? WHERE id = ?', [$userId, $memberId]); }
                if(!empty($options['update_profiles']) && $member) {
                    if(Database::first("SELECT ur.id FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.slug<>'member'",[$userId]))throw new RuntimeException('ไม่สามารถแก้ชื่อบัญชีเจ้าหน้าที่ผ่านไฟล์สมาชิก');
                    $before=MemberAdminService::profile((int)$memberId,true);
                    $after=$before;$after['prefix']='';$after['first_name']=$record['name'];$after['last_name']='';$after['department']=$record['department'];
                    Database::execute('UPDATE members SET prefix=?,first_name=?,last_name=?,department=? WHERE id=?',['',$record['name'],'',$record['department'],$memberId]);
                    Database::execute('UPDATE users SET name=? WHERE id=?',[$record['name'],$userId]);
                    if($before!==$after)MemberAdminService::recordChange((int)$memberId,'import_profile','อัปเดตจากไฟล์นำเข้า',$before,$after);
                }
                $action=$oldRecord===null?'created':(self::sameFinancialRecord($oldRecord,$record)?'unchanged':'updated');$counts[$action]++;
                $netTotal+=MemberAdminService::cents($record['net']);
                if($action==='unchanged')continue;
                Database::execute('INSERT INTO member_dividends (member_id, year, details_json) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE details_json = VALUES(details_json), imported_at = NOW()', [$memberId, $record['year'], json_encode($record, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                MemberAdminService::recordChange((int)$memberId,'dividend_import','นำเข้าไฟล์: '.($options['source_name']??'CLI'),$oldRecord,$record);
            }
            $years=array_unique(array_column($records,'year'));
            Database::execute('INSERT INTO member_import_runs (user_id,year,source_name,record_count,created_count,updated_count,unchanged_count,net_total) VALUES (?,?,?,?,?,?,?,?)',[PHP_SAPI==='cli'?null:\App\Core\Auth::id(),count($years)===1?reset($years):null,mb_substr($options['source_name']??'CLI',0,255),count($records),$counts['created'],$counts['updated'],$counts['unchanged'],MemberAdminService::amount($netTotal)]);
            $pdo->commit(); return count($records);
        } catch (\Throwable $e) { $pdo->rollBack(); throw $e; }
    }
}
