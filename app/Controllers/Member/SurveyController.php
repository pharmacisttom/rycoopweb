<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\MemberPortalService;

class SurveyController extends Controller
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
     * List surveys available for members
     */
    public function index(): void
    {
        if ($this->member === null || $this->memberId <= 0) {
            $this->redirect(url('login'));
            return;
        }
        $surveys = Database::query(
            "SELECT s.*, 
                    (SELECT COUNT(*) FROM survey_responses r WHERE r.survey_id = s.id AND r.member_id = ?) as has_responded
             FROM surveys s
             WHERE s.status = 'active' AND s.deleted_at IS NULL AND s.start_date <= CURDATE() AND (s.end_date IS NULL OR s.end_date >= CURDATE())
             ORDER BY s.created_at DESC",
            [$this->memberId]
        );

        $notifications = MemberPortalService::getNotifications($this->memberId);

        $this->render('member.surveys.index', [
            'title' => 'แบบสำรวจความพึงพอใจและโพลล์สมาชิก',
            'member' => $this->member,
            'surveys' => $surveys,
            'unreadCount' => $notifications['unread_count'],
        ], 'layouts.member');
    }

    /**
     * Show survey questions form
     */
    public function show(string $idOrSlug): void
    {
        if ($this->member === null || $this->memberId <= 0) {
            $this->redirect(url('login'));
            return;
        }
        $survey = Database::first(
            "SELECT * FROM surveys WHERE (id = ? OR slug = ?) AND status = 'active' AND deleted_at IS NULL",
            [is_numeric($idOrSlug) ? (int)$idOrSlug : 0, $idOrSlug]
        );

        if (!$survey) {
            Session::flash('error', 'ไม่พบแบบสำรวจ หรือแบบสำรวจนี้ปิดการรับคำตอบแล้ว');
            $this->redirect(url('member/surveys'));
            return;
        }

        $surveyId = (int)$survey['id'];

        // Check if member already submitted
        $existingResponse = Database::first(
            "SELECT id FROM survey_responses WHERE survey_id = ? AND member_id = ?",
            [$surveyId, $this->memberId]
        );

        if ($existingResponse && empty($survey['allow_multiple_responses'])) {
            Session::flash('info', 'ท่านได้ร่วมตอบแบบสำรวจนี้เรียบร้อยแล้ว ขอขอบพระคุณเป็นอย่างยิ่ง');
            $this->redirect(url('member/surveys'));
            return;
        }

        $questions = Database::query(
            "SELECT * FROM survey_questions WHERE survey_id = ? ORDER BY sort_order ASC",
            [$surveyId]
        );

        foreach ($questions as &$q) {
            if (!empty($q['options_json']) && is_string($q['options_json'])) {
                $q['options'] = json_decode($q['options_json'], true) ?? [];
            } else {
                $q['options'] = [];
            }
        }

        $notifications = MemberPortalService::getNotifications($this->memberId);

        $this->render('member.surveys.show', [
            'title' => $survey['title'],
            'member' => $this->member,
            'survey' => $survey,
            'questions' => $questions,
            'unreadCount' => $notifications['unread_count'],
        ], 'layouts.member');
    }

    /**
     * Submit survey response
     */
    public function submit(string $idOrSlug): void
    {
        if ($this->member === null || $this->memberId <= 0) {
            if ($this->request && $this->request->isAjax()) {
                $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลสมาชิก'], 403);
                return;
            }
            $this->redirect(url('login'));
            return;
        }
        $survey = Database::first(
            "SELECT * FROM surveys WHERE (id = ? OR slug = ?) AND status = 'active' AND deleted_at IS NULL",
            [is_numeric($idOrSlug) ? (int)$idOrSlug : 0, $idOrSlug]
        );
        if (!$survey) {
            Session::flash('error', 'แบบสำรวจไม่พร้อมใช้งาน');
            $this->redirect(url('member/surveys'));
            return;
        }

        $surveyId = (int)$survey['id'];
        $questions = Database::query("SELECT * FROM survey_questions WHERE survey_id = ?", [$surveyId]);
        $answers = $this->request->input('answers') ?? [];

        // Validate required questions
        foreach ($questions as $q) {
            if (!empty($q['is_required'])) {
                $val = $answers[$q['id']] ?? null;
                if ($val === null || (is_string($val) && trim($val) === '')) {
                    Session::flash('error', 'กรุณาตอบคำถามที่จำเป็นให้ครบทุกข้อ (*)');
                    $this->redirect(url('member/surveys/' . $id));
                    return;
                }
            }
        }

        $token = bin2hex(random_bytes(16));
        $ip = (string)$this->request->ip();
        $ua = substr((string)$this->request->userAgent(), 0, 250);

        $responseId = Database::insert(
            "INSERT INTO survey_responses (survey_id, member_id, session_token, ip_address, user_agent, submitted_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [(int)$id, $this->memberId, $token, $ip, $ua]
        );

        foreach ($questions as $q) {
            $qid = (int)$q['id'];
            $ans = $answers[$qid] ?? null;

            $ratingVal = null;
            $ansText = null;
            $selectedJson = null;

            if ($q['question_type'] === 'rating_1_5') {
                $ratingVal = is_numeric($ans) ? (int)$ans : 5;
            } elseif ($q['question_type'] === 'multiple_choice' && is_array($ans)) {
                $selectedJson = json_encode($ans, JSON_UNESCAPED_UNICODE);
            } else {
                $ansText = is_string($ans) ? trim($ans) : (string)$ans;
            }

            Database::execute(
                "INSERT INTO survey_answers (response_id, question_id, rating_value, answer_text, selected_options_json)
                 VALUES (?, ?, ?, ?, ?)",
                [$responseId, $qid, $ratingVal, $ansText, $selectedJson]
            );
        }

        Session::flash('success', $survey['thank_you_message'] ?: 'บันทึกคำตอบของท่านเรียบร้อยแล้ว ขอขอบพระคุณเป็นอย่างยิ่ง');
        $this->redirect(url('member/surveys'));
    }
}
