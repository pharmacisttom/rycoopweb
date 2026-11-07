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
require_once __DIR__ . '/../app/Services/PublicContentService.php';
require_once __DIR__ . '/../app/Controllers/Api/SpaApiController.php';

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\PublicContentService;
use App\Controllers\Api\SpaApiController;

class MockProductRateResponse extends Response
{
    public int $statusCode = 200;
    public ?array $jsonData = null;

    public function json(array $data, int $status = 200): void
    {
        $this->statusCode = $status;
        $this->jsonData = $data;
    }
}

function assertPr(bool $cond, string $msg): void
{
    if (!$cond) {
        echo "FAIL: {$msg}\n";
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

echo "Running Products, Interest Rates & Contact Consistency Tests...\n";
echo "=========================================================================\n";

// 1. Cooperative Contact Info
$catalog = PublicContentService::catalog();
$coopInfo = $catalog['coopInfo'];

assertPr(!empty($coopInfo['phone']), "1.1 Coop phone is not empty");
assertPr(str_contains($coopInfo['phone'], '038-967-409') || str_contains($coopInfo['phone'], '086-368-1132'), "1.2 Official phone matches cooperative records (actual: {$coopInfo['phone']})");
assertPr(!str_contains($coopInfo['phone'], '038-611178'), "1.3 Obsolete phone 038-611178 is eliminated");
assertPr($coopInfo['email'] === 'coop2530@gmail.com', "1.4 Official email matches rayongcoop records");

// 2. Interest Rates API Endpoint
$resRates = new MockProductRateResponse();
$controllerRates = new SpaApiController(new Request(), $resRates);
$controllerRates->interestRates();

assertPr($resRates->statusCode === 200, "2.1 /api/public/interest-rates returns HTTP 200");
assertPr($resRates->jsonData['success'] === true, "2.2 Response success is true");
assertPr(isset($resRates->jsonData['data']['deposit']), "2.3 Response contains deposit array");
assertPr(isset($resRates->jsonData['data']['loan']), "2.4 Response contains loan array");

$deposits = $resRates->jsonData['data']['deposit'];
assertPr(count($deposits) >= 2, "2.5 Deposit rate products are present");
$depositRates = array_column($deposits, 'rate');
assertPr(in_array(1.75, $depositRates, true), "2.6 Contains active deposit rate 1.75%");
assertPr(in_array(1.50, $depositRates, true), "2.7 Contains active deposit rate 1.50%");
assertPr(!in_array(3.10, $depositRates, true), "2.8 Fabricated rate 3.10% is not present");

// 3. Loan Products API Endpoint
$resLoans = new MockProductRateResponse();
$controllerLoans = new SpaApiController(new Request(), $resLoans);
$controllerLoans->loanProducts();

assertPr($resLoans->statusCode === 200, "3.1 /api/public/loan-products returns HTTP 200");
assertPr($resLoans->jsonData['success'] === true, "3.2 Response success is true");
assertPr(is_array($resLoans->jsonData['data']), "3.3 Response data is array");
assertPr(count($resLoans->jsonData['data']) >= 5, "3.4 At least 5 official loan products returned");

$loanKeys = ['id', 'name', 'max_amount', 'max_term_months', 'interest_rate'];
$firstLoan = $resLoans->jsonData['data'][0];
foreach ($loanKeys as $k) {
    assertPr(array_key_exists($k, $firstLoan), "3.5 Loan payload contains required key '{$k}'");
}

// 4. Cross-Endpoint Consistency
$emergency = null;
foreach ($resLoans->jsonData['data'] as $p) {
    if ($p['id'] === 'emergency') {
        $emergency = $p;
        break;
    }
}
assertPr($emergency !== null, "4.1 Emergency loan product exists");
assertPr($emergency['max_amount'] === 200000, "4.2 Emergency loan max limit is 200,000");
assertPr($emergency['max_term_months'] === 12, "4.3 Emergency loan max term is 12 months");

echo "=========================================================================\n";
echo "All Products, Interest Rates & Contact Consistency tests PASSED successfully!\n";
