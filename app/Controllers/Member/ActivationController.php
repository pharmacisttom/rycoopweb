<?php
declare(strict_types=1);
namespace App\Controllers\Member;

use App\Core\Controller;
use App\Services\MemberActivationService;
use App\Core\Logger;

final class ActivationController extends Controller
{
    public function redeem(): void
    {
        header('Cache-Control: no-store, private');
        if (!MemberActivationService::enabled()) {
            $this->json(['success'=>false,'message'=>'ระบบเปิดใช้งานบัญชีสมาชิกยังไม่เปิดให้บริการ'],403); return;
        }
        $code = strtolower(trim((string)$this->request->input('invitation_code', '')));
        $password = (string)$this->request->input('new_password','');
        $confirm = (string)$this->request->input('confirm_password','');
        try {
            MemberActivationService::redeem($code,$password,$confirm);
            $this->json(['success'=>true,'message'=>'ตั้งรหัสผ่านสำเร็จ กรุณาเข้าสู่ระบบสมาชิก']);
        } catch (\Throwable $e) {
            // Do not disclose token existence, member status or matching member identity.
            Logger::auth('Member activation refused from IP hash '.hash('sha256',$this->request->ip()));
            $this->json(['success'=>false,'message'=>'ไม่สามารถเปิดบัญชีได้ ตรวจสอบรหัสเชิญและรหัสผ่าน หรือติดต่อสหกรณ์'],422);
        }
    }
}
