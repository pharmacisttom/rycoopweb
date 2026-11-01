<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Router;
use App\Middlewares\RoleMiddleware;
use App\Middlewares\{AuthMiddleware,PermissionMiddleware,CsrfMiddleware};
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
        self::assertMemberPrivacyGate();
        self::assertMemberActivationSafety();
        self::assertLegacyMigrationBackupRestoreGate();

        if (self::$failures === 0) {
            echo "Security regression tests passed.\n";
            return true;
        }

        echo self::$failures . " security regression test(s) failed.\n";
        return false;
    }

    private static function assertLegacyMigrationBackupRestoreGate(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/Services/LegacyMemberMigrationService.php');
        self::assert($source !== false && str_contains($source, "config('security.dual_control.backup_restore_execution_enabled', false)"), 'migration checks actual nested backup restore safety flag');
        self::assert((bool)config('security.dual_control.backup_restore_execution_enabled', false) === filter_var(env('BACKUP_RESTORE_EXECUTION_ENABLED', false), FILTER_VALIDATE_BOOL), 'backup restore safety flag resolves through runtime configuration');
    }

    private static function assertMemberActivationSafety(): void
    {
        $flags = file_get_contents(__DIR__ . '/../config/features.php');
        self::assert($flags !== false && str_contains($flags, "env('FEATURE_MEMBER_ACTIVATION', false)"), 'member activation disabled by default');
        $routes = file_get_contents(__DIR__ . '/../config/routes.php');
        self::assert($routes !== false && str_contains($routes, "new RateLimitMiddleware('member_activation_redeem', 6, 900)"), 'public activation rate limited');
        self::assert($routes !== false && str_contains($routes, "new RoleMiddleware(['super_admin']), CsrfMiddleware::class"), 'member invitation issuance reserved for super admin and CSRF');
        $service = file_get_contents(__DIR__ . '/../app/Services/MemberActivationService.php');
        self::assert($service !== false && str_contains($service, 'random_bytes(32)'), 'first-use invitations use cryptographic randomness');
        self::assert($service !== false && str_contains($service, "hash('sha256',\$code)"), 'only hashed invitation codes stored');
        self::assert($service !== false && str_contains($service, "status='active'"), 'activation updates user only after invite verification');
        self::assert($service !== false && str_contains($service, "used_at IS NULL"), 'single-use invitation enforced');
        $page = file_get_contents(__DIR__ . '/../src/pages/MemberActivationPage.jsx');
        self::assert($page !== false && str_contains($page, 'autoComplete="new-password"'), 'activation page requests a new user-chosen password');
    }

    private static function assertMemberPrivacyGate(): void
    {
        $routes = file_get_contents(__DIR__ . '/../config/routes.php');
        self::assert($routes !== false && str_contains($routes, "'/api/member/privacy'"), 'member privacy status and record routes exist');
        self::assert($routes !== false && str_contains($routes, "PrivacyController@record', [AuthMiddleware::class, new RoleMiddleware(['member']), CsrfMiddleware::class]"), 'member PDPA writes require auth, member role and CSRF');
        $gate = file_get_contents(__DIR__ . '/../app/Middlewares/MemberMiddleware.php');
        self::assert($gate !== false && str_contains($gate, 'MemberPrivacyService::acknowledged'), 'member middleware enforces privacy notice acknowledgement');
        $financial = file_get_contents(__DIR__ . '/../app/Controllers/Member/DividendController.php');
        self::assert($financial !== false && str_contains($financial, 'MemberPrivacyService::acknowledged'), 'financial endpoint blocks access before notice acknowledgement');
        $privacy = file_get_contents(__DIR__ . '/../app/Services/MemberPrivacyService.php');
        self::assert($privacy !== false && str_contains($privacy, 'optional_cooperative_updates'), 'optional consent is purpose-specific and separate');
        self::assert($privacy !== false && str_contains($privacy, "'withdrawn'"), 'optional consent can be withdrawn');
        $page = file_get_contents(__DIR__ . '/../src/pages/MemberDividendsPage.jsx');
        self::assert($page !== false && !str_contains($page, 'รหัสผ่านเริ่มต้นคือเลขสมาชิก'), 'member page never advertises a predictable initial password');
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
        self::assertRouteHasRoles($routes, '/staff/dashboard', ['staff', 'manager', 'executive', 'finance', 'loan_officer', 'welfare_officer', 'document_officer', 'complaint_officer', 'auditor', 'it_admin']);
        self::assertRouteHasRoles($routes, '/admin/dashboard', ['super_admin']);
        self::assertRouteHasRoles($routes, '/dashboard', ['super_admin']);
        self::assertRouteHasRoles($routes, '/api/admin/users', ['super_admin']);
        self::assertRouteHasRoles($routes, '/api/admin/access/roles', ['super_admin']);
        foreach([
            ['/api/admin/members/dashboard','GET','members','view'],
            ['/api/admin/members/health','GET','members','view'],
            ['/api/admin/members','GET','members','view'],
            ['/api/admin/members/{id}','GET','members','view'],
            ['/api/admin/members/{id}','POST','members','update'],
            ['/api/admin/dividends/preview','POST','dividends','import'],
            ['/api/admin/dividends/confirm','POST','dividends','import'],
            ['/api/admin/news/{id}/update','POST','news','update'],
            ['/api/admin/news/{id}/publish','POST','news','publish'],
            ['/api/admin/faqs','POST','faqs','create'],
            ['/api/admin/faqs/{id}/update','POST','faqs','update'],
            ['/api/admin/faqs/{id}/delete','POST','faqs','delete'],
        ] as [$path,$method,$module,$action])self::assertPermissionRoute($routes,$path,$method,$module,$action);
    }

    private static function assertPermissionRoute(array $routes,string $path,string $method,string $module,string $action):void
    {
        $route=null;foreach($routes as $candidate)if($candidate['path']===$path&&$candidate['method']===$method){$route=$candidate;break;}
        self::assert($route!==null,"$method $path is registered");if(!$route)return;
        self::assert(in_array(AuthMiddleware::class,$route['middlewares'],true),"$method $path requires authentication");
        $matched=false;foreach($route['middlewares'] as $guard)if($guard instanceof PermissionMiddleware){
            $matched=$matched||((new ReflectionProperty(PermissionMiddleware::class,'module'))->getValue($guard)===$module&&(new ReflectionProperty(PermissionMiddleware::class,'action'))->getValue($guard)===$action);
        }
        self::assert($matched,"$method $path requires $module.$action");
        if($method!=='GET')self::assert(in_array(CsrfMiddleware::class,$route['middlewares'],true),"$method $path requires CSRF");
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
        self::assert(config('features.member_portal', true) === filter_var(env('FEATURE_MEMBER_PORTAL', false), FILTER_VALIDATE_BOOL), 'member portal respects explicit feature flag and defaults to disabled');
        self::assert(config('features.member_login', false) === filter_var(env('FEATURE_MEMBER_LOGIN', true), FILTER_VALIDATE_BOOL), 'member login respects explicit feature flag and defaults to dividend access');

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
