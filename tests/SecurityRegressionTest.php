<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Router;
use App\Middlewares\RoleMiddleware;
use App\Middlewares\FeatureFlagMiddleware;

final class SecurityRegressionTest
{
    private static int $failures = 0;

    public static function run(): bool
    {
        self::assertNoAuthenticationBypass();
        self::assertMemberLookupHasNoFallback();
        self::assertRouteRoleGuards();
        self::assertRateLimitUsesSharedStorage();
        self::assertLoginKeepsCsrfProtection();
        self::assertAdminDashboardImportsLoanProducts();
        self::assertMemberPortalIsDenyByDefault();

        if (self::$failures === 0) {
            echo "Security regression tests passed.\n";
            return true;
        }

        echo self::$failures . " security regression test(s) failed.\n";
        return false;
    }

    private static function assertNoAuthenticationBypass(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/Controllers/Admin/AuthController.php');
        self::assert($source !== false && !str_contains($source, "\$inputUsername === 'rayongcoop1'"), 'login controller contains no hardcoded username bypass');
        self::assert($source !== false && !str_contains($source, "\$password === 'coop1'"), 'login controller contains no hardcoded password bypass');
    }

    private static function assertMemberLookupHasNoFallback(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/Services/MemberPortalService.php');
        self::assert($source !== false && !str_contains($source, "member_no = 'MEM-2024-0001'"), 'member lookup does not select a default member');
        self::assert($source !== false && !str_contains($source, 'ORDER BY id ASC LIMIT 1'), 'member lookup does not select the first member as a fallback');

        foreach (['MemberPortalController.php', 'TaxCertificateController.php', 'SurveyController.php', 'SuggestionController.php', 'DividendEstimatorController.php'] as $file) {
            $controller = file_get_contents(__DIR__ . '/../app/Controllers/Member/' . $file);
            self::assert($controller !== false && !str_contains($controller, "'member_no' => 'MEM-2024-0001'"), "{$file} contains no default member profile");
        }
    }

    private static function assertRouteRoleGuards(): void
    {
        $router = new Router();
        require __DIR__ . '/../config/routes.php';

        $routes = (new \ReflectionProperty(Router::class, 'routes'))->getValue($router);
        self::assertRouteHasRoles($routes, '/member/dashboard', ['member']);
        self::assertRouteHasRoles($routes, '/staff/dashboard', ['staff', 'manager', 'executive', 'finance', 'loan_officer', 'welfare_officer', 'pr_officer', 'document_officer', 'complaint_officer', 'auditor', 'it_admin']);
        self::assertRouteHasRoles($routes, '/admin/dashboard', ['super_admin']);
        self::assertRouteHasRoles($routes, '/dashboard', ['super_admin']);
        self::assertRouteHasRoles($routes, '/api/admin/members/dashboard', ['super_admin', 'member_admin']);
        self::assertRouteHasRoles($routes, '/api/admin/members', ['super_admin', 'member_admin']);
        self::assertRouteHasRoles($routes, '/api/admin/members/{id}', ['super_admin', 'member_admin']);
        self::assertRouteHasRoles($routes, '/api/admin/dividends/preview', ['super_admin', 'member_admin']);
        self::assertRouteHasRoles($routes, '/api/admin/dividends/confirm', ['super_admin', 'member_admin']);
        self::assertRouteHasRoles($routes, '/api/admin/users', ['super_admin']);
    }

    private static function assertRateLimitUsesSharedStorage(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/Middlewares/RateLimitMiddleware.php');
        self::assert($source !== false && str_contains($source, 'rate_limit_attempts'), 'rate limit uses shared database storage');
        self::assert($source !== false && !str_contains($source, 'Session::set("{$cacheKey}:attempts"'), 'rate limit does not store counters in the browser session');
    }

    private static function assertLoginKeepsCsrfProtection(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/Middlewares/CsrfMiddleware.php');
        self::assert($source !== false && !str_contains($source, 'Allow initial Ajax login request from SPA'), 'CSRF protection is not bypassed for AJAX login');
    }

    private static function assertAdminDashboardImportsLoanProducts(): void
    {
        $source = file_get_contents(__DIR__ . '/../src/pages/AdminDashboardPage.jsx');
        self::assert(
            $source !== false && preg_match('/import\s*\{[^}]*\bLOAN_PRODUCTS\b[^}]*\}\s*from\s*[\'\"]\.\.\/data\/mockData[\'\"]/', $source) === 1,
            'admin dashboard imports LOAN_PRODUCTS before using it'
        );
    }

    private static function assertMemberPortalIsDenyByDefault(): void
    {
        self::assert(config('features.member_portal', true) === false, 'member portal defaults to disabled');
        self::assert(config('features.member_login', false) === true, 'member login defaults to enabled for dividend access');

        $router = new Router();
        require __DIR__ . '/../config/routes.php';
        $routes = (new \ReflectionProperty(Router::class, 'routes'))->getValue($router);
        foreach (['/api/member/dashboard', '/member/dashboard', '/portal'] as $path) {
            $matched = array_values(array_filter($routes, static fn(array $route): bool => $route['path'] === $path));
            $guarded = false;
            foreach ($matched as $route) {
                foreach ($route['middlewares'] as $middleware) {
                    if ($middleware instanceof FeatureFlagMiddleware) {
                        $guarded = true;
                    }
                }
            }
            self::assert($guarded, "{$path} is protected by a backend feature flag");
        }

        $auth = file_get_contents(__DIR__ . '/../app/Core/Auth.php');
        self::assert($auth !== false && !str_contains($auth, "role_slug'] ?? 'super_admin'"), 'missing role never defaults to super_admin');

        $controller = file_get_contents(__DIR__ . '/../app/Controllers/Admin/AuthController.php');
        self::assert($controller !== false && str_contains($controller, "config('features.member_login', false)"), 'member login is denied by the backend when disabled');
        self::assert($controller !== false && str_contains($controller, 'Account has no assigned role'), 'login fails closed when no role is assigned');
    }

    private static function assertRouteHasRoles(array $routes, string $path, array $expectedRoles): void
    {
        foreach ($routes as $route) {
            if ($route['path'] !== $path) {
                continue;
            }

            foreach ($route['middlewares'] as $middleware) {
                if ($middleware instanceof RoleMiddleware) {
                    $roles = (new \ReflectionProperty(RoleMiddleware::class, 'allowedRoles'))->getValue($middleware);
                    self::assert($roles === $expectedRoles, "{$path} permits only " . implode(', ', $expectedRoles));
                    return;
                }
            }
            self::assert(false, "{$path} has a RoleMiddleware");
            return;
        }

        self::assert(false, "{$path} is registered");
    }

    private static function assert(bool $condition, string $message): void
    {
        if ($condition) {
            echo "PASS: {$message}\n";
            return;
        }

        self::$failures++;
        echo "FAIL: {$message}\n";
    }
}

exit(SecurityRegressionTest::run() ? 0 : 1);
