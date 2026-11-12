<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\MemberActivationService;
use App\Core\Logger;

final class MemberActivationController extends Controller
{
    public function issue(string $id): void
    {
        header('Cache-Control: no-store, private');
        if (!MemberActivationService::enabled() ||
            !Auth::check() || (Auth::user()['role_slug'] ?? '') !== 'super_admin') {
            $this->json(['success'=>false,'message'=>'ยังไม่เปิดระบบออกคำเชิญ หรือไม่มีสิทธิ์'],403); return;
        }
        if (!ctype_digit($id) || (int)$id < 1 ||
            (string)$this->request->input('verified_in_person') !== 'yes') {
            $this->json(['success'=>false,'message'=>'ต้องยืนยันตัวตนสมาชิกต่อหน้าและระบุการตรวจสอบก่อน'],422); return;
        }
        try {
            $code = MemberActivationService::issue((int)$id,(int)Auth::id());
            // Single-use sensitive code: never write code to audit logs.
            Logger::auth('Member invitation issued by super_admin '.Auth::id().' for member ID '.$id);
            $this->json(['success'=>true,'invitation_code'=>$code,'expires_in_minutes'=>20,
                'message'=>'แสดงรหัสครั้งเดียว ส่งมอบแก่สมาชิกหลังตรวจตัวตนเท่านั้น']);
        } catch (\Throwable $e) {
            Logger::error('Member activation invitation issuance failed: '.$e->getMessage());
            $this->json(['success'=>false,'message'=>'ออกคำเชิญไม่ได้ กรุณาตรวจสิทธิ์และสถานะบัญชี'],422);
        }
    }
}
