<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Controllers\Admin\PopupController;
use App\Controllers\Api\SpaApiController;

final class TestResponse extends Response
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

echo "========================================================================\n";
echo " RYCOOP SMART POPUP CAMPAIGN MANAGER V2 — TEST SUITE\n";
echo "========================================================================\n";

$passed = 0;
$failed = 0;

function it(string $desc, bool $cond): void {
    global $passed, $failed;
    if ($cond) {
        echo " [PASS] {$desc}\n";
        $passed++;
    } else {
        echo " [FAIL] {$desc}\n";
        $failed++;
    }
}

function makeRequest(string $method = 'GET', array $post = [], array $get = []): Request {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = $post;
    $_GET = $get;
    return new Request();
}

// Requirement 1: Non-destructive test setup. ONLY clean up tagged test records!
Database::execute("DELETE FROM popups WHERE title LIKE '[POPUP_V2_TEST]%'");

// 1. Initial count check
$resObj = new TestResponse();
$controller = new PopupController(makeRequest('GET'), $resObj);
$controller->apiIndex();

it("1. Popup index API returns success and list of campaigns", 
    $resObj->body['success'] === true && isset($resObj->body['data']['count']));

// 2. Insert Popup 1 (banner_only)
$post1 = [
    'title' => '[POPUP_V2_TEST] 1: ตรวจสอบสิทธิเงินปันผล 2568',
    'subtitle' => 'คำนวณและตรวจสอบสิทธิประโยชน์ปันผลเฉลี่ยคืนผ่านระบบดิจิทัล',
    'banner_image' => 'popup_dividend.jpg',
    'layout_mode' => 'banner_only',
    'button_text' => 'ตรวจสอบทันที',
    'button_url' => '/dividend-estimator',
    'priority_order' => 1,
    'status' => 'active'
];
$res1 = new TestResponse();
$c1 = new PopupController(makeRequest('POST', $post1), $res1);
$c1->apiStore();

it("2. Insert Popup 1 succeeds (banner_only)", 
    $res1->body['success'] === true && ($res1->body['data']['id'] ?? 0) > 0);

// 3. Insert Popup 2 (mascot_only)
$post2 = [
    'title' => '[POPUP_V2_TEST] 2: น้องออมสุขชวนออมทรัพย์ทวีสิน',
    'subtitle' => 'เปิดบัญชีเงินฝากออมทรัพย์พิเศษดอกเบี้ยสูงเพื่อความมั่นคง',
    'layout_mode' => 'mascot_only',
    'mascot_image' => '',
    'button_text' => 'ดูอัตราดอกเบี้ย',
    'button_url' => '/deposits',
    'priority_order' => 2,
    'status' => 'active'
];
$res2 = new TestResponse();
$c2 = new PopupController(makeRequest('POST', $post2), $res2);
$c2->apiStore();

it("3. Insert Popup 2 succeeds with mascot_only", 
    $res2->body['success'] === true && ($res2->body['data']['id'] ?? 0) > 0);

// 4. Insert Popup 3 (banner_and_mascot)
$post3 = [
    'title' => '[POPUP_V2_TEST] 3: สวัสดิการและกองทุนสงเคราะห์สมาชิก',
    'subtitle' => 'คุ้มครองดูแลสมาชิกและครอบครัวในทุกช่วงชีวิต',
    'banner_image' => 'Announcement.png',
    'layout_mode' => 'banner_and_mascot',
    'button_text' => 'ดาวน์โหลดแบบฟอร์ม',
    'button_url' => '/documents',
    'priority_order' => 3,
    'status' => 'active'
];
$res3 = new TestResponse();
$c3 = new PopupController(makeRequest('POST', $post3), $res3);
$c3->apiStore();

it("4. Insert Popup 3 succeeds (reaching max limit of 3)", 
    $res3->body['success'] === true && ($res3->body['data']['id'] ?? 0) > 0);

// 5. Index API shows can_create is false when 3 records exist
$resIndex2 = new TestResponse();
$cIndex2 = new PopupController(makeRequest('GET'), $resIndex2);
$cIndex2->apiIndex();

it("5. Exactly 3 records configured, can_create is false", 
    $resIndex2->body['data']['count'] >= 3 && $resIndex2->body['data']['can_create'] === false);

// 6. Attempting 4th popup is strictly blocked
$post4 = [
    'title' => '[POPUP_V2_TEST] 4: แคมเปญส่วนเกินที่ต้องถูกปฏิเสธ',
    'subtitle' => 'ต้องไม่อนุญาตให้สร้าง',
    'layout_mode' => 'banner_only',
    'priority_order' => 1
];
$res4 = new TestResponse();
$c4 = new PopupController(makeRequest('POST', $post4), $res4);
$c4->apiStore();

it("6. Attempting 4th popup is strictly blocked with MAX_POPUPS_REACHED (422)", 
    $res4->status === 422 && ($res4->body['code'] ?? '') === 'MAX_POPUPS_REACHED');

// 7. Duplicate title check (updating Popup 2 with title of Popup 1)
$postDup = [
    'title' => '[POPUP_V2_TEST] 1: ตรวจสอบสิทธิเงินปันผล 2568',
    'subtitle' => 'อัปเดตชื่อซ้ำ',
    'layout_mode' => 'mascot_only'
];
$resDup = new TestResponse();
$cDup = new PopupController(makeRequest('POST', $postDup), $resDup);
$cDup->apiUpdate((string)$res2->body['data']['id']);

it("7. Duplicate title is rejected", 
    $resDup->body['success'] === false && str_contains($resDup->body['message'], 'มีป็อปอัปชื่อนี้อยู่ในระบบแล้ว'));

// 8. Public API returns active popups ordered by priority
$resPub = new TestResponse();
$spaController = new SpaApiController(makeRequest('GET'), $resPub);
$spaController->popups();

it("8. Public API returns active popups sorted by priority", 
    $resPub->body['success'] === true && count($resPub->body['data']) === 3 && $resPub->body['data'][0]['priority'] === 1);

// 9. Requirement 7: Mascot fallback provides approved official artwork /assets/img/logo.webp
// Unapproved mascot-aomsuk.svg must NOT be published to the public endpoint
it("9. Mascot fallback provides approved official artwork (/assets/img/logo.webp) and unapproved asset is disabled", 
    $resPub->body['data'][1]['layout_mode'] === 'mascot_only' && 
    str_contains($resPub->body['data'][1]['mascot_image'], 'logo.webp') &&
    !str_contains($resPub->body['data'][1]['mascot_image'], 'mascot-aomsuk.svg'));

// 10. Update Popup 1 to inactive
$postUp = [
    'title' => '[POPUP_V2_TEST] 1 (ปรับปรุง): สิทธิเงินปันผล 2568',
    'subtitle' => 'อัปเดตข้อมูล',
    'priority_order' => 1,
    'status' => 'inactive'
];
$resUp = new TestResponse();
$cUpdate = new PopupController(makeRequest('POST', $postUp), $resUp);
$cUpdate->apiUpdate((string)$res1->body['data']['id']);

it("10. Update popup succeeds and can change status to inactive", 
    $resUp->body['success'] === true && $resUp->body['data']['status'] === 'inactive');

// 11. Public API now excludes inactive popup (should have 2 active)
$resPub2 = new TestResponse();
$spa2 = new SpaApiController(makeRequest('GET'), $resPub2);
$spa2->popups();

it("11. Public API excludes inactive popups (now returns 2)", 
    $resPub2->body['success'] === true && count($resPub2->body['data']) === 2);

// 12. Soft-delete Popup 3
$resDel = new TestResponse();
$cDel = new PopupController(makeRequest('POST'), $resDel);
$cDel->apiDestroy((string)$res3->body['data']['id']);

it("12. Soft-delete popup succeeds", 
    $resDel->body['success'] === true);

// 13. Count drops, can_create becomes true again
$resIndex3 = new TestResponse();
$cIndex3 = new PopupController(makeRequest('GET'), $resIndex3);
$cIndex3->apiIndex();

it("13. Count drops to 2, can_create becomes true again", 
    $resIndex3->body['data']['count'] === 2 && $resIndex3->body['data']['can_create'] === true);

// 14. Protected Core Verification: Data intact
$memberCount = (int)Database::value("SELECT COUNT(*) FROM members");
$userCount = (int)Database::value("SELECT COUNT(*) FROM users");
$dividendCount = (int)Database::value("SELECT COUNT(*) FROM member_dividends");

it("15. Protected core members count intact (2,508)", $memberCount === 2508);
it("16. Protected core users count intact (2,510)", $userCount === 2510);
it("17. Protected core dividends count intact (4,855)", $dividendCount === 4855);

// Requirement 1: Non-destructive teardown: Only delete [POPUP_V2_TEST] records
Database::execute("DELETE FROM popups WHERE title LIKE '[POPUP_V2_TEST]%'");

echo "========================================================================\n";
echo " RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================================\n";

if ($failed > 0) {
    exit(1);
}
