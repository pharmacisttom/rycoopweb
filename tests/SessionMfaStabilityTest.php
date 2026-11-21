<?php

declare(strict_types=1);

require __DIR__ . '/WorkflowFixture.php';

use App\Controllers\Admin\MfaController;
use App\Core\{Auth, Database, Request, Response, Session};
use App\Services\TwoFactorService as Mfa;

function callMfa(string $method, array $body = []): WorkflowResponse
{
    $r = new WorkflowResponse();
    (new MfaController(workflowRequest($body), $r))->$method();
    return $r;
}

try {
    $tempSessionPath = __DIR__ . '/../storage/tmp/stability-test-sessions-' . uniqid();
    if (!is_dir($tempSessionPath)) {
        mkdir($tempSessionPath, 0700, true);
    }
    putenv('SESSION_SAVE_PATH=' . $tempSessionPath);
    ini_set('session.save_path', $tempSessionPath);

    // 1. Session start configures gc_maxlifetime and cookie_lifetime
    Session::destroy();
    Session::start();
    $expectedLifetime = (string) config('security.session.lifetime', 7200);
    workflowCheck(ini_get('session.gc_maxlifetime') === $expectedLifetime, 'session.gc_maxlifetime configured to match application lifetime');
    workflowCheck(ini_get('session.cookie_lifetime') === $expectedLifetime, 'session.cookie_lifetime configured to match application lifetime');
    workflowCheck(ini_get('session.use_strict_mode') === '1', 'session.use_strict_mode is enabled');

    // 2. Safe rotation with bounded transition window
    Session::destroy();
    workflowLogin(1, true);
    $originalId = session_id();
    workflowCheck(Auth::check(), 'initial login is authenticated');

    // Simulate 31 minutes passed
    $_SESSION['__created_at'] = time() - 1860;

    // Request A rotates session
    Session::rotate(60);
    $newId = session_id();
    workflowCheck($originalId !== $newId, 'session ID changed upon rotation');
    workflowCheck(Session::get('user_id') === 1, 'user session preserved in new session');
    workflowCheck(Auth::check(), 'authenticated status preserved in new session');

    // Concurrent Request B arrives with old ID within grace period
    session_write_close();
    (new ReflectionProperty(Session::class, 'started'))->setValue(null, false);

    session_id($originalId);
    $_COOKIE['rayongcoop_session'] = $originalId;
    Session::start();
    workflowCheck(session_id() === $newId, 'concurrent request transparently migrated to new session ID within grace period');
    workflowCheck(Session::get('user_id') === 1 && Auth::check(), 'concurrent request authenticated state preserved');

    // 3. Rotated session rejected after grace period (anti-fixation / anti-hijack)
    session_write_close();
    $oldFile = session_save_path() . '/sess_' . $originalId;
    workflowCheck(file_exists($oldFile), 'old session file exists during transition');

    // Simulate expiry: set __rotated_at to 70 seconds ago
    session_id($originalId);
    $_COOKIE['rayongcoop_session'] = $originalId;
    session_start();
    $_SESSION['__rotated_at'] = time() - 70;
    session_write_close();
    (new ReflectionProperty(Session::class, 'started'))->setValue(null, false);

    session_id($originalId);
    $_COOKIE['rayongcoop_session'] = $originalId;
    Session::start();
    workflowCheck(!Session::has('user_id'), 'expired rotated session ID rejected and unauthenticated');
    workflowCheck(session_id() !== $originalId && session_id() !== $newId, 'expired rotated session ID triggers fresh guest session');

    // 4. Idle timeout enforcement (1200 seconds)
    Session::destroy();
    workflowLogin(1, true);
    $activeId = session_id();
    $_SESSION['__last_activity'] = time() - 1205; // 20m 5s ago
    session_write_close();
    (new ReflectionProperty(Session::class, 'started'))->setValue(null, false);

    session_id($activeId);
    Session::start();
    workflowCheck(!Session::has('user_id'), 'idle session exceeding 1200 seconds is destroyed');

    // 5. MFA challenge expiration at 600s
    workflowLogin(1, false);
    Session::set('mfa_started_at', time() - 605); // 605s ago
    workflowCheck(!Auth::checkPending2FA(), 'MFA challenge older than 600 seconds is expired');

    // 6. Fully authenticated Admin calling MfaController::status is NOT kicked out
    workflowLogin(1, true);
    $response = new WorkflowResponse();
    $controller = new MfaController(workflowRequest(), $response);
    $controller->status();
    workflowCheck($response->status === 200, 'authenticated admin reaches MFA status endpoint successfully');
    workflowCheck($response->data['pending_enrollment'] === false, 'authenticated admin is not marked pending enrollment');
    workflowCheck($response->data['pending_verification'] === false, 'authenticated admin is not marked pending verification');
    workflowCheck(Auth::check(), 'authenticated admin remains authenticated after MFA status check');

    // 7. Verify MFA completes cleanly with single regeneration
    workflowLogin(1, false, true);
    $enrollResp = callMfa('enroll');
    workflowCheck($enrollResp->status === 200, 'pending user can enroll MFA');
    $secret = $enrollResp->data['secret'];
    $calc = new ReflectionMethod(Mfa::class, 'calculateCode');
    $validCode = $calc->invoke(null, $secret, (int) floor(time() / 30));

    $confirmResp = callMfa('confirm', ['code' => $validCode]);
    workflowCheck($confirmResp->status === 200, 'enrollment confirmed successfully');
    workflowCheck(Auth::check(), 'admin is fully authenticated after single regeneration');

    // Cleanup temp sessions
    if (is_dir($tempSessionPath)) {
        $files = glob($tempSessionPath . '/*');
        if ($files) {
            array_map('unlink', $files);
        }
        @rmdir($tempSessionPath);
    }

} finally {
    workflowFinish();
}
