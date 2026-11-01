<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Centralized Server-Side PII Masking Service for Cooperative Members.
 *
 * Enforces PDPA data minimization across member portal APIs, views, and exports.
 *
 * Standard Formats:
 * - Full name: นายกรณ์ชัย สมประสงค์  => นายกร**** สม****
 * - Citizen ID: 3-****-*****-**-*
 * - Member number: 00*42
 * - Phone: 081-XXX-5678 (or 08X-XXX-1234)
 */
class MemberPiiMaskingService
{
    /**
     * Standard Thai honorifics and titles to preserve.
     */
    private const THAI_TITLES = [
        'นางสาว', 'นาง', 'นาย', 'ด.ช.', 'ด.ญ.', 'เด็กชาย', 'เด็กหญิง',
        'นพ.', 'พญ.', 'ทพ.', 'ทพญ.', 'เภสัชกร', 'ภก.', 'ภญ.',
        'ศ.ดร.', 'รศ.ดร.', 'ผศ.ดร.', 'ดร.', 'ศ.', 'รศ.', 'ผศ.', 'อาจารย์', 'อ.',
        'ว่าที่ ร.ต.', 'ร.ต.', 'ร.ท.', 'ร.อ.', 'พ.ต.', 'พ.ท.', 'พ.อ.',
        'พล.ต.', 'พล.ท.', 'พล.อ.', 'จ.ส.อ.', 'จ.ส.ท.', 'จ.ส.ต.',
        'ส.อ.', 'ส.ท.', 'ส.ต.',
        'Mr.', 'Mrs.', 'Ms.', 'Miss', 'Dr.'
    ];

    /**
     * Mask 13-digit Thai Citizen ID per privacy-preserving format.
     * Example: 3100600123456 => 3-****-*****-**-*
     */
    public static function maskCitizenId(?string $idCard): string
    {
        if ($idCard === null || trim($idCard) === '') {
            return '-';
        }

        $digits = preg_replace('/\D/', '', $idCard);
        $len = strlen($digits);

        if ($len === 13) {
            $d1 = substr($digits, 0, 1);
            return "{$d1}-****-*****-**-*";
        }

        if ($len > 4) {
            return substr($digits, 0, 1) . str_repeat('*', $len - 2) . substr($digits, -1);
        }

        return str_repeat('*', max(1, $len));
    }

    /**
     * Mask 5-digit member number, preserving leading zeros and revealing first 2 and last 2 digits.
     * Example: 00042 => 00*42
     */
    public static function maskMemberNo(?string $memberNo): string
    {
        if ($memberNo === null || trim($memberNo) === '') {
            return '-';
        }

        $clean = trim((string)$memberNo);
        $digits = preg_replace('/\D/', '', $clean);

        if ($digits === '') {
            return '-';
        }

        // Standard 5-digit format with leading zeros preserved
        $padded = str_pad($digits, 5, '0', STR_PAD_LEFT);
        $len = strlen($padded);

        if ($len === 5) {
            return substr($padded, 0, 2) . '*' . substr($padded, 3, 2);
        }

        if ($len >= 4) {
            return substr($padded, 0, 2) . '*' . substr($padded, -2);
        }

        return substr($padded, 0, 1) . '*';
    }

    /**
     * Mask first name, keeping first 2 UTF-8 characters followed by ****.
     * Example: กรณ์ชัย => กร****
     */
    public static function maskFirstName(?string $firstName): string
    {
        if ($firstName === null || trim($firstName) === '') {
            return '';
        }

        $clean = trim($firstName);
        $len = mb_strlen($clean, 'UTF-8');

        if ($len <= 2) {
            return mb_substr($clean, 0, 1, 'UTF-8') . '****';
        }

        return mb_substr($clean, 0, 2, 'UTF-8') . '****';
    }

    /**
     * Mask surname, keeping first 2 UTF-8 characters followed by ****.
     * Example: สมประสงค์ => สม****
     */
    public static function maskLastName(?string $lastName): string
    {
        if ($lastName === null || trim($lastName) === '') {
            return '';
        }

        $clean = trim($lastName);
        $len = mb_strlen($clean, 'UTF-8');

        if ($len <= 2) {
            return mb_substr($clean, 0, 1, 'UTF-8') . '****';
        }

        return mb_substr($clean, 0, 2, 'UTF-8') . '****';
    }

    /**
     * Mask full name from components (prefix, first name, last name).
     * Example: ('นาย', 'กรณ์ชัย', 'สมประสงค์') => นายกร**** สม****
     */
    public static function maskMemberName(?string $prefix, ?string $firstName, ?string $lastName): string
    {
        $prefix = trim((string)$prefix);
        $first = self::maskFirstName($firstName);
        $last = self::maskLastName($lastName);

        $name = trim("{$prefix}{$first} {$last}");
        return $name !== '' ? $name : '-';
    }

    /**
     * Mask single full name string, extracting title and masking first and last names.
     * Example: นายกรณ์ชัย สมประสงค์ => นายกร**** สม****
     */
    public static function maskFullName(?string $fullName): string
    {
        if ($fullName === null || trim($fullName) === '') {
            return '-';
        }

        $cleaned = trim(preg_replace('/\s+/', ' ', $fullName));

        $matchedPrefix = '';
        $nameWithoutPrefix = $cleaned;

        foreach (self::THAI_TITLES as $p) {
            if (mb_strpos($cleaned, $p) === 0) {
                $matchedPrefix = $p;
                $nameWithoutPrefix = trim(mb_substr($cleaned, mb_strlen($p, 'UTF-8'), null, 'UTF-8'));
                break;
            }
        }

        $parts = explode(' ', $nameWithoutPrefix, 2);
        $firstName = $parts[0] ?? '';
        $lastName = $parts[1] ?? '';

        $maskedFirst = self::maskFirstName($firstName);
        $maskedLast = self::maskLastName($lastName);

        $combined = trim("{$matchedPrefix}{$maskedFirst} {$maskedLast}");
        return $combined !== '' ? $combined : '-';
    }

    /**
     * Mask phone number.
     * Example: 0812345678 => 081-XXX-5678
     */
    public static function maskPhone(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') {
            return '-';
        }

        $digits = preg_replace('/\D/', '', $phone);
        $len = strlen($digits);

        if ($len === 10) {
            return substr($digits, 0, 3) . '-XXX-' . substr($digits, -4);
        }

        if ($len === 9) {
            return substr($digits, 0, 2) . '-XXX-' . substr($digits, -4);
        }

        if ($len > 4) {
            return substr($digits, 0, 2) . '-XXX-' . substr($digits, -2);
        }

        return '***-***-****';
    }

    /**
     * Sanitize member record array for frontend API transmission.
     * Strips sensitive raw PII and injects standardized masked fields.
     */
    public static function sanitizeMemberData(array $member): array
    {
        $rawPrefix = $member['prefix'] ?? '';
        $rawFirst = $member['first_name'] ?? '';
        $rawLast = $member['last_name'] ?? '';
        $rawIdCard = (string)($member['id_card'] ?? '');
        $rawMemberNo = (string)($member['member_no'] ?? '');
        $rawPhone = (string)($member['phone'] ?? '');

        $fullName = trim("{$rawPrefix}{$rawFirst} {$rawLast}");
        if ($fullName === '') {
            $fullName = (string)($member['name'] ?? '');
        }

        $maskedDisplay = self::maskFullName($fullName);

        $member['name'] = $maskedDisplay;
        $member['name_pdpa'] = $maskedDisplay;
        $member['display_name'] = $maskedDisplay;

        if ($rawFirst !== '') {
            $member['first_name'] = self::maskFirstName($rawFirst);
        }
        if ($rawLast !== '') {
            $member['last_name'] = self::maskLastName($rawLast);
        }
        if ($rawMemberNo !== '') {
            $member['member_no'] = self::maskMemberNo($rawMemberNo);
        }
        if ($rawPhone !== '') {
            $member['phone'] = self::maskPhone($rawPhone);
        }

        if ($rawIdCard !== '') {
            $member['id_card_pdpa'] = self::maskCitizenId($rawIdCard);
            $member['id_card_masked'] = $member['id_card_pdpa'];
        }

        // Strictly remove raw sensitive fields
        unset($member['id_card'], $member['password'], $member['raw_id_card'], $member['source_row']);

        if (array_key_exists('avatar', $member) && function_exists('resolve_media_url')) {
            $member['avatar'] = resolve_media_url($member['avatar']);
        }

        return $member;
    }
}
