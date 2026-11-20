<?php
declare(strict_types=1);
// RC12 read-only source contract. No HTTP writes or database modifications.
$root=dirname(__DIR__);
$cookie=file_get_contents($root.'/src/components/common/CookieConsent.jsx');
$api=file_get_contents($root.'/app/Controllers/Public/ApiController.php');
$routes=file_get_contents($root.'/config/routes.php');
$service=file_get_contents($root.'/app/Services/AdminControlCenterService.php');
$screen=file_get_contents($root.'/src/pages/AdminControlCenterPage.jsx');
$checks=[
 'consent writes'=>str_contains($cookie,"fetch('/api/cookie-consent'"),
 'CSRF frontend'=>str_contains($cookie,"'X-CSRF-TOKEN': token"),
 'CSRF backend'=>str_contains($routes,"'Public\\ApiController@logCookieConsent', [CsrfMiddleware::class]"),
 'explicit choice'=>str_contains($cookie,"choice === 'accepted'"),
 'no premature consent'=>strpos($cookie,"if (!response.ok || result.success !== true)")<strpos($cookie,"window.localStorage.setItem(CHOICE_KEY, choice)"),
 'repeat settings'=>str_contains($cookie,'ตั้งค่าคุกกี้'),
 'reject records zeros'=>str_contains($cookie,'Number(isAccepted)'),
 'consent validated'=>str_contains($api,"preg_match('/^[a-f0-9]{8}"),
 'latest preference update'=>str_contains($api,'consented_at=NOW(),updated_at=NOW()'),
 'security source list'=>str_contains($service,"'security_audit_logs' => 'timestamp_utc'") &&
   str_contains($service,"'cookie_consents' => 'consented_at'"),
 'separate read errors'=>str_contains($service,"'status' => 'query_error'"),
 'UI table'=>str_contains($screen,'securityData.log_sources'),
 'audit read error'=>str_contains($service,"'records_read_error' =>") &&
    str_contains($screen,'auditData.records_read_error'),
];
foreach($checks as $key=>$ok) {
 if(!$ok) throw new RuntimeException("RC12 contract failed: $key");
}
echo "SECURITY_COOKIE_RC12_CONTRACT=PASS\n";
