<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Services\AuditService;

class DepositProductController extends Controller
{
    public function apiStore(): void
    {
        $name = trim((string)$this->request->input('name'));
        $interestRate = (float)$this->request->input('interest_rate', 0.0);

        if ($name === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกชื่อผลิตภัณฑ์'], 422);
            return;
        }

        $sql = "INSERT INTO deposit_products (name, slug, short_description, full_description, interest_rate, min_deposit, max_deposit, withdrawal_condition, eligibility, required_documents, is_featured, sort_order, status, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $id = (int)Database::insert($sql, [
            $name,
            str_slug($name) . '-' . time(),
            $this->request->input('short_description', ''),
            $this->request->input('full_description', ''),
            $interestRate,
            (float)($this->request->input('min_deposit') ?: 0),
            (float)($this->request->input('max_deposit') ?: 0),
            $this->request->input('withdrawal_condition', ''),
            $this->request->input('eligibility', ''),
            $this->request->input('required_documents', ''),
            (int)($this->request->input('is_featured') ? 1 : 0),
            (int)$this->request->input('sort_order', 0),
            $this->request->input('status', 'active'),
            Auth::id()
        ]);

        AuditService::log('deposit_products', 'create', (string)$id, null, ['name' => $name, 'rate' => $interestRate]);
        $row = Database::first("SELECT * FROM deposit_products WHERE id = ?", [$id]);
        $row['version'] = \App\Services\AccessService::version($row);
        $this->response->json(['success' => true, 'message' => 'เพิ่มผลิตภัณฑ์เงินฝากเรียบร้อยแล้ว', 'data' => $row], 201);
    }

    public function apiUpdate(string $id): void
    {
        $productId = (int)$id;
        $old = Database::first("SELECT * FROM deposit_products WHERE id = ? AND deleted_at IS NULL", [$productId]);
        if (!$old) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลผลิตภัณฑ์'], 404);
            return;
        }

        $name = trim((string)$this->request->input('name', $old['name']));
        $interestRate = (float)$this->request->input('interest_rate', $old['interest_rate']);

        if ($name === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกชื่อผลิตภัณฑ์'], 422);
            return;
        }

        $sql = "UPDATE deposit_products SET
                name = ?, short_description = ?, full_description = ?, interest_rate = ?,
                min_deposit = ?, max_deposit = ?, withdrawal_condition = ?, eligibility = ?,
                required_documents = ?, is_featured = ?, sort_order = ?, status = ?,
                updated_by = ?, updated_at = NOW()
                WHERE id = ?";

        Database::execute($sql, [
            $name,
            $this->request->input('short_description', $old['short_description']),
            $this->request->input('full_description', $old['full_description']),
            $interestRate,
            (float)($this->request->input('min_deposit', $old['min_deposit']) ?: 0),
            (float)($this->request->input('max_deposit', $old['max_deposit']) ?: 0),
            $this->request->input('withdrawal_condition', $old['withdrawal_condition']),
            $this->request->input('eligibility', $old['eligibility']),
            $this->request->input('required_documents', $old['required_documents']),
            (int)($this->request->input('is_featured', $old['is_featured']) ? 1 : 0),
            (int)$this->request->input('sort_order', $old['sort_order']),
            $this->request->input('status', $old['status'] ?? 'active'),
            Auth::id(),
            $productId
        ]);

        AuditService::log('deposit_products', 'update', (string)$productId, $old, ['name' => $name, 'rate' => $interestRate]);
        $updated = Database::first("SELECT * FROM deposit_products WHERE id = ?", [$productId]);
        $updated['version'] = \App\Services\AccessService::version($updated);
        $this->response->json(['success' => true, 'message' => 'บันทึกการแก้ไขเรียบร้อยแล้ว', 'data' => $updated]);
    }

    public function apiDestroy(string $id): void
    {
        $productId = (int)$id;
        $old = Database::first("SELECT * FROM deposit_products WHERE id = ? AND deleted_at IS NULL", [$productId]);
        if (!$old) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลผลิตภัณฑ์'], 404);
            return;
        }

        Database::execute("UPDATE deposit_products SET deleted_at = NOW(), updated_by = ?, updated_at = NOW() WHERE id = ?", [Auth::id(), $productId]);
        AuditService::log('deposit_products', 'delete', (string)$productId, $old);
        $this->response->json(['success' => true, 'message' => 'ลบผลิตภัณฑ์เรียบร้อยแล้ว']);
    }

    public function index(): void
    {
        $products = Database::query("SELECT * FROM deposit_products WHERE deleted_at IS NULL ORDER BY sort_order ASC");

        $this->render('admin.deposits.index', [
            'title' => 'จัดการผลิตภัณฑ์เงินฝาก',
            'products' => $products,
        ], 'layouts.admin');
    }

    public function store(): void
    {
        $data = $this->validate([
            'name' => 'required',
            'interest_rate' => 'required|numeric',
        ]);

        $sql = "INSERT INTO deposit_products (name, slug, short_description, full_description, interest_rate, min_deposit, max_deposit, withdrawal_condition, eligibility, required_documents, is_featured, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $id = Database::insert($sql, [
            $data['name'],
            str_slug($data['name']) . '-' . time(),
            $this->request->input('short_description'),
            $this->request->input('full_description'),
            (float) $data['interest_rate'],
            (float) ($this->request->input('min_deposit') ?: 0),
            (float) ($this->request->input('max_deposit') ?: 0),
            $this->request->input('withdrawal_condition'),
            $this->request->input('eligibility'),
            $this->request->input('required_documents'),
            (int) ($this->request->input('is_featured') ? 1 : 0),
            $this->request->input('status', 'active'),
            Auth::id()
        ]);

        AuditService::log('deposit_products', 'create', (string)$id, null, ['name' => $data['name']]);
        Session::flash('success', 'เพิ่มผลิตภัณฑ์เงินฝากเรียบร้อยแล้ว');
        $this->redirect(url('admin/deposits'));
    }

    public function destroy(string $id): void
    {
        $p = Database::first("SELECT * FROM deposit_products WHERE id = ? LIMIT 1", [(int) $id]);
        if ($p) {
            Database::execute("UPDATE deposit_products SET deleted_at = NOW(), updated_by = ? WHERE id = ?", [Auth::id(), (int) $id]);
            AuditService::log('deposit_products', 'delete', (string)$id, $p);
        }

        if ($this->request->isAjax()) {
            $this->json(['success' => true, 'message' => 'ลบผลิตภัณฑ์เรียบร้อยแล้ว']);
        } else {
            Session::flash('success', 'ลบผลิตภัณฑ์เรียบร้อยแล้ว');
            $this->redirect(url('admin/deposits'));
        }
    }
}
