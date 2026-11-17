<?php
declare(strict_types=1);

/**
 * AdminLTE visual refactors must not remove member login, dividend checks or
 * their access controls. Static checks only: never contacts a live database.
 */
$root = dirname(__DIR__);
$routes = file_get_contents($root . '/config/routes.php');
$app = file_get_contents($root . '/src/App.jsx');
$shell = file_get_contents($root . '/src/components/admin/AdminControlCenterShell.jsx');
if ($routes === false || $app === false || $shell === false) {
    throw new RuntimeException('Required route files missing');
}
foreach ([
    "/api/member/dividends",
    "Member\\DividendController@index",
    "new RoleMiddleware(['member'])",
    "MemberMiddleware::class",
    "new FeatureFlagMiddleware('member_portal')",
    "'api/member'",
    "'member'",
    "Member\\TaxCertificateController@index",
    "Member\\MemberPortalController@dashboard",
] as $token) {
    if (!str_contains($routes, $token)) {
        throw new RuntimeException("Member route contract missing: {$token}");
    }
}
foreach ([
    'path="/member/login"',
    'path="/member/dividends"',
    'MemberDividendsPage',
    'MemberDashboardPage',
    'memberPortalEnabled',
] as $token) {
    if (!str_contains($app, $token)) {
        throw new RuntimeException("React member portal contract missing: {$token}");
    }
}
foreach ([
    "'/admin/members'",
    "'/admin/dividends/import'",
    "'dividends.import'",
    'hasControlCenterAccess',
    'footerGroups.map',
] as $token) {
    if (!str_contains($shell, $token)) {
        throw new RuntimeException("Admin RBAC or system navigation missing: {$token}");
    }
}
echo "PASS: member login, dividend, tax, protected member API and Admin RBAC route contracts retained\n";
echo "NOTICE: static check only; browser PDPA and dividend UAT still required\n";
