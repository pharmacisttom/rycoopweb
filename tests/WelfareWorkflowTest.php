<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_WARNING);

require_once __DIR__ . '/../app/Core/Helpers.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Logger.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Request.php';
require_once __DIR__ . '/../app/Core/Response.php';
require_once __DIR__ . '/../app/Core/Controller.php';
require_once __DIR__ . '/../app/Controllers/Api/SpaApiController.php';

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Controllers\Api\SpaApiController;

class MockWelfareResponse extends Response
{
    public int $statusCode = 200;
    public ?array $jsonData = null;

    public function json(array $data, int $status = 200): void
    {
        $this->statusCode = $status;
        $this->jsonData = $data;
    }
}

function assertWelfare(bool $condition, string $message): void {
    if (!$condition) {
        echo "FAIL: {$message}\n";
        exit(1);
    }
    echo "PASS: {$message}\n";
}

echo "Running Welfare Subsystem Database & API Consistency Tests...\n";
echo "=========================================================================\n";

// 1. Table schema & records check
assertWelfare(Database::hasTable('welfare'), "1.1 Table welfare exists in database");
$activeCount = (int)Database::value("SELECT COUNT(*) FROM welfare WHERE status = 'active' AND deleted_at IS NULL");
assertWelfare($activeCount >= 3, "1.2 Database has at least 3 approved active welfare programs (actual: {$activeCount})");

// 2. Public API endpoint test
$res1 = new MockWelfareResponse();
$controller1 = new SpaApiController(new Request(), $res1);
$controller1->welfare();

assertWelfare($res1->statusCode === 200, "2.1 /api/public/welfare returns HTTP 200");
assertWelfare($res1->jsonData['success'] === true, "2.2 API response success flag is true");
assertWelfare(is_array($res1->jsonData['data']), "2.3 Response data is an array");
assertWelfare(count($res1->jsonData['data']) === $activeCount, "2.4 Returned program count matches database active count");

// 3. Schema integrity in returned payload
$first = $res1->jsonData['data'][0];
$requiredKeys = ['id', 'category', 'title', 'slug', 'short_description', 'benefit_amount', 'eligibility', 'status'];
foreach ($requiredKeys as $k) {
    assertWelfare(array_key_exists($k, $first), "3.1 Payload contains required key '{$k}'");
}
assertWelfare($first['status'] === 'active', "3.2 Program status is strictly 'active'");

// 4. Draft / Inactive program isolation test
$draftId = (int)Database::insert(
    "INSERT INTO welfare (category, title, slug, short_description, benefit_amount, status, sort_order, created_at, updated_at)
     VALUES ('สวัสดิการพิเศษ', 'โครงการร่างสวัสดิการที่ยังไม่อนุมัติ', 'draft-unapproved-welfare', 'คำอธิบายร่าง', '50,000 บาท', 'inactive', 99, NOW(), NOW())"
);

assertWelfare($draftId > 0, "4.1 Inserted test inactive/draft welfare program");

$res2 = new MockWelfareResponse();
$controller2 = new SpaApiController(new Request(), $res2);
$controller2->welfare();

$returnedIds = array_column($res2->jsonData['data'], 'id');
assertWelfare(!in_array($draftId, $returnedIds, true), "4.2 Draft/inactive welfare program is strictly EXCLUDED from public endpoint");

// Clean up test draft record
Database::execute("DELETE FROM welfare WHERE id = ?", [$draftId]);

echo "=========================================================================\n";
echo "All Welfare Subsystem Consistency tests PASSED successfully!\n";
