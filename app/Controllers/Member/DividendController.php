<?php
declare(strict_types=1);
namespace App\Controllers\Member;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\MemberPortalService;
use App\Services\MemberPrivacyService;

class DividendController extends Controller
{
    public function index(): void
    {
        header('Cache-Control: no-store, private');
        if (!Auth::check() || (Auth::user()['role_slug'] ?? '') !== 'member' || (Auth::user()['status'] ?? '') !== 'active') {
            $this->response->json(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง'], 403);
            return;
        }
        // Deny financial details until the authenticated member has acknowledged
        // the currently published privacy notice. Optional consent is not required.
        if (!config('features.member_login', false) || !MemberPrivacyService::acknowledged((int)Auth::id())) {
            $this->response->json(['success' => false, 'code' => 'PRIVACY_NOTICE_REQUIRED',
                'message' => 'กรุณารับทราบประกาศความเป็นส่วนตัวก่อนดูข้อมูลปันผล'], 403);
            return;
        }
        $member = MemberPortalService::getActiveMemberByUserId((int)Auth::id());
        if (!$member) {
            $this->response->json(['success' => false, 'code' => 'MEMBER_NOT_LINKED', 'message' => 'ไม่พบข้อมูลสมาชิก กรุณาติดต่อสหกรณ์'], 404);
            return;
        }
        $rows = Database::query('SELECT year, details_json, imported_at FROM member_dividends WHERE member_id = ? ORDER BY year DESC', [$member['id']]);
        $records = array_map(function ($row) {
            $data = json_decode($row['details_json'], true, 512, JSON_THROW_ON_ERROR);
            unset($data['id_card'], $data['name'], $data['department'], $data['member_no'], $data['source_row']);
            return $data;
        }, $rows);
        $this->response->json(['success' => true, 'member' => ['member_no' => $member['member_no'], 'name' => trim(($member['prefix'] ?? '') . $member['first_name'] . ' ' . $member['last_name'])], 'records' => $records]);
    }
}
