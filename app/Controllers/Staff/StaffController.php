<?php

declare(strict_types=1);

namespace App\Controllers\Staff;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\StaffService;

class StaffController extends Controller
{
    public function dashboard(): void
    {
        $kpis = StaffService::getDashboardKPIs();
        $recentLoans = StaffService::getLoanApplications('submitted');
        $recentMembers = StaffService::searchMembers(null, null, 5);

        $this->render('staff.dashboard', [
            'title' => 'Staff Dashboard — ระบบงานเจ้าหน้าที่สหกรณ์',
            'kpis' => $kpis,
            'recentLoans' => $recentLoans,
            'recentMembers' => $recentMembers,
        ], 'layouts.admin');
    }

    public function members(): void
    {
        $keyword = $this->request->query('q');
        $department = $this->request->query('dept');
        $members = StaffService::searchMembers($keyword, $department, 50);
        $departments = Database::query("SELECT DISTINCT department FROM members WHERE department IS NOT NULL AND department != ''");

        $this->render('staff.members', [
            'title' => 'จัดการและค้นหาข้อมูลสมาชิก (Member Management)',
            'members' => $members,
            'departments' => array_column($departments, 'department'),
            'keyword' => $keyword,
            'selectedDept' => $department,
        ], 'layouts.admin');
    }

    public function memberDetail(): void
    {
        $id = (int)$this->request->query('id');
        $data = StaffService::getMember360($id);

        if (!$data) {
            Session::flash('error', 'ไม่พบข้อมูลสมาชิกที่ระบุ');
            $this->redirect(url('staff/members'));
            return;
        }

        $this->render('staff.member_detail', [
            'title' => "ข้อมูลสมาชิก 360° — {$data['member']['first_name']} {$data['member']['last_name']}",
            'data' => $data,
        ], 'layouts.admin');
    }

    public function loans(): void
    {
        $status = $this->request->query('status');
        $applications = StaffService::getLoanApplications($status);

        $this->render('staff.loans', [
            'title' => 'ตรวจสอบและอนุมัติคำขอกู้เงิน (Loan Management)',
            'applications' => $applications,
            'currentStatus' => $status,
        ], 'layouts.admin');
    }

    public function reviewLoan(): void
    {
        $appId = (int)$this->request->input('application_id');
        $action = (string)$this->request->input('action'); // approved, rejected, document_review
        $comment = trim((string)$this->request->input('comment'));
        $staffUserId = Auth::id() ?? 1;

        StaffService::reviewLoanApplication($appId, $action, $comment, $staffUserId);

        AuditService::log('loan', $action, (string)$appId, null, ['comment' => $comment]);

        $msg = match($action) {
            'approved' => 'อนุมัติคำขอกู้เงินเรียบร้อยแล้ว',
            'rejected' => 'ปฏิเสธคำขอกู้เงินเรียบร้อยแล้ว',
            'document_review' => 'ส่งคำขอเอกสารเพิ่มเติมไปยังสมาชิกเรียบร้อยแล้ว',
            default => 'บันทึกการตรวจสอบเรียบร้อยแล้ว'
        };

        Session::flash('success', $msg);
        $this->redirect(url('staff/loans'));
    }

    public function viewLoanDocument(): void
    {
        $file = trim((string)$this->request->query('file'));
        $download = (bool)$this->request->query('download');
        $origName = trim((string)$this->request->query('name'));

        if (empty($file)) {
            Session::flash('error', 'ไม่พบชื่อไฟล์เอกสาร');
            $this->redirect(url('staff/loans'));
            return;
        }

        $filename = basename(str_replace('\\', '/', $file));
        try {
            $canonicalPath = storage_upload_path('loans/' . $filename);
        } catch (\InvalidArgumentException $e) {
            $canonicalPath = '';
        }

        // Canonical location first; legacy public paths remain read-only during migration.
        $searchPaths = array_filter([
            $canonicalPath,
            dirname(__DIR__, 3) . '/public/uploads/loans/' . $filename,
            dirname(__DIR__, 3) . '/public/uploads/' . $filename,
        ]);

        $targetPath = null;
        foreach ($searchPaths as $path) {
            if (file_exists($path) && is_file($path)) {
                $targetPath = $path;
                break;
            }
        }

        if (!$targetPath) {
            http_response_code(404);
            echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>ไม่พบไฟล์</title></head><body style='font-family:sans-serif;padding:40px;text-align:center;'>";
            echo "<h2 style='color:#dc3545;'>ขออภัย ไม่พบไฟล์เอกสารในระบบ</h2>";
            echo "<p style='color:#6c757d;'>ชื่อไฟล์: " . htmlspecialchars($filename) . "</p>";
            echo "<a href='javascript:history.back()' style='display:inline-block;padding:8px 16px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:20px;margin-top:10px;'>กลับไปหน้าก่อนหน้า</a>";
            echo "</body></html>";
            exit;
        }

        $ext = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'zip' => 'application/zip',
            'txt' => 'text/plain',
        ];

        $mime = $mimeTypes[$ext] ?? mime_content_type($targetPath) ?: 'application/octet-stream';
        $disposition = $download ? 'attachment' : 'inline';
        $displayName = !empty($origName) ? basename($origName) : $filename;

        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . $disposition . '; filename="' . $displayName . '"');
        header('Content-Length: ' . filesize($targetPath));
        header('Cache-Control: private, max-age=3600, must-revalidate');
        header('Pragma: public');
        readfile($targetPath);
        exit;
    }

    public function welfare(): void
    {
        $status = $this->request->query('status');
        $applications = StaffService::getWelfareApplications($status);

        $this->render('staff.welfare', [
            'title' => 'ตรวจสอบและอนุมัติสวัสดิการสมาชิก (Welfare Claims)',
            'applications' => $applications,
            'currentStatus' => $status,
        ], 'layouts.admin');
    }

    public function reviewWelfare(): void
    {
        $appId = (int)$this->request->input('application_id');
        $action = (string)$this->request->input('action'); // approved, rejected
        $comment = trim((string)$this->request->input('comment'));

        Database::execute(
            "UPDATE welfare_applications SET status = ?, comment = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW() WHERE id = ?",
            [$action, $comment, Auth::id() ?? 1, $appId]
        );

        AuditService::log('welfare', $action, (string)$appId, null, ['comment' => $comment]);

        Session::flash('success', 'บันทึกการพิจารณาคำขอสวัสดิการเรียบร้อยแล้ว');
        $this->redirect(url('staff/welfare'));
    }

    public function reports(): void
    {
        $reportType = (string)($this->request->query('type') ?? 'members');
        $search = trim((string)$this->request->query('q', ''));
        $dept = trim((string)$this->request->query('dept', ''));
        $status = trim((string)$this->request->query('status', ''));
        $export = $this->request->query('export');

        $filters = [
            'search' => $search,
            'dept' => $dept,
            'status' => $status,
        ];

        // If Export requested, stream CSV download
        if ($export === 'csv') {
            $csv = StaffService::exportReportCsv($reportType, $filters);
            $filename = "rayongcoop_report_{$reportType}_" . date('Ymd_His') . ".csv";

            header('Content-Type: text/csv; charset=UTF-8');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Pragma: no-cache');
            header('Expires: 0');
            echo $csv;
            exit;
        }

        $reportData = StaffService::generateReport($reportType, $filters);
        $departments = Database::query("SELECT DISTINCT department FROM members WHERE department IS NOT NULL AND department != '' ORDER BY department ASC");

        $this->render('staff.reports', [
            'title' => 'ระบบรายงานและส่งออกข้อมูล (Reporting & Export Engine)',
            'reportType' => $reportType,
            'reportData' => $reportData,
            'departments' => array_column($departments, 'department'),
            'search' => $search,
            'selectedDept' => $dept,
            'selectedStatus' => $status,
        ], 'layouts.admin');
    }

    /**
     * Show Bulk Import Wizard View
     */
    public function import(): void
    {
        $this->render('staff.import', [
            'title' => 'ระบบนำเข้าข้อมูลสมาชิกจาก Excel / CSV (Bulk Import Tool)',
        ], 'layouts.admin');
    }

    /**
     * Download Sample CSV Template
     */
    public function downloadTemplate(): void
    {
        $csvContent = \App\Services\MemberImportService::generateTemplateCsv();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="rayongcoop_member_template.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $csvContent;
        exit;
    }

    /**
     * Handle File Upload & Validation Preview (AJAX)
     */
    public function previewImport(): void
    {
        if (empty($_FILES['import_file']['tmp_name'])) {
            $this->response->json(['success' => false, 'message' => 'กรุณาเลือกไฟล์ที่ต้องการนำเข้า'], 400);
            return;
        }

        $tmpFile = $_FILES['import_file']['tmp_name'];
        $fileName = $_FILES['import_file']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv', 'txt', 'xlsx', 'xls'])) {
            $this->response->json(['success' => false, 'message' => 'รองรับเฉพาะไฟล์ประเภท .csv หรือ .txt'], 400);
            return;
        }

        $result = \App\Services\MemberImportService::parseAndValidate($tmpFile);
        $this->response->json($result);
    }

    /**
     * Process Confirmed Batch Import (AJAX)
     */
    public function processImport(): void
    {
        $rowsJson = $this->request->input('rows');
        $updateDuplicates = (bool)$this->request->input('update_duplicates');
        $createAccounts = (bool)$this->request->input('create_accounts');

        $rows = is_array($rowsJson) ? $rowsJson : json_decode((string)$rowsJson, true);

        if (empty($rows) || !is_array($rows)) {
            $this->response->json(['success' => false, 'message' => 'ไม่มีข้อมูลแถวสำหรับนำเข้า'], 400);
            return;
        }

        $result = \App\Services\MemberImportService::executeImport($rows, $updateDuplicates, $createAccounts);
        $this->response->json($result);
    }

    /**
     * Show Monthly Billing and Receipts Management View
     */
    public function billing(): void
    {
        $year = $this->request->query('year') ? (int)$this->request->query('year') : null;
        $month = $this->request->query('month') ? (int)$this->request->query('month') : null;
        $search = trim((string)$this->request->query('search', ''));

        $sql = "SELECT r.*, m.member_no, m.prefix, m.first_name, m.last_name, m.department 
                FROM receipts r 
                JOIN members m ON r.member_id = m.id 
                WHERE 1=1";
        $params = [];

        if ($year) {
            $sql .= " AND r.billing_year = ?";
            $params[] = $year;
        }
        if ($month) {
            $sql .= " AND r.billing_month = ?";
            $params[] = $month;
        }
        if ($search !== '') {
            $sql .= " AND (r.receipt_no LIKE ? OR m.member_no LIKE ? OR m.first_name LIKE ? OR m.last_name LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $sql .= " ORDER BY r.billing_year DESC, r.billing_month DESC, r.id DESC";

        $receipts = Database::query($sql, $params);

        // Overall stats
        $totalShareSum = array_sum(array_column($receipts, 'share_amount'));
        $totalLoanSum = array_sum(array_column($receipts, 'loan_principal')) + array_sum(array_column($receipts, 'loan_interest'));
        $totalGrandSum = array_sum(array_column($receipts, 'total_amount'));

        $this->render('staff.billing', [
            'title' => 'ระบบประมวลผลใบเสร็จรับเงินรายเดือน (Billing Engine)',
            'receipts' => $receipts,
            'selectedYear' => $year,
            'selectedMonth' => $month,
            'search' => $search,
            'totalCount' => count($receipts),
            'totalShareSum' => $totalShareSum,
            'totalLoanSum' => $totalLoanSum,
            'totalGrandSum' => $totalGrandSum,
        ], 'layouts.admin');
    }

    /**
     * Batch Generate Monthly Receipts for All Members
     */
    public function generateBatchBilling(): void
    {
        $year = (int)$this->request->input('billing_year', (string)date('Y'));
        $month = (int)$this->request->input('billing_month', (string)date('n'));

        $res = \App\Services\ReceiptService::batchGenerateMonthlyReceipts($year, $month);

        if ($res['success']) {
            Session::flash('success', $res['message']);
        } else {
            Session::flash('error', $res['message'] ?? 'เกิดข้อผิดพลาดในการประมวลผล');
        }

        $this->redirect(url("staff/billing?year={$year}&month={$month}"));
    }
}

