<?php
declare(strict_types=1);
namespace App\Controllers\Member;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

class DividendController extends Controller
{
    public function index(): void
    {
        header('Cache-Control: no-store, private');
        $member = Database::first('SELECT id, member_no, prefix, first_name, last_name FROM members WHERE user_id = ? AND status = ? LIMIT 1', [Auth::id(), 'active']);
        if (!$member) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลสมาชิก กรุณาติดต่อสหกรณ์'], 404);
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
