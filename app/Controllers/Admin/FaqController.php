<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Services\AuditService;

class FaqController extends Controller
{
    public function index(): void
    {
        $faqs = Database::query("SELECT * FROM faqs ORDER BY sort_order ASC, created_at DESC");

        $this->render('admin.faqs.index', [
            'title' => 'จัดการคำถามที่พบบ่อย (FAQ)',
            'faqs' => $faqs,
        ], 'layouts.admin');
    }

    public function store(): void
    {
        $data = $this->validate([
            'question' => 'required',
            'answer' => 'required',
        ]);

        Database::execute("INSERT INTO faqs (category, question, answer, status, created_at) VALUES (?, ?, ?, 'active', NOW())", [
            $this->request->input('category', 'general'),
            $data['question'],
            \App\Services\RichTextService::clean($data['answer'])
        ]);

        AuditService::log('faqs', 'create', null, null, ['question' => $data['question']]);
        Session::flash('success', 'เพิ่มคำถามที่พบบ่อยเรียบร้อยแล้ว');
        $this->redirect(url('admin/faqs'));
    }

    public function destroy(string $id): void
    {
        Database::execute("DELETE FROM faqs WHERE id = ?", [(int) $id]);
        if ($this->request->isAjax()) {
            $this->json(['success' => true]);
        } else {
            Session::flash('success', 'ลบคำถามเรียบร้อยแล้ว');
            $this->redirect(url('admin/faqs'));
        }
    }
}
