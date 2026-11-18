<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;
use RuntimeException;

/** Versioned site navigation text, restricted to a stable allowlist of presentation keys. */
final class SiteNavigationCopyService
{
    private const PUBLISHED = 'site_navigation_copy_v1';
    private const DRAFT = 'site_navigation_copy_draft_v1';
    public const LABELS = [
        'about_group' => 'หัวข้อกลุ่ม: ข้อมูลและโครงสร้างองค์กร',
        'about_title' => 'ชื่อเมนู: ประวัติและวิสัยทัศน์',
        'about_desc' => 'คำอธิบาย: ประวัติและวิสัยทัศน์',
        'board_title' => 'ชื่อเมนู: คณะกรรมการ',
        'board_desc' => 'คำอธิบาย: คณะกรรมการ',
        'staff_title' => 'ชื่อเมนู: ฝ่ายจัดการ',
        'staff_desc' => 'คำอธิบาย: ฝ่ายจัดการ',
        'finance_group' => 'หัวข้อกลุ่ม: ความมั่นคงและธรรมาภิบาล',
        'finance_title' => 'ชื่อเมนู: ฐานะทางการเงิน',
        'finance_desc' => 'คำอธิบาย: ฐานะทางการเงิน',
        'dividends_title' => 'ชื่อเมนู: เงินปันผลเฉลี่ยคืน',
        'dividends_desc' => 'คำอธิบาย: เงินปันผลเฉลี่ยคืน',
        'documents_title' => 'ชื่อเมนู: ข้อบังคับและระเบียบ',
        'home_nav' => 'เมนูหลัก: หน้าแรก',
        'about_nav' => 'เมนูหลัก: เกี่ยวกับสหกรณ์',
        'finance_nav' => 'เมนูหลัก: บริการทางการเงิน',
        'welfare_nav' => 'เมนูหลัก: สวัสดิการ',
        'news_nav' => 'เมนูหลัก: ข่าวสารและเอกสาร',
        'member_nav' => 'ปุ่ม: ระบบสมาชิก',
        'documents_desc' => 'คำอธิบาย: ข้อบังคับและระเบียบ',
        'finance_highlight_desc' => 'คำอธิบายการเงินในการ์ดด้านข้าง',
    ];
    public static function defaults(): array
    {
        return [
            'about_group' => 'ข้อมูลและโครงสร้างองค์กร',
            'about_title' => 'ประวัติและวิสัยทัศน์',
            'about_desc' => 'ความเป็นมา พันธกิจ และค่านิยมหลักของสหกรณ์',
            'board_title' => 'คณะกรรมการดำเนินการ',
            'board_desc' => 'ทำเนียบคณะกรรมการดำเนินการ {term}',
            'staff_title' => 'ฝ่ายจัดการและเจ้าหน้าที่',
            'staff_desc' => 'บุคลากรฝ่ายจัดการและเจ้าหน้าที่สหกรณ์',
            'finance_group' => 'ความมั่นคงและธรรมาภิบาล',
            'finance_title' => 'ฐานะและสถิติทางการเงิน',
            'finance_desc' => 'ข้อมูลฐานะการเงินและผลการดำเนินงานของสหกรณ์',
            'dividends_title' => 'สถิติเงินปันผล-เฉลี่ยคืน',
            'dividends_desc' => 'ประวัติอัตราเงินปันผลและเงินเฉลี่ยคืนตามข้อมูลที่เผยแพร่',
            'documents_title' => 'ข้อบังคับและระเบียบสหกรณ์',
            'home_nav' => 'หน้าแรก',
            'about_nav' => 'เกี่ยวกับสหกรณ์',
            'finance_nav' => 'บริการทางการเงิน',
            'welfare_nav' => 'สวัสดิการ',
            'news_nav' => 'ข่าวสารและเอกสาร',
            'member_nav' => 'ระบบสมาชิก',
            'documents_desc' => 'ข้อบังคับ ระเบียบ และนโยบายที่เผยแพร่',
            'finance_highlight_desc' => 'ติดตามข้อมูลฐานะการเงินที่สหกรณ์เผยแพร่อย่างเป็นทางการ',
            'documents_desc' => 'ข้อบังคับ ระเบียบ และเอกสารที่สหกรณ์เผยแพร่',
        ];
    }
    private static function validate(array $data): array
    {
        $keys = array_keys(self::LABELS);
        if (array_keys($data) !== $keys) throw new InvalidArgumentException('รายการข้อความไม่ตรงกับเวอร์ชันปัจจุบัน');
        foreach ($data as $key => $value) {
            if (!is_string($value) || trim($value)==='' || mb_strlen($value)>240
                || preg_match('/[\x00-\x1F\x7F]/u', $value)) {
                throw new InvalidArgumentException('ข้อความไม่ถูกต้อง: '.$key);
            }
            $data[$key] = trim($value);
        }
        return $data;
    }
    private static function read(string $key, bool $lock = false): ?array
    {
        $sql = 'SELECT `value` FROM site_settings WHERE `key`=?'
            . ($lock && Database::connect()->getAttribute(\PDO::ATTR_DRIVER_NAME)==='mysql' ? ' FOR UPDATE' : '');
        $row = Database::first($sql, [$key]);
        if (!$row) return null;
        $value = json_decode((string) $row['value'], true);
        if (!is_array($value)) throw new RuntimeException('Invalid navigation copy');
        return self::validate($value);
    }
    public static function published(): array { return self::read(self::PUBLISHED) ?? self::defaults(); }
    public static function state(bool $lock = false): array
    {
        $published = self::read(self::PUBLISHED, $lock) ?? self::defaults();
        $draft = self::read(self::DRAFT, $lock);
        return ['published'=>$published, 'draft'=>$draft, 'labels'=>self::LABELS,
            'version'=>hash('sha256',json_encode([$published,$draft],JSON_THROW_ON_ERROR))];
    }
    private static function put(string $key, array $values): void
    {
        Database::execute(
            "INSERT INTO site_settings (`key`,`value`,`group`,is_public,updated_at)
             VALUES (?,?,'site_copy',0,NOW()) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`),updated_at=NOW()",
            [$key,json_encode($values,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]
        );
    }
    public static function draft(array $input): array
    {
        if (!is_array($input['values']??null)) throw new InvalidArgumentException('ไม่พบข้อความที่ต้องการบันทึก');
        $values = self::validate($input['values']);
        return Database::transaction(static function () use ($input,$values) {
            $old = self::state(true);
            if (!hash_equals($old['version'],(string)($input['version']??''))) throw new RuntimeException('VERSION_CONFLICT',409);
            self::put(self::DRAFT,$values);
            AuditService::log('site_copy','draft',null,$old['draft'],$values,required:true);
            return self::state();
        });
    }
    public static function publish(array $input): array
    {
        return Database::transaction(static function () use ($input) {
            $old = self::state(true);
            if (!hash_equals($old['version'],(string)($input['version']??''))) throw new RuntimeException('VERSION_CONFLICT',409);
            if ($old['draft']===null) throw new InvalidArgumentException('ยังไม่มีร่าง');
            self::put(self::PUBLISHED,$old['draft']);
            Database::execute('DELETE FROM site_settings WHERE `key`=?',[self::DRAFT]);
            AuditService::log('site_copy','publish',null,$old['published'],$old['draft'],required:true);
            return self::state();
        });
    }
}
