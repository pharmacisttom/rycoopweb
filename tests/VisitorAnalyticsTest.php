<?php

declare(strict_types=1);

// Comprehensive 20-Scenario Automated Test Suite for RYCOOPWEB 3.0 Visitor Counter & Web Analytics
require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\Admin\ControlCenterApiController;
use App\Controllers\Api\SpaApiController;
use App\Core\{Auth, Database, Request, Response};
use App\Services\{AdminControlCenterService, VisitorCounterService};

final class VisitorAnalyticsCapturedResponse extends Response
{
    public int $status = 200;
    public array $body = [];

    public function json(array $data, int $status = 200): void
    {
        $this->body = $data;
        $this->status = $status;
    }

    public function redirect(string $url, int $status = 302): void
    {
        $this->status = $status;
    }
}

$sessionDirectory = dirname(__DIR__) . '/storage/tmp/visitor-analytics-test-sessions';
if (!is_dir($sessionDirectory)) {
    mkdir($sessionDirectory, 0700, true);
}
putenv('SESSION_SAVE_PATH=' . $sessionDirectory);
putenv('SESSION_SECURE=false');

$_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/api/public/visitors/summary',
    'SCRIPT_NAME' => '/index.php',
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0.0.0 Safari/537.36',
    'REMOTE_ADDR' => '203.113.10.25',
];
$_GET = $_POST = $_FILES = [];

function createVisitorTestPdo(): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->sqliteCreateFunction('NOW', static fn(): string => date('Y-m-d H:i:s'));
    $pdo->exec("
    CREATE TABLE website_visits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id TEXT NOT NULL,
        ip_address TEXT NOT NULL,
        page_url TEXT NOT NULL,
        page_title TEXT DEFAULT NULL,
        device_type TEXT DEFAULT 'desktop',
        browser TEXT DEFAULT 'Other',
        os TEXT DEFAULT 'Other',
        referrer TEXT DEFAULT NULL,
        visited_at TEXT NOT NULL
    );
    CREATE INDEX idx_visited_at ON website_visits(visited_at);
    CREATE INDEX idx_session_id ON website_visits(session_id);
    CREATE INDEX idx_device_type ON website_visits(device_type);

    CREATE TABLE visitor_daily_summary (
        visit_date TEXT PRIMARY KEY,
        unique_visitors INTEGER NOT NULL DEFAULT 0,
        total_page_views INTEGER NOT NULL DEFAULT 0,
        desktop_views INTEGER NOT NULL DEFAULT 0,
        mobile_views INTEGER NOT NULL DEFAULT 0,
        tablet_views INTEGER NOT NULL DEFAULT 0,
        updated_at TEXT DEFAULT NULL
    );

    CREATE TABLE visitor_counters (
        metric_key TEXT PRIMARY KEY,
        metric_value INTEGER NOT NULL DEFAULT 0,
        updated_at TEXT DEFAULT NULL
    );

    CREATE TABLE users (
        id INTEGER PRIMARY KEY,
        username TEXT,
        name TEXT,
        email TEXT,
        department TEXT,
        status TEXT,
        two_factor_enabled INTEGER DEFAULT 1,
        two_factor_secret TEXT,
        two_factor_recovery_codes TEXT,
        two_factor_confirmed_at TEXT,
        last_login_at TEXT,
        locked_until TEXT,
        created_at TEXT,
        deleted_at TEXT
    );
    CREATE TABLE roles (
        id INTEGER PRIMARY KEY,
        name TEXT,
        slug TEXT,
        description TEXT,
        updated_at TEXT
    );
    CREATE TABLE user_roles (
        user_id INTEGER,
        role_id INTEGER
    );
    CREATE TABLE permissions (
        id INTEGER PRIMARY KEY,
        module TEXT,
        action TEXT
    );
    CREATE TABLE role_permissions (
        role_id INTEGER,
        permission_id INTEGER
    );
    CREATE TABLE audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        module TEXT,
        action TEXT,
        record_id TEXT,
        old_values TEXT,
        new_values TEXT,
        ip_address TEXT,
        user_agent TEXT,
        created_at TEXT
    );
    ");

    $pdo->exec("
    INSERT INTO roles (id, name, slug) VALUES
        (1, 'ผู้ดูแลระบบสูงสุด', 'super_admin'),
        (2, 'ผู้ตรวจสอบกิจการ', 'auditor'),
        (3, 'เจ้าหน้าที่ทั่วไป', 'staff'),
        (4, 'สมาชิกสหกรณ์', 'member');

    INSERT INTO users (id, username, name, status) VALUES
        (1, 'admin', 'Super Admin', 'active'),
        (2, 'auditor1', 'Internal Auditor', 'active'),
        (3, 'staff1', 'General Staff', 'active'),
        (4, 'member1', 'Coop Member', 'active');

    INSERT INTO user_roles (user_id, role_id) VALUES
        (1, 1), (2, 2), (3, 3), (4, 4);
    ");

    return $pdo;
}

$pdo = createVisitorTestPdo();
(new ReflectionProperty(Database::class, 'instance'))->setValue(null, $pdo);

$results = [];
function assertScenario(bool $condition, string $label): void
{
    global $results;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    $results[] = "PASS: {$label}";
}

$uaDesktop = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0.0.0 Safari/537.36';
$uaMobile = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1';

// Set deterministic clock in Asia/Bangkok
VisitorCounterService::setTestNow('2026-10-15 10:00:00');

// ---------------------------------------------------------------------------
// Scenario 15 (Part A): True Zero Before Any Traffic (No Fabricated Offsets)
// ---------------------------------------------------------------------------
$initialStats = VisitorCounterService::getPublicStats();
assertScenario(
    $initialStats['available'] === true
    && $initialStats['online_visitors'] === 0
    && $initialStats['today_visitors'] === 0
    && $initialStats['month_visitors'] === 0
    && $initialStats['total_visitors'] === 0
    && $initialStats['total_page_views'] === 0,
    'Scenario 15A: Public stats return true zero (0) when database has no visits (no fabricated offsets)'
);

// ---------------------------------------------------------------------------
// Scenario 1: First visit from a new browser
// ---------------------------------------------------------------------------
$res1 = VisitorCounterService::recordVisit([
    'visitor_id' => 'vid_browser_alpha_123456',
    'session_id' => 'sid_session_alpha_001',
    'page_url' => '/',
    'page_title' => 'หน้าแรกสหกรณ์',
    'referrer' => 'https://www.google.com/search?q=rayongcoop',
], $uaDesktop, '203.113.10.25');

$statsAfter1 = VisitorCounterService::getPublicStats();
assertScenario(
    ($res1['recorded'] ?? false) === true
    && $statsAfter1['today_visitors'] === 1
    && $statsAfter1['month_visitors'] === 1
    && $statsAfter1['total_visitors'] === 1
    && $statsAfter1['online_visitors'] === 1
    && $statsAfter1['today_page_views'] === 1,
    'Scenario 1: First visit from a new browser increments visitor, session, online, and page view counts to 1'
);

// ---------------------------------------------------------------------------
// Scenario 2 & 9: Refreshing the same page repeatedly / Duplicate event submission within 15s
// ---------------------------------------------------------------------------
VisitorCounterService::setTestNow('2026-10-15 10:00:05'); // 5 seconds later
$resDup = VisitorCounterService::recordVisit([
    'visitor_id' => 'vid_browser_alpha_123456',
    'session_id' => 'sid_session_alpha_001',
    'page_url' => '/',
    'page_title' => 'หน้าแรกสหกรณ์',
], $uaDesktop, '203.113.10.25');

$statsAfterDup = VisitorCounterService::getPublicStats();
assertScenario(
    ($resDup['recorded'] ?? true) === false
    && ($resDup['reason'] ?? '') === 'duplicate_suppressed'
    && $statsAfterDup['today_visitors'] === 1
    && $statsAfterDup['today_page_views'] === 1,
    'Scenario 2 & 9: Refreshing same page / duplicate submission within 15s is suppressed and does not inflate page views'
);

// ---------------------------------------------------------------------------
// Scenario 3: Navigating between public pages
// ---------------------------------------------------------------------------
VisitorCounterService::setTestNow('2026-10-15 10:01:00');
$resNav = VisitorCounterService::recordVisit([
    'visitor_id' => 'vid_browser_alpha_123456',
    'session_id' => 'sid_session_alpha_001',
    'page_url' => '/loans',
    'page_title' => 'บริการสินเชื่อ',
], $uaDesktop, '203.113.10.25');

$statsAfterNav = VisitorCounterService::getPublicStats();
assertScenario(
    ($resNav['recorded'] ?? false) === true
    && $statsAfterNav['today_visitors'] === 1
    && $statsAfterNav['today_sessions'] === 1
    && $statsAfterNav['today_page_views'] === 2,
    'Scenario 3: Navigating to a second public page increments page_views to 2 while keeping unique visitors and sessions at 1'
);

// ---------------------------------------------------------------------------
// Scenario 4: Opening multiple tabs in the same browser
// ---------------------------------------------------------------------------
VisitorCounterService::setTestNow('2026-10-15 10:02:00');
$resTab2 = VisitorCounterService::recordVisit([
    'visitor_id' => 'vid_browser_alpha_123456',
    'session_id' => 'sid_session_alpha_001',
    'page_url' => '/deposits',
    'page_title' => 'บริการเงินฝาก',
], $uaDesktop, '203.113.10.25');

$statsAfterTab2 = VisitorCounterService::getPublicStats();
assertScenario(
    ($resTab2['recorded'] ?? false) === true
    && $statsAfterTab2['today_visitors'] === 1
    && $statsAfterTab2['today_sessions'] === 1
    && $statsAfterTab2['online_visitors'] === 1
    && $statsAfterTab2['today_page_views'] === 3,
    'Scenario 4: Multiple tabs in the same browser share visitor_id and session_id without duplicating unique or online visitor counts'
);

// ---------------------------------------------------------------------------
// Scenario 5: Session expiration after 30-minute inactivity threshold
// ---------------------------------------------------------------------------
VisitorCounterService::setTestNow('2026-10-15 10:40:00'); // 38 minutes after 10:02:00
$resNewSession = VisitorCounterService::recordVisit([
    'visitor_id' => 'vid_browser_alpha_123456',
    'session_id' => 'sid_session_alpha_001', // even if client sends old session_id, server rotates after >30m inactivity
    'page_url' => '/welfare',
    'page_title' => 'สวัสดิการสมาชิก',
], $uaDesktop, '203.113.10.25');

$statsAfterSessionExpire = VisitorCounterService::getPublicStats();
assertScenario(
    ($resNewSession['recorded'] ?? false) === true
    && $statsAfterSessionExpire['today_visitors'] === 1
    && $statsAfterSessionExpire['today_sessions'] === 2
    && $statsAfterSessionExpire['today_page_views'] === 4,
    'Scenario 5: Visit after >30 minutes of inactivity starts a new session (today_sessions = 2) while keeping today_visitors = 1'
);

// ---------------------------------------------------------------------------
// Scenario 6: Visit on a new calendar day (Asia/Bangkok)
// ---------------------------------------------------------------------------
VisitorCounterService::setTestNow('2026-10-16 00:05:00');
$resNextDay = VisitorCounterService::recordVisit([
    'visitor_id' => 'vid_browser_alpha_123456',
    'session_id' => 'sid_session_alpha_day2',
    'page_url' => '/',
    'page_title' => 'หน้าแรกสหกรณ์',
], $uaDesktop, '203.113.10.25');

$statsNextDay = VisitorCounterService::getPublicStats();
assertScenario(
    ($resNextDay['recorded'] ?? false) === true
    && $statsNextDay['today_visitors'] === 1
    && $statsNextDay['today_page_views'] === 1
    && $statsNextDay['month_visitors'] === 1
    && $statsNextDay['total_visitors'] === 1
    && $statsNextDay['total_page_views'] === 5,
    'Scenario 6: Visit on a new calendar day resets today_visitors to 1 while same visitor remains 1 unique visitor for the month'
);

// ---------------------------------------------------------------------------
// Scenario 7 & 8: Month boundary transition & Asia/Bangkok timezone handling
// ---------------------------------------------------------------------------
// Test 2026-10-31 23:59:30 Asia/Bangkok vs 2026-11-01 00:00:30 Asia/Bangkok
VisitorCounterService::setTestNow('2026-10-31 23:59:30');
VisitorCounterService::recordVisit([
    'visitor_id' => 'vid_browser_bravo_999999',
    'session_id' => 'sid_bravo_oct',
    'page_url' => '/news',
    'page_title' => 'ข่าวประชาสัมพันธ์',
], $uaMobile, '203.113.20.55');

$statsOctEnd = VisitorCounterService::getPublicStats();
assertScenario(
    $statsOctEnd['month_visitors'] === 2 && $statsOctEnd['total_visitors'] === 2,
    'Scenario 8: Late-night visit at 23:59:30 Asia/Bangkok is bucketed into October'
);

VisitorCounterService::setTestNow('2026-11-01 00:00:30');
VisitorCounterService::recordVisit([
    'visitor_id' => 'vid_browser_bravo_999999',
    'session_id' => 'sid_bravo_nov',
    'page_url' => '/contact',
    'page_title' => 'ติดต่อสหกรณ์',
], $uaMobile, '203.113.20.55');

$statsNovStart = VisitorCounterService::getPublicStats();
assertScenario(
    $statsNovStart['today_visitors'] === 1
    && $statsNovStart['month_visitors'] === 1
    && $statsNovStart['total_visitors'] === 2,
    'Scenario 7: Month boundary transition at 00:00:30 Asia/Bangkok resets month_visitors to 1 while preserving total_visitors = 2'
);

// ---------------------------------------------------------------------------
// Scenario 11: Bot user-agent exclusion
// ---------------------------------------------------------------------------
$botUAs = [
    'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'curl/8.4.0',
    'Mozilla/5.0 HeadlessChrome/125.0.0.0',
    'UptimeRobot/2.0',
];
foreach ($botUAs as $botUa) {
    $botRes = VisitorCounterService::recordVisit([
        'visitor_id' => 'vid_bot_' . md5($botUa),
        'session_id' => 'sid_bot_' . md5($botUa),
        'page_url' => '/',
    ], $botUa, '66.249.66.1');
    assertScenario(
        ($botRes['recorded'] ?? true) === false && ($botRes['reason'] ?? '') === 'bot_filtered',
        'Scenario 11: Bot signature rejected (' . substr($botUa, 0, 24) . ')'
    );
}
$statsAfterBots = VisitorCounterService::getPublicStats();
assertScenario(
    $statsAfterBots['total_visitors'] === 2 && $statsAfterBots['today_page_views'] === 1,
    'Scenario 11B: Bot hits do not increment public visitor or page view counters'
);

// ---------------------------------------------------------------------------
// Scenario 12: Private-route exclusion (/admin/*, /member/*, /api/*, /login, MFA)
// ---------------------------------------------------------------------------
$privatePaths = [
    '/admin/dashboard',
    '/admin/security/mfa',
    '/member/dashboard',
    '/member/dividends',
    '/api/admin/users',
    '/login',
    '/admin/login',
    '/portal',
    '/verify-tax-cert/secret-token-xyz',
];
foreach ($privatePaths as $privPath) {
    $privRes = VisitorCounterService::recordVisit([
        'visitor_id' => 'vid_private_tester',
        'session_id' => 'sid_private_tester',
        'page_url' => $privPath,
    ], $uaDesktop, '203.113.50.10');
    assertScenario(
        ($privRes['recorded'] ?? true) === false && ($privRes['reason'] ?? '') === 'excluded_route',
        "Scenario 12: Private route excluded ({$privPath})"
    );
}

// ---------------------------------------------------------------------------
// Scenario 13: Query-string secret removal
// ---------------------------------------------------------------------------
VisitorCounterService::setTestNow('2026-11-01 00:05:00');
$resSecretUrl = VisitorCounterService::recordVisit([
    'visitor_id' => 'vid_browser_bravo_999999',
    'session_id' => 'sid_bravo_nov',
    'page_url' => '/news/42?token=eyJhbGciOiJIUzI1NiJ9&member_no=009123&code=123456#private',
    'page_title' => 'ข่าวสารที่ 42',
    'referrer' => 'https://auth.rayongcoop.org/callback?code=oauth_secret_999&state=xyz',
], $uaMobile, '203.113.20.55');

$storedVisit = Database::first("SELECT page_url, referrer FROM website_visits ORDER BY id DESC LIMIT 1");
assertScenario(
    ($resSecretUrl['recorded'] ?? false) === true
    && ($storedVisit['page_url'] ?? '') === '/news/42'
    && strpos((string) ($storedVisit['referrer'] ?? ''), 'oauth_secret_999') === false,
    'Scenario 13: Query-string tokens, member_no, and fragments are completely stripped from stored page_url and referrer'
);

// ---------------------------------------------------------------------------
// Scenario 17: SQL injection resistance in tracking inputs
// ---------------------------------------------------------------------------
VisitorCounterService::setTestNow('2026-11-01 00:10:00');
$sqliRes = VisitorCounterService::recordVisit([
    'visitor_id' => "vid_sqli'; DROP TABLE website_visits; --",
    'session_id' => "sid_sqli' OR '1'='1",
    'page_url' => "/loans'; DROP TABLE website_visits; --",
    'page_title' => "<script>alert(1)</script>'; DROP TABLE users; --",
    'referrer' => "https://evil.example.com/?q=' OR 1=1 --",
], $uaDesktop, '203.113.77.88');

$tableStillExists = (int) Database::value("SELECT COUNT(*) FROM website_visits");
assertScenario(
    ($sqliRes['recorded'] ?? true) === false
    && ($sqliRes['reason'] ?? '') === 'excluded_route'
    && $tableStillExists > 0,
    'Scenario 17: SQL injection payloads in tracking inputs are safely rejected/parameterized and tables remain intact'
);

// ---------------------------------------------------------------------------
// Scenario 10 & 20: Concurrent / Burst Traffic & Performance under expected load
// ---------------------------------------------------------------------------
$startPerf = microtime(true);
for ($i = 1; $i <= 30; $i++) {
    VisitorCounterService::setTestNow(sprintf('2026-11-01 01:%02d:00', $i % 60));
    $r = VisitorCounterService::recordVisit([
        'visitor_id' => "vid_concurrent_user_{$i}",
        'session_id' => "sid_concurrent_sess_{$i}",
        'page_url' => ($i % 2 === 0) ? '/calculator' : '/faqs',
        'page_title' => 'ทดสอบทราฟฟิกพร้อมกัน',
    ], $uaDesktop, "203.113.90.{$i}");
    if (($r['recorded'] ?? false) !== true) {
        fwrite(STDERR, "FAIL: Concurrent visit {$i} was not recorded\n");
        exit(1);
    }
}
$elapsedPerfMs = (microtime(true) - $startPerf) * 1000;
$statsAfterConcurrent = VisitorCounterService::getPublicStats();
assertScenario(
    $statsAfterConcurrent['today_visitors'] === 31
    && $elapsedPerfMs < 2500,
    sprintf('Scenario 10 & 20: 30 distinct visitors processed accurately in %.1f ms (< 2500 ms budget)', $elapsedPerfMs)
);

// ---------------------------------------------------------------------------
// Scenario 14: Rate limiting under burst requests (>60 events/minute from single visitor)
// ---------------------------------------------------------------------------
VisitorCounterService::setTestNow('2026-11-01 02:00:00');
$rateLimitedTriggered = false;
$allowedPages = ['/', '/about', '/board', '/deposits', '/loans', '/calculator', '/welfare', '/eservice', '/documents', '/news', '/contact', '/faqs'];
for ($hit = 1; $hit <= 65; $hit++) {
    // Advance clock by 16 seconds only every 10 hits or vary path so dedup doesn't catch it before rate limit
    $path = '/news/' . $hit;
    $burstRes = VisitorCounterService::recordVisit([
        'visitor_id' => 'vid_burst_attacker_001',
        'session_id' => 'sid_burst_attacker_001',
        'page_url' => $path,
    ], $uaDesktop, '203.113.99.99');
    if (($burstRes['reason'] ?? '') === 'rate_limited') {
        $rateLimitedTriggered = true;
        break;
    }
}
assertScenario(
    $rateLimitedTriggered === true,
    'Scenario 14: Burst traffic exceeding 60 events/minute from a single visitor triggers rate_limited protection'
);

// ---------------------------------------------------------------------------
// Scenario 15B: Public Counter & Detailed Analytics API accuracy
// ---------------------------------------------------------------------------
$detailedPublic = VisitorCounterService::getDetailedAnalytics(30, false);
assertScenario(
    $detailedPublic['available'] === true
    && isset($detailedPublic['summary']['online_now'])
    && isset($detailedPublic['timeline'])
    && isset($detailedPublic['monthly_trend'])
    && isset($detailedPublic['hourly_distribution'])
    && isset($detailedPublic['devices'])
    && isset($detailedPublic['top_pages'])
    && isset($detailedPublic['definitions'])
    && !isset($detailedPublic['recent_visits'])
    && !isset($detailedPublic['observability']),
    'Scenario 15B: Public analytics returns real aggregations and metric definitions without exposing admin-only recent_visits or observability'
);

// ---------------------------------------------------------------------------
// Scenario 18: Unauthorized access to Admin Analytics blocked (403)
// ---------------------------------------------------------------------------
function invokeControlCenterEndpoint(int $userId, string $roleSlug, string $method, string $action, array $query = [], array $post = []): VisitorAnalyticsCapturedResponse
{
    $_GET = $query;
    $_POST = $post;
    $_SERVER['REQUEST_METHOD'] = $method;
    Auth::login([
        'id' => $userId,
        'username' => "user_{$userId}",
        'name' => "User {$userId}",
        'email' => "user_{$userId}@example.invalid",
        'role_slug' => $roleSlug,
        'role_name' => $roleSlug,
    ], true);
    $req = new Request();
    $res = new VisitorAnalyticsCapturedResponse();
    $ctrl = new ControlCenterApiController($req, $res);
    $ctrl->$action();
    return $res;
}

$memberAdminRes = invokeControlCenterEndpoint(4, 'member', 'GET', 'visitorAnalytics');
$staffAdminRes = invokeControlCenterEndpoint(3, 'staff', 'GET', 'visitorAnalytics');
$superAdminRes = invokeControlCenterEndpoint(1, 'super_admin', 'GET', 'visitorAnalytics');

assertScenario(
    $memberAdminRes->status === 403
    && $staffAdminRes->status === 403
    && $superAdminRes->status === 200
    && ($superAdminRes->body['success'] ?? false) === true
    && isset($superAdminRes->body['data']['observability']['bot_filtered_events']),
    'Scenario 18: Member and unpermitted staff receive HTTP 403 on /api/admin/analytics/visitors while super_admin receives HTTP 200 with observability metrics'
);

// ---------------------------------------------------------------------------
// Scenario 19: Export permission validation & Audit Trail logging
// ---------------------------------------------------------------------------
$staffExportRes = invokeControlCenterEndpoint(3, 'staff', 'POST', 'exportVisitorAnalytics', [], ['days' => 30, 'format' => 'csv']);
$auditorExportRes = invokeControlCenterEndpoint(2, 'auditor', 'POST', 'exportVisitorAnalytics', [], ['days' => 30, 'format' => 'csv']);

$exportAuditLog = Database::first("SELECT module, action, record_id FROM audit_logs WHERE module = 'visitor_analytics' AND action = 'export' ORDER BY id DESC LIMIT 1");
assertScenario(
    $staffExportRes->status === 403
    && $auditorExportRes->status === 200
    && ($auditorExportRes->body['success'] ?? false) === true
    && ($exportAuditLog['record_id'] ?? '') === 'visitors_30d',
    'Scenario 19: Export endpoint enforces analytics.visitors.export / audit.export permission and writes an Audit Trail record'
);

// ---------------------------------------------------------------------------
// Scenario 16: Graceful API Failure State when Database is Unavailable (No Fake Numbers)
// ---------------------------------------------------------------------------
$pdo->exec("DROP TABLE website_visits; DROP TABLE visitor_daily_summary; DROP TABLE visitor_counters;");
$failedStats = VisitorCounterService::getPublicStats();
$reqFail = new Request();
$resFail = new VisitorAnalyticsCapturedResponse();
$spaCtrl = new SpaApiController($reqFail, $resFail);
$spaCtrl->visitorStats();

assertScenario(
    $failedStats['available'] === false
    && $failedStats['online_visitors'] === null
    && $failedStats['today_visitors'] === null
    && $resFail->status === 503
    && ($resFail->body['available'] ?? true) === false,
    'Scenario 16: Database failure returns HTTP 503 with available=false and null counters (never fabricated fallback numbers)'
);

VisitorCounterService::setTestNow(null);

foreach ($results as $line) {
    echo $line . PHP_EOL;
}
echo "ALL 20 VISITOR ANALYTICS SCENARIOS PASSED (" . count($results) . " assertions)\n";
