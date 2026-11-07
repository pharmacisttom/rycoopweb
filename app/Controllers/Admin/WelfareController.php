<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Services\AuditService;

class WelfareController extends Controller
{
    public function apiIndex(): void
    {
        $welfares = Database::query("SELECT * FROM welfare WHERE deleted_at IS NULL ORDER BY sort_order ASC, id DESC");
        $welfares = array_map(function (array $w): array {
            $w['version'] = \App\Services\AccessService::version($w);
            return $w;
        }, $welfares);

        $this->response->json(['success' => true, 'data' => $welfares]);
    }

    public function apiStore(): void
    {
        $category = trim((string)$this->request->input('category', 'ทั่วไป'));
        $title = trim((string)$this->request->input('title'));
        $benefitAmount = trim((string)$this->request->input('benefit_amount'));

        if ($title === '' || $benefitAmount === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกชื่อสวัสดิการและวงเงินช่วยเหลือ'], 422);
            return;
        }

        $sql = "INSERT INTO welfare (category, title, slug, short_description, full_description, benefit_amount, eligibility, contact_info, status, sort_order, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $id = (int)Database::insert($sql, [
            $category,
            $title,
            str_slug($title) . '-' . time(),
            $this->request->input('short_description', ''),
            $this->request->input('full_description', ''),
            $benefitAmount,
            $this->request->input('eligibility', ''),
            $this->request->input('contact_info', ''),
            $this->request->input('status', 'active'),
            (int)$this->request->input('sort_order', 0),
            Auth::id()
        ]);

        AuditService::log('welfare', 'create', (string)$id, null, ['title' => $title, 'amount' => $benefitAmount]);
        $row = Database::first("SELECT * FROM welfare WHERE id = ?", [$id]);
        $row['version'] = \App\Services\AccessService::version($row);
        $this->response->json(['success' => true, 'message' => 'เพิ่มสวัสดิการเรียบร้อยแล้ว', 'data' => $row], 201);
    }

    public function apiUpdate(string $id): void
    {
        $welfareId = (int)$id;
        $old = Database::first("SELECT * FROM welfare WHERE id = ? AND deleted_at IS NULL", [$welfareId]);
        if (!$old) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลสวัสดิการ'], 404);
            return;
        }

        $category = trim((string)$this->request->input('category', $old['category']));
        $title = trim((string)$this->request->input('title', $old['title']));
        $benefitAmount = trim((string)$this->request->input('benefit_amount', $old['benefit_amount']));

        if ($title === '' || $benefitAmount === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกชื่อสวัสดิการและวงเงินช่วยเหลือ'], 422);
            return;
        }

        $sql = "UPDATE welfare SET 
                category = ?, title = ?, short_description = ?, full_description = ?, 
                benefit_amount = ?, eligibility = ?, contact_info = ?, status = ?, 
                sort_order = ?, updated_by = ?, updated_at = NOW() 
                WHERE id = ?";

        Database::execute($sql, [
            $category,
            $title,
            $this->request->input('short_description', $old['short_description']),
            $this->request->input('full_description', $old['full_description']),
            $benefitAmount,
            $this->request->input('eligibility', $old['eligibility']),
            $this->request->input('contact_info', $old['contact_info']),
            $this->request->input('status', $old['status'] ?? 'active'),
            (int)$this->request->input('sort_order', $old['sort_order'] ?? 0),
            Auth::id(),
            $welfareId
        ]);

        AuditService::log('welfare', 'update', (string)$welfareId, $old, ['title' => $title, 'amount' => $benefitAmount]);
        $updated = Database::first("SELECT * FROM welfare WHERE id = ?", [$welfareId]);
        $updated['version'] = \App\Services\AccessService::version($updated);
        $this->response->json(['success' => true, 'message' => 'บันทึกการแก้ไขสวัสดิการเรียบร้อยแล้ว', 'data' => $updated]);
    }

    public function apiDestroy(string $id): void
    {
        $welfareId = (int)$id;
        $old = Database::first("SELECT * FROM welfare WHERE id = ? AND deleted_at IS NULL", [$welfareId]);
        if (!$old) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลสวัสดิการ'], 404);
            return;
        }

        Database::execute("UPDATE welfare SET deleted_at = NOW(), updated_by = ?, updated_at = NOW() WHERE id = ?", [Auth::id(), $welfareId]);
        AuditService::log('welfare', 'delete', (string)$welfareId, $old);
        $this->response->json(['success' => true, 'message' => 'ลบสวัสดิการเรียบร้อยแล้ว']);
    }

    public function index(): void
    {
        $welfares = Database::query("SELECT * FROM welfare WHERE deleted_at IS NULL ORDER BY sort_order ASC");

        $this->render('admin.welfare.index', [
            'title' => 'จัดการสวัสดิการสมาชิก',
            'welfares' => $welfares,
        ], 'layouts.admin');
    }

    public function store(): void
    {
        $data = $this->validate([
            'category' => 'required',
            'title' => 'required',
            'benefit_amount' => 'required',
        ]);

        $sql = "INSERT INTO welfare (category, title, slug, short_description, full_description, benefit_amount, eligibility, contact_info, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $id = Database::insert($sql, [
            $data['category'],
            $data['title'],
            str_slug($data['title']) . '-' . time(),
            $this->request->input('short_description'),
            $this->request->input('full_description'),
            $data['benefit_amount'],
            $this->request->input('eligibility'),
            $this->request->input('contact_info'),
            $this->request->input('status', 'active'),
            Auth::id()
        ]);

        AuditService::log('welfare', 'create', (string)$id, null, ['title' => $data['title']]);
        Session::flash('success', 'เพิ่มสวัสดิการเรียบร้อยแล้ว');
        $this->redirect(url('admin/welfare'));
    }

    public function destroy(string $id): void
    {
        $w = Database::first("SELECT * FROM welfare WHERE id = ? LIMIT 1", [(int) $id]);
        if ($w) {
            Database::execute("UPDATE welfare SET deleted_at = NOW(), updated_by = ? WHERE id = ?", [Auth::id(), (int) $id]);
            AuditService::log('welfare', 'delete', (string)$id, $w);
        }

        if ($this->request->isAjax()) {
            $this->json(['success' => true, 'message' => 'ลบสวัสดิการเรียบร้อยแล้ว']);
        } else {
            Session::flash('success', 'ลบสวัสดิการเรียบร้อยแล้ว');
            $this->redirect(url('admin/welfare'));
        }
    }
}
