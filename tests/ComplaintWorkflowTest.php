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
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Services/AuditService.php';
require_once __DIR__ . '/../app/Controllers/Api/SpaApiController.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Controllers\Api\SpaApiController;

class MockComplaintResponse extends Response
{
    public int $statusCode = 200;
    public ?array $jsonData = null;

    public function json(array $data, int $status = 200): void
    {
        $this->statusCode = $status;
        $this->jsonData = $data;
    }
}

function assertComplaint(bool $condition, string $message): void {
    if (!$condition) {
        echo "FAIL: {$message}\n";
        exit(1);
    }
    echo "PASS: {$message}\n";
}

echo "Running Complaints Subsystem Server-Side Workflow Tests...\n";
echo "=========================================================================\n";

// 1. Validation test: Missing required fields
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['name' => '', 'phone' => '', 'message' => ''];
$_GET = [];
$res1 = new MockComplaintResponse();
$controller1 = new SpaApiController(new Request(), $res1);
$controller1->publicSubmitComplaint();

assertComplaint($res1->statusCode === 422, "1.1 Missing required fields returns HTTP 422");
assertComplaint($res1->jsonData['success'] === false, "1.2 Error response success flag is false");

// 2. Successful complaint submission
$uniqueMsg = 'ทดสอบร้องเรียน ' . bin2hex(random_bytes(4));
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'name' => 'นายสมชาย มั่นคง',
    'phone' => '0812345678',
    'email' => 'somchai@example.com',
    'topic' => 'ข้อเสนอแนะการให้บริการ',
    'message' => $uniqueMsg,
];
$res2 = new MockComplaintResponse();
$controller2 = new SpaApiController(new Request(), $res2);
$controller2->publicSubmitComplaint();

assertComplaint($res2->statusCode === 201, "2.1 Valid complaint submission returns HTTP 201");
assertComplaint($res2->jsonData['success'] === true, "2.2 Submission response success is true");
assertComplaint(!empty($res2->jsonData['data']['ticket_no']), "2.3 Server returned generated ticket number");

$ticketNo = $res2->jsonData['data']['ticket_no'];
assertComplaint(str_starts_with($ticketNo, 'TKT-'), "2.4 Ticket number follows format TKT-...");

// 3. Database persistence verification
$row = Database::first("SELECT * FROM complaints WHERE ticket_no = ?", [$ticketNo]);
assertComplaint($row !== null, "3.1 Complaint record persisted in MySQL complaints table");
assertComplaint($row['complainant_name'] === 'นายสมชาย มั่นคง', "3.2 Stored complainant_name matches");
assertComplaint($row['complainant_phone'] === '0812345678', "3.3 Stored complainant_phone matches");
assertComplaint($row['status'] === 'received', "3.4 Initial complaint status is 'received'");

$logs = Database::query("SELECT * FROM complaint_logs WHERE complaint_id = ?", [$row['id']]);
assertComplaint(count($logs) === 1, "3.5 Initial lifecycle log entry created in complaint_logs");
assertComplaint($logs[0]['action'] === 'ยื่นเรื่องร้องเรียนเข้าสู่ระบบ', "3.6 Initial log action matches");

// 4. Tracking with invalid ticket
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = [];
$_GET = ['ticket' => 'TKT-NONEXISTENT'];
$res4 = new MockComplaintResponse();
$controller4 = new SpaApiController(new Request(), $res4);
$controller4->publicTrackComplaint();

assertComplaint($res4->statusCode === 404, "4.1 Non-existent ticket tracking returns HTTP 404");
assertComplaint($res4->jsonData['data']['notFound'] === true, "4.2 Response indicates notFound");

// 5. Tracking with valid ticket & PII Protection
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['ticket' => $ticketNo];
$res5 = new MockComplaintResponse();
$controller5 = new SpaApiController(new Request(), $res5);
$controller5->publicTrackComplaint();

assertComplaint($res5->statusCode === 200, "5.1 Valid ticket tracking returns HTTP 200");
$trackData = $res5->jsonData['data'];
assertComplaint($trackData['id'] === $ticketNo, "5.2 Tracking ID matches ticket");
assertComplaint($trackData['status'] === 'รอดำเนินการ', "5.3 Status mapped to Thai label 'รอดำเนินการ'");
assertComplaint(!isset($trackData['complainant_phone']), "5.4 Phone number is NOT exposed in public tracking response");
assertComplaint(!isset($trackData['complainant_email']), "5.5 Email is NOT exposed in public tracking response");
assertComplaint(str_contains($trackData['name'], '***'), "5.6 Complainant name is masked for privacy ({$trackData['name']})");

// 6. Admin Status Update
$complaintId = (string)$row['id'];
$adminUser = Database::first("SELECT u.id FROM users u JOIN user_roles ur ON u.id = ur.user_id JOIN roles r ON ur.role_id = r.id WHERE r.name = 'super_admin' LIMIT 1")
    ?? Database::first("SELECT id FROM users LIMIT 1");
if ($adminUser) {
    $_SESSION['user_id'] = $adminUser['id'];
}

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'status' => 'answered',
    'priority' => 'high',
    'response_message' => 'เจ้าหน้าที่ได้ดำเนินการตรวจสอบและประสานงานแก้ไขเรียบร้อยแล้ว',
    'note' => 'ตรวจสอบพบปัญหาและแก้ไขให้แล้ว'
];
$res6 = new MockComplaintResponse();
$controller6 = new SpaApiController(new Request(), $res6);
$controller6->adminUpdateComplaintStatus($complaintId);

assertComplaint($res6->statusCode === 200, "6.1 Admin status update returns HTTP 200");
$updatedRow = Database::first("SELECT * FROM complaints WHERE id = ?", [$row['id']]);
assertComplaint($updatedRow['status'] === 'answered', "6.2 Database status updated to 'answered'");
assertComplaint(!empty($updatedRow['answered_at']), "6.3 answered_at timestamp set");
assertComplaint($updatedRow['response_message'] === 'เจ้าหน้าที่ได้ดำเนินการตรวจสอบและประสานงานแก้ไขเรียบร้อยแล้ว', "6.4 Response message recorded in database");

$updatedLogs = Database::query("SELECT * FROM complaint_logs WHERE complaint_id = ? ORDER BY id ASC", [$row['id']]);
assertComplaint(count($updatedLogs) === 2, "6.5 Second log entry created in complaint_logs for status transition");

// 7. Verify Public Tracking shows official response
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = ['ticket' => $ticketNo];
$res7 = new MockComplaintResponse();
$controller7 = new SpaApiController(new Request(), $res7);
$controller7->publicTrackComplaint();

assertComplaint($res7->jsonData['data']['status'] === 'ตอบกลับแล้ว', "7.1 Public status mapped to 'ตอบกลับแล้ว'");
assertComplaint($res7->jsonData['data']['adminReply'] === 'เจ้าหน้าที่ได้ดำเนินการตรวจสอบและประสานงานแก้ไขเรียบร้อยแล้ว', "7.2 Public response displays official cooperative reply");
assertComplaint(count($res7->jsonData['data']['logs']) === 2, "7.3 Public timeline displays all complaint logs");

// Clean up test complaint
Database::execute("DELETE FROM complaints WHERE id = ?", [$row['id']]);

echo "=========================================================================\n";
echo "All Complaints Subsystem Server-Side Workflow tests PASSED successfully!\n";
