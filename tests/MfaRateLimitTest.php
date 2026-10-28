<?php

declare(strict_types=1);

require __DIR__ . '/WorkflowFixture.php';

use App\Controllers\Admin\MfaController;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Services\MfaRateLimiter;
use App\Services\TwoFactorService as Mfa;

function mfaRequest(array $body = [], string $ip = '127.0.0.1'): \App\Core\Request
{
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/api/auth/mfa/verify';
    $_SERVER['REMOTE_ADDR'] = $ip;
    $_POST = $body;
    $_GET = [];
    return new \App\Core\Request();
}

function callMfaWithIp(string $method, array $body = [], string $ip = '127.0.0.1'): WorkflowResponse
{
    $r = new WorkflowResponse();
    $controller = new MfaController(mfaRequest($body, $ip), $r);
    $controller->$method();
    return $r;
}

try {
    echo "Running MFA Rate Limiting & Schema Compatibility Tests..." . PHP_EOL;

    // -------------------------------------------------------------------------
    // Test 1: Rate limit threshold (5 attempts max, 300s window)
    // -------------------------------------------------------------------------
    MfaRateLimiter::clear(1, '192.168.1.100');
    workflowCheck(!MfaRateLimiter::isRateLimited(1, '192.168.1.100'), 'Initial state is not rate limited');

    for ($i = 1; $i <= 4; $i++) {
        MfaRateLimiter::recordFailedAttempt(1, '192.168.1.100');
        workflowCheck(!MfaRateLimiter::isRateLimited(1, '192.168.1.100'), "Attempt {$i} of 4 is not rate limited");
    }

    // 5th attempt reaches threshold
    MfaRateLimiter::recordFailedAttempt(1, '192.168.1.100');
    workflowCheck(MfaRateLimiter::isRateLimited(1, '192.168.1.100'), '5th attempt reaches rate limit threshold and is blocked');

    // -------------------------------------------------------------------------
    // Test 2: Invalid OTP in verify() triggers HTTP 429 when threshold exceeded
    // -------------------------------------------------------------------------
    $secret = Mfa::generateSecret(32);
    $encSecret = Mfa::encryptSecret($secret);
    Database::execute(
        "UPDATE users SET two_factor_secret = ?, two_factor_enabled = 1, two_factor_confirmed_at = datetime('now'), status = 'active' WHERE id = 1",
        [$encSecret]
    );

    workflowLogin(1, false);
    // User 1 is currently rate limited from Test 1
    $resp = callMfaWithIp('verify', ['code' => '123456'], '192.168.1.100');
    workflowCheck($resp->status === 429, 'MfaController::verify() returns HTTP 429 when rate limited');
    workflowCheck(
        str_contains($resp->data['message'] ?? '', 'คุณกรอกรหัสผิดเกินจำนวนที่กำหนด'),
        'MfaController::verify() returns expected Thai rate limit message'
    );

    // -------------------------------------------------------------------------
    // Test 3: Clear rate limit on successful TOTP verification
    // -------------------------------------------------------------------------
    MfaRateLimiter::clear(1, '192.168.1.100');
    workflowCheck(!MfaRateLimiter::isRateLimited(1, '192.168.1.100'), 'clear() removes active rate limit');

    // Setup user 1 with known secret
    $secret = Mfa::generateSecret(32);
    $encSecret = Mfa::encryptSecret($secret);
    Database::execute(
        "UPDATE users SET two_factor_secret = ?, two_factor_enabled = 1, two_factor_confirmed_at = datetime('now'), status = 'active' WHERE id = 1",
        [$encSecret]
    );

    // Accumulate 3 failed attempts
    workflowLogin(1, false);
    callMfaWithIp('verify', ['code' => '000000'], '192.168.1.100');
    callMfaWithIp('verify', ['code' => '000001'], '192.168.1.100');
    callMfaWithIp('verify', ['code' => '000002'], '192.168.1.100');

    // Verify valid TOTP code
    $calc = new ReflectionMethod(Mfa::class, 'calculateCode');
    $validCode = $calc->invoke(null, $secret, (int) floor(time() / 30) + 1);

    workflowLogin(1, false);
    $successResp = callMfaWithIp('verify', ['code' => $validCode], '192.168.1.100');
    workflowCheck($successResp->status === 200, 'Valid TOTP code verifies successfully with HTTP 200');
    workflowCheck(!MfaRateLimiter::isRateLimited(1, '192.168.1.100'), 'Successful verification cleared rate limit counters');

    // -------------------------------------------------------------------------
    // Test 4: Single-use recovery code rate limiting & success clearing
    // -------------------------------------------------------------------------
    $recoveryCodes = Mfa::generateRecoveryCodes(1, 4);
    $validRecovery = $recoveryCodes[0];

    // Cause 5 failed recovery code attempts
    workflowLogin(1, false);
    for ($i = 1; $i <= 5; $i++) {
        callMfaWithIp('recovery', ['recovery_code' => 'INVALID-CODE-' . $i], '192.168.1.100');
    }

    $recBlockResp = callMfaWithIp('recovery', ['recovery_code' => $validRecovery], '192.168.1.100');
    workflowCheck($recBlockResp->status === 429, 'Excessive failed recovery attempts trigger HTTP 429');

    // Clear and verify successful recovery code clears rate limit
    MfaRateLimiter::clear(1, '192.168.1.100');
    workflowLogin(1, false);
    $recSuccess = callMfaWithIp('recovery', ['recovery_code' => $validRecovery], '192.168.1.100');
    workflowCheck($recSuccess->status === 200, 'Valid recovery code succeeds with HTTP 200');
    workflowCheck(!MfaRateLimiter::isRateLimited(1, '192.168.1.100'), 'Successful recovery code verification clears rate limit');

    // -------------------------------------------------------------------------
    // Test 5: Enrollment confirmation clearing rate limit
    // -------------------------------------------------------------------------
    // Reset user 2 for enrollment
    Database::execute("UPDATE users SET two_factor_secret = NULL, two_factor_enabled = 0, two_factor_confirmed_at = NULL WHERE id = 2");
    MfaRateLimiter::clear(2, '192.168.1.102');
    workflowLogin(2, false, true);

    $enrollResp = callMfaWithIp('enroll', [], '192.168.1.102');
    workflowCheck($enrollResp->status === 200, 'Enrollment initiates successfully');
    $enrollSecret = $enrollResp->data['secret'];

    // 2 failed confirmation attempts
    callMfaWithIp('confirm', ['code' => '000000'], '192.168.1.102');
    callMfaWithIp('confirm', ['code' => '000001'], '192.168.1.102');

    // Valid confirmation
    $validEnrollCode = $calc->invoke(null, $enrollSecret, (int) floor(time() / 30));
    $confirmResp = callMfaWithIp('confirm', ['code' => $validEnrollCode], '192.168.1.102');
    workflowCheck($confirmResp->status === 200, 'Valid confirmation code succeeds with HTTP 200');
    workflowCheck(!MfaRateLimiter::isRateLimited(2, '192.168.1.102'), 'Enrollment confirmation cleared rate limit');

    // -------------------------------------------------------------------------
    // Test 6: Window expiry resets attempts
    // -------------------------------------------------------------------------
    MfaRateLimiter::clear(3, '192.168.1.103');
    for ($i = 0; $i < 5; $i++) {
        MfaRateLimiter::recordFailedAttempt(3, '192.168.1.103');
    }
    workflowCheck(MfaRateLimiter::isRateLimited(3, '192.168.1.103'), 'User 3 is rate limited');

    // Manually age the window by 305 seconds in the past for both user and test IP
    Database::execute(
        "UPDATE rate_limit_attempts
         SET window_started_at = datetime('now', '-305 seconds')
         WHERE rate_key = 'mfa:user:3' OR (rate_key = 'mfa:ip' AND ip_address = '192.168.1.103')"
    );

    workflowCheck(
        !MfaRateLimiter::isRateLimited(3, '192.168.1.103'),
        'Rate limit expired after decay window (300 seconds) elapsed'
    );

    // New failed attempt resets window and starts counter fresh from 1
    MfaRateLimiter::recordFailedAttempt(3, '192.168.1.103');
    $attemptsAfterReset = (int) Database::value(
        "SELECT attempts FROM rate_limit_attempts WHERE rate_key = 'mfa:user:3' AND ip_address = '0.0.0.0'"
    );
    workflowCheck($attemptsAfterReset === 1, 'First attempt after window expiry resets counter to 1');

    // -------------------------------------------------------------------------
    // Test 7: Per-user isolation (User 1 blocked does not block User 4)
    // -------------------------------------------------------------------------
    MfaRateLimiter::clear(1, '192.168.1.10');
    MfaRateLimiter::clear(4, '192.168.1.20');

    for ($i = 0; $i < 5; $i++) {
        MfaRateLimiter::recordFailedAttempt(1, '192.168.1.10');
    }
    workflowCheck(MfaRateLimiter::isRateLimited(1, '192.168.1.10'), 'User 1 is rate limited');
    workflowCheck(!MfaRateLimiter::isRateLimited(4, '192.168.1.20'), 'User 4 is NOT rate limited by User 1 failures');

    // -------------------------------------------------------------------------
    // Test 8: Per-IP spray protection (Single IP spraying multiple users blocked)
    // -------------------------------------------------------------------------
    $sprayIp = '10.99.88.77';
    MfaRateLimiter::clear(1, $sprayIp);
    MfaRateLimiter::clear(2, $sprayIp);

    // 5 failures from same spray IP targeting various user IDs
    for ($u = 1; $u <= 5; $u++) {
        MfaRateLimiter::recordFailedAttempt($u, $sprayIp);
    }
    workflowCheck(
        MfaRateLimiter::isRateLimited(99, $sprayIp),
        'Per-IP rate limit blocks new target user 99 from abusive spray IP'
    );

    // -------------------------------------------------------------------------
    // Test 9: Fail-closed security behavior on database query failure
    // -------------------------------------------------------------------------
    // Rename table temporarily to simulate DB outage / table missing
    Database::execute("ALTER TABLE rate_limit_attempts RENAME TO rate_limit_attempts_bak");

    $failClosedResult = MfaRateLimiter::isRateLimited(1, '127.0.0.1');
    workflowCheck($failClosedResult === true, 'MfaRateLimiter fails closed (returns true / access denied) on database failure');

    // Restore table
    Database::execute("ALTER TABLE rate_limit_attempts_bak RENAME TO rate_limit_attempts");
    workflowCheck(!MfaRateLimiter::isRateLimited(4, '192.168.1.20'), 'MfaRateLimiter resumes normal operation after DB restored');

} finally {
    workflowFinish();
}
