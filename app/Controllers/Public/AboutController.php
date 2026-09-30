<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Controller;
use App\Core\Database;

class AboutController extends Controller
{
    public function index(): void
    {
        $this->render('public.about', [
            'title' => 'เกี่ยวกับสหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด',
        ]);
    }

    public function board(): void
    {
        $directors = Database::query("SELECT * FROM boards WHERE role_type = 'director' AND status = 'active' ORDER BY sort_order ASC");
        $auditors = Database::query("SELECT * FROM boards WHERE role_type = 'auditor' AND status = 'active' ORDER BY sort_order ASC");
        $advisors = Database::query("SELECT * FROM boards WHERE role_type = 'advisor' AND status = 'active' ORDER BY sort_order ASC");
        $staffList = Database::query("SELECT * FROM staff WHERE status = 'active' ORDER BY sort_order ASC");
        $resolvePhotos = static function (array $item): array { $item['photo'] = resolve_media_url($item['photo'] ?? null); return $item; };
        $directors = array_map($resolvePhotos, $directors);
        $auditors = array_map($resolvePhotos, $auditors);
        $advisors = array_map($resolvePhotos, $advisors);
        $staffList = array_map($resolvePhotos, $staffList);

        $this->render('public.board', [
            'title' => 'คณะกรรมการดำเนินการและเจ้าหน้าที่',
            'directors' => $directors,
            'auditors' => $auditors,
            'advisors' => $advisors,
            'staffList' => $staffList,
        ]);
    }

    public function statistics(): void
    {
        $statsHistory = Database::query("SELECT * FROM financial_statistics ORDER BY year ASC, month ASC");
        $latest = end($statsHistory) ?: null;

        $this->render('public.statistics', [
            'title' => 'ฐานะการเงินและสถิติการดำเนินงาน',
            'statsHistory' => $statsHistory,
            'latest' => $latest,
        ]);
    }
}
