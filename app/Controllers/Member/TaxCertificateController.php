<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\MemberPortalService;
use App\Services\TaxCertificateService;

class TaxCertificateController extends Controller
{
    private ?array $member = null;
    private int $memberId = 0;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        
        $this->member = MemberPortalService::getActiveMemberByUserId((int)Auth::id());
        if ($this->member === null) {
            $this->memberId = 0;
            Session::flash('error', 'ไม่พบข้อมูลสมาชิกที่เปิดใช้งานหรือเชื่อมโยงกับบัญชีของคุณ กรุณาติดต่อเจ้าหน้าที่');
            $this->redirect(url('login'));
            return;
        }
        $this->memberId = (int)$this->member['id'];
    }

    /**
     * List all available tax certificates for the member
     */
    public function index(): void
    {
        if ($this->member === null || $this->memberId <= 0) {
            $this->redirect(url('login'));
            return;
        }
        $certificates = TaxCertificateService::getMemberTaxCertificates($this->memberId);
        $notifications = MemberPortalService::getNotifications($this->memberId);

        $this->render('member.tax_certificates.index', [
            'title' => 'หนังสือรับรองดอกเบี้ยเงินกู้เพื่อลดหย่อนภาษี (ภ.ง.ด.90/91)',
            'member' => $this->member,
            'certificates' => $certificates,
            'unreadCount' => $notifications['unread_count'],
        ], 'layouts.member');
    }

    /**
     * View/Print/Download official A4 Tax Certificate PDF
     */
    public function print(string $id): void
    {
        if ($this->member === null || $this->memberId <= 0) {
            $this->redirect(url('login'));
            return;
        }
        $cert = TaxCertificateService::getCertificateById((int)$id, $this->memberId);

        if (!$cert) {
            Session::flash('error', 'ไม่พบหนังสือรับรองภาษีที่ระบุ หรือท่านไม่มีสิทธิ์เข้าถึงเอกสารนี้');
            $this->redirect(url('member/tax-certificates'));
            return;
        }

        $this->render('member.tax_certificates.print', [
            'title' => "หนังสือรับรองการชำระดอกเบี้ยเงินกู้ยืมเพื่อที่อยู่อาศัย ประจำปีภาษี {$cert['thai_year']}",
            'cert' => $cert,
            'member' => $this->member,
        ]);
    }
}
