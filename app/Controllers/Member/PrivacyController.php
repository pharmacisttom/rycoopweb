<?php
declare(strict_types=1);

namespace App\Controllers\Member;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\MemberPrivacyService as Privacy;
use RuntimeException;

final class PrivacyController extends Controller
{
    private function verifiedMemberId(): ?int
    {
        if (!config('features.member_login', false) ||
            !Auth::check() ||
            (Auth::user()['role_slug'] ?? '') !== 'member' ||
            (Auth::user()['status'] ?? '') !== 'active') {
            return null;
        }
        return (int) Auth::id();
    }

    public function status(): void
    {
        header('Cache-Control: no-store, private');
        $id = $this->verifiedMemberId();
        if ($id === null) {
            $this->json(['success'=>false,'message'=>'ไม่มีสิทธิ์เข้าถึง'],403); return;
        }
        $this->json([
            'success'=>true,
            'notice_url'=>Privacy::NOTICE_URL,
            'notice_version'=>Privacy::NOTICE_VERSION,
            'acknowledged'=>Privacy::acknowledged($id),
            'optional_purpose'=>Privacy::OPTIONAL_PURPOSE,
            'optional_choice'=>Privacy::optionalChoice($id),
        ]);
    }

    public function record(): void
    {
        header('Cache-Control: no-store, private');
        $id = $this->verifiedMemberId();
        if ($id === null) {
            $this->json(['success'=>false,'message'=>'ไม่มีสิทธิ์เข้าถึง'],403); return;
        }
        $type = (string)($this->request->input('event_type') ?? '');
        $choice = (string)($this->request->input('choice') ?? '');
        $version = (string)($this->request->input('notice_version') ?? '');
        $purpose = $type === 'notice_acknowledgement' ? 'member_online_service' : Privacy::OPTIONAL_PURPOSE;
        if ($type === 'optional_consent' && !Privacy::acknowledged($id)) {
            $this->json(['success'=>false,'message'=>'กรุณารับทราบประกาศความเป็นส่วนตัวก่อน'],409); return;
        }
        try {
            Privacy::record($id,$type,$purpose,$choice,$version,$this->request->ip(),$this->request->userAgent());
        } catch (RuntimeException $e) {
            $this->json(['success'=>false,'message'=>'ข้อมูลการรับทราบหรือความยินยอมไม่ถูกต้อง กรุณาโหลดหน้าใหม่'],422); return;
        }
        $this->json(['success'=>true,'acknowledged'=>Privacy::acknowledged($id),
            'optional_choice'=>Privacy::optionalChoice($id)]);
    }
}
