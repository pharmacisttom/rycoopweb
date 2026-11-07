<?php
declare(strict_types=1);

require __DIR__ . '/WorkflowFixture.php';

use App\Core\{Auth, Database, Session, Request};
use App\Controllers\Admin\{AuthController, MfaController};
use App\Controllers\Api\SpaApiController;
use App\Services\TwoFactorService as Mfa;

function callAuth(string $method, array $body = []): WorkflowResponse
{
    $r = new WorkflowResponse();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/login';
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $_POST = $body;
    $_GET = [];
    $controller = new AuthController(new Request(), $r);
    $controller->$method();
    return $r;
}

function callSpa(string $method): WorkflowResponse
{
    $r = new WorkflowResponse();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/api/auth/me';
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $_POST = [];
    $_GET = [];
    $controller = new SpaApiController(new Request(), $r);
    $controller->$method();
    return $r;
}

function callMfa(string $method, array $body = []): WorkflowResponse
{
    $r = new WorkflowResponse();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/api/auth/mfa/' . $method;
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $_POST = $body;
    $_GET = [];
    $controller = new MfaController(new Request(), $r);
    $controller->$method();
    return $r;
}

try {
    echo "========================================================================" . PHP_EOL;
    echo " RYCOOPWEB — SECURE ADMIN MFA RE-ENROLLMENT INTEGRATION TEST" . PHP_EOL;
    echo "========================================================================" . PHP_EOL;

    // 1. Setup schema and initial user state
    $password = 'SecretAdmin123!';
    $hash = password_hash($password, PASSWORD_BCRYPT);
    Database::execute("ALTER TABLE users ADD COLUMN password TEXT");
    Database::execute("ALTER TABLE users ADD COLUMN username TEXT");
    Database::execute("CREATE TABLE IF NOT EXISTS members(id INTEGER PRIMARY KEY, user_id INTEGER, member_no TEXT, id_card TEXT, phone TEXT, status TEXT)");
    Database::execute("CREATE TABLE IF NOT EXISTS user_google_identities(id INTEGER PRIMARY KEY, user_id INTEGER, google_email TEXT, google_sub TEXT, linked_at TEXT)");
    Database::execute("UPDATE users SET password = ?, username = 'admin' WHERE id = 1", [$hash]);

    // Initial setup: Admin ID 1 is already enrolled in MFA with an original secret and recovery codes
    $origSecret = Mfa::generateSecret(32);
    $encryptedOrigSecret = Mfa::encryptSecret($origSecret);
    Database::execute(
        "UPDATE users SET two_factor_secret = ?, two_factor_enabled = 1, two_factor_confirmed_at = NOW(), session_version = 1 WHERE id = 1",
        [$encryptedOrigSecret]
    );
    $origRecoveryCodes = Mfa::generateRecoveryCodes(1, 8);
    workflowCheck(count($origRecoveryCodes) === 8, 'Setup: Admin enrolled with original secret and 8 recovery codes');

    // -------------------------------------------------------------------------
    // Acceptance Test 1 & 2: Administrator logs in with password and single-use Recovery Code
    // -------------------------------------------------------------------------
    Auth::logout();
    $loginResp = callAuth('login', ['username' => 'admin', 'password' => $password, 'ajax' => '1']);
    workflowCheck(
        $loginResp->status === 200 &&
        $loginResp->data['mfa_required'] === true &&
        $loginResp->data['redirect'] === '/admin/mfa/verify',
        '1. Login credentials valid; user challenged for 2FA'
    );

    // Verify with invalid recovery code is rejected
    $invalidRec = callMfa('recovery', ['recovery_code' => 'INVALID-CODE-0000']);
    workflowCheck($invalidRec->status === 400, '2. Invalid recovery code is rejected with 400');

    // Authenticate using the first valid recovery code
    $usedRecoveryCode = $origRecoveryCodes[0];
    $validRec = callMfa('recovery', ['recovery_code' => $usedRecoveryCode]);
    workflowCheck(
        $validRec->status === 200 &&
        Auth::check() === true &&
        $validRec->data['remaining_codes'] === 7,
        '3. Existing Recovery Code works once and completes full authentication'
    );

    // Verify single-use: Replay of the consumed recovery code is rejected
    Auth::logout();
    callAuth('login', ['username' => 'admin', 'password' => $password, 'ajax' => '1']);
    $replayRec = callMfa('recovery', ['recovery_code' => $usedRecoveryCode]);
    workflowCheck($replayRec->status === 400, '4. Consumed recovery code cannot be reused (replay rejected)');

    // Log back in using the second recovery code
    $secondRecoveryCode = $origRecoveryCodes[1];
    $recLogin2 = callMfa('recovery', ['recovery_code' => $secondRecoveryCode]);
    workflowCheck(
        $recLogin2->status === 200 && Auth::check() === true,
        '5. Second recovery code authenticates user into Admin Portal'
    );

    // -------------------------------------------------------------------------
    // Acceptance Test 3: Unverified / Unauthenticated users cannot start replacement
    // -------------------------------------------------------------------------
    Auth::logout();
    $anonReEnroll = callMfa('reEnrollStart', ['password' => $password]);
    workflowCheck($anonReEnroll->status === 401, '6. Unauthenticated session cannot start device replacement');

    // Partial session (pending 2FA) cannot start replacement
    callAuth('login', ['username' => 'admin', 'password' => $password, 'ajax' => '1']);
    workflowCheck(Auth::check() === false && Auth::checkPending2FA() === true, 'Setup: Session is pending-2FA');
    $pendingReEnroll = callMfa('reEnrollStart', ['password' => $password]);
    workflowCheck($pendingReEnroll->status === 401, '7. Merely pending-MFA session cannot start device replacement');

    // Re-authenticate user to valid full session for subsequent tests
    $thirdRecoveryCode = $origRecoveryCodes[2];
    callMfa('recovery', ['recovery_code' => $thirdRecoveryCode]);
    workflowCheck(Auth::check() === true, 'Setup: User fully authenticated for replacement testing');

    // -------------------------------------------------------------------------
    // Acceptance Test 4: Password re-verification required
    // -------------------------------------------------------------------------
    $wrongPass = callMfa('reEnrollStart', ['password' => 'WrongPassword!']);
    workflowCheck($wrongPass->status === 401, '8. Wrong password rejects device replacement start');

    // -------------------------------------------------------------------------
    // Acceptance Test 5: Valid password generates new secret & QR code while keeping old secret active
    // -------------------------------------------------------------------------
    $startResp = callMfa('reEnrollStart', ['password' => $password]);
    workflowCheck(
        $startResp->status === 200 &&
        !empty($startResp->data['qr_code_uri']) &&
        !empty($startResp->data['formatted_secret']) &&
        $startResp->data['expires_in'] === 600,
        '9. Valid password starts replacement: returns new QR code and 160-bit secret'
    );

    // Verify Invariant: Old secret in DB remains active!
    $dbUser = Database::first("SELECT two_factor_secret FROM users WHERE id = 1");
    workflowCheck(
        $dbUser['two_factor_secret'] === $encryptedOrigSecret,
        '10. Old TOTP secret in DB remains strictly active while replacement is pending'
    );

    // -------------------------------------------------------------------------
    // Acceptance Test 6: Cancellation keeps old secret active
    // -------------------------------------------------------------------------
    $cancelResp = callMfa('reEnrollCancel');
    workflowCheck($cancelResp->status === 200, '11. Re-enrollment can be cancelled safely');
    workflowCheck(empty(Session::get('mfa_re_enroll_secret')), '12. Cancellation clears pending secret from session');

    $dbUserAfterCancel = Database::first("SELECT two_factor_secret FROM users WHERE id = 1");
    workflowCheck(
        $dbUserAfterCancel['two_factor_secret'] === $encryptedOrigSecret,
        '13. Old secret remains active in database after cancellation'
    );

    // -------------------------------------------------------------------------
    // Acceptance Test 7 & 8: Wrong TOTP cannot activate new secret; Valid TOTP activates
    // -------------------------------------------------------------------------
    // Start re-enrollment again
    $startResp2 = callMfa('reEnrollStart', ['password' => $password]);
    workflowCheck($startResp2->status === 200, '14. Re-enrollment started successfully');
    $newSecret = Session::get('mfa_re_enroll_secret');
    workflowCheck(!empty($newSecret) && $newSecret !== $origSecret, '15. Server generated new distinct 160-bit secret');

    // Try wrong TOTP code
    $wrongTotp = callMfa('reEnrollConfirm', ['code' => '000000']);
    workflowCheck($wrongTotp->status === 400, '16. Wrong TOTP code cannot activate new secret');

    // Verify DB still has old secret after failed attempt
    $dbUserAfterFail = Database::first("SELECT two_factor_secret FROM users WHERE id = 1");
    workflowCheck(
        $dbUserAfterFail['two_factor_secret'] === $encryptedOrigSecret,
        '17. Failed TOTP attempt leaves old secret intact in database'
    );

    // Calculate valid TOTP code for the new secret
    $calcMethod = new ReflectionMethod(Mfa::class, 'calculateCode');
    $validNewCode = $calcMethod->invoke(null, $newSecret, (int) floor(time() / 30));

    // Confirm with valid TOTP code
    $confirmResp = callMfa('reEnrollConfirm', ['code' => $validNewCode]);
    workflowCheck(
        $confirmResp->status === 200 &&
        $confirmResp->data['success'] === true &&
        count($confirmResp->data['recovery_codes']) === 8,
        '18. Valid TOTP confirms re-enrollment and returns 8 new recovery codes'
    );
    $newRecoveryCodes = $confirmResp->data['recovery_codes'];

    // -------------------------------------------------------------------------
    // Acceptance Test 9 & 10: Old secret & old recovery codes are invalidated; DB updated
    // -------------------------------------------------------------------------
    $dbUserAfterConfirm = Database::first("SELECT two_factor_secret, session_version FROM users WHERE id = 1");
    workflowCheck(
        $dbUserAfterConfirm['two_factor_secret'] !== $encryptedOrigSecret,
        '19. Old secret in database is atomically replaced with encrypted new secret'
    );
    $decryptedDbSecret = Mfa::decryptSecret($dbUserAfterConfirm['two_factor_secret']);
    workflowCheck(
        $decryptedDbSecret === $newSecret,
        '20. Decrypted database secret matches confirmed new TOTP secret'
    );

    // Invariant: Verify old recovery codes are completely revoked
    $fourthOldCode = $origRecoveryCodes[3];
    workflowCheck(
        Mfa::verifyRecoveryCode(1, $fourthOldCode) === false,
        '21. Old recovery codes are completely revoked in database'
    );

    // -------------------------------------------------------------------------
    // Acceptance Test 11: New Recovery Codes work once
    // -------------------------------------------------------------------------
    $newCodeToTest = $newRecoveryCodes[0];
    workflowCheck(
        Mfa::verifyRecoveryCode(1, $newCodeToTest) === true,
        '22. New recovery code verified and consumed successfully'
    );
    workflowCheck(
        Mfa::verifyRecoveryCode(1, $newCodeToTest) === false,
        '23. Consumed new recovery code cannot be reused (single-use)'
    );

    // -------------------------------------------------------------------------
    // Acceptance Test 12: Concurrent sessions revoked via session_version bump
    // -------------------------------------------------------------------------
    workflowCheck(
        (int) $dbUserAfterConfirm['session_version'] === 2,
        '24. session_version bumped to revoke concurrent active sessions'
    );
    workflowCheck(
        (int) Session::get('session_version') === 2,
        '25. Current session updated to new session_version and remains active'
    );

    // -------------------------------------------------------------------------
    // Acceptance Test 13: Audit log verification
    // -------------------------------------------------------------------------
    $deviceReplacedLog = Database::first(
        "SELECT * FROM audit_logs WHERE user_id = 1 AND module = 'mfa' AND action = 'device_replaced' ORDER BY id DESC LIMIT 1"
    );
    workflowCheck(!empty($deviceReplacedLog), '26. Audit log recorded for device_replaced event');

    // -------------------------------------------------------------------------
    // Acceptance Test 14: TOTP code from OLD secret is rejected for new login
    // -------------------------------------------------------------------------
    Auth::logout();
    callAuth('login', ['username' => 'admin', 'password' => $password, 'ajax' => '1']);
    $oldTotpCode = $calcMethod->invoke(null, $origSecret, (int) floor(time() / 30));
    $oldVerify = callMfa('verify', ['code' => $oldTotpCode]);
    workflowCheck(
        $oldVerify->status === 400 && $oldVerify->data['success'] === false,
        '27. TOTP code from original/lost device is strictly rejected'
    );

    // TOTP code from NEW secret succeeds (using forward slice within allowed window to satisfy replay protection)
    $nextSlice = (int) floor(time() / 30) + 1;
    $freshNewCode = $calcMethod->invoke(null, $newSecret, $nextSlice);
    $newVerify = callMfa('verify', ['code' => $freshNewCode]);
    workflowCheck(
        $newVerify->status === 200 &&
        Auth::check() === true &&
        $newVerify->data['success'] === true,
        '28. TOTP code from new Authenticator device logs in successfully'
    );

    workflowFinish();
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    exit(1);
}
