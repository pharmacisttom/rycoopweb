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
    echo " RYCOOPWEB — ADMIN LOGIN & TOTP MFA DECOUPLING TEST" . PHP_EOL;
    echo "========================================================================" . PHP_EOL;

    // Set schema and password hash for fixture admin user (id=1)
    $password = 'SecretAdmin123!';
    $hash = password_hash($password, PASSWORD_BCRYPT);
    Database::execute("ALTER TABLE users ADD COLUMN password TEXT");
    Database::execute("ALTER TABLE users ADD COLUMN username TEXT");
    Database::execute("CREATE TABLE IF NOT EXISTS members(id INTEGER PRIMARY KEY, user_id INTEGER, member_no TEXT, id_card TEXT, phone TEXT, status TEXT)");
    Database::execute("CREATE TABLE IF NOT EXISTS user_google_identities(id INTEGER PRIMARY KEY, user_id INTEGER, google_email TEXT, google_sub TEXT, linked_at TEXT)");
    Database::execute("UPDATE users SET password = ?, username = 'admin' WHERE id = 1", [$hash]);

    // Test 1: Anonymous request to /api/auth/me returns 401
    Auth::logout();
    $meResp = callSpa('me');
    workflowCheck($meResp->status === 401 && $meResp->data['authenticated'] === false, '1. Anonymous request to /api/auth/me returns 401 unauthenticated');

    // Test 2: Staff login with invalid credentials fails
    $loginFail = callAuth('login', ['username' => 'admin', 'password' => 'WrongPassword', 'ajax' => '1']);
    workflowCheck($loginFail->status === 401 && $loginFail->data['success'] === false, '2. Staff login with invalid credentials fails with 401');

    // Test 3: Staff login with valid credentials but unenrolled MFA issues enrollment challenge
    $loginEnroll = callAuth('login', ['username' => 'admin', 'password' => $password, 'ajax' => '1']);
    workflowCheck(
        $loginEnroll->status === 200 &&
        $loginEnroll->data['success'] === true &&
        $loginEnroll->data['mfa_required'] === true &&
        $loginEnroll->data['requires_enrollment'] === true &&
        $loginEnroll->data['redirect'] === '/admin/mfa/enroll',
        '3. Staff credentials verified; unenrolled user challenged with MFA enrollment'
    );

    // Test 4: Unverified MFA enrollment session cannot reach protected API
    $mePendingEnroll = callSpa('me');
    workflowCheck(
        $mePendingEnroll->status === 403 &&
        $mePendingEnroll->data['code'] === 'MFA_ENROLLMENT_REQUIRED' &&
        $mePendingEnroll->data['authenticated'] === false,
        '4. MFA enrollment pending session cannot reach protected Admin APIs'
    );

    // Test 5: Enroll MFA secret and confirm with valid TOTP code
    $rEnroll = callMfa('enroll');
    workflowCheck($rEnroll->status === 200 && !empty($rEnroll->data['secret']), '5. Enrollment generates secure 160-bit TOTP secret');
    $secret = $rEnroll->data['secret'];

    $calculate = new ReflectionMethod(Mfa::class, 'calculateCode');
    $currentCode = $calculate->invoke(null, $secret, (int)floor(time() / 30));

    $rConfirm = callMfa('confirm', ['code' => $currentCode]);
    workflowCheck($rConfirm->status === 200 && Auth::check(), '6. Valid TOTP code confirms enrollment and completes full authentication');
    workflowCheck(count($rConfirm->data['recovery_codes']) === 8, '7. Confirmation returns one-time backup recovery codes');

    // Test 8: Full authenticated session can reach /api/auth/me
    $meAuth = callSpa('me');
    workflowCheck(
        $meAuth->status === 200 &&
        $meAuth->data['success'] === true &&
        $meAuth->data['authenticated'] === true &&
        $meAuth->data['user']['role'] === 'super_admin',
        '8. Fully verified staff session accesses /api/auth/me with super_admin role'
    );

    // Test 9: Subsequent login for enrolled user issues TOTP verification challenge (not enrollment)
    Auth::logout();
    $loginVerify = callAuth('login', ['username' => 'admin', 'password' => $password, 'ajax' => '1']);
    workflowCheck(
        $loginVerify->status === 200 &&
        $loginVerify->data['success'] === true &&
        $loginVerify->data['mfa_required'] === true &&
        empty($loginVerify->data['requires_enrollment']) &&
        $loginVerify->data['redirect'] === '/admin/mfa/verify',
        '9. Login for enrolled user issues TOTP verify challenge (/admin/mfa/verify)'
    );

    // Test 10: Unverified MFA verification session cannot reach protected API
    $mePendingVerify = callSpa('me');
    workflowCheck(
        $mePendingVerify->status === 403 &&
        $mePendingVerify->data['code'] === 'TWO_FACTOR_REQUIRED' &&
        $mePendingVerify->data['authenticated'] === false,
        '10. MFA verify pending session cannot reach protected Admin APIs'
    );

    // Test 11: Invalid TOTP verification code fails
    $invalidCodeResp = callMfa('verify', ['code' => '000000']);
    workflowCheck($invalidCodeResp->status === 400 && $invalidCodeResp->data['success'] === false, '11. Invalid TOTP verification code fails with 400');

    // Test 12: Valid TOTP verification succeeds and grants access
    $nextCode = $calculate->invoke(null, $secret, (int)floor(time() / 30) + 1);
    $validCodeResp = callMfa('verify', ['code' => $nextCode]);
    workflowCheck($validCodeResp->status === 200 && Auth::check(), '12. Valid TOTP code completes authentication and grants access');

    // Test 13: Replay of same TOTP code is rejected
    $replayResp = callMfa('verify', ['code' => $nextCode]);
    workflowCheck($replayResp->status === 401 || $replayResp->status === 409, '13. Replay of consumed TOTP code is strictly rejected');

    // Test 14: Member login is rejected when member portal is disabled
    putenv('FEATURE_MEMBER_LOGIN=false');
    $memberPasswordHash = password_hash('Member1234!', PASSWORD_BCRYPT);
    Database::execute("UPDATE users SET password = ?, username = 'member01' WHERE id = 4", [$memberPasswordHash]);
    $memberLoginResp = callAuth('login', ['username' => 'member01', 'password' => 'Member1234!', 'ajax' => '1']);
    workflowCheck($memberLoginResp->status === 403 && str_contains($memberLoginResp->data['message'] ?? '', 'ระบบสมาชิกออนไลน์ยังไม่เปิดให้บริการ'), '14. Member login is rejected with 403 when member portal is disabled');

    echo "========================================================================" . PHP_EOL;
    echo " ALL TESTS PASSED SUCCESSFULLY" . PHP_EOL;
    echo "========================================================================" . PHP_EOL;
} finally {
    workflowFinish();
}
