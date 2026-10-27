<?php

declare(strict_types=1);

namespace App\Services;

/**
 * PDPA Compliance & Privacy Protection Service
 *
 * Implements strict data masking and pseudonymization per Thailand's
 * Personal Data Protection Act (PDPA B.E. 2562 / 2019).
 */
class PdpaService
{
    /**
     * Mask 13-digit Thai Citizen ID.
     * Format requested: 192.xxxxxx171
     * Keeps first 3 digits, masks middle with 'x', keeps last 3 digits.
     */
    public static function maskIdCard(?string $idCard): string
    {
        if ($idCard === null || trim($idCard) === '') {
            return '-';
        }

        // Strip non-digits
        $digits = preg_replace('/\D/', '', $idCard);
        $len = strlen($digits);

        if ($len < 6) {
            return str_repeat('x', $len);
        }

        if ($len === 13) {
            $prefix = substr($digits, 0, 3);
            $suffix = substr($digits, -3);
            // 192.xxxxxx171 (3 digits + . + 6 or 7 x's + 3 digits)
            return "{$prefix}.xxxxxx{$suffix}";
        }

        // Generic fallback for any length >= 6
        $prefix = substr($digits, 0, 3);
        $suffix = substr($digits, -3);
        $maskedCount = max(4, $len - 6);
        return "{$prefix}." . str_repeat('x', $maskedCount) . "{$suffix}";
    }

    /**
     * Mask Thai Full Name per PDPA.
     * Format requested: นายสมxxx xxxมา
     * Preserves title/prefix, shows first 2-3 characters of first name + xxx,
     * and shows xxx + last 2-3 characters of last name.
     */
    public static function maskName(?string $fullName): string
    {
        if ($fullName === null || trim($fullName) === '') {
            return '-';
        }

        $cleaned = trim(preg_replace('/\s+/', ' ', $fullName));

        // List of common Thai prefixes / titles
        $prefixes = [
            'นายสาว' => 'นางสาว', // safety
            'นางสาว', 'นาง', 'นาย', 'ด.ช.', 'ด.ญ.', 'เด็กชาย', 'เด็กหญิง',
            'นพ.', 'พญ.', 'ทพ.', 'ทพญ.', 'ภก.', 'ภญ.',
            'ศ.', 'รศ.', 'ผศ.', 'ดร.', 'อ.',
            'ว่าที่ ร.ต.', 'ร.ต.', 'ร.ท.', 'ร.อ.', 'พ.ต.', 'พ.ท.', 'พ.อ.',
            'พล.ต.', 'พล.ท.', 'พล.อ.', 'จ.ส.อ.', 'จ.ส.ท.', 'จ.ส.ต.',
            'ส.อ.', 'ส.ท.', 'ส.ต.',
            'Mr.', 'Mrs.', 'Ms.', 'Miss', 'Dr.'
        ];

        $matchedPrefix = '';
        $nameWithoutPrefix = $cleaned;

        foreach ($prefixes as $p) {
            if (mb_strpos($cleaned, $p) === 0) {
                $matchedPrefix = $p;
                $nameWithoutPrefix = trim(mb_substr($cleaned, mb_strlen($p)));
                break;
            }
        }

        // Split into first and last name
        $parts = explode(' ', $nameWithoutPrefix, 2);
        $firstName = $parts[0] ?? '';
        $lastName = $parts[1] ?? '';

        // Mask first name: Keep first 2 characters + 'xxx'
        $maskedFirstName = '';
        if ($firstName !== '') {
            $fLen = mb_strlen($firstName);
            if ($fLen <= 2) {
                $maskedFirstName = $firstName . 'xxx';
            } else {
                $keepLen = min(2, $fLen);
                $maskedFirstName = mb_substr($firstName, 0, $keepLen) . 'xxx';
            }
        }

        // Mask last name: 'xxx' + last 2 characters
        $maskedLastName = '';
        if ($lastName !== '') {
            $lLen = mb_strlen($lastName);
            if ($lLen <= 2) {
                $maskedLastName = 'xxx' . $lastName;
            } else {
                $keepLen = min(2, $lLen);
                $maskedLastName = 'xxx' . mb_substr($lastName, -$keepLen);
            }
        }

        $combined = trim("{$matchedPrefix}{$maskedFirstName} {$maskedLastName}");
        return $combined !== '' ? $combined : '-';
    }

    /**
     * Mask Phone Number.
     * Format: 081-xxx-x234 or 081-xxxx-123
     */
    public static function maskPhone(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') {
            return '-';
        }

        $digits = preg_replace('/\D/', '', $phone);
        $len = strlen($digits);

        if ($len === 10) {
            return substr($digits, 0, 3) . '-xxx-' . substr($digits, 6);
        }

        if ($len === 9) {
            return substr($digits, 0, 2) . '-xxx-' . substr($digits, 5);
        }

        return substr($digits, 0, 3) . '-xxxx-' . substr($digits, -3);
    }

    /**
     * Mask IP Address for PDPA visitor tracking.
     * IPv4: 192.168.1.171 -> 192.168.xxx.171
     * IPv6: masks middle hextets
     */
    public static function maskIpAddress(?string $ip): string
    {
        if ($ip === null || trim($ip) === '' || $ip === '127.0.0.1' || $ip === '::1') {
            return '127.0.0.xxx';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                return "{$parts[0]}.{$parts[1]}.xxx.{$parts[3]}";
            }
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            if (count($parts) >= 4) {
                return $parts[0] . ':' . $parts[1] . ':xxxx:xxxx::' . end($parts);
            }
        }

        return 'xxx.xxx.xxx.xxx';
    }
}
