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

class MockEserviceFaqResponse extends Response
{
    public int $statusCode = 200;
    public ?array $jsonData = null;

    public function json(array $data, int $status = 200): void
    {
        $this->statusCode = $status;
        $this->jsonData = $data;
    }
}

function assertEf(bool $cond, string $msg): void
{
    if (!$cond) {
        echo "FAIL: {$msg}\n";
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

echo "Running E-Services & FAQs Database & API Consistency Tests...\n";
echo "=========================================================================\n";

// 1. E-Services Table & Schema
assertEf(Database::hasTable('eservice_links'), "1.1 Table eservice_links exists in database");

$activeEservicesCount = (int)Database::value("SELECT COUNT(*) FROM eservice_links WHERE status = 'active' AND deleted_at IS NULL");
assertEf($activeEservicesCount >= 4, "1.2 Database has at least 4 active approved e-services (actual: {$activeEservicesCount})");

// 2. Public E-Services Endpoint
$resEs = new MockEserviceFaqResponse();
$controllerEs = new SpaApiController(new Request(), $resEs);
$controllerEs->eservices();

assertEf($resEs->statusCode === 200, "2.1 /api/public/eservices returns HTTP 200");
assertEf($resEs->jsonData['success'] === true, "2.2 API response success flag is true");
assertEf(is_array($resEs->jsonData['data']), "2.3 Response data is an array");
assertEf(count($resEs->jsonData['data']) === $activeEservicesCount, "2.4 Returned count matches active count ({$activeEservicesCount})");

// 3. E-Service Schema Verification
$firstEs = $resEs->jsonData['data'][0];
$esRequiredKeys = ['id', 'name', 'title', 'description', 'category', 'url', 'icon', 'is_maintenance', 'open_new_tab'];
foreach ($esRequiredKeys as $k) {
    assertEf(array_key_exists($k, $firstEs), "3.1 E-Service payload contains required key '{$k}'");
}

// 4. Inactive E-Service Isolation
$inactiveEsId = (int)Database::insert(
    "INSERT INTO eservice_links (name, description, url, icon, category, is_internal, open_new_tab, confirm_before_redirect, is_maintenance, sort_order, status, created_at, updated_at)
     VALUES ('Inactive Portal Test', 'Test Desc', 'https://example.com/test', 'globe', 'general', 0, 1, 0, 0, 999, 'inactive', NOW(), NOW())"
);
assertEf($inactiveEsId > 0, "4.1 Inserted test inactive e-service");

$resEsCheck = new MockEserviceFaqResponse();
$controllerEsCheck = new SpaApiController(new Request(), $resEsCheck);
$controllerEsCheck->eservices();

$returnedEsIds = array_column($resEsCheck->jsonData['data'], 'id');
assertEf(!in_array($inactiveEsId, $returnedEsIds, true), "4.2 Inactive e-service is strictly EXCLUDED from public endpoint");

// Clean up test e-service
Database::execute("DELETE FROM eservice_links WHERE id = ?", [$inactiveEsId]);

// 5. FAQs Table & Schema
assertEf(Database::hasTable('faqs'), "5.1 Table faqs exists in database");

$activeFaqsCount = (int)Database::value("SELECT COUNT(*) FROM faqs WHERE status = 'active'");
assertEf($activeFaqsCount >= 4, "5.2 Database has at least 4 active FAQs (actual: {$activeFaqsCount})");

// 6. Public FAQs Endpoint
$resFaq = new MockEserviceFaqResponse();
$controllerFaq = new SpaApiController(new Request(), $resFaq);
$controllerFaq->faqs();

assertEf($resFaq->statusCode === 200, "6.1 /api/public/faqs returns HTTP 200");
assertEf($resFaq->jsonData['success'] === true, "6.2 API response success flag is true");
assertEf(is_array($resFaq->jsonData['data']), "6.3 Response data is an array");
assertEf(count($resFaq->jsonData['data']) === $activeFaqsCount, "6.4 Returned count matches active count ({$activeFaqsCount})");

// 7. FAQ Schema Verification
$firstFaq = $resFaq->jsonData['data'][0];
$faqRequiredKeys = ['id', 'question', 'answer', 'category', 'sort_order'];
foreach ($faqRequiredKeys as $k) {
    assertEf(array_key_exists($k, $firstFaq), "7.1 FAQ payload contains required key '{$k}'");
}

// 8. Inactive FAQ Isolation
$inactiveFaqId = (int)Database::insert(
    "INSERT INTO faqs (category, question, answer, sort_order, status, created_at, updated_at)
     VALUES ('general', 'Inactive FAQ Question Test', 'Answer Test', 999, 'inactive', NOW(), NOW())"
);
assertEf($inactiveFaqId > 0, "8.1 Inserted test inactive FAQ");

$resFaqCheck = new MockEserviceFaqResponse();
$controllerFaqCheck = new SpaApiController(new Request(), $resFaqCheck);
$controllerFaqCheck->faqs();

$returnedFaqIds = array_column($resFaqCheck->jsonData['data'], 'id');
assertEf(!in_array($inactiveFaqId, $returnedFaqIds, true), "8.2 Inactive FAQ is strictly EXCLUDED from public endpoint");

// Clean up test faq
Database::execute("DELETE FROM faqs WHERE id = ?", [$inactiveFaqId]);

echo "=========================================================================\n";
echo "All E-Services & FAQs Consistency tests PASSED successfully!\n";
