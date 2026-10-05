<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Core\Controller;
use App\Core\Session;
use App\Services\DividendImportService;

class DividendImportController extends Controller
{
    public function preview(): void
    {
        header('Cache-Control: no-store, private');
        Session::remove('dividend_import');
        try {
            $file = $_FILES['file'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) { throw new \RuntimeException('กรุณาเลือกไฟล์ที่อัปโหลดสำเร็จ'); }
            if ($file['size'] > 5000000) { throw new \RuntimeException('ไฟล์ต้องไม่เกิน 5 MB'); }
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, ['xlsx', 'csv'], true)) { throw new \RuntimeException('รองรับเฉพาะ XLSX และ CSV UTF-8'); }
            $year = (string)$this->request->input('year');
            if (!preg_match('/^\d{4}$/D', $year) || (int)$year < 2400 || (int)$year > 2800) { throw new \RuntimeException('กรุณาระบุปี พ.ศ. 4 หลัก'); }
            $parsed = DividendImportService::parseFile($file['tmp_name'], $extension, (int)$year);
            if ($parsed['errors']) {
                $this->json(['success' => false, 'message' => 'กรุณาแก้ไขข้อมูลก่อนนำเข้า ไม่มีรายการใดถูกบันทึก', 'errors' => array_slice($parsed['errors'], 0, 100), 'error_count' => count($parsed['errors'])], 422);
                return;
            }
            $token = bin2hex(random_bytes(24));
            Session::set('dividend_import', ['token' => $token, 'expires' => time() + 1800, 'records' => $parsed['records']]);
            $this->json(['success' => true, 'token' => $token, 'count' => count($parsed['records']), 'year' => (int)$year, 'total_net' => array_sum(array_column($parsed['records'], 'net'))]);
        } catch (\Throwable $e) { $this->json(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function confirm(): void
    {
        header('Cache-Control: no-store, private');
        $preview = Session::get('dividend_import');
        if (!$preview || $preview['expires'] < time() || !hash_equals($preview['token'], (string)$this->request->input('token'))) {
            $this->json(['success' => false, 'message' => 'ผลตรวจสอบหมดอายุ กรุณาอัปโหลดและตรวจสอบอีกครั้ง'], 422); return;
        }
        try {
            $count = DividendImportService::import($preview['records']);
            Session::remove('dividend_import');
            $this->json(['success' => true, 'count' => $count, 'message' => 'นำเข้าสำเร็จ โดยคงรหัสผ่านเดิมของบัญชีที่มีอยู่']);
        } catch (\Throwable $e) { $this->json(['success' => false, 'message' => $e instanceof \RuntimeException ? $e->getMessage() : 'นำเข้าไม่สำเร็จ ระบบย้อนกลับข้อมูลทั้งหมด กรุณาตรวจสอบข้อมูลสมาชิกหรือสอบถามผู้ดูแล'], 422); }
    }
}
