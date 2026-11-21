<?php
declare(strict_types=1);

/** Source contract regression, not an OAuth end-to-end test.
 * Run: php tests/GoogleMfaIdentityMappingRegressionTest.php
 */
$source = file_get_contents(__DIR__ . '/../app/Controllers/Admin/GoogleAuthController.php');
if (!is_string($source)) {
    fwrite(STDERR, "FAIL: source unavailable\n");
    exit(1);
}
$start = strpos($source, 'private function handleUserSessionAndMfa(');
$end = strpos($source, 'private function handlePrimarySuperAdminBootstrap(');
if ($start === false || $end === false || $end <= $start) {
    fwrite(STDERR, "FAIL: handler not found\n");
    exit(1);
}
$handler = substr($source, $start, $end - $start);
$required = [
    <<<'PHP'
$sessionIdentity = $identity;
PHP,
    <<<'PHP'
$sessionIdentity['id'] = $userId;
PHP,
    <<<'PHP'
$sessionIdentity['email'] = (string) ($identity['user_email'] ?? '');
PHP,
    <<<'PHP'
$sessionIdentity['name'] = (string) ($identity['user_name'] ?? '');
PHP,
    <<<'PHP'
throw new \RuntimeException('Invalid linked staff identity');
PHP,
    <<<'PHP'
Auth::login($sessionIdentity, false, false)
PHP,
    <<<'PHP'
Auth::login($sessionIdentity, false, true)
PHP,
    <<<'PHP'
Auth::login($sessionIdentity, true, false)
PHP,
];
foreach ($required as $needle) {
    if (!str_contains($handler, $needle)) {
        fwrite(STDERR, "FAIL: identity mapping or MFA branch guard missing\n");
        exit(1);
    }
}
if (str_contains($handler, 'Auth::login($identity,') ||
    substr_count($handler, 'Auth::login($sessionIdentity,') !== 3) {
    fwrite(STDERR, "FAIL: unsafe Google identity passed to Auth::login\n");
    exit(1);
}
echo "GOOGLE_MFA_IDENTITY_MAPPING_REGRESSION=PASS\n";
