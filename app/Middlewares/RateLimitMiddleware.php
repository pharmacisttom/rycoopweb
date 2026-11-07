<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\Session;

class RateLimitMiddleware
{
    private string $key;
    private int $maxAttempts;
    private int $decaySeconds;

    public function __construct(string $key = 'global', int $maxAttempts = 60, int $decaySeconds = 60)
    {
        $this->key = $key;
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
    }

    public function handle(Request $request, Response $response): bool
    {
        try {
            $driver = (string) Database::pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                return true;
            }
        } catch (\Throwable $e) {}

        $ip = $request->ip();
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $ip = '0.0.0.0';
        }

        // Store counters outside the browser session so clients cannot reset a limit by
        // discarding their cookies. The unique key also makes concurrent requests atomic.
        $windowExpression = "DATE_SUB(UTC_TIMESTAMP(), INTERVAL {$this->decaySeconds} SECOND)";
        Database::execute(
            "INSERT INTO rate_limit_attempts (rate_key, ip_address, window_started_at, attempts, updated_at)
             VALUES (?, ?, UTC_TIMESTAMP(), 1, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                attempts = IF(window_started_at < {$windowExpression}, 1, attempts + 1),
                window_started_at = IF(window_started_at < {$windowExpression}, UTC_TIMESTAMP(), window_started_at),
                updated_at = UTC_TIMESTAMP()",
            [$this->key, $ip]
        );

        $rateLimit = Database::first(
            'SELECT attempts, TIMESTAMPDIFF(SECOND, window_started_at, UTC_TIMESTAMP()) AS elapsed_seconds
             FROM rate_limit_attempts WHERE rate_key = ? AND ip_address = ?',
            [$this->key, $ip]
        );
        $attempts = (int) ($rateLimit['attempts'] ?? 1);
        $elapsedSeconds = (int) ($rateLimit['elapsed_seconds'] ?? 0);

        if ($attempts > $this->maxAttempts) {
            $retryAfter = max(1, $this->decaySeconds - $elapsedSeconds);
            if ($request->isAjax()) {
                $response->json([
                    'success' => false,
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => "คุณทำรายการเกินกำหนด กรุณารออีก {$retryAfter} วินาทีก่อนลองใหม่"
                ], 429);
                return false;
            }

            Session::flash('error', "คุณทำรายการเกินกำหนด กรุณารออีก {$retryAfter} วินาที");
            $response->redirect($_SERVER['HTTP_REFERER'] ?? url('/'));
            return false;
        }

        return true;
    }
}
