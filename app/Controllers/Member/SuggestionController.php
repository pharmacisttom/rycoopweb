<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Services\MemberPortalService;
use App\Services\SuggestionService;

class SuggestionController extends Controller
{
    private ?array $member = null;
    private int $memberId = 0;

    public function __construct($request = null, $response = null)
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
     * Display member suggestions list and submission form
     */
    public function index(): void
    {
        if ($this->member === null || $this->memberId <= 0) {
            $this->redirect(url('login'));
            return;
        }
        $suggestions = SuggestionService::getMemberSuggestions($this->memberId);
        $categories = SuggestionService::getCategories();
        $statuses = SuggestionService::getStatuses();
        $notifications = MemberPortalService::getNotifications($this->memberId);

        $this->render('member.suggestions.index', [
            'title' => 'กล่องรับฟังเสียงและข้อเสนอแนะสมาชิก (Member Voice)',
            'member' => $this->member,
            'suggestions' => $suggestions,
            'categories' => $categories,
            'statuses' => $statuses,
            'unreadCount' => $notifications['unread_count'],
        ], 'layouts.member');
    }

    /**
     * Submit a new suggestion
     */
    public function store(): void
    {
        if ($this->member === null || $this->memberId <= 0) {
            if ($this->request && $this->request->isAjax()) {
                $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลสมาชิก'], 403);
                return;
            }
            $this->redirect(url('login'));
            return;
        }
        $title = trim((string)$this->request->input('title', ''));
        $content = trim((string)$this->request->input('content', ''));
        $category = (string)$this->request->input('category', 'general');
        $isAnonymous = !empty($this->request->input('is_anonymous')) ? 1 : 0;

        if (empty($title) || empty($content)) {
            if ($this->request->isAjax()) {
                $this->response->json([
                    'success' => false,
                    'message' => 'กรุณากรอกหัวข้อและรายละเอียดข้อเสนอแนะให้ครบถ้วน'
                ], 400);
                return;
            }
            Session::flash('error', 'กรุณากรอกหัวข้อและรายละเอียดข้อเสนอแนะให้ครบถ้วน');
            $this->redirect(url('member/suggestions'));
            return;
        }

        $file = $_FILES['attachment'] ?? null;
        $result = SuggestionService::createSuggestion($this->memberId, [
            'title' => $title,
            'content' => $content,
            'category' => $category,
            'is_anonymous' => $isAnonymous,
        ], $file);

        if ($this->request->isAjax()) {
            $this->response->json([
                'success' => true,
                'message' => "บันทึกข้อเสนอแนะสำเร็จ เลขที่ {$result['suggestion_no']}",
                'suggestion_no' => $result['suggestion_no'],
                'redirect' => '/member/suggestions'
            ]);
            return;
        }

        Session::flash('success', "ส่งข้อเสนอแนะสำเร็จ เลขที่ {$result['suggestion_no']} สหกรณ์จะนำข้อคิดเห็นของท่านไปพิจารณาพัฒนาการบริการต่อไป");
        $this->redirect(url('member/suggestions'));
    }
}
