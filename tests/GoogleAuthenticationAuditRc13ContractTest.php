<?php
declare(strict_types=1);

/**
 * RC13A source contract: pure file reads, no database or network calls.
 * Run: php tests/GoogleAuthenticationAuditRc13ContractTest.php
 */
$root = dirname(__DIR__);
$google = file_get_contents($root . '/app/Controllers/Admin/GoogleAuthController.php');
$service = file_get_contents($root . '/app/Services/AdminControlCenterService.php');
$audit = file_get_contents($root . '/app/Services/AuditService.php');
if ($google === false || $service === false || $audit === false) {
    fwrite(STDERR, "RC13A_CONTRACT=FAIL missing source\n");
    exit(1);
}
$assert = static function (bool $condition, string $name): void {
    if (!$condition) {
        fwrite(STDERR, "RC13A_CONTRACT=FAIL {$name}\n");
        exit(1);
    }
};
$methodStart = strpos($google, 'private function handleUserSessionAndMfa(');
$methodEnd = strpos($google, 'private function handlePrimarySuperAdminBootstrap(', $methodStart === false ? 0 : $methodStart);
$assert($methodStart !== false && $methodEnd !== false, 'google_session_method');
$method = substr($google, $methodStart, $methodEnd - $methodStart);
$assert(substr_count($method, 'AuditService::logVerifiedGoogleIdentity($userId)') === 1, 'single_google_audit_event');
$assert(strpos($method, 'AuditService::logVerifiedGoogleIdentity($userId)') < strpos($method, 'Auth::login('), 'audit_before_session');
$assert(strpos($audit, 'public static function logVerifiedGoogleIdentity(int $verifiedUserId)') !== false, 'explicit_verified_actor_writer');
$assert(strpos($audit, "VALUES (?, 'google_auth', 'identity_verified'") !== false, 'identity_audit_sql');
$assert(strpos($audit, "'stage' => 'pre_mfa'") !== false, 'pre_mfa_stage');
$assert(strpos($audit, "'provider' => 'google'") !== false, 'provider');
$assert(strpos($method, 'Auth::login($identity, false, false)') !== false, 'existing_mfa_verification_gate');
$assert(strpos($method, 'Auth::login($identity, false, true)') !== false, 'existing_mfa_enrollment_gate');
$assert(strpos($method, 'Auth::login($identity, true, false)') !== false, 'existing_non_mfa_branch_unchanged');
foreach (['google_identity_success_7d', 'local_credential_success_7d', 'mfa_verified_events_7d', 'authentication_stage_note'] as $key) {
    $assert(strpos($service, "'{$key}'") !== false, 'metric_' . $key);
}
$assert(strpos($service, "auth_provider = 'google' AND status = 'success'") !== false, 'google_metric_source');
$assert(strpos($service, "module = 'auth' AND action = '2fa_verified'") !== false, 'mfa_metric_source');
echo "GOOGLE_AUTHENTICATION_AUDIT_RC13A_CONTRACT=PASS\n";
