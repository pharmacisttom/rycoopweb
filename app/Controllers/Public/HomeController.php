<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Controller;
use App\Core\Database;

class HomeController extends Controller
{
    public function index(): void
    {
        // 1. Hero Slides
        $heroSlides = [];
        try {
            $heroSlides = Database::query("SELECT * FROM hero_slides WHERE status = 'active' AND (start_at IS NULL OR start_at <= NOW()) AND (end_at IS NULL OR end_at >= NOW()) ORDER BY priority DESC, sort_order ASC");
            $heroSlides = array_map(static function (array $slide): array {
                foreach (['image_url', 'desktop_image', 'mobile_image'] as $field) {
                    if (array_key_exists($field, $slide)) $slide[$field] = resolve_media_url($slide[$field]);
                }
                return $slide;
            }, $heroSlides);
        } catch (\Throwable $e) {}

        // 2. Deposit Rates & Loan Rates
        $depositRates = [];
        $loanRates = [];
        try {
            $depositRates = Database::query("SELECT * FROM interest_rates WHERE product_type = 'deposit' AND status = 'active' ORDER BY sort_order ASC LIMIT 4");
            $loanRates = Database::query("SELECT * FROM interest_rates WHERE product_type = 'loan' AND status = 'active' ORDER BY sort_order ASC");
        } catch (\Throwable $e) {}

        // 3. Featured News & Announcements
        $latestNews = [];
        try {
            $latestNews = Database::query("SELECT n.*, c.name as category_name FROM news n JOIN news_categories c ON n.category_id = c.id WHERE n.workflow_status = 'published' AND (n.publish_at IS NULL OR n.publish_at <= NOW()) ORDER BY n.is_pinned DESC, n.publish_at DESC LIMIT 6");
            $latestNews = array_map(static function (array $news): array {
                $news['cover_image'] = resolve_media_url($news['cover_image'] ?? null);
                return $news;
            }, $latestNews);
        } catch (\Throwable $e) {}

        // 4. Featured Deposit & Loan Products & Calculator Loan Products
        $featuredDeposits = [];
        $featuredLoans = [];
        $calcLoanProducts = [];
        try {
            $featuredDeposits = Database::query("SELECT * FROM deposit_products WHERE is_featured = 1 AND status = 'active' ORDER BY sort_order ASC LIMIT 3");
            $featuredLoans = Database::query("SELECT * FROM loan_products WHERE is_featured = 1 AND status = 'active' ORDER BY sort_order ASC LIMIT 3");
            $calcLoanProducts = Database::query("SELECT * FROM loan_products WHERE is_calculator_enabled = 1 AND status = 'active' ORDER BY sort_order ASC");
        } catch (\Throwable $e) {}

        // 5. Executive Statistics (Latest)
        $latestStats = null;
        try {
            $latestStats = Database::first("SELECT * FROM financial_statistics ORDER BY year DESC, month DESC LIMIT 1");
        } catch (\Throwable $e) {}

        // 6. E-Service Quick Links
        $eservices = [];
        try {
            $eservices = Database::query("SELECT * FROM eservice_links WHERE status = 'active' ORDER BY sort_order ASC LIMIT 6");
        } catch (\Throwable $e) {}

        // 7. Important Announcements (Latest Published & Active)
        $importantAnnouncements = [];
        try {
            $importantAnnouncements = Database::query("SELECT * FROM important_announcements WHERE status = 'published' AND publication_date <= CURDATE() AND (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY is_pinned DESC, priority DESC, publication_date DESC LIMIT 3");
        } catch (\Throwable $e) {}

        // 8. Upcoming Events
        $upcomingEvents = [];
        try {
            $upcomingEvents = Database::query("SELECT * FROM events WHERE status = 'upcoming' AND deleted_at IS NULL AND start_date >= CURDATE() ORDER BY start_date ASC, start_time ASC LIMIT 4");
        } catch (\Throwable $e) {}

        $this->render('public.home', [
            'title' => 'หน้าแรก',
            'heroSlides' => $heroSlides,
            'depositRates' => $depositRates,
            'loanRates' => $loanRates,
            'latestNews' => $latestNews,
            'featuredDeposits' => $featuredDeposits,
            'featuredLoans' => $featuredLoans,
            'calcLoanProducts' => $calcLoanProducts,
            'latestStats' => $latestStats,
            'eservices' => $eservices,
            'importantAnnouncements' => $importantAnnouncements,
            'upcomingEvents' => $upcomingEvents,
        ]);
    }
}

