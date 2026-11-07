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

class MockDocResponse extends Response
{
    public int $statusCode = 200;
    public ?array $jsonData = null;

    public function json(array $data, int $status = 200): void
    {
        $this->statusCode = $status;
        $this->jsonData = $data;
    }
}

function assertDoc(bool $condition, string $message): void {
    if (!$condition) {
        echo "FAIL: {$message}\n";
        exit(1);
    }
    echo "PASS: {$message}\n";
}

echo "Running Downloadable Documents Database & API Consistency Tests...\n";
echo "=========================================================================\n";

// 1. Table schema & records check
assertDoc(Database::hasTable('documents'), "1.1 Table documents exists in database");
assertDoc(Database::hasTable('document_categories'), "1.2 Table document_categories exists in database");

$activeDocs = (int)Database::value("SELECT COUNT(*) FROM documents WHERE status = 'active' AND deleted_at IS NULL");
assertDoc($activeDocs >= 12, "1.3 Database has at least 12 active approved documents (actual: {$activeDocs})");

// 2. Public API endpoint test
$res1 = new MockDocResponse();
$controller1 = new SpaApiController(new Request(), $res1);
$controller1->documents();

assertDoc($res1->statusCode === 200, "2.1 /api/public/documents returns HTTP 200");
assertDoc($res1->jsonData['success'] === true, "2.2 API response success flag is true");
assertDoc(is_array($res1->jsonData['data']), "2.3 Response data is an array");
assertDoc(count($res1->jsonData['data']) === $activeDocs, "2.4 Returned document count matches active database count");

// 3. Schema integrity in returned payload
$first = $res1->jsonData['data'][0];
$requiredKeys = ['id', 'title', 'category', 'category_slug', 'file_path', 'file_size', 'download_count', 'file_url'];
foreach ($requiredKeys as $k) {
    assertDoc(array_key_exists($k, $first), "3.1 Payload contains required key '{$k}'");
}
assertDoc(!empty($first['file_url']), "3.2 file_url is resolved and non-empty");

// 4. Download tracking test
$testDocId = (string)$first['id'];
$initialDownloads = (int)$first['download_count'];

$resDownload = new MockDocResponse();
$controllerDownload = new SpaApiController(new Request(), $resDownload);
$controllerDownload->downloadDocument($testDocId);

assertDoc($resDownload->statusCode === 200, "4.1 /api/public/documents/{id}/download returns HTTP 200");
$newDbDownloads = (int)Database::value("SELECT download_count FROM documents WHERE id = ?", [(int)$testDocId]);
assertDoc($newDbDownloads === $initialDownloads + 1, "4.2 Database download_count incremented by 1 (actual: {$newDbDownloads})");

// 5. Inactive document isolation test
$inactiveId = (int)Database::insert(
    "INSERT INTO documents (category_id, title, slug, file_path, file_size, status, sort_order, created_at, updated_at)
     VALUES (1, 'เอกสารร่างที่ยังไม่อนุมัติ', 'draft-unapproved-doc', 'storage/uploads/documents/draft.pdf', 1024, 'inactive', 99, NOW(), NOW())"
);

assertDoc($inactiveId > 0, "5.1 Inserted test inactive document");

$resInactiveCheck = new MockDocResponse();
$controllerInactiveCheck = new SpaApiController(new Request(), $resInactiveCheck);
$controllerInactiveCheck->documents();

$returnedDocIds = array_column($resInactiveCheck->jsonData['data'], 'id');
assertDoc(!in_array($inactiveId, $returnedDocIds, true), "5.2 Inactive document is strictly EXCLUDED from public endpoint");

// Clean up
Database::execute("DELETE FROM documents WHERE id = ?", [$inactiveId]);
// Reset download count for clean state
Database::execute("UPDATE documents SET download_count = 0 WHERE id = ?", [(int)$testDocId]);

echo "=========================================================================\n";
echo "All Downloadable Documents Consistency tests PASSED successfully!\n";
