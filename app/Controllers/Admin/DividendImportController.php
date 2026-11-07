<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Core\Controller;
use App\Core\Session;
use App\Services\DividendImportService;
use App\Services\MemberDataException;
use App\Core\Logger;
use App\Services\MemberSystemHealth;

class DividendImportController extends Controller
{
    public function preview(): void
    {
        header('Cache-Control: no-store, private');
        Session::remove('dividend_import');
        try {
            MemberSystemHealth::requireWrites();
            $file = $_FILES['file'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) { throw new MemberDataException('กรุณาเลือกไฟล์ที่อัปโหลดสำเร็จ'); }
            if ($file['size'] > 5000000) { throw new MemberDataException('ไฟล์ต้องไม่เกิน 5 MB'); }
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, ['xlsx', 'csv'], true)) { throw new MemberDataException('รองรับเฉพาะ XLSX และ CSV UTF-8'); }
            $year = (string)$this->request->input('year');
            if (!preg_match('/^\d{4}$/D', $year) || (int)$year < 2400 || (int)$year > 2800) { throw new MemberDataException('กรุณาระบุปี พ.ศ. 4 หลัก'); }
            $parsed = DividendImportService::parseFile($file['tmp_name'], $extension, (int)$year);
            if ($parsed['errors']) {
                $this->json(['success' => false, 'message' => 'กรุณาแก้ไขข้อมูลก่อนนำเข้า ไม่มีรายการใดถูกบันทึก', 'errors' => array_slice($parsed['errors'], 0, 100), 'error_count' => count($parsed['errors'])], 422);
                return;
            }
            $check=DividendImportService::databasePreview($parsed['records'],false);
            if($check['errors']) {$this->json(['success'=>false,'message'=>'ข้อมูลไม่ตรงกับฐานข้อมูล กรุณาแก้ไขก่อนนำเข้า','errors'=>array_slice($check['errors'],0,100),'error_count'=>count($check['errors'])],422);return;}
            $token = bin2hex(random_bytes(24));
            Session::set('dividend_import', ['token' => $token, 'expires' => time() + 1800, 'records' => $parsed['records'], 'snapshots'=>$check['snapshots'],'source_name'=>mb_substr(basename($file['name']),0,255)]);
            $this->json(['success' => true, 'token' => $token, 'count' => count($parsed['records']), 'year' => (int)$year, 'total_net' => \App\Services\MemberAdminService::amount(array_sum(array_map(static fn(array $r): int => \App\Services\MemberAdminService::cents($r['net']), $parsed['records']))), 'counts'=>$check['counts'],'sample'=>$check['sample']]);
        } catch (\Throwable $e) {
            if (!$e instanceof MemberDataException) Logger::error('Dividend preview failure: '.$e->getMessage());
            $this->json(['success' => false, 'message' => $e instanceof MemberDataException ? $e->getMessage() : 'ตรวจสอบไฟล์ไม่สำเร็จ กรุณาติดต่อผู้ดูแล'], $e instanceof MemberDataException ? 422 : 500);
        }
    }

    public function confirm(): void
    {
        header('Cache-Control: no-store, private');
        $preview = Session::get('dividend_import');
        if (!$preview || $preview['expires'] < time() || !hash_equals($preview['token'], (string)$this->request->input('token'))) {
            $this->json(['success' => false, 'message' => 'ผลตรวจสอบหมดอายุ กรุณาอัปโหลดและตรวจสอบอีกครั้ง'], 422); return;
        }
        try {
            MemberSystemHealth::requireWrites();
            $count = DividendImportService::import($preview['records'],['snapshots'=>$preview['snapshots'],'source_name'=>$preview['source_name']]);
            \App\Services\SecurityAuditService::recordAdminFinancialImport((int)\App\Core\Auth::id(), (string)(\App\Core\Auth::user()['role_slug'] ?? 'staff'), 'dividends', (int)$count);
            Session::remove('dividend_import');
            $this->json(['success' => true, 'count' => $count, 'message' => 'นำเข้าสำเร็จ โดยคงรหัสผ่านเดิมของบัญชีที่มีอยู่']);
        } catch (\Throwable $e) {
            if (!$e instanceof MemberDataException) Logger::error('Dividend import failure: '.$e->getMessage());
            $this->json(['success' => false, 'message' => $e instanceof MemberDataException ? $e->getMessage() : 'นำเข้าไม่สำเร็จ ระบบย้อนกลับข้อมูลทั้งหมด กรุณาตรวจสอบข้อมูลสมาชิกหรือสอบถามผู้ดูแล'], $e instanceof MemberDataException ? 422 : 500);
        }
    }
}
