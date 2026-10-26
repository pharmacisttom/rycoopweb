<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

class TwoFactorService
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a new 160-bit (32 character) or 80-bit (16 character) Base32 secret.
     */
    public static function generateSecret(int $length = 16): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_CHARS[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Format a Base32 secret into 4-character chunks for manual entry readability.
     * Example: "JBSWY3DPEHPK3PXP" -> "JBSW Y3DP EHPK 3PXP"
     */
    public static function formatSecret(string $secret): string
    {
        $clean = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        return trim(chunk_split($clean, 4, ' '));
    }

    /**
     * Encrypt a TOTP secret using AES-256-GCM before storing in database.
     */
    public static function encryptSecret(string $secret): string
    {
        $key = self::deriveKey();
        $iv = random_bytes(12); // Standard GCM 96-bit IV
        $tag = '';

        $cipher = openssl_encrypt(
            $secret,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($cipher === false) {
            throw new \RuntimeException('Failed to encrypt MFA secret.');
        }

        // Return base64 payload: IV (12) + TAG (16) + CIPHER
        return base64_encode($iv . $tag . $cipher);
    }

    /**
     * Decrypt an encrypted TOTP secret from database.
     * Backwards-compatible: if already a raw Base32 string (only A-Z2-7), returns as is.
     */
    public static function decryptSecret(string $stored): string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return '';
        }

        // Check if legacy unencrypted Base32 secret
        if (preg_match('/^[A-Z2-7]{16,64}$/', $stored)) {
            return $stored;
        }

        $decoded = base64_decode($stored, true);
        if ($decoded === false || strlen($decoded) < 28) {
            // Not a valid GCM ciphertext, return raw fallback
            return $stored;
        }

        $iv = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $cipher = substr($decoded, 28);
        $key = self::deriveKey();

        $plain = openssl_decrypt(
            $cipher,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plain === false) {
            Logger::error('Failed to decrypt MFA secret for user.');
            return '';
        }

        return $plain;
    }

    /**
     * Verify a 6-digit TOTP code against secret.
     * Includes OTP replay prevention by comparing with $lastUsedSlice.
     */
    public static function verifyCode(
        string $secretOrEncrypted,
        string $code,
        int $discrepancy = 1,
        ?int $lastUsedSlice = null,
        ?int &$matchedSlice = null
    ): bool {
        $secret = self::decryptSecret($secretOrEncrypted);
        if ($secret === '') {
            return false;
        }

        $cleanCode = trim($code);
        if (!preg_match('/^\d{6}$/', $cleanCode)) {
            return false;
        }

        $currentTimeSlice = (int) floor(time() / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $slice = $currentTimeSlice + $i;

            // Replay protection: cannot reuse the same time slice
            if ($lastUsedSlice !== null && $slice <= $lastUsedSlice) {
                continue;
            }

            $calculatedCode = self::calculateCode($secret, $slice);
            if (hash_equals($calculatedCode, $cleanCode)) {
                $matchedSlice = $slice;
                return true;
            }
        }

        return false;
    }

    /**
     * Generate standard otpauth:// URL.
     */
    public static function getOtpAuthUrl(string $email, string $secret, ?string $issuer = null): string
    {
        $issuer = $issuer ?: (string) config('security.two_factor.issuer', 'RayongCoop');
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($issuer),
            rawurlencode($email),
            $secret,
            rawurlencode($issuer)
        );
    }

    /**
     * Generate inline SVG string (no remote call, zero third-party disclosure).
     */
    public static function getQrCodeSvg(string $email, string $secret, ?string $issuer = null, int $size = 220): string
    {
        $url = self::getOtpAuthUrl($email, $secret, $issuer);
        return QrCodeGenerator::svg($url, $size);
    }

    /**
     * Generate Data URI (SVG base64) for frontend image display.
     */
    public static function getQrCodeDataUri(string $email, string $secret, ?string $issuer = null, int $size = 220): string
    {
        $url = self::getOtpAuthUrl($email, $secret, $issuer);
        return QrCodeGenerator::dataUri($url, $size);
    }

    /**
     * Generate single-use recovery codes (e.g. 8 codes formatted as XXXX-XXXX).
     * Hashes codes using password_hash and persists to user_mfa_recovery_codes.
     * Returns the array of plaintext codes for one-time display to user.
     */
    public static function generateRecoveryCodes(int $userId, int $count = 8): array
    {
        // 1. Invalidate previous recovery codes for this user
        Database::execute("DELETE FROM user_mfa_recovery_codes WHERE user_id = ?", [$userId]);

        $plainCodes = [];
        for ($i = 0; $i < $count; $i++) {
            // Generate clean 8-character uppercase alphanumeric code: XXXX-XXXX
            $part1 = strtoupper(bin2hex(random_bytes(2))); // 4 hex
            $part2 = strtoupper(bin2hex(random_bytes(2))); // 4 hex
            $code = "{$part1}-{$part2}";
            $plainCodes[] = $code;

            $hash = password_hash($code, PASSWORD_DEFAULT);
            Database::execute(
                "INSERT INTO user_mfa_recovery_codes (user_id, code_hash, created_at) VALUES (?, ?, NOW())",
                [$userId, $hash]
            );
        }

        return $plainCodes;
    }

    /**
     * Verify and consume a single-use recovery code.
     */
    public static function verifyRecoveryCode(int $userId, string $inputCode): bool
    {
        $clean = strtoupper(trim($inputCode));
        // Normalize: allow user to type with or without hyphen
        if (strlen($clean) === 8 && !str_contains($clean, '-')) {
            $clean = substr($clean, 0, 4) . '-' . substr($clean, 4);
        }

        $records = Database::query(
            "SELECT id, code_hash FROM user_mfa_recovery_codes WHERE user_id = ? AND used_at IS NULL",
            [$userId]
        );

        foreach ($records as $record) {
            if (password_verify($clean, $record['code_hash'])) {
                // Mark consumed immediately
                Database::execute(
                    "UPDATE user_mfa_recovery_codes SET used_at = NOW() WHERE id = ?",
                    [$record['id']]
                );
                Logger::auth("Single-use recovery code consumed for user ID: {$userId}");
                return true;
            }
        }

        return false;
    }

    /**
     * Count remaining unused recovery codes.
     */
    public static function getRecoveryCodesRemaining(int $userId): int
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM user_mfa_recovery_codes WHERE user_id = ? AND used_at IS NULL",
            [$userId]
        );
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    private static function deriveKey(): string
    {
        $keySource = (string) config('security.two_factor.encryption_key', '');
        if ($keySource === '') {
            $keySource = (string) config('app.key', 'rayongcoop-fallback-secret-key-32ch!');
        }
        return hash('sha256', $keySource, true); // 32 bytes for AES-256
    }

    private static function calculateCode(string $secret, int $timeSlice): string
    {
        $secretKey = self::base32Decode($secret);
        $time = chr(0) . chr(0) . chr(0) . chr(0) . pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $hashPart = substr($hmac, $offset, 4);
        $value = unpack('N', $hashPart)[1] & 0x7FFFFFFF;
        $modulo = 10 ** 6;
        return str_pad((string) ($value % $modulo), 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32) ?? '');
        $buffer = 0;
        $length = 0;
        $binary = '';

        for ($i = 0; $i < strlen($b32); $i++) {
            $position = strpos(self::BASE32_CHARS, $b32[$i]);
            if ($position === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $position;
            $length += 5;

            if ($length >= 8) {
                $length -= 8;
                $binary .= chr(($buffer >> $length) & 0xFF);
            }
        }

        return $binary;
    }
}
