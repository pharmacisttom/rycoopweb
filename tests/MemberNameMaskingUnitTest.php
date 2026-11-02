<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\MemberPiiMaskingService;

final class MemberNameMaskingUnitTest
{
    private static int $passed = 0;
    private static int $failed = 0;

    public static function run(): bool
    {
        echo "========================================================================\n";
        echo " RYCOOPWEB — MEMBER NAME PII MASKING UNIT TEST SUITE\n";
        echo "========================================================================\n";

        self::testApprovedExample();
        self::testStandardThaiTitles();
        self::testThaiGraphemeClustersWithCombiningMarks();
        self::testShortNames();
        self::testEmptyAndNullNames();
        self::testRepeatedMaskingIdempotence();
        self::testCitizenIdAndMemberNumberPreserved();

        echo "========================================================================\n";
        echo sprintf(" RESULTS: %d PASSED, %d FAILED\n", self::$passed, self::$failed);
        echo "========================================================================\n";

        return self::$failed === 0;
    }

    private static function testApprovedExample(): void
    {
        // Target Example: นายจัตุพล กันทะมูล => นายจัตุxx กันxxx
        $masked = MemberPiiMaskingService::maskFullName('นายจัตุพล กันทะมูล');
        self::assert($masked === 'นายจัตุxx กันxxx', "1. Approved example: 'นายจัตุพล กันทะมูล' => 'นายจัตุxx กันxxx'");

        $component = MemberPiiMaskingService::maskMemberName('นาย', 'จัตุพล', 'กันทะมูล');
        self::assert($component === 'นายจัตุxx กันxxx', "1. Component mask: ('นาย', 'จัตุพล', 'กันทะมูล') => 'นายจัตุxx กันxxx'");
    }

    private static function testStandardThaiTitles(): void
    {
        // Preserve Thai titles
        self::assert(MemberPiiMaskingService::maskFullName('นางสมพร ใจดีมาก') === 'นางสมพรxx ใจxxx', "2. Title 'นาง' preserved: 'นางสมพรxx ใจxxx'");
        self::assert(MemberPiiMaskingService::maskFullName('นางสาวกรวรรณ มีสุข') === 'นางสาวกรวรxx มีxxx', "2. Title 'นางสาว' preserved: 'นางสาวกรวรxx มีxxx'");
        self::assert(MemberPiiMaskingService::maskFullName('ภก.ทวีศักดิ์ วรเดช') === 'ภก.ทวีxx วรเxxx', "2. Title 'ภก.' preserved: 'ภก.ทวีxx วรเxxx'");
        self::assert(MemberPiiMaskingService::maskFullName('นพ.ประวิทย์ ชัยชนะ') === 'นพ.ประxx ชัยxxx', "2. Title 'นพ.' preserved: 'นพ.ประxx ชัยxxx'");
        self::assert(MemberPiiMaskingService::maskFullName('ด.ช.ปิติ ชูใจ') === 'ด.ช.ปิติxx ชูใxxx', "2. Title 'ด.ช.' preserved: 'ด.ช.ปิติxx ชูใxxx'");
    }

    private static function testThaiGraphemeClustersWithCombiningMarks(): void
    {
        // Grapheme clusters combining marks: จั (จ+ั), ตุ (ต+ุ)
        self::assert(MemberPiiMaskingService::maskFirstName('จัตุพล') === 'จัตุxx', "3. First name combining marks: 'จัตุพล' => 'จัตุxx'");
        self::assert(MemberPiiMaskingService::maskLastName('กันทะมูล') === 'กันxxx', "3. Surname combining marks: 'กันทะมูล' => 'กันxxx'");

        // Tone marks and upper vowels
        self::assert(MemberPiiMaskingService::maskFirstName('ยิ่งลักษณ์') === 'ยิ่งxx', "3. Tone mark and upper vowel: 'ยิ่งลักษณ์' => 'ยิ่งxx'");
        self::assert(MemberPiiMaskingService::maskLastName('ชินวัตร') === 'ชินxxx', "3. Consonant cluster: 'ชินวัตร' => 'ชินxxx'");
    }

    private static function testShortNames(): void
    {
        // Short first names (<= 4 chars)
        self::assert(MemberPiiMaskingService::maskFirstName('เอก') === 'เอกxx', "4. Short 3-char first name 'เอก' => 'เอกxx'");
        self::assert(MemberPiiMaskingService::maskFirstName('บี') === 'บีxx', "4. Short 2-char first name 'บี' => 'บีxx'");

        // Short surnames (<= 3 chars)
        self::assert(MemberPiiMaskingService::maskLastName('สุข') === 'สุขxxx', "4. Short 3-char surname 'สุข' => 'สุขxxx'");
        self::assert(MemberPiiMaskingService::maskLastName('ดี') === 'ดีxxx', "4. Short 2-char surname 'ดี' => 'ดีxxx'");
    }

    private static function testEmptyAndNullNames(): void
    {
        self::assert(MemberPiiMaskingService::maskFullName('') === '-', "5. Empty string returns '-'");
        self::assert(MemberPiiMaskingService::maskFullName(null) === '-', "5. Null returns '-'");
        self::assert(MemberPiiMaskingService::maskFullName('   ') === '-', "5. Whitespace-only returns '-'");
        self::assert(MemberPiiMaskingService::maskFirstName('') === '', "5. Empty first name returns empty string");
        self::assert(MemberPiiMaskingService::maskFirstName(null) === '', "5. Null first name returns empty string");
        self::assert(MemberPiiMaskingService::maskLastName('') === '', "5. Empty last name returns empty string");
        self::assert(MemberPiiMaskingService::maskLastName(null) === '', "5. Null last name returns empty string");
    }

    private static function testRepeatedMaskingIdempotence(): void
    {
        // Repeated masking must not add extra 'xx' or 'xxx'
        $first = MemberPiiMaskingService::maskFirstName('จัตุพล');
        self::assert($first === 'จัตุxx', "6. First pass: 'จัตุxx'");
        self::assert(MemberPiiMaskingService::maskFirstName($first) === 'จัตุxx', "6. Second pass: 'จัตุxx' (no double-masking)");

        $last = MemberPiiMaskingService::maskLastName('กันทะมูล');
        self::assert($last === 'กันxxx', "6. First pass: 'กันxxx'");
        self::assert(MemberPiiMaskingService::maskLastName($last) === 'กันxxx', "6. Second pass: 'กันxxx' (no double-masking)");

        $full = MemberPiiMaskingService::maskFullName('นายจัตุพล กันทะมูล');
        self::assert($full === 'นายจัตุxx กันxxx', "6. First pass full: 'นายจัตุxx กันxxx'");
        self::assert(MemberPiiMaskingService::maskFullName($full) === 'นายจัตุxx กันxxx', "6. Second pass full: 'นายจัตุxx กันxxx' (no double-masking)");
    }

    private static function testCitizenIdAndMemberNumberPreserved(): void
    {
        // Existing citizen ID and member-number masking preserved
        self::assert(MemberPiiMaskingService::maskCitizenId('3100600123456') === '3-****-*****-**-*', "7. Citizen ID format preserved: '3-****-*****-**-*'");
        self::assert(MemberPiiMaskingService::maskMemberNo('00042') === '00*42', "7. Member number format preserved: '00*42'");
        self::assert(MemberPiiMaskingService::maskMemberNo('02723') === '02*23', "7. Member 02723 format: '02*23'");
        self::assert(MemberPiiMaskingService::maskPhone('0812345678') === '081-XXX-5678', "7. Phone format preserved: '081-XXX-5678'");
    }

    private static function assert(bool $condition, string $label): void
    {
        if ($condition) {
            self::$passed++;
            echo " [PASS] {$label}\n";
        } else {
            self::$failed++;
            echo " [FAIL] {$label}\n";
        }
    }
}

exit(MemberNameMaskingUnitTest::run() ? 0 : 1);

