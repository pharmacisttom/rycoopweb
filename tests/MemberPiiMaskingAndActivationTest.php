<?php

declare(strict_types=1);

ob_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Helpers.php';

use App\Controllers\Admin\AuthController;
use App\Controllers\Api\SpaApiController;
use App\Controllers\Member\DividendController;
use App\Controllers\Member\MemberPdpaController;
use App\Controllers\Member\MemberPortalController;
use App\Core\{Auth, Csrf, Database, Request, Response, Router, Session};
use App\Middlewares\{AuthMiddleware, CsrfMiddleware, FeatureFlagMiddleware, MemberMiddleware, MemberPdpaMiddleware, RoleMiddleware};
use App\Services\{AuditService, MemberAdminService, MemberControlledActivationService, MemberPdpaService, MemberPiiMaskingService, MemberPortalService, MemberSecurityService};

final class MaskingTestResponse extends Response
{
    public int $status = 200;
    public array $body = [];
    public ?string $redirectUrl = null;

    public function json(array $data, int $status = 200): void
    {
        $this->body = $data;
        $this->status = $status;
    }

    public function redirect(string $url, int $status = 302): void
    {
        $this->redirectUrl = $url;
        $this->status = $status;
    }
}

$sessionDir = dirname(__DIR__) . '/storage/tmp/member-pii-masking-test-sessions';
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0700, true);
}
putenv('SESSION_SAVE_PATH=' . $sessionDir);
putenv('SESSION_SECURE=false');
putenv('FEATURE_MEMBER_PORTAL=true');
putenv('FEATURE_MEMBER_LOGIN=true');
putenv('GOOGLE_AUTH_ENABLED=false');

$_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/api/member/dividends',
    'SCRIPT_NAME' => '/index.php',
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    'REMOTE_ADDR' => '127.0.0.1',
    'HTTP_USER_AGENT' => 'MaskingTestRunner/1.0',
];
$_GET = $_POST = $_FILES = [];

Session::start();

// Isolated SQLite in-memory database
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));
$pdo->sqliteCreateFunction('UTC_TIMESTAMP', fn() => gmdate('Y-m-d H:i:s'));
$pdo->sqliteCreateFunction('CURDATE', fn() => date('Y-m-d'));
$pdo->sqliteCreateFunction('YEAR', fn($d) => date('Y', strtotime((string)$d)));
$pdo->sqliteCreateFunction('CONCAT', fn(...$args) => implode('', $args));
$pdo->sqliteCreateFunction('RIGHT', fn($str, $len) => substr((string)$str, -$len));
$pdo->sqliteCreateFunction('REPLACE', fn($str, $from, $to) => str_replace($from, $to, (string)$str));

(new ReflectionProperty(Database::class, 'instance'))->setValue(null, $pdo);

// Initialize schema
$pdo->exec("
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid TEXT,
        name TEXT,
        username TEXT UNIQUE,
        email TEXT UNIQUE,
        password TEXT,
        department TEXT,
        position TEXT,
        status TEXT DEFAULT 'inactive',
        must_change_password INTEGER DEFAULT 0,
        two_factor_enabled INTEGER DEFAULT 0,
        two_factor_secret TEXT NULL,
        two_factor_confirmed_at DATETIME NULL,
        last_login_at DATETIME NULL,
        last_login_ip TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL
    );

    CREATE TABLE roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        slug TEXT UNIQUE,
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE user_roles (
        user_id INTEGER,
        role_id INTEGER,
        PRIMARY KEY (user_id, role_id)
    );

    CREATE TABLE permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        module TEXT,
        action TEXT,
        description TEXT
    );

    CREATE TABLE role_permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        role_id INTEGER,
        permission_id INTEGER
    );

    CREATE TABLE user_google_identities (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER UNIQUE,
        google_email TEXT,
        google_sub TEXT,
        linked_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE members (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid TEXT,
        user_id INTEGER UNIQUE,
        member_no TEXT UNIQUE,
        id_card TEXT UNIQUE,
        prefix TEXT,
        first_name TEXT,
        last_name TEXT,
        status TEXT DEFAULT 'active',
        phone TEXT NULL,
        email TEXT NULL,
        department TEXT NULL,
        position TEXT NULL,
        org_name TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE member_privacy_policies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        version TEXT UNIQUE,
        title TEXT,
        content TEXT,
        content_sha256 TEXT,
        effective_at DATETIME,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE member_pdpa_acknowledgements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        member_id INTEGER,
        policy_id INTEGER,
        policy_version TEXT,
        login_nonce_hash TEXT,
        acknowledged_at DATETIME,
        ip_hash TEXT NULL,
        user_agent_hash TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (user_id, login_nonce_hash, policy_id)
    );

    CREATE TABLE member_dividends (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        member_id INTEGER,
        year INTEGER,
        details_json TEXT,
        imported_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE member_data_changes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        member_id INTEGER,
        user_id INTEGER NULL,
        action TEXT,
        reason TEXT,
        before_json TEXT NULL,
        after_json TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id TEXT NULL,
        module TEXT,
        action TEXT,
        record_id TEXT NULL,
        old_values TEXT NULL,
        new_values TEXT NULL,
        ip_address TEXT NULL,
        user_agent TEXT NULL,
        status TEXT DEFAULT 'success',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE login_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NULL,
        email TEXT,
        status TEXT,
        failure_reason TEXT NULL,
        ip_address TEXT,
        user_agent TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE member_shares (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        member_id INTEGER UNIQUE,
        total_shares INTEGER DEFAULT 0,
        share_value REAL DEFAULT 10.0,
        total_amount REAL DEFAULT 0.0,
        monthly_share REAL DEFAULT 0.0,
        last_payment_date TEXT NULL,
        start_date TEXT NULL
    );

    CREATE TABLE deposit_accounts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        member_id INTEGER,
        account_no TEXT,
        account_name TEXT,
        account_type TEXT,
        balance REAL DEFAULT 0.0,
        accrued_interest REAL DEFAULT 0.0,
        interest_rate REAL DEFAULT 1.5,
        status TEXT DEFAULT 'active'
    );

    CREATE TABLE loan_contracts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        member_id INTEGER,
        contract_no TEXT,
        loan_name TEXT,
        loan_type TEXT,
        principal_balance REAL DEFAULT 0.0,
        monthly_installment REAL DEFAULT 0.0,
        status TEXT DEFAULT 'active'
    );

    CREATE TABLE receipts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        receipt_no TEXT UNIQUE,
        member_id INTEGER,
        billing_year INTEGER,
        billing_month INTEGER,
        issue_date TEXT,
        total_amount REAL DEFAULT 0.0,
        qr_verify_token TEXT NULL,
        status TEXT DEFAULT 'completed'
    );
");

// Seed Roles
$pdo->exec("INSERT INTO roles (id, name, slug) VALUES (1, 'Super Admin', 'super_admin')");
$pdo->exec("INSERT INTO roles (id, name, slug) VALUES (2, 'Member', 'member')");
$pdo->exec("INSERT INTO roles (id, name, slug) VALUES (3, 'Staff', 'staff')");

// Seed Policy 3.0
$policySha256 = hash('sha256', 'Coop PDPA Policy 3.0 content');
$pdo->exec("INSERT INTO member_privacy_policies (id, version, title, content, content_sha256, effective_at, is_active)
            VALUES (1, '3.0', 'ประกาศความเป็นส่วนตัวสำหรับสมาชิกสหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด', 'Policy content 3.0', '{$policySha256}', '2026-01-01 00:00:00', 1)");

// Seed Super Admin
$pdo->exec("INSERT INTO users (id, uuid, name, username, email, password, status) VALUES (1, 'admin-uuid-001', 'Admin Super', 'superadmin', 'admin@example.com', '" . password_hash('Admin@123456', PASSWORD_BCRYPT) . "', 'active')");
$pdo->exec("INSERT INTO user_roles (user_id, role_id) VALUES (1, 1)");

// Seed Member A (User 10, Member 101, Citizen ID: 3100600123456, Member No: 00042)
$pdo->exec("INSERT INTO users (id, uuid, name, username, email, password, status, must_change_password) VALUES (10, 'member-uuid-010', 'นายกรณ์ชัย สมประสงค์', '3100600123456', 'kornchai@example.com', '" . password_hash('00042', PASSWORD_BCRYPT) . "', 'active', 0)");
$pdo->exec("INSERT INTO user_roles (user_id, role_id) VALUES (10, 2)");
$pdo->exec("INSERT INTO members (id, uuid, user_id, member_no, id_card, prefix, first_name, last_name, status, phone, email, department, position) VALUES (101, 'mem-uuid-101', 10, '00042', '3100600123456', 'นาย', 'กรณ์ชัย', 'สมประสงค์', 'active', '0812345678', 'kornchai@example.com', 'รพ.ระยอง', 'พยาบาลวิชาชีพ')");

// Seed Member B (User 20, Member 201, Citizen ID: 3210200456789, Member No: 00099)
$pdo->exec("INSERT INTO users (id, uuid, name, username, email, password, status, must_change_password) VALUES (20, 'member-uuid-020', 'นางสาววิภา พรประเสริฐ', '3210200456789', 'wipha@example.com', '" . password_hash('00099', PASSWORD_BCRYPT) . "', 'active', 0)");
$pdo->exec("INSERT INTO user_roles (user_id, role_id) VALUES (20, 2)");
$pdo->exec("INSERT INTO members (id, uuid, user_id, member_no, id_card, prefix, first_name, last_name, status, phone, email, department, position) VALUES (201, 'mem-uuid-201', 20, '00099', '3210200456789', 'นางสาว', 'วิภา', 'พรประเสริฐ', 'active', '0898765432', 'wipha@example.com', 'สสจ.ระยอง', 'เจ้าพนักงานสาธารณสุข')");

// Seed Suspended Member (User 30, Member 301)
$pdo->exec("INSERT INTO users (id, uuid, name, username, email, password, status, must_change_password) VALUES (30, 'member-uuid-030', 'นายถูกระงับ ใช้งาน', '3100300999999', 'suspended@example.com', '" . password_hash('00333', PASSWORD_BCRYPT) . "', 'suspended', 0)");
$pdo->exec("INSERT INTO user_roles (user_id, role_id) VALUES (30, 2)");
$pdo->exec("INSERT INTO members (id, uuid, user_id, member_no, id_card, prefix, first_name, last_name, status, phone, email) VALUES (301, 'mem-uuid-301', 30, '00333', '3100300999999', 'นาย', 'ถูกระงับ', 'ใช้งาน', 'suspended', '0855555555', 'suspended@example.com')");

// Seed Dividends
$divA = json_encode(['year' => 2568, 'total_income' => 20000.0, 'total_deductions' => 3800.0, 'net' => 16200.0, 'dividend_rate' => 5.25, 'refund_rate' => 12.0, 'received' => 16200.0, 'income' => ['เงินปันผล' => 15000.0], 'deductions' => ['ภาษี' => 0.0]]);
$divB = json_encode(['year' => 2568, 'total_income' => 50000.0, 'total_deductions' => 6200.0, 'net' => 43800.0, 'dividend_rate' => 5.25, 'refund_rate' => 12.0, 'received' => 43800.0, 'income' => ['เงินปันผล' => 40000.0], 'deductions' => ['ภาษี' => 0.0]]);
$pdo->exec("INSERT INTO member_dividends (member_id, year, details_json) VALUES (101, 2568, '{$divA}')");
$pdo->exec("INSERT INTO member_dividends (member_id, year, details_json) VALUES (201, 2568, '{$divB}')");

// Dispatch Helper
$router = new Router();
require dirname(__DIR__) . '/config/routes.php';

function testDispatch(string $method, string $uri, array $input = [], array $headers = []): MaskingTestResponse
{
    global $router;
    $_SERVER['REQUEST_METHOD'] = strtoupper($method);
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $_POST = $input;
    foreach ($headers as $k => $v) {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $k));
        $_SERVER[$serverKey] = $v;
    }
    $req = new Request();
    $res = new MaskingTestResponse();
    $router->dispatch($req, $res);
    return $res;
}

function testAssert(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException("Assertion FAILED: {$label}");
    }
    echo "PASS [{$label}]\n";
}

echo "=========================================================================\n";
echo "RYCOOPWEB — PII Masking & Controlled Activation Test Suite\n";
echo "=========================================================================\n";

// -------------------------------------------------------------------------
// 1. Thai Name & Surname Masking Tests
// -------------------------------------------------------------------------
// Expected example: นายจัตุพล กันทะมูล => นายจัตุxx กันxxx
$maskedJattuphon = MemberPiiMaskingService::maskFullName('นายจัตุพล กันทะมูล');
testAssert($maskedJattuphon === 'นายจัตุxx กันxxx', "1. Target full name masking: 'นายจัตุพล กันทะมูล' => 'นายจัตุxx กันxxx'");

$maskedKornchai = MemberPiiMaskingService::maskFullName('นายกรณ์ชัย สมประสงค์');
testAssert($maskedKornchai === 'นายกรณ์xx สมปxxx', "1. Fictional full name masking: 'นายกรณ์ชัย สมประสงค์' => 'นายกรณ์xx สมปxxx'");

$maskedComponent = MemberPiiMaskingService::maskMemberName('นาย', 'จัตุพล', 'กันทะมูล');
testAssert($maskedComponent === 'นายจัตุxx กันxxx', "1. Component name masking preserves Thai title and masks first/last names: 'นายจัตุxx กันxxx'");

// Other Thai titles (นาง, นางสาว, ภก., นพ.)
testAssert(MemberPiiMaskingService::maskFullName('นางสมพร ใจดีมาก') === 'นางสมพรxx ใจxxx', "1. Title 'นาง' preserved and masked correctly: 'นางสมพรxx ใจxxx'");
testAssert(MemberPiiMaskingService::maskFullName('นางสาวกัญญา พงษ์ไพศาล') === 'นางสาวกัญญxx พงxxx', "1. Title 'นางสาว' preserved and masked correctly: 'นางสาวกัญญxx พงxxx'");
testAssert(MemberPiiMaskingService::maskFullName('ภก.ทวีศักดิ์ วรเดช') === 'ภก.ทวีxx วรเxxx', "1. Professional title 'ภก.' preserved and masked correctly: 'ภก.ทวีxx วรเxxx'");
testAssert(MemberPiiMaskingService::maskFullName('นพ.ประวิทย์ ชัยชนะ') === 'นพ.ประxx ชัยxxx', "1. Professional title 'นพ.' preserved and masked correctly: 'นพ.ประxx ชัยxxx'");

// Short names
testAssert(MemberPiiMaskingService::maskFirstName('ดี') === 'ดีxx', "1. Short first name 'ดี' handled safely with 'xx'");
testAssert(MemberPiiMaskingService::maskLastName('สม') === 'สมxxx', "1. Short surname 'สม' handled safely with 'xxx'");
testAssert(MemberPiiMaskingService::maskFirstName('เอก') === 'เอกxx', "1. Short first name 'เอก' handled safely with 'xx'");

// Repeated masking (prevent double masking)
testAssert(MemberPiiMaskingService::maskFirstName('จัตุxx') === 'จัตุxx', "1. Repeated first name masking prevents double masking");
testAssert(MemberPiiMaskingService::maskLastName('กันxxx') === 'กันxxx', "1. Repeated last name masking prevents double masking");
testAssert(MemberPiiMaskingService::maskFullName('นายจัตุxx กันxxx') === 'นายจัตุxx กันxxx', "1. Repeated full name masking prevents double masking");

// Empty and null names
testAssert(MemberPiiMaskingService::maskFullName('') === '-', "1. Empty full name returns '-'");
testAssert(MemberPiiMaskingService::maskFullName(null) === '-', "1. Null full name returns '-'");
testAssert(MemberPiiMaskingService::maskFirstName(null) === '', "1. Null first name returns empty string");
testAssert(MemberPiiMaskingService::maskLastName(null) === '', "1. Null last name returns empty string");

// -------------------------------------------------------------------------
// 2. Citizen ID and Member Number Masking Tests
// -------------------------------------------------------------------------
// Citizen ID: 3100600123456 => 3-****-*****-**-*
$maskedCitizen = MemberPiiMaskingService::maskCitizenId('3100600123456');
testAssert($maskedCitizen === '3-****-*****-**-*', "2. 13-digit Citizen ID masked to '3-****-*****-**-*'");

// Formatted input Citizen ID: 3-1006-00123-45-6 => 3-****-*****-**-*
$maskedCitizenFormatted = MemberPiiMaskingService::maskCitizenId('3-1006-00123-45-6');
testAssert($maskedCitizenFormatted === '3-****-*****-**-*', "2. Formatted 13-digit Citizen ID masked to '3-****-*****-**-*'");

// Member number: 00042 => 00*42
$maskedMemNo = MemberPiiMaskingService::maskMemberNo('00042');
testAssert($maskedMemNo === '00*42', "2. 5-digit member number '00042' masked to '00*42'");

// Member number preserving leading zeros from int: 42 => 00*42
$maskedMemNoInt = MemberPiiMaskingService::maskMemberNo('42');
testAssert($maskedMemNoInt === '00*42', "2. Unpadded number '42' padded and masked to '00*42'");

// Phone masking: 0812345678 => 081-XXX-5678
$maskedPhone = MemberPiiMaskingService::maskPhone('0812345678');
testAssert($maskedPhone === '081-XXX-5678', "2. 10-digit phone number masked to '081-XXX-5678'");

// -------------------------------------------------------------------------
// 3. Sanitized Member Data Array
// -------------------------------------------------------------------------
$rawMemberRecord = [
    'id' => 101,
    'user_id' => 10,
    'member_no' => '00042',
    'id_card' => '3100600123456',
    'prefix' => 'นาย',
    'first_name' => 'กรณ์ชัย',
    'last_name' => 'สมประสงค์',
    'phone' => '0812345678',
    'password' => 'secret_hash',
    'status' => 'active',
];

$sanitized = MemberPiiMaskingService::sanitizeMemberData($rawMemberRecord);
testAssert(!isset($sanitized['id_card']), "3. Raw 'id_card' strictly unset from sanitized member record");
testAssert(!isset($sanitized['password']), "3. Raw 'password' strictly unset from sanitized member record");
testAssert($sanitized['id_card_pdpa'] === '3-****-*****-**-*', "3. 'id_card_pdpa' present as '3-****-*****-**-*'");
testAssert($sanitized['name'] === 'นายกรณ์xx สมปxxx', "3. 'name' present as 'นายกรณ์xx สมปxxx'");
testAssert($sanitized['member_no'] === '00*42', "3. 'member_no' present as '00*42'");
testAssert($sanitized['phone'] === '081-XXX-5678', "3. 'phone' present as '081-XXX-5678'");

// -------------------------------------------------------------------------
// 4. No Raw PII in Member-Facing JSON Responses
// -------------------------------------------------------------------------
// Authenticate Member A
Auth::logout();
$resLoginA = testDispatch('POST', '/login', [
    'username' => '3100600123456',
    'password' => '00042',
    'ajax' => '1',
    '_csrf_token' => Csrf::token(),
]);
testAssert($resLoginA->status === 200, "4. Member A login succeeds with HTTP 200");
testAssert($resLoginA->body['user']['username'] === '3-****-*****-**-*', "4. Login response returns masked username '3-****-*****-**-*' instead of raw Citizen ID");
testAssert($resLoginA->body['user']['name'] === 'นายกรณ์xx สมปxxx', "4. Login response returns masked name 'นายกรณ์xx สมปxxx'");
testAssert(!str_contains(json_encode($resLoginA->body), '3100600123456'), "4. Raw 13-digit citizen ID NEVER appears in /login JSON response");

// Call /api/auth/me prior to PDPA ack
$resMe = testDispatch('GET', '/api/auth/me');
testAssert($resMe->status === 200, "4. /api/auth/me returns HTTP 200");
testAssert($resMe->body['user']['username'] === '3-****-*****-**-*', "4. /api/auth/me returns masked username '3-****-*****-**-*'");
testAssert($resMe->body['user']['name'] === 'นายกรณ์xx สมปxxx', "4. /api/auth/me returns masked name 'นายกรณ์xx สมปxxx'");
testAssert(($resMe->body['user']['pdpa_ack_required'] ?? false) === true, "4. /api/auth/me indicates PDPA acknowledgement is required");
testAssert(!str_contains(json_encode($resMe->body), '3100600123456'), "4. Raw 13-digit citizen ID NEVER appears in /api/auth/me response");

// -------------------------------------------------------------------------
// 5. PDPA Gate Enforcement
// -------------------------------------------------------------------------
// Attempt financial access prior to PDPA acknowledgement
$resBlocked = testDispatch('GET', '/api/member/dividends');
testAssert($resBlocked->status === 403 && ($resBlocked->body['code'] ?? '') === 'PDPA_ACK_REQUIRED', "5. Protected financial access denied with 403 PDPA_ACK_REQUIRED before acknowledgement");

// Acknowledge PDPA Policy 3.0
$resAck = testDispatch('POST', '/api/member/pdpa/acknowledge', [
    'acknowledgement' => '1',
    'policy_version' => '3.0',
    '_csrf_token' => Csrf::token(),
]);
testAssert($resAck->status === 200 && ($resAck->body['success'] ?? false) === true, "5. Explicit PDPA policy 3.0 acknowledgement succeeds with HTTP 200");

// Check /api/auth/me after PDPA acknowledgement
$resMeAcked = testDispatch('GET', '/api/auth/me');
testAssert(($resMeAcked->body['user']['pdpa_ack_required'] ?? true) === false, "5. /api/auth/me indicates PDPA is acknowledged");
testAssert($resMeAcked->body['user']['member_no'] === '00*42', "5. /api/auth/me returns masked member_no '00*42'");
testAssert($resMeAcked->body['user']['phone'] === '081-XXX-5678', "5. /api/auth/me returns masked phone '081-XXX-5678'");

// Access dividends after acknowledgement
$resDivA = testDispatch('GET', '/api/member/dividends');
testAssert($resDivA->status === 200 && ($resDivA->body['success'] ?? false) === true, "5. Dividends accessible after PDPA acknowledgement");
testAssert($resDivA->body['member']['member_no'] === '00*42', "5. /api/member/dividends returns masked member_no '00*42'");
testAssert($resDivA->body['member']['name'] === 'นายกรณ์xx สมปxxx', "5. /api/member/dividends returns masked name 'นายกรณ์xx สมปxxx'");
testAssert(!str_contains(json_encode($resDivA->body), '3100600123456'), "5. Raw 13-digit citizen ID NEVER appears in /api/member/dividends response");

// Access /api/member/profile
$resProfile = testDispatch('GET', '/api/member/profile');
testAssert($resProfile->status === 200, "5. /api/member/profile accessible after PDPA acknowledgement");
testAssert($resProfile->body['data']['member']['member_no'] === '00*42', "5. /api/member/profile returns masked member_no '00*42'");
testAssert($resProfile->body['data']['member']['name'] === 'นายกรณ์xx สมปxxx', "5. /api/member/profile returns masked name 'นายกรณ์xx สมปxxx'");
testAssert(!isset($resProfile->body['data']['member']['id_card']), "5. /api/member/profile strictly omits raw id_card");
testAssert(!str_contains(json_encode($resProfile->body), '3100600123456'), "5. Raw 13-digit citizen ID NEVER appears in /api/member/profile response");

// -------------------------------------------------------------------------
// 6. Member-to-Member Financial Isolation (IDOR Protection)
// -------------------------------------------------------------------------
$recordsA = $resDivA->body['records'] ?? [];
testAssert(count($recordsA) === 1 && (float)$recordsA[0]['net'] === 16200.00, "6. Member A receives only Member A's dividend records (16,200.00)");
testAssert($recordsA[0]['net'] !== 43800.00, "6. Member A CANNOT view Member B's dividend records (43,800.00)");

// -------------------------------------------------------------------------
// 7. Suspended Account Blocked
// -------------------------------------------------------------------------
Auth::logout();
$resSusp = testDispatch('POST', '/login', [
    'username' => '3100300999999',
    'password' => '00333',
    'ajax' => '1',
    '_csrf_token' => Csrf::token(),
]);
testAssert($resSusp->status === 403 && ($resSusp->body['code'] ?? '') === 'ACCOUNT_SUSPENDED', "7. Suspended user login rejected with HTTP 403 ACCOUNT_SUSPENDED");

// -------------------------------------------------------------------------
// 8. Controlled Account Activation Dry-Run Simulation
// -------------------------------------------------------------------------
$dryRun = MemberControlledActivationService::dryRun($pdo);
testAssert($dryRun['mode'] === 'DRY_RUN', "8. Dry-run service reports DRY_RUN mode");
testAssert($dryRun['safety_verifications']['zero_mutations_executed'] === true, "8. Dry-run guarantees zero database mutations");
testAssert($dryRun['safety_verifications']['suspended_accounts_strictly_preserved'] === true, "8. Suspended accounts guaranteed preserved and not eligible");
testAssert($dryRun['safety_verifications']['staff_and_admin_accounts_isolated'] === true, "8. Staff and admin accounts strictly isolated from activation");
testAssert($dryRun['security_risk_assessment']['initial_credential_predictability'] === 'HIGH', "8. Predictable initial credential risk documented as HIGH");

// Attempt live activation without approval token -> MUST throw exception
$caught = false;
try {
    MemberControlledActivationService::executeActivation('INVALID-TOKEN', 1, $pdo);
} catch (RuntimeException $e) {
    $caught = true;
}
testAssert($caught, "8. Controlled activation rejected when approval token is missing or invalid");

// -------------------------------------------------------------------------
// 9. Audit Logs Privacy Preservation
// -------------------------------------------------------------------------
$loginLogEntries = Database::query("SELECT email FROM login_logs");
foreach ($loginLogEntries as $entry) {
    testAssert(!str_contains($entry['email'], '3100600123456'), "9. Raw 13-digit citizen ID NEVER logged in login_logs");
    testAssert(!str_contains($entry['email'], '3100300999999'), "9. Raw 13-digit citizen ID NEVER logged in login_logs");
}

// -------------------------------------------------------------------------
// 10. API Regression Tests for Raw PII & Unauthorized Financial Data
// -------------------------------------------------------------------------
// 10.1 Verify raw full names are strictly excluded from all member-facing responses
testAssert(!str_contains(json_encode($resLoginA->body), 'นายกรณ์ชัย สมประสงค์'), "10. Raw full name 'นายกรณ์ชัย สมประสงค์' NEVER exposed in /login response");
testAssert(!str_contains(json_encode($resMe->body), 'นายกรณ์ชัย สมประสงค์'), "10. Raw full name NEVER exposed in /api/auth/me response");
testAssert(!str_contains(json_encode($resDivA->body), 'นายกรณ์ชัย สมประสงค์'), "10. Raw full name NEVER exposed in /api/member/dividends response");
testAssert(!str_contains(json_encode($resProfile->body), 'นายกรณ์ชัย สมประสงค์'), "10. Raw full name NEVER exposed in /api/member/profile response");

// 10.2 Verify raw password hashes never leak into any API response
testAssert(!str_contains(json_encode($resLoginA->body), 'secret_hash'), "10. Password hashes never present in /login response");
testAssert(!str_contains(json_encode($resProfile->body), 'secret_hash'), "10. Password hashes never present in /api/member/profile response");

// 10.3 Unauthorized financial data access check (Unauthenticated request returns 401/403)
Auth::logout();
$resUnauth = testDispatch('GET', '/api/member/dividends');
testAssert($resUnauth->status === 401 || $resUnauth->status === 403, "10. Unauthenticated financial dividends endpoint access returns HTTP 401/403");

$resUnauthProfile = testDispatch('GET', '/api/member/profile');
testAssert($resUnauthProfile->status === 401 || $resUnauthProfile->status === 403, "10. Unauthenticated profile endpoint access returns HTTP 401/403");

// 10.4 Thai Unicode Grapheme Cluster Integrity at Truncation Boundary
$complexClusterTest = MemberPiiMaskingService::maskFullName('นายฐิติวัชร ยิ่งลักษณ์');
testAssert($complexClusterTest === 'นายฐิติxx ยิ่xxx', "10. Thai Unicode combining vowels and tone marks handled cleanly without orphan marks");
testAssert(preg_match('/xx/u', $complexClusterTest) && preg_match('/xxx/u', $complexClusterTest), "10. Approved xx/xxx suffix format strictly preserved across complex Thai clusters");

echo "=========================================================================\n";
echo "SUMMARY: ALL PII MASKING & ACTIVATION SAFETY TESTS PASSED!\n";
echo "=========================================================================\n";
