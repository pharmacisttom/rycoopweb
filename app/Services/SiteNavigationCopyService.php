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
        'finance_deposit_group' => 'หัวข้อกลุ่มเงินฝาก',
        'deposit_savings_title' => 'ชื่อเมนูเงินฝากออมทรัพย์',
        'deposit_savings_desc' => 'คำอธิบายเงินฝากออมทรัพย์',
        'deposit_rates_title' => 'ชื่อเมนูตารางดอกเบี้ยเงินฝาก',
        'deposit_rates_desc' => 'คำอธิบายตารางดอกเบี้ยเงินฝาก',
        'dividend_estimator_title' => 'ชื่อเมนูประมาณการเงินปันผล',
        'dividend_estimator_desc' => 'คำอธิบายประมาณการเงินปันผล',
        'loan_group' => 'หัวข้อกลุ่มสินเชื่อ',
        'emergency_title' => 'ชื่อเมนูเงินกู้ฉุกเฉิน',
        'emergency_desc' => 'คำอธิบายเงินกู้ฉุกเฉิน',
        'ordinary_title' => 'ชื่อเมนูเงินกู้สามัญ',
        'ordinary_desc' => 'คำอธิบายเงินกู้สามัญ',
        'housing_title' => 'ชื่อเมนูเงินกู้เคหะ',
        'housing_desc' => 'คำอธิบายเงินกู้เคหะ',
        'loan_apply_title' => 'ชื่อเมนูคำขอกู้เงิน',
        'loan_apply_desc' => 'คำอธิบายคำขอกู้เงิน',
        'loan_checklist_title' => 'ชื่อเมนูเช็คเอกสาร',
        'loan_checklist_desc' => 'คำอธิบายเช็คเอกสาร',
        'welfare_fund_group' => 'หัวข้อกลุ่มกองทุนสวัสดิการ',
        'welfare_six_title' => 'ชื่อเมนูสวัสดิการ 6 ประเภท',
        'welfare_six_desc' => 'คำอธิบายสวัสดิการ 6 ประเภท',
        'welfare_education_title' => 'ชื่อเมนูทุนการศึกษา',
        'welfare_education_desc' => 'คำอธิบายทุนการศึกษา',
        'welfare_medical_title' => 'ชื่อเมนูสวัสดิการเจ็บป่วย',
        'welfare_medical_desc' => 'คำอธิบายสวัสดิการเจ็บป่วย',
        'welfare_protection_group' => 'หัวข้อกลุ่มสวัสดิการคุ้มครอง',
        'welfare_senior_title' => 'ชื่อเมนูผู้สูงอายุ',
        'welfare_senior_desc' => 'คำอธิบายผู้สูงอายุ',
        'welfare_debt_title' => 'ชื่อเมนูคุ้มครองหนี้',
        'welfare_debt_desc' => 'คำอธิบายคุ้มครองหนี้',
        'welfare_forms_title' => 'ชื่อเมนูแบบฟอร์มสวัสดิการ',
        'welfare_forms_desc' => 'คำอธิบายแบบฟอร์มสวัสดิการ',
        'news_group' => 'หัวข้อกลุ่มข่าวสาร',
        'news_activity_title' => 'ชื่อเมนูข่าวสารกิจกรรม',
        'news_activity_desc' => 'คำอธิบายข่าวสารกิจกรรม',
        'announcement_title' => 'ชื่อเมนูประกาศ',
        'announcement_desc' => 'คำอธิบายประกาศ',
        'calendar_title' => 'ชื่อเมนูปฏิทินกิจกรรม',
        'calendar_desc' => 'คำอธิบายปฏิทินกิจกรรม',
        'docs_services_group' => 'หัวข้อกลุ่มเอกสารดิจิทัล',
        'forms_title' => 'ชื่อเมนูดาวน์โหลดแบบฟอร์ม',
        'forms_desc' => 'คำอธิบายดาวน์โหลดแบบฟอร์ม',
        'eservices_title' => 'ชื่อเมนู e-Services',
        'eservices_desc' => 'คำอธิบาย e-Services',
        'receipt_title' => 'ชื่อเมนูตรวจสอบใบเสร็จ',
        'receipt_desc' => 'คำอธิบายตรวจสอบใบเสร็จ',
        'finance_report_button' => 'ปุ่มดูรายงานการเงิน',
        'calc_button' => 'ปุ่มเครื่องคำนวณ',
        'welfare_button' => 'ปุ่มสวัสดิการ',
        'docs_button' => 'ปุ่มเอกสาร',
        'home_hero_badge' => 'หน้าแรก: ข้อความป้าย Hero',
        'home_rates_title' => 'หน้าแรก: หัวข้ออัตราดอกเบี้ย',
        'home_news_title' => 'หน้าแรก: หัวข้อข่าวสาร',
        'home_loans_title' => 'หน้าแรก: หัวข้อผลิตภัณฑ์สินเชื่อ',
        'home_loans_desc' => 'หน้าแรก: คำอธิบายผลิตภัณฑ์สินเชื่อ',
        'home_welfare_title' => 'หน้าแรก: หัวข้อสวัสดิการ',
        'docs_page_title' => 'ดาวน์โหลด: หัวข้อหน้าเอกสาร',
        'docs_page_desc' => 'ดาวน์โหลด: คำอธิบายหน้าเอกสาร',
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
            'finance_deposit_group' => 'เงินฝากและผลตอบแทน',
            'deposit_savings_title' => 'เงินฝากออมทรัพย์ & ประจำ',
            'deposit_savings_desc' => 'ดอกเบี้ยสูง รับดอกเบี้ยรายเดือนและรายปี',
            'deposit_rates_title' => 'ตารางอัตราดอกเบี้ยเงินฝาก',
            'deposit_rates_desc' => 'อัปเดตอัตราดอกเบี้ยเงินฝากประจำและออมทรัพย์ล่าสุด',
            'dividend_estimator_title' => 'ประมาณการเงินปันผล-เฉลี่ยคืน',
            'dividend_estimator_desc' => 'คำนวณผลตอบแทนหุ้นและเงินเฉลี่ยคืนสิ้นปีล่วงหน้า',
            'loan_group' => 'ผลิตภัณฑ์สินเชื่อ & เงินกู้',
            'emergency_title' => 'เงินกู้เพื่อเหตุฉุกเฉิน',
            'emergency_desc' => 'วงเงินตามความจำเป็น ไม่ต้องมีผู้ค้ำประกัน',
            'ordinary_title' => 'เงินกู้สามัญ & สามัญศึกษา',
            'ordinary_desc' => 'เพื่อการลงทุน สวัสดิการ และการศึกษาต่อ',
            'housing_title' => 'เงินกู้พิเศษเพื่อเคหะ',
            'housing_desc' => 'ซื้อที่อยู่อาศัย ปลูกสร้าง หรือไถ่ถอนจำนอง',
            'loan_apply_title' => 'ยื่นคำขอกู้เงินออนไลน์ & ติดตามสถานะ',
            'loan_apply_desc' => 'ยื่นกู้ฉุกเฉิน กู้สามัญ และติดตามผลการอนุมัติแบบเรียลไทม์',
            'loan_checklist_title' => 'เช็คความพร้อมและเอกสารขอกู้',
            'loan_checklist_desc' => 'ตรวจสอบคุณสมบัติและรายการเอกสารที่ต้องใช้',
            'welfare_fund_group' => 'กองทุนสวัสดิการสมาชิก',
            'welfare_six_title' => 'สวัสดิการสงเคราะห์ 6 ประเภท',
            'welfare_six_desc' => 'ดูแลสมาชิกตั้งแต่แรกเข้า ตลอดจนถึงวัยเกษียณ',
            'welfare_education_title' => 'ทุนการศึกษาบุตรสมาชิก',
            'welfare_education_desc' => 'สนับสนุนการศึกษาตั้งแต่ระดับประถมจนถึงปริญญาตรี',
            'welfare_medical_title' => 'สวัสดิการรักษาพยาบาล/เจ็บป่วย',
            'welfare_medical_desc' => 'เงินช่วยเหลือเมื่อเข้ารับการรักษาตัวในโรงพยาบาล',
            'welfare_protection_group' => 'สวัสดิการคุ้มครองและเกียรติยศ',
            'welfare_senior_title' => 'สวัสดิการสมาชิกผู้สูงอายุ',
            'welfare_senior_desc' => 'บำเหน็จเกียรติยศและเงินขวัญถุงแด่สมาชิกอาวุโส',
            'welfare_debt_title' => 'คุ้มครองหนี้ & ฌาปนกิจ (สสธท.)',
            'welfare_debt_desc' => 'ปกป้องครอบครัวและทายาท วงเงินสงเคราะห์สูงสุด',
            'welfare_forms_title' => 'ดาวน์โหลดฟอร์มขอรับสวัสดิการ',
            'welfare_forms_desc' => 'แบบคำขอและเอกสารแนบเพื่อยื่นขอสวัสดิการ',
            'news_group' => 'ข่าวสารและกิจกรรมสหกรณ์',
            'news_activity_title' => 'ข่าวประชาสัมพันธ์ & กิจกรรม',
            'news_activity_desc' => 'ข่าวสารโครงการ สัมมนา และภาพกิจกรรมสหกรณ์',
            'announcement_title' => 'ประกาศทางการสหกรณ์',
            'announcement_desc' => 'ประกาศอัตราดอกเบี้ย วันหยุด และมติที่ประชุม',
            'calendar_title' => 'ปฏิทินกิจกรรมและการประชุม',
            'calendar_desc' => 'กำหนดการประชุมใหญ่สามัญและวันจ่ายเงินปันผล',
            'docs_services_group' => 'เอกสารและศูนย์บริการดิจิทัล',
            'forms_title' => 'ดาวน์โหลดแบบฟอร์ม & รายงาน',
            'forms_desc' => 'คำขอกู้เงิน สมัครสมาชิก และรายงานประจำปี',
            'eservices_title' => 'ศูนย์บริการออนไลน์ e-Services',
            'eservices_desc' => 'ยื่นคำขอออนไลน์ ปรับค่าหุ้น ขอหนังสือรับรองภาษี',
            'receipt_title' => 'ตรวจสอบใบเสร็จ e-Receipt',
            'receipt_desc' => 'ตรวจสอบความถูกต้องและลายมือชื่อดิจิทัล',
            'finance_report_button' => 'ดูรายงานฐานะการเงิน',
            'calc_button' => 'เปิดโปรแกรมคำนวณ',
            'welfare_button' => 'ดูสวัสดิการทั้งหมด',
            'docs_button' => 'ไปที่คลังเอกสาร',
            'home_hero_badge' => 'ยินดีต้อนรับสู่ระบบสหกรณ์ดิจิทัล',
            'home_rates_title' => 'อัตราดอกเบี้ยเด่นวันนี้',
            'home_news_title' => 'ข่าวสารล่าสุด',
            'home_loans_title' => 'สินเชื่อเพื่อบุคลากรสาธารณสุข',
            'home_loans_desc' => 'ตรวจสอบผลิตภัณฑ์เงินกู้ที่สหกรณ์เผยแพร่แล้ว',
            'home_welfare_title' => 'ดูแลสมาชิกและครอบครัวในทุกช่วงชีวิต',
            'docs_page_title' => 'เอกสารและแบบฟอร์ม',
            'docs_page_desc' => 'ดาวน์โหลดแบบฟอร์มคำขอ ระเบียบ ข้อบังคับ และรายงานประจำปีของสหกรณ์',
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
        // Add new approved copy keys without overwriting published legacy values.
        // Reject unknown fields so a draft cannot silently define arbitrary keys.
        foreach (array_keys($value) as $field) {
            if (!array_key_exists($field, self::LABELS)) throw new RuntimeException('Unknown navigation field');
        }
        return self::validate(array_replace(self::defaults(), $value));
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
