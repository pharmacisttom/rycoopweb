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

final class ConcurrencyTestResponse extends Response
{
    public int $status = 200;
    public array $body = [];

    public function json(array $data, int $status = 200): void
    {
        $this->body = $data;
        $this->status = $status;
    }
}

echo "========================================================================\n";
echo " POPUP CONTROLLER CONCURRENCY & SERIALIZATION VERIFICATION\n";
echo "========================================================================\n";

$passed = 0;
$failed = 0;

function assertCondition(string $desc, bool $cond): void {
    global $passed, $failed;
    if ($cond) {
        echo " [PASS] {$desc}\n";
        $passed++;
    } else {
        echo " [FAIL] {$desc}\n";
        $failed++;
    }
}

// 1. Reset popups to clean state
Database::execute("DELETE FROM popups");

// 2. Insert 2 popups
for ($i = 1; $i <= 2; $i++) {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'title' => "Initial Popup {$i}",
        'layout_mode' => 'banner_only',
        'status' => 'active',
        'priority_order' => $i
    ];
    $res = new ConcurrencyTestResponse();
    $c = new PopupController(new Request(), $res);
    $c->apiStore();
    assertCondition("Pre-populate popup {$i} succeeds", $res->body['success'] === true);
}

$count = (int)Database::value("SELECT COUNT(*) FROM popups WHERE deleted_at IS NULL");
assertCondition("Current count is exactly 2", $count === 2);

// 3. Test Named Mutex Lock across independent PDO connections
// Create a secondary connection to simulate a concurrent transaction
$config = config('database.connections.mysql');
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], $config['port'], $config['database'], $config['charset']);
$pdo2 = new PDO($dsn, $config['username'], $config['password'], $config['options']);

// Connection 2 takes the named lock
$lockAcquired = (int)$pdo2->query("SELECT GET_LOCK('rycoop_popup_quota_mutex', 2)")->fetchColumn();
assertCondition("Connection 2 acquires rycoop_popup_quota_mutex", $lockAcquired === 1);

// Now Connection 1 attempts to acquire with 1s timeout - should return 0 (locked)
$mainLockAttempt = (int)Database::value("SELECT GET_LOCK('rycoop_popup_quota_mutex', 1)");
assertCondition("Connection 1 is blocked by Connection 2 lock (returns 0)", $mainLockAttempt === 0);

// Connection 2 releases the lock
$pdo2->query("SELECT RELEASE_LOCK('rycoop_popup_quota_mutex')");

// 4. Simultaneous simulation:
// Both Request A and Request B attempt to insert when count = 2
// Request A creates popup 3 -> succeeds
// Request B attempts popup 4 -> serialized and rejected with MAX_POPUPS_REACHED
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'title' => "Simultaneous Candidate A",
    'layout_mode' => 'banner_only',
    'status' => 'active',
    'priority_order' => 3
];
$resA = new ConcurrencyTestResponse();
$cA = new PopupController(new Request(), $resA);
$cA->apiStore();

assertCondition("Candidate A succeeds (reaches limit of 3)", $resA->status === 201 && $resA->body['success'] === true);

$_POST = [
    'title' => "Simultaneous Candidate B",
    'layout_mode' => 'banner_only',
    'status' => 'active',
    'priority_order' => 3
];
$resB = new ConcurrencyTestResponse();
$cB = new PopupController(new Request(), $resB);
$cB->apiStore();

assertCondition("Candidate B is strictly rejected with MAX_POPUPS_REACHED (422)", 
    $resB->status === 422 && ($resB->body['code'] ?? '') === 'MAX_POPUPS_REACHED');

// 5. Final database row count verification
$finalCount = (int)Database::value("SELECT COUNT(*) FROM popups WHERE deleted_at IS NULL");
assertCondition("Database count strictly equals 3 (limit never breached)", $finalCount === 3);

// Clean up test records
Database::execute("DELETE FROM popups");

echo "========================================================================\n";
echo " CONCURRENCY VERIFICATION: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================================\n";

if ($failed > 0) {
    exit(1);
}
