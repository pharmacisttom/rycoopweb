<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use DateTimeImmutable;
use DateTimeZone;

/**
 * RYCOOPWEB 3.0 — Production Visitor Counter & Web Analytics Service
 *
 * Principles:
 * - 100% real database metrics (never fabricates or pads counts).
 * - Explicit Asia/Bangkok (+07:00) calendar day and month boundaries.
 * - Strict public-route allowlist & private-route exclusion (/admin/*, /member/*, /api/*, /login, etc.).
 * - Query-string secret stripping (never persists tokens, codes, or query parameters).
 * - Bot & synthetic traffic classification and exclusion.
 * - 30-minute session inactivity semantics and 15-second idempotent duplicate suppression.
 * - PDPA data minimization (masked IP only; pseudonymous visitor/session hashes).
 */
class VisitorCounterService
{
    public const TIMEZONE = 'Asia/Bangkok';
    public const SESSION_TIMEOUT_SECONDS = 1800; // 30 minutes
    public const ONLINE_WINDOW_SECONDS = 300;    // 5 minutes
    public const DEDUPE_WINDOW_SECONDS = 15;     // 15 seconds same-page refresh deduplication
    public const RETENTION_DAYS = 90;

    /**
     * Private/sensitive route prefixes that must NEVER be tracked.
     */
    private const EXCLUDED_PREFIXES = [
        '/admin',
        '/member',
        '/api',
        '/auth',
        '/login',
        '/logout',
        '/portal',
        '/dashboard',
        '/staff',
        '/profile',
        '/settings',
        '/edit-profile',
        '/e-tracking',
        '/tracking',
        '/loan-requests',
        '/verify-tax-cert',
        '/csrf-token',
        '/service-unavailable',
    ];

    /**
     * Exact public route patterns permitted for visitor tracking.
     */
    private const ALLOWED_PUBLIC_PATTERNS = [
        '#^/$#',
        '#^/about$#',
        '#^/board$#',
        '#^/board_committee$#',
        '#^/board_officer$#',
        '#^/statistics$#',
        '#^/visitor-stats$#',
        '#^/analytics$#',
        '#^/assets$#',
        '#^/financial-assets$#',
        '#^/deposits(?:/[a-zA-Z0-9_\-]+)?$#',
        '#^/rates$#',
        '#^/loans(?:/checklist)?$#',
        '#^/calculator$#',
        '#^/loan-checklist$#',
        '#^/loan-readiness$#',
        '#^/dividend-estimator$#',
        '#^/welfare$#',
        '#^/eservice$#',
        '#^/documents$#',
        '#^/announcements(?:/[a-zA-Z0-9_\-]+)?$#',
        '#^/events$#',
        '#^/calendar$#',
        '#^/news(?:/[a-zA-Z0-9_\-]+)?$#',
        '#^/contact$#',
        '#^/complaints(?:/track)?$#',
        '#^/faqs$#',
        '#^/privacy/(?:policy|cookies)$#',
        '#^/terms$#',
        '#^/surveys/[a-zA-Z0-9_\-]+$#',
        '#^/verify-receipt$#',
    ];

    /**
     * Bot, crawler, spider, and synthetic monitor signatures.
     */
    private const BOT_SIGNATURES = [
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'apis-google',
        'lighthouse', 'pagespeed', 'gtmetrix', 'pingdom', 'uptimerobot',
        'statuscake', 'datadog', 'newrelic', 'headlesschrome', 'phantomjs',
        'puppeteer', 'playwright', 'selenium', 'cypress', 'curl/', 'wget/',
        'python-requests', 'python-urllib', 'aiohttp', 'httpx', 'go-http-client',
        'java/', 'okhttp', 'axios/', 'node-fetch', 'undici', 'postmanruntime',
        'insomnia', 'facebookexternalhit', 'facebot', 'twitterbot', 'linkedinbot',
        'telegrambot', 'discordbot', 'whatsapp', 'line-poker', 'bytespider',
        'petalbot', 'semrushbot', 'ahrefsbot', 'mj12bot', 'dotbot', 'bingpreview',
        'yandex', 'baiduspider', 'duckduckbot', 'gptbot', 'claudebot', 'perplexitybot',
        'applebot', 'ia_archiver', 'screaming frog', 'nessus', 'nikto', 'sqlmap', 'nmap',
    ];

    /**
     * Current Asia/Bangkok DateTimeImmutable (supports optional test clock override).
     */
    private static ?DateTimeImmutable $testNow = null;

    public static function setTestNow(?string $isoOrDateTime): void
    {
        $tz = new DateTimeZone(self::TIMEZONE);
        self::$testNow = $isoOrDateTime !== null ? new DateTimeImmutable($isoOrDateTime, $tz) : null;
    }

    public static function now(): DateTimeImmutable
    {
        if (self::$testNow !== null) {
            return self::$testNow;
        }
        return new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE));
    }

    /**
     * Normalize and validate a candidate route path.
     * Strips query strings and fragments, blocks private routes, and enforces allowlist.
     */
    public static function normalizeAllowedPublicPath(string $rawUrl): ?string
    {
        $trimmed = trim($rawUrl);
        if ($trimmed === '' || strlen($trimmed) > 512) {
            return null;
        }

        // Reject null bytes, control chars, or SQL/script injection payloads
        if (preg_match('/[\x00-\x1F\x7F<>"\'`;]/', $trimmed)) {
            return null;
        }

        $parsedPath = parse_url($trimmed, PHP_URL_PATH);
        if (!is_string($parsedPath) || $parsedPath === '') {
            return null;
        }

        // Collapse multiple slashes and remove trailing slash (except root "/")
        $path = preg_replace('#/+#', '/', $parsedPath) ?? '/';
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        // Strictly reject excluded prefixes (case-insensitive)
        $lower = strtolower($path);
        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if ($lower === $prefix || str_starts_with($lower, $prefix . '/')) {
                return null;
            }
        }

        // Enforce explicit public route allowlist
        foreach (self::ALLOWED_PUBLIC_PATTERNS as $pattern) {
            if (preg_match($pattern, $path)) {
                return substr($path, 0, 250);
            }
        }

        return null;
    }

    /**
     * Sanitize referrer URL so query-string tokens/secrets are never persisted.
     */
    public static function sanitizeReferrer(?string $rawReferrer): ?string
    {
        if (!is_string($rawReferrer) || trim($rawReferrer) === '') {
            return null;
        }
        $trimmed = trim($rawReferrer);
        $parts = @parse_url($trimmed);
        if (!is_array($parts)) {
            return null;
        }
        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        $path = isset($parts['path']) ? (string) $parts['path'] : '/';
        $path = preg_replace('/[^a-zA-Z0-9_\-\/.]/', '', $path) ?? '/';
        $clean = ($host !== '' ? $host : '') . $path;
        return $clean !== '' ? substr($clean, 0, 250) : null;
    }

    /**
     * Determine whether a User-Agent string belongs to a bot, crawler, or synthetic check.
     */
    public static function isBotUserAgent(?string $userAgent): bool
    {
        if ($userAgent === null) {
            return true;
        }
        $ua = strtolower(trim($userAgent));
        if ($ua === '' || strlen($ua) < 10) {
            return true;
        }
        foreach (self::BOT_SIGNATURES as $sig) {
            if (str_contains($ua, $sig)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Record a page visit with bot filtering, private-route exclusion,
     * 30-minute session semantics, 15-second duplicate suppression, and PDPA IP masking.
     */
    public static function recordVisit(array $payload, ?string $userAgent, ?string $rawIp): array
    {
        $now = self::now();
        $nowStr = $now->format('Y-m-d H:i:s');
        $todayDate = $now->format('Y-m-d');

        // 1. Validate public route & strip query strings/secrets
        $rawPageUrl = (string) ($payload['page_url'] ?? '');
        $normalizedPath = self::normalizeAllowedPublicPath($rawPageUrl);
        if ($normalizedPath === null) {
            return [
                'success' => true,
                'recorded' => false,
                'reason' => 'excluded_route',
            ];
        }

        // 2. Filter bots and synthetic monitors
        if (self::isBotUserAgent($userAgent)) {
            self::incrementCounterMetric('bot_filtered_events', 1, $nowStr);
            return [
                'success' => true,
                'recorded' => false,
                'reason' => 'bot_filtered',
            ];
        }

        // 3. PDPA IP Masking (never store raw IP)
        $maskedIp = PdpaService::maskIpAddress($rawIp ?? '127.0.0.1');

        // 4. Derive pseudonymous visitor hash (16 hex chars) and session hash (16 hex chars)
        $clientVisitorId = trim((string) ($payload['visitor_id'] ?? ''));
        $clientSessionId = trim((string) ($payload['session_id'] ?? ''));
        $appKey = (string) (getenv('APP_KEY') ?: 'rycoop-visitor-privacy-salt');

        if ($clientVisitorId !== '' && preg_match('/^[A-Za-z0-9_\-]{8,64}$/', $clientVisitorId)) {
            $visitorHash = substr(hash_hmac('sha256', 'v:' . $clientVisitorId, $appKey), 0, 16);
        } elseif ($clientSessionId !== '' && preg_match('/^[A-Za-z0-9_\-:]{8,64}$/', $clientSessionId)) {
            $basePart = explode(':', $clientSessionId)[0];
            $visitorHash = substr(hash_hmac('sha256', 'v:' . $basePart, $appKey), 0, 16);
        } else {
            $visitorHash = substr(hash_hmac('sha256', 'v:' . $maskedIp . '|' . ($userAgent ?? '') . '|' . $todayDate, $appKey), 0, 16);
        }

        if ($clientSessionId !== '' && preg_match('/^[A-Za-z0-9_\-:]{8,64}$/', $clientSessionId)) {
            $rawSessionPart = str_contains($clientSessionId, ':') ? explode(':', $clientSessionId, 2)[1] : $clientSessionId;
            $sessionHash = substr(hash_hmac('sha256', 's:' . $rawSessionPart, $appKey), 0, 16);
        } else {
            $timeBucket = (int) floor($now->getTimestamp() / self::SESSION_TIMEOUT_SECONDS);
            $sessionHash = substr(hash_hmac('sha256', 's:' . $visitorHash . '|' . $timeBucket, $appKey), 0, 16);
        }

        $compositeSessionKey = $visitorHash . ':' . $sessionHash;

        try {
            // 5. Rate limit guard per visitor (max 60 accepted events per 60 seconds)
            $oneMinAgo = $now->modify('-60 seconds')->format('Y-m-d H:i:s');
            $recentBurst = (int) Database::value(
                "SELECT COUNT(*) FROM website_visits WHERE session_id LIKE ? AND visited_at >= ?",
                [$visitorHash . ':%', $oneMinAgo]
            );
            if ($recentBurst >= 60) {
                self::incrementCounterMetric('rate_limited_events', 1, $nowStr);
                return [
                    'success' => false,
                    'recorded' => false,
                    'reason' => 'rate_limited',
                    'message' => 'คำขอถี่เกินไป กรุณารอสักครู่',
                ];
            }

            // 6. Enforce 30-minute session inactivity timeout on server side
            $lastActivityAt = Database::value(
                "SELECT MAX(visited_at) FROM website_visits WHERE session_id = ?",
                [$compositeSessionKey]
            );
            if (is_string($lastActivityAt) && $lastActivityAt !== '') {
                $lastTs = (new DateTimeImmutable($lastActivityAt, new DateTimeZone(self::TIMEZONE)))->getTimestamp();
                if (($now->getTimestamp() - $lastTs) > self::SESSION_TIMEOUT_SECONDS) {
                    // Rotate session hash when previous activity was > 30 minutes ago
                    $sessionHash = substr(hash_hmac('sha256', 's:' . $sessionHash . '|' . $now->getTimestamp(), $appKey), 0, 16);
                    $compositeSessionKey = $visitorHash . ':' . $sessionHash;
                }
            }

            // 7. Duplicate / Same-Page Refresh Suppression (15-second window)
            $dedupeCutoff = $now->modify('-' . self::DEDUPE_WINDOW_SECONDS . ' seconds')->format('Y-m-d H:i:s');
            $duplicate = Database::first(
                "SELECT id FROM website_visits
                 WHERE session_id = ? AND page_url = ? AND visited_at >= ?
                 LIMIT 1",
                [$compositeSessionKey, $normalizedPath, $dedupeCutoff]
            );

            if ($duplicate) {
                self::incrementCounterMetric('deduplicated_events', 1, $nowStr);
                return [
                    'success' => true,
                    'recorded' => false,
                    'reason' => 'duplicate_suppressed',
                ];
            }

            // 8. Sanitize metadata & classify device
            $rawTitle = trim((string) ($payload['page_title'] ?? ''));
            $cleanTitle = $rawTitle !== ''
                ? mb_substr(strip_tags($rawTitle), 0, 200)
                : self::guessPageTitle($normalizedPath);
            $cleanReferrer = self::sanitizeReferrer(isset($payload['referrer']) ? (string) $payload['referrer'] : null);
            $deviceInfo = self::parseUserAgent($userAgent ?? '');

            // Check whether this visitorHash has been seen before (all-time in retained table)
            $seenVisitorBefore = (int) Database::value(
                "SELECT COUNT(*) FROM website_visits WHERE session_id LIKE ?",
                [$visitorHash . ':%']
            ) > 0;

            Database::execute(
                "INSERT INTO website_visits (session_id, ip_address, page_url, page_title, referrer, device_type, browser, os, visited_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $compositeSessionKey,
                    $maskedIp,
                    $normalizedPath,
                    $cleanTitle,
                    $cleanReferrer,
                    $deviceInfo['device'],
                    $deviceInfo['browser'],
                    $deviceInfo['os'],
                    $nowStr,
                ]
            );

            self::incrementCounterMetric('total_visits', 1, $nowStr);
            if (!$seenVisitorBefore) {
                self::incrementCounterMetric('total_unique_visitors', 1, $nowStr);
            }

            return [
                'success' => true,
                'recorded' => true,
                'route' => $normalizedPath,
                'updated_at' => $now->format(DATE_ATOM),
            ];
        } catch (\Throwable $e) {
            Logger::error('Failed to record visit: ' . $e->getMessage());
            return [
                'success' => false,
                'recorded' => false,
                'reason' => 'storage_unavailable',
                'message' => 'ไม่สามารถบันทึกข้อมูลสถิติได้ในขณะนี้',
            ];
        }
    }

    /**
     * Compute real, non-fabricated public visitor summary metrics.
     * Distinguishes true zero (0) from unavailable data source (available = false).
     */
    public static function getPublicStats(): array
    {
        $now = self::now();
        $todayStart = $now->format('Y-m-d 00:00:00');
        $todayEnd = $now->format('Y-m-d 23:59:59');
        $yesterdayDate = $now->modify('-1 day');
        $yesterdayStart = $yesterdayDate->format('Y-m-d 00:00:00');
        $yesterdayEnd = $yesterdayDate->format('Y-m-d 23:59:59');
        $weekStart = $now->modify('-6 days')->format('Y-m-d 00:00:00');
        $monthStart = $now->format('Y-m-01 00:00:00');
        $yearStart = $now->format('Y-01-01 00:00:00');
        $onlineCutoff = $now->modify('-' . self::ONLINE_WINDOW_SECONDS . ' seconds')->format('Y-m-d H:i:s');

        try {
            // Verify table accessibility first; throws if DB or website_visits is unavailable
            $rawTotalPageviews = (int) Database::value("SELECT COUNT(*) FROM website_visits");

            // Distinct visitor expression: SUBSTR(session_id, 1, 16) when composite "visitor:session" is used, or full session_id
            $visitorExpr = "CASE WHEN INSTR(session_id, ':') > 0 THEN SUBSTR(session_id, 1, INSTR(session_id, ':') - 1) ELSE session_id END";

            $onlineNow = (int) Database::value(
                "SELECT COUNT(DISTINCT {$visitorExpr}) FROM website_visits WHERE visited_at >= ? AND visited_at <= ?",
                [$onlineCutoff, $todayEnd]
            );

            $todayVisitors = (int) Database::value(
                "SELECT COUNT(DISTINCT {$visitorExpr}) FROM website_visits WHERE visited_at >= ? AND visited_at <= ?",
                [$todayStart, $todayEnd]
            );

            $todayPageviews = (int) Database::value(
                "SELECT COUNT(*) FROM website_visits WHERE visited_at >= ? AND visited_at <= ?",
                [$todayStart, $todayEnd]
            );

            $todaySessions = (int) Database::value(
                "SELECT COUNT(DISTINCT session_id) FROM website_visits WHERE visited_at >= ? AND visited_at <= ?",
                [$todayStart, $todayEnd]
            );

            $yesterdayVisitors = (int) Database::value(
                "SELECT COUNT(DISTINCT {$visitorExpr}) FROM website_visits WHERE visited_at >= ? AND visited_at <= ?",
                [$yesterdayStart, $yesterdayEnd]
            );

            $yesterdayPageviews = (int) Database::value(
                "SELECT COUNT(*) FROM website_visits WHERE visited_at >= ? AND visited_at <= ?",
                [$yesterdayStart, $yesterdayEnd]
            );

            $weekVisitors = (int) Database::value(
                "SELECT COUNT(DISTINCT {$visitorExpr}) FROM website_visits WHERE visited_at >= ? AND visited_at <= ?",
                [$weekStart, $todayEnd]
            );

            $weekPageviews = (int) Database::value(
                "SELECT COUNT(*) FROM website_visits WHERE visited_at >= ? AND visited_at <= ?",
                [$weekStart, $todayEnd]
            );

            $monthVisitors = (int) Database::value(
                "SELECT COUNT(DISTINCT {$visitorExpr}) FROM website_visits WHERE visited_at >= ? AND visited_at <= ?",
                [$monthStart, $todayEnd]
            );

            $monthPageviews = (int) Database::value(
                "SELECT COUNT(*) FROM website_visits WHERE visited_at >= ? AND visited_at <= ?",
                [$monthStart, $todayEnd]
            );

            $yearVisitors = (int) Database::value(
                "SELECT COUNT(DISTINCT {$visitorExpr}) FROM website_visits WHERE visited_at >= ? AND visited_at <= ?",
                [$yearStart, $todayEnd]
            );

            $yearPageviews = (int) Database::value(
                "SELECT COUNT(*) FROM website_visits WHERE visited_at >= ? AND visited_at <= ?",
                [$yearStart, $todayEnd]
            );

            $distinctRetainedVisitors = (int) Database::value(
                "SELECT COUNT(DISTINCT {$visitorExpr}) FROM website_visits"
            );

            $totalSessions = (int) Database::value(
                "SELECT COUNT(DISTINCT session_id) FROM website_visits"
            );

            $totalVisitors = $distinctRetainedVisitors;
            $totalPageviews = $rawTotalPageviews;

            return [
                'available' => true,
                'online_visitors' => $onlineNow,
                'online_now' => $onlineNow,
                'today_visitors' => $todayVisitors,
                'today_page_views' => $todayPageviews,
                'today_pageviews' => $todayPageviews,
                'today_sessions' => $todaySessions,
                'yesterday_visitors' => $yesterdayVisitors,
                'yesterday_page_views' => $yesterdayPageviews,
                'yesterday_pageviews' => $yesterdayPageviews,
                'week_visitors' => $weekVisitors,
                'this_week' => $weekVisitors,
                'week_page_views' => $weekPageviews,
                'month_visitors' => $monthVisitors,
                'month_page_views' => $monthPageviews,
                'month_pageviews' => $monthPageviews,
                'year_visitors' => $yearVisitors,
                'year_page_views' => $yearPageviews,
                'year_pageviews' => $yearPageviews,
                'total_visitors' => $totalVisitors,
                'total_page_views' => $totalPageviews,
                'total_pageviews' => $totalPageviews,
                'total_sessions' => $totalSessions,
                // Legacy aliases for backward compatibility (using real values only)
                'today' => $todayVisitors,
                'yesterday' => $yesterdayVisitors,
                'this_month' => $monthVisitors,
                'this_year' => $yearVisitors,
                'total' => $totalVisitors,
                'total_visits' => $totalPageviews,
                'timezone' => self::TIMEZONE,
                'generated_at_bangkok' => $now->format('Y-m-d H:i:s'),
                'updated_at' => $now->format(DATE_ATOM),
            ];
        } catch (\Throwable $e) {
            Logger::error('Failed to load visitor stats: ' . $e->getMessage());
            return [
                'available' => false,
                'online_visitors' => null,
                'online_now' => null,
                'today_visitors' => null,
                'today_page_views' => null,
                'today_pageviews' => null,
                'today_sessions' => null,
                'yesterday_visitors' => null,
                'yesterday_page_views' => null,
                'yesterday_pageviews' => null,
                'week_visitors' => null,
                'this_week' => null,
                'week_page_views' => null,
                'month_visitors' => null,
                'month_page_views' => null,
                'month_pageviews' => null,
                'year_visitors' => null,
                'year_page_views' => null,
                'year_pageviews' => null,
                'total_visitors' => null,
                'total_page_views' => null,
                'total_pageviews' => null,
                'total_sessions' => null,
                'today' => null,
                'yesterday' => null,
                'this_month' => null,
                'this_year' => null,
                'total' => null,
                'total_visits' => null,
                'timezone' => self::TIMEZONE,
                'generated_at_bangkok' => $now->format('Y-m-d H:i:s'),
                'updated_at' => $now->format(DATE_ATOM),
                'error' => 'ไม่สามารถโหลดข้อมูลสถิติผู้เข้าชมได้ในขณะนี้',
            ];
        }
    }

    /**
     * Get comprehensive, real-data analytics for both public /visitor-stats and Admin Analytics Dashboard.
     */
    public static function getDetailedAnalytics(int $days = 30, bool $includeAdminMetrics = false): array
    {
        $days = max(7, min(365, $days));
        $now = self::now();
        $overview = self::getPublicStats();

        if (($overview['available'] ?? false) === false) {
            return [
                'available' => false,
                'summary' => $overview,
                'overview' => $overview,
                'error' => $overview['error'] ?? 'ไม่สามารถโหลดข้อมูลได้',
                'generated_at_bangkok' => $now->format('Y-m-d H:i:s'),
                'generated_at' => $now->format(DATE_ATOM),
            ];
        }

        $visitorExpr = "CASE WHEN INSTR(session_id, ':') > 0 THEN SUBSTR(session_id, 1, INSTR(session_id, ':') - 1) ELSE session_id END";
        $startWindow = $now->modify('-' . ($days - 1) . ' days')->format('Y-m-d 00:00:00');
        $endWindow = $now->format('Y-m-d 23:59:59');
        $todayStart = $now->format('Y-m-d 00:00:00');

        // 1. Daily visitor & pageview trend over N days (real DB aggregation)
        $dailyRows = Database::query(
            "SELECT SUBSTR(visited_at, 1, 10) AS visit_date,
                    COUNT(*) AS pageviews,
                    COUNT(DISTINCT {$visitorExpr}) AS unique_visitors,
                    COUNT(DISTINCT session_id) AS sessions
             FROM website_visits
             WHERE visited_at >= ? AND visited_at <= ?
             GROUP BY SUBSTR(visited_at, 1, 10)
             ORDER BY visit_date ASC",
            [$startWindow, $endWindow]
        );

        $dailyMap = [];
        foreach ($dailyRows as $r) {
            $dailyMap[(string) $r['visit_date']] = [
                'pageviews' => (int) $r['pageviews'],
                'unique_visitors' => (int) $r['unique_visitors'],
                'sessions' => (int) $r['sessions'],
            ];
        }

        $timeline = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = $now->modify("-{$i} days");
            $dateStr = $d->format('Y-m-d');
            $shortLabel = self::formatThaiDateShort($d);
            $metrics = $dailyMap[$dateStr] ?? ['pageviews' => 0, 'unique_visitors' => 0, 'sessions' => 0];
            $timeline[] = [
                'full_date' => $dateStr,
                'date' => $shortLabel,
                'label' => $shortLabel,
                'visitors' => $metrics['unique_visitors'],
                'unique_visitors' => $metrics['unique_visitors'],
                'page_views' => $metrics['pageviews'],
                'pageviews' => $metrics['pageviews'],
                'visits' => $metrics['pageviews'],
                'sessions' => $metrics['sessions'],
            ];
        }

        // 2. Monthly visitor trend (last 12 months from real DB)
        $monthlyRows = Database::query(
            "SELECT SUBSTR(visited_at, 1, 7) AS ym,
                    COUNT(*) AS pageviews,
                    COUNT(DISTINCT {$visitorExpr}) AS unique_visitors,
                    COUNT(DISTINCT session_id) AS sessions
             FROM website_visits
             GROUP BY SUBSTR(visited_at, 1, 7)
             ORDER BY ym DESC
             LIMIT 12"
        );
        $monthlyTrend = array_reverse(array_map(static fn(array $r): array => [
            'year_month' => (string) $r['ym'],
            'month' => (string) $r['ym'],
            'label' => (string) $r['ym'],
            'visitors' => (int) $r['unique_visitors'],
            'unique_visitors' => (int) $r['unique_visitors'],
            'page_views' => (int) $r['pageviews'],
            'pageviews' => (int) $r['pageviews'],
            'sessions' => (int) $r['sessions'],
        ], $monthlyRows));

        // 3. Visits by hour (00:00 to 23:00 Asia/Bangkok from real DB)
        $hourlyRows = Database::query(
            "SELECT SUBSTR(visited_at, 12, 2) AS hh,
                    COUNT(*) AS pageviews,
                    COUNT(DISTINCT {$visitorExpr}) AS unique_visitors
             FROM website_visits
             WHERE visited_at >= ? AND visited_at <= ?
             GROUP BY SUBSTR(visited_at, 12, 2)",
            [$startWindow, $endWindow]
        );
        $hourlyMap = [];
        foreach ($hourlyRows as $r) {
            $hourlyMap[(string) $r['hh']] = [
                'pageviews' => (int) $r['pageviews'],
                'unique_visitors' => (int) $r['unique_visitors'],
            ];
        }
        $hourly = [];
        for ($h = 0; $h < 24; $h++) {
            $key = sprintf('%02d', $h);
            $m = $hourlyMap[$key] ?? ['pageviews' => 0, 'unique_visitors' => 0];
            $hourly[] = [
                'hour' => "{$key}:00",
                'label' => "{$key}:00",
                'count' => $m['pageviews'],
                'page_views' => $m['pageviews'],
                'pageviews' => $m['pageviews'],
                'visitors' => $m['unique_visitors'],
                'unique_visitors' => $m['unique_visitors'],
            ];
        }

        // 4. Device breakdown (real DB)
        $windowTotalViews = max(0, (int) Database::value(
            "SELECT COUNT(*) FROM website_visits WHERE visited_at >= ? AND visited_at <= ?",
            [$startWindow, $endWindow]
        ));

        $deviceRows = Database::query(
            "SELECT device_type, COUNT(*) AS cnt
             FROM website_visits
             WHERE visited_at >= ? AND visited_at <= ?
             GROUP BY device_type",
            [$startWindow, $endWindow]
        );
        $deviceCounts = ['mobile' => 0, 'desktop' => 0, 'tablet' => 0];
        foreach ($deviceRows as $r) {
            $dtype = strtolower((string) ($r['device_type'] ?? 'desktop'));
            if (isset($deviceCounts[$dtype])) {
                $deviceCounts[$dtype] = (int) $r['cnt'];
            }
        }
        $devices = [
            [
                'type' => 'mobile',
                'device' => 'mobile',
                'label' => 'สมาร์ทโฟน (Mobile)',
                'name' => 'สมาร์ทโฟน (Mobile)',
                'count' => $deviceCounts['mobile'],
                'percentage' => $windowTotalViews > 0 ? round(($deviceCounts['mobile'] / $windowTotalViews) * 100, 1) : 0.0,
                'icon' => 'Smartphone',
            ],
            [
                'type' => 'desktop',
                'device' => 'desktop',
                'label' => 'คอมพิวเตอร์ (Desktop)',
                'name' => 'คอมพิวเตอร์ (Desktop)',
                'count' => $deviceCounts['desktop'],
                'percentage' => $windowTotalViews > 0 ? round(($deviceCounts['desktop'] / $windowTotalViews) * 100, 1) : 0.0,
                'icon' => 'Monitor',
            ],
            [
                'type' => 'tablet',
                'device' => 'tablet',
                'label' => 'แท็บเล็ต (Tablet)',
                'name' => 'แท็บเล็ต (Tablet)',
                'count' => $deviceCounts['tablet'],
                'percentage' => $windowTotalViews > 0 ? round(($deviceCounts['tablet'] / $windowTotalViews) * 100, 1) : 0.0,
                'icon' => 'Tablet',
            ],
        ];

        // 5. Browser breakdown (real DB)
        $browserRows = Database::query(
            "SELECT browser, COUNT(*) AS cnt
             FROM website_visits
             WHERE visited_at >= ? AND visited_at <= ?
             GROUP BY browser
             ORDER BY cnt DESC
             LIMIT 6",
            [$startWindow, $endWindow]
        );
        $browsers = array_map(static fn(array $r): array => [
            'name' => (string) ($r['browser'] ?: 'Other'),
            'count' => (int) $r['cnt'],
            'percentage' => $windowTotalViews > 0 ? round(((int) $r['cnt'] / $windowTotalViews) * 100, 1) : 0.0,
        ], $browserRows);

        // 6. OS breakdown (real DB)
        $osRows = Database::query(
            "SELECT os, COUNT(*) AS cnt
             FROM website_visits
             WHERE visited_at >= ? AND visited_at <= ?
             GROUP BY os
             ORDER BY cnt DESC
             LIMIT 6",
            [$startWindow, $endWindow]
        );
        $osList = array_map(static fn(array $r): array => [
            'name' => (string) ($r['os'] ?: 'Other'),
            'count' => (int) $r['cnt'],
            'percentage' => $windowTotalViews > 0 ? round(((int) $r['cnt'] / $windowTotalViews) * 100, 1) : 0.0,
        ], $osRows);

        // 7. Top public pages (real DB, filtered through normalizeAllowedPublicPath)
        $pageRows = Database::query(
            "SELECT page_url, MAX(page_title) AS title,
                    COUNT(*) AS views,
                    COUNT(DISTINCT {$visitorExpr}) AS unique_visitors
             FROM website_visits
             WHERE visited_at >= ? AND visited_at <= ?
             GROUP BY page_url
             ORDER BY views DESC
             LIMIT 15",
            [$startWindow, $endWindow]
        );
        $topPages = [];
        foreach ($pageRows as $r) {
            $safeUrl = self::normalizeAllowedPublicPath((string) $r['page_url']);
            if ($safeUrl === null) {
                continue;
            }
            $views = (int) $r['views'];
            $title = $r['title'] ?: self::guessPageTitle($safeUrl);
            $topPages[] = [
                'url' => $safeUrl,
                'label' => $title,
                'title' => $title,
                'views' => $views,
                'unique_visitors' => (int) $r['unique_visitors'],
                'percentage' => $windowTotalViews > 0 ? round(($views / $windowTotalViews) * 100, 1) : 0.0,
            ];
            if (count($topPages) >= 10) {
                break;
            }
        }

        $windowSessions = (int) Database::value(
            "SELECT COUNT(DISTINCT session_id) FROM website_visits WHERE visited_at >= ? AND visited_at <= ?",
            [$startWindow, $endWindow]
        );
        $windowUniqueVisitors = (int) Database::value(
            "SELECT COUNT(DISTINCT {$visitorExpr}) FROM website_visits WHERE visited_at >= ? AND visited_at <= ?",
            [$startWindow, $endWindow]
        );
        $avgPagesPerSession = $windowSessions > 0 ? round($windowTotalViews / $windowSessions, 2) : 0.0;

        $summaryPayload = array_merge($overview, [
            'window_pageviews' => $windowTotalViews,
            'window_unique_visitors' => $windowUniqueVisitors,
            'window_sessions' => $windowSessions,
            'period_sessions' => $windowSessions,
            'avg_pages_per_session' => $avgPagesPerSession,
            'top_public_page' => $topPages[0]['url'] ?? null,
        ]);

        $definitions = [
            'page_views' => 'จำนวนครั้งของการเปิดหน้าเว็บสาธารณะที่ผ่านการตรวจสอบและตัดการกดซ้ำภายใน 15 วินาที',
            'unique_visitors' => 'จำนวนรหัสผู้เข้าชมแบบนามแฝง (Pseudonymous Hash) ที่ไม่ซ้ำกันในช่วงเวลาที่กำหนด',
            'sessions' => 'จำนวนรอบการเข้าใช้งานที่คั่นด้วยระยะเวลาไม่มีกิจกรรม (Inactivity Threshold) เกิน 30 นาที',
            'online_visitors' => 'จำนวนผู้เข้าชมที่มีกิจกรรมบนหน้าเว็บสาธารณะภายในช่วง 5 นาทีล่าสุด',
            'limitations' => 'รหัสผู้เข้าชมแบบนามแฝงอ้างอิงตามเบราว์เซอร์/อุปกรณ์ ไม่สามารถรับประกันว่าเป็นบุคคลธรรมดาคนเดียวกัน 100% หากเปลี่ยนอุปกรณ์หรือล้างข้อมูลเบราว์เซอร์',
        ];

        $result = [
            'available' => true,
            'filter_days' => $days,
            'summary' => $summaryPayload,
            'overview' => $summaryPayload,
            'timeline' => $timeline,
            'timeline_30d' => $timeline,
            'monthly_trend' => $monthlyTrend,
            'hourly_distribution' => $hourly,
            'hourly' => $hourly,
            'devices' => $devices,
            'browsers' => $browsers,
            'os' => $osList,
            'top_pages' => $topPages,
            'definitions' => $definitions,
            'limitations_note' => $definitions['limitations'],
            'generated_at_bangkok' => $now->format('Y-m-d H:i:s'),
            'generated_at' => $now->format(DATE_ATOM),
        ];

        if ($includeAdminMetrics) {
            // 8. Recent visits (admin-only, PDPA-anonymized)
            $recentRows = Database::query(
                "SELECT page_url, page_title, ip_address, device_type, browser, os, visited_at
                 FROM website_visits
                 ORDER BY id DESC
                 LIMIT 15"
            );
            $recentVisits = [];
            foreach ($recentRows as $r) {
                $safeUrl = self::normalizeAllowedPublicPath((string) $r['page_url']);
                if ($safeUrl === null) {
                    continue;
                }
                $relTime = self::formatRelativeTime((string) $r['visited_at'], $now);
                $masked = PdpaService::maskIpAddress((string) ($r['ip_address'] ?? ''));
                $title = $r['page_title'] ?: self::guessPageTitle($safeUrl);
                $recentVisits[] = [
                    'url' => $safeUrl,
                    'page_url' => $safeUrl,
                    'title' => $title,
                    'page_title' => $title,
                    'masked_ip' => $masked,
                    'ip_masked' => $masked,
                    'device' => $r['device_type'],
                    'browser' => $r['browser'],
                    'os' => $r['os'],
                    'time' => $r['visited_at'],
                    'time_ago' => $relTime,
                    'time_relative' => $relTime,
                ];
            }

            $botFiltered = self::getCounterMetric('bot_filtered_events');
            $deduplicated = self::getCounterMetric('deduplicated_events');
            $rateLimited = self::getCounterMetric('rate_limited_events');
            $acceptedEvents = (int) ($overview['total_page_views'] ?? 0);

            $result['recent_visits'] = $recentVisits;
            $result['traffic_quality'] = [
                'accepted_events' => $acceptedEvents,
                'bot_filtered_events' => $botFiltered,
                'deduplicated_events' => $deduplicated,
                'rate_limited_events' => $rateLimited,
            ];
            $result['observability'] = [
                'ingestion_status' => 'healthy',
                'accepted_events' => $acceptedEvents,
                'bot_filtered_events' => $botFiltered,
                'deduplicated_events' => $deduplicated,
                'rate_limited_events' => $rateLimited,
                'timezone' => self::TIMEZONE,
                'session_timeout_minutes' => 30,
                'online_window_minutes' => 5,
                'dedupe_window_seconds' => self::DEDUPE_WINDOW_SECONDS,
                'retention_days' => self::RETENTION_DAYS,
                'data_freshness' => $now->format(DATE_ATOM),
            ];
        }

        return $result;
    }

    /**
     * Increment a key in visitor_counters (portable across MySQL 8 and SQLite).
     */
    private static function incrementCounterMetric(string $key, int $delta, string $nowStr): void
    {
        try {
            $exists = Database::first("SELECT metric_value FROM visitor_counters WHERE metric_key = ?", [$key]);
            if ($exists) {
                Database::execute(
                    "UPDATE visitor_counters SET metric_value = metric_value + ?, updated_at = ? WHERE metric_key = ?",
                    [$delta, $nowStr, $key]
                );
            } else {
                Database::execute(
                    "INSERT INTO visitor_counters (metric_key, metric_value, updated_at) VALUES (?, ?, ?)",
                    [$key, $delta, $nowStr]
                );
            }
        } catch (\Throwable $e) {
            // Non-fatal if visitor_counters table is absent
        }
    }

    private static function getCounterMetric(string $key): int
    {
        try {
            return (int) Database::value("SELECT metric_value FROM visitor_counters WHERE metric_key = ?", [$key]);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * User-Agent parser for device, browser, and OS.
     */
    public static function parseUserAgent(string $ua): array
    {
        $device = 'desktop';
        if (preg_match('/(ipad|tablet|(android(?!.*mobile))|(windows(?!.*phone)(.*touch))|kindle|playbook|silk|(puffin(?!.*(IP|AP|WP))))/i', $ua)) {
            $device = 'tablet';
        } elseif (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $ua)) {
            $device = 'mobile';
        }

        $browser = 'Other Browser';
        if (stripos($ua, 'Edg') !== false) {
            $browser = 'Microsoft Edge';
        } elseif (stripos($ua, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (stripos($ua, 'OPR') !== false || stripos($ua, 'Opera') !== false) {
            $browser = 'Opera';
        } elseif (stripos($ua, 'SamsungBrowser') !== false) {
            $browser = 'Samsung Internet';
        } elseif (stripos($ua, 'Chrome') !== false) {
            $browser = 'Google Chrome';
        } elseif (stripos($ua, 'Safari') !== false) {
            $browser = 'Safari';
        }

        $os = 'Other OS';
        if (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false || stripos($ua, 'iPod') !== false) {
            $os = 'iOS';
        } elseif (stripos($ua, 'Android') !== false) {
            $os = 'Android';
        } elseif (stripos($ua, 'Macintosh') !== false || stripos($ua, 'Mac OS') !== false) {
            $os = 'macOS';
        } elseif (stripos($ua, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (stripos($ua, 'Linux') !== false) {
            $os = 'Linux';
        }

        return [
            'device' => $device,
            'browser' => $browser,
            'os' => $os,
        ];
    }

    /**
     * Map approved public paths to readable Thai titles.
     */
    public static function guessPageTitle(string $path): string
    {
        $map = [
            '/' => 'หน้าแรก (Home)',
            '/loans' => 'เงินกู้และสินเชื่อ',
            '/loans/checklist' => 'เตรียมเอกสารขอสินเชื่อ',
            '/loan-checklist' => 'เตรียมเอกสารขอสินเชื่อ',
            '/loan-readiness' => 'เตรียมความพร้อมขอสินเชื่อ',
            '/deposits' => 'เงินฝากออมทรัพย์และอัตราดอกเบี้ย',
            '/rates' => 'อัตราดอกเบี้ยเงินฝากและเงินกู้',
            '/calculator' => 'โปรแกรมคำนวณค่างวดเงินกู้',
            '/dividend-estimator' => 'คำนวณเงินปันผลและเฉลี่ยคืน',
            '/news' => 'ข่าวสารและประชาสัมพันธ์',
            '/announcements' => 'ประกาศสำคัญสหกรณ์',
            '/events' => 'ปฏิทินกิจกรรมสหกรณ์',
            '/calendar' => 'ปฏิทินกิจกรรมสหกรณ์',
            '/documents' => 'ดาวน์โหลดแบบฟอร์มและระเบียบ',
            '/welfare' => 'สวัสดิการสมาชิกสหกรณ์',
            '/eservice' => 'ศูนย์บริการ e-Services',
            '/about' => 'เกี่ยวกับสหกรณ์',
            '/board' => 'คณะกรรมการดำเนินการ',
            '/board_committee' => 'คณะกรรมการดำเนินการ',
            '/board_officer' => 'เจ้าหน้าที่สหกรณ์',
            '/statistics' => 'สถิติทางการเงินสหกรณ์',
            '/contact' => 'ติดต่อสหกรณ์และแผนที่',
            '/complaints' => 'ช่องทางแจ้งเรื่องร้องเรียน',
            '/faqs' => 'คำถามที่พบบ่อย (FAQ)',
            '/privacy/policy' => 'นโยบายคุ้มครองข้อมูลส่วนบุคคล (PDPA)',
            '/privacy/cookies' => 'นโยบายคุกกี้ (Cookie Policy)',
            '/visitor-stats' => 'สถิติผู้เข้าชมเว็บไซต์',
            '/analytics' => 'สถิติผู้เข้าชมเว็บไซต์',
        ];

        return $map[$path] ?? $path;
    }

    protected static function formatThaiDateShort(DateTimeImmutable $dt): string
    {
        $months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        $d = (int) $dt->format('j');
        $m = (int) $dt->format('n');
        return "{$d} {$months[$m]}";
    }

    protected static function formatRelativeTime(string $datetime, DateTimeImmutable $now): string
    {
        try {
            $ts = (new DateTimeImmutable($datetime, new DateTimeZone(self::TIMEZONE)))->getTimestamp();
        } catch (\Throwable $e) {
            return $datetime;
        }
        $diff = max(0, $now->getTimestamp() - $ts);
        if ($diff < 60) {
            return max(1, $diff) . ' วินาทีที่แล้ว';
        }
        if ($diff < 3600) {
            return (int) floor($diff / 60) . ' นาทีที่แล้ว';
        }
        if ($diff < 86400) {
            return (int) floor($diff / 3600) . ' ชั่วโมงที่แล้ว';
        }
        return (int) floor($diff / 86400) . ' วันที่แล้ว';
    }
}
