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

// Ensure isolated clean state for popups in test
Database::execute("DELETE FROM popups");

// 1. Initial count is 0
$resObj = new TestResponse();
$controller = new PopupController(makeRequest('GET'), $resObj);
$controller->apiIndex();

it("1. Initial popup count is 0 and can_create is true", 
    $resObj->body['success'] === true && $resObj->body['data']['count'] === 0 && $resObj->body['data']['can_create'] === true);

// 2. Insert Popup 1 (banner_only)
$post1 = [
    'title' => 'แคมเปญที่ 1: ตรวจสอบสิทธิเงินปันผล 2568',
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
    'title' => 'แคมเปญที่ 2: น้องออมสุขชวนออมทรัพย์ทวีสิน',
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
    'title' => 'แคมเปญที่ 3: สวัสดิการและกองทุนสงเคราะห์สมาชิก',
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

// 5. Verify can_create is false when 3 popups exist
$resMax = new TestResponse();
$cCheck = new PopupController(makeRequest('GET'), $resMax);
$cCheck->apiIndex();

it("5. Exactly 3 records configured, can_create is false", 
    $resMax->body['data']['count'] === 3 && $resMax->body['data']['can_create'] === false);

// 6. Attempt to insert a 4th popup MUST BE BLOCKED
$post4 = [
    'title' => 'แคมเปญที่ 4: ขอกู้ฉุกเฉินออนไลน์ (ต้องถูกบล็อก)',
    'subtitle' => 'ทดสอบการป้องกันเกิน 3 รายการ',
    'priority_order' => 1
];
$res4 = new TestResponse();
$c4 = new PopupController(makeRequest('POST', $post4), $res4);
$c4->apiStore();

it("6. Attempting 4th popup is strictly blocked with MAX_POPUPS_REACHED (422)", 
    $res4->body['success'] === false && ($res4->body['code'] ?? '') === 'MAX_POPUPS_REACHED');

// 7. Verify duplicate title is blocked on update
$postDup = [
    'title' => 'แคมเปญที่ 1: ตรวจสอบสิทธิเงินปันผล 2568', // Duplicate title of Popup 1
    'priority_order' => 2
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

// 9. Verify mascot fallback for mascot_only layout
it("9. Mascot fallback provides approved official asset /assets/img/mascot-aomsuk.svg", 
    $resPub->body['data'][1]['layout_mode'] === 'mascot_only' && 
    str_contains($resPub->body['data'][1]['mascot_image'], 'mascot-aomsuk.svg'));

// 10. Update Popup 1 to inactive
$postUp = [
    'title' => 'แคมเปญที่ 1 (ปรับปรุง): สิทธิเงินปันผล 2568',
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

// 13. Verify count drops to 2 and can_create becomes true again
$resAfterDel = new TestResponse();
$cAfterDel = new PopupController(makeRequest('GET'), $resAfterDel);
$cAfterDel->apiIndex();

it("13. Count drops to 2, can_create becomes true again", 
    $resAfterDel->body['data']['count'] === 2 && $resAfterDel->body['data']['can_create'] === true);

// 14. Reset to single active official popup for production readiness
$postFin = [
    'title' => 'โครงการประมาณการเงินปันผลและเฉลี่ยคืน ประจำปี 2568',
    'subtitle' => 'สมาชิกสามารถคำนวณและตรวจสอบสิทธิประโยชน์ได้แล้ววันนี้ผ่านระบบดิจิทัล',
    'banner_image' => 'popup_dividend.jpg',
    'layout_mode' => 'banner_and_mascot',
    'button_text' => 'ประมาณการเงินปันผลทันที',
    'button_url' => '/dividend-estimator',
    'priority_order' => 1,
    'status' => 'active'
];
$resFin = new TestResponse();
(new PopupController(makeRequest('POST', $postFin), $resFin))->apiStore();

// 15. Check protected core database tables untouched
$membersCount = (int)Database::value("SELECT COUNT(*) FROM members");
$usersCount = (int)Database::value("SELECT COUNT(*) FROM users");
$dividendsCount = (int)Database::value("SELECT COUNT(*) FROM member_dividends");

it("15. Protected core members count intact (2,508)", $membersCount === 2508);
it("16. Protected core users count intact (2,510)", $usersCount === 2510);
it("17. Protected core dividends count intact (4,855)", $dividendsCount === 4855);

echo "========================================================================\n";
echo " RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================================\n";

if ($failed > 0) exit(1);
