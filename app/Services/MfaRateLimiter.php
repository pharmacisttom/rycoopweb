<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use PDO;

class MfaRateLimiter
{
    public const DEFAULT_MAX_ATTEMPTS = 5;
    public const DEFAULT_DECAY_SECONDS = 300; // 5 minutes

    /**
     * Determine if a user or IP address has exceeded the MFA attempt threshold.
     * Enforces fail-closed security: if the database query fails, access is denied (returns true).
     */
    public static function isRateLimited(
        int $userId,
        string $ip,
        ?int $maxAttempts = null,
        ?int $decaySeconds = null
    ): bool {
        $maxAttempts ??= (int) config('security.two_factor.rate_limit.max_attempts', self::DEFAULT_MAX_ATTEMPTS);
        $decaySeconds ??= (int) config('security.two_factor.rate_limit.decay_seconds', self::DEFAULT_DECAY_SECONDS);
        $normalizedIp = self::normalizeIp($ip);
        $userKey = "mfa:user:{$userId}";
        $ipKey = 'mfa:ip';

        try {
            $driver = Database::connect()->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'mysql') {
                $rows = Database::query(
                    'SELECT rate_key, ip_address, attempts,
                            TIMESTAMPDIFF(SECOND, window_started_at, UTC_TIMESTAMP()) AS elapsed_seconds
                     FROM rate_limit_attempts
                     WHERE (rate_key = ? AND ip_address = "0.0.0.0")
                        OR (rate_key = ? AND ip_address = ?)',
                    [$userKey, $ipKey, $normalizedIp]
                );
            } else {
                // SQLite fallback for testing environments
                $rows = Database::query(
                    'SELECT rate_key, ip_address, attempts,
                            (strftime("%s", "now") - strftime("%s", window_started_at)) AS elapsed_seconds,
                            window_started_at
                     FROM rate_limit_attempts
                     WHERE (rate_key = ? AND ip_address = "0.0.0.0")
                        OR (rate_key = ? AND ip_address = ?)',
                    [$userKey, $ipKey, $normalizedIp]
                );
            }

            foreach ($rows as $row) {
                $elapsed = isset($row['elapsed_seconds'])
                    ? (int) $row['elapsed_seconds']
                    : (time() - strtotime((string) $row['window_started_at']));
                $attempts = (int) ($row['attempts'] ?? 0);

                if ($elapsed < $decaySeconds && $attempts >= $maxAttempts) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            Logger::error('MFA RateLimiter check failed: ' . $e->getMessage());
            // Fail-closed: block verification attempts on database failure to prevent brute-force
            return true;
        }
    }

    /**
     * Record a failed MFA verification attempt atomically.
     * Upserts counters for both the specific user and the client IP address.
     */
    public static function recordFailedAttempt(
        int $userId,
        string $ip,
        ?int $decaySeconds = null
    ): void {
        $decaySeconds ??= (int) config('security.two_factor.rate_limit.decay_seconds', self::DEFAULT_DECAY_SECONDS);
        $normalizedIp = self::normalizeIp($ip);
        $userKey = "mfa:user:{$userId}";
        $ipKey = 'mfa:ip';

        try {
            $driver = Database::connect()->getAttribute(PDO::ATTR_DRIVER_NAME);

            // Record User-level attempt
            self::incrementAttempt($userKey, '0.0.0.0', $decaySeconds, $driver);

            // Record IP-level attempt
            if ($normalizedIp !== '0.0.0.0') {
                self::incrementAttempt($ipKey, $normalizedIp, $decaySeconds, $driver);
            }
        } catch (\Throwable $e) {
            Logger::error('MFA RateLimiter record failed: ' . $e->getMessage());
        }
    }

    /**
     * Clear active rate limit attempts upon successful MFA verification or enrollment.
     */
    public static function clear(int $userId, string $ip): void
    {
        $normalizedIp = self::normalizeIp($ip);
        $userKey = "mfa:user:{$userId}";
        $ipKey = 'mfa:ip';

        try {
            Database::execute(
                'DELETE FROM rate_limit_attempts
                 WHERE (rate_key = ? AND ip_address = "0.0.0.0")
                    OR (rate_key = ? AND ip_address = ?)',
                [$userKey, $ipKey, $normalizedIp]
            );
        } catch (\Throwable $e) {
            Logger::error('MFA RateLimiter clear failed: ' . $e->getMessage());
        }
    }

    /**
     * Increment an attempt counter atomically for a given rate_key and ip_address.
     */
    private static function incrementAttempt(
        string $key,
        string $ip,
        int $decaySeconds,
        string $driver
    ): void {
        if ($driver === 'mysql') {
            $windowExpression = "DATE_SUB(UTC_TIMESTAMP(), INTERVAL {$decaySeconds} SECOND)";
            Database::execute(
                "INSERT INTO rate_limit_attempts (rate_key, ip_address, window_started_at, attempts, updated_at)
                 VALUES (?, ?, UTC_TIMESTAMP(), 1, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE
                    attempts = IF(window_started_at < {$windowExpression}, 1, attempts + 1),
                    window_started_at = IF(window_started_at < {$windowExpression}, UTC_TIMESTAMP(), window_started_at),
                    updated_at = UTC_TIMESTAMP()",
                [$key, $ip]
            );
        } else {
            // SQLite atomic upsert using ON CONFLICT DO UPDATE
            Database::execute(
                "INSERT INTO rate_limit_attempts (rate_key, ip_address, window_started_at, attempts, updated_at)
                 VALUES (?, ?, datetime('now'), 1, datetime('now'))
                 ON CONFLICT(rate_key, ip_address) DO UPDATE SET
                    attempts = CASE WHEN window_started_at < datetime('now', '-' || {$decaySeconds} || ' seconds') THEN 1 ELSE attempts + 1 END,
                    window_started_at = CASE WHEN window_started_at < datetime('now', '-' || {$decaySeconds} || ' seconds') THEN datetime('now') ELSE window_started_at END,
                    updated_at = datetime('now')",
                [$key, $ip]
            );
        }
    }

    /**
     * Normalize client IP address to ensure valid schema length (VARCHAR 45).
     */
    public static function normalizeIp(string $ip): string
    {
        $trimmed = trim($ip);
        if (filter_var($trimmed, FILTER_VALIDATE_IP) !== false) {
            return $trimmed;
        }
        return '0.0.0.0';
    }
}
