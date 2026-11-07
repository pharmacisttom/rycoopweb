<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Services\AuditService;

class LoanProductController extends Controller
{
    public function apiIndex(): void
    {
        $loanProducts = Database::query("SELECT * FROM loan_products WHERE deleted_at IS NULL ORDER BY sort_order ASC, id ASC");
        $depositProducts = Database::query("SELECT * FROM deposit_products WHERE deleted_at IS NULL ORDER BY sort_order ASC, id ASC");

        $loanProducts = array_map(function (array $p): array {
            $p['version'] = \App\Services\AccessService::version($p);
            return $p;
        }, $loanProducts);

        $depositProducts = array_map(function (array $p): array {
            $p['version'] = \App\Services\AccessService::version($p);
            return $p;
        }, $depositProducts);

        $this->response->json([
            'success' => true,
            'data' => [
                'loans' => $loanProducts,
                'deposits' => $depositProducts,
            ]
        ]);
    }

    public function apiStore(): void
    {
        $name = trim((string)$this->request->input('name'));
        $category = trim((string)$this->request->input('category', 'เงินกู้สามัญ'));
        $interestRate = (float)$this->request->input('interest_rate', 0.0);

        if ($name === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกชื่อผลิตภัณฑ์'], 422);
            return;
        }

        $sql = "INSERT INTO loan_products (category, name, slug, short_description, full_description, interest_rate, min_loan_amount, max_loan_limit, max_term_months, calculation_type, eligibility, guarantor_requirement, is_featured, is_calculator_enabled, sort_order, status, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $id = (int)Database::insert($sql, [
            $category,
            $name,
            str_slug($name) . '-' . time(),
            $this->request->input('short_description', ''),
            $this->request->input('full_description', ''),
            $interestRate,
            (float)($this->request->input('min_loan_amount') ?: 0),
            (float)($this->request->input('max_loan_limit') ?: 0),
            (int)($this->request->input('max_term_months') ?: 120),
            $this->request->input('calculation_type', 'effective'),
            $this->request->input('eligibility', ''),
            $this->request->input('guarantor_requirement', ''),
            (int)($this->request->input('is_featured') ? 1 : 0),
            (int)($this->request->input('is_calculator_enabled') ? 1 : 0),
            (int)$this->request->input('sort_order', 0),
            $this->request->input('status', 'active'),
            Auth::id()
        ]);

        AuditService::log('loan_products', 'create', (string)$id, null, ['name' => $name, 'rate' => $interestRate]);
        $row = Database::first("SELECT * FROM loan_products WHERE id = ?", [$id]);
        $row['version'] = \App\Services\AccessService::version($row);
        $this->response->json(['success' => true, 'message' => 'เพิ่มผลิตภัณฑ์สินเชื่อเรียบร้อยแล้ว', 'data' => $row], 201);
    }

    public function apiUpdate(string $id): void
    {
        $productId = (int)$id;
        $old = Database::first("SELECT * FROM loan_products WHERE id = ? AND deleted_at IS NULL", [$productId]);
        if (!$old) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลผลิตภัณฑ์'], 404);
            return;
        }

        $name = trim((string)$this->request->input('name', $old['name']));
        $category = trim((string)$this->request->input('category', $old['category'] ?? 'เงินกู้สามัญ'));
        $interestRate = (float)$this->request->input('interest_rate', $old['interest_rate']);

        if ($name === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกชื่อผลิตภัณฑ์'], 422);
            return;
        }

        $sql = "UPDATE loan_products SET
                category = ?, name = ?, short_description = ?, full_description = ?, interest_rate = ?,
                min_loan_amount = ?, max_loan_limit = ?, max_term_months = ?, calculation_type = ?,
                eligibility = ?, guarantor_requirement = ?, is_featured = ?, is_calculator_enabled = ?,
                sort_order = ?, status = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ?";

        Database::execute($sql, [
            $category,
            $name,
            $this->request->input('short_description', $old['short_description']),
            $this->request->input('full_description', $old['full_description']),
            $interestRate,
            (float)($this->request->input('min_loan_amount', $old['min_loan_amount'] ?? 0) ?: 0),
            (float)($this->request->input('max_loan_limit', $old['max_loan_limit']) ?: 0),
            (int)($this->request->input('max_term_months', $old['max_term_months']) ?: 120),
            $this->request->input('calculation_type', $old['calculation_type'] ?? 'effective'),
            $this->request->input('eligibility', $old['eligibility']),
            $this->request->input('guarantor_requirement', $old['guarantor_requirement']),
            (int)($this->request->input('is_featured', $old['is_featured']) ? 1 : 0),
            (int)($this->request->input('is_calculator_enabled', $old['is_calculator_enabled']) ? 1 : 0),
            (int)$this->request->input('sort_order', $old['sort_order']),
            $this->request->input('status', $old['status'] ?? 'active'),
            Auth::id(),
            $productId
        ]);

        AuditService::log('loan_products', 'update', (string)$productId, $old, ['name' => $name, 'rate' => $interestRate]);
        $updated = Database::first("SELECT * FROM loan_products WHERE id = ?", [$productId]);
        $updated['version'] = \App\Services\AccessService::version($updated);
        $this->response->json(['success' => true, 'message' => 'บันทึกการแก้ไขเรียบร้อยแล้ว', 'data' => $updated]);
    }

    public function apiDestroy(string $id): void
    {
        $productId = (int)$id;
        $old = Database::first("SELECT * FROM loan_products WHERE id = ? AND deleted_at IS NULL", [$productId]);
        if (!$old) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลผลิตภัณฑ์'], 404);
            return;
        }

        Database::execute("UPDATE loan_products SET deleted_at = NOW(), updated_by = ?, updated_at = NOW() WHERE id = ?", [Auth::id(), $productId]);
        AuditService::log('loan_products', 'delete', (string)$productId, $old);
        $this->response->json(['success' => true, 'message' => 'ลบผลิตภัณฑ์เรียบร้อยแล้ว']);
    }

    public function index(): void
    {
        $products = Database::query("SELECT * FROM loan_products WHERE deleted_at IS NULL ORDER BY sort_order ASC");

        $this->render('admin.loans.index', [
            'title' => 'จัดการผลิตภัณฑ์เงินกู้และสินเชื่อ',
            'products' => $products,
        ], 'layouts.admin');
    }

    public function store(): void
    {
        $data = $this->validate([
            'name' => 'required',
            'category' => 'required',
            'interest_rate' => 'required|numeric',
        ]);

        $sql = "INSERT INTO loan_products (category, name, slug, short_description, full_description, interest_rate, max_loan_limit, max_term_months, calculation_type, eligibility, guarantor_requirement, is_featured, is_calculator_enabled, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $id = Database::insert($sql, [
            $data['category'],
            $data['name'],
            str_slug($data['name']) . '-' . time(),
            $this->request->input('short_description'),
            $this->request->input('full_description'),
            (float) $data['interest_rate'],
            (float) ($this->request->input('max_loan_limit') ?: 0),
            (int) ($this->request->input('max_term_months') ?: 120),
            $this->request->input('calculation_type', 'effective'),
            $this->request->input('eligibility'),
            $this->request->input('guarantor_requirement'),
            (int) ($this->request->input('is_featured') ? 1 : 0),
            (int) ($this->request->input('is_calculator_enabled') ? 1 : 0),
            $this->request->input('status', 'active'),
            Auth::id()
        ]);

        AuditService::log('loan_products', 'create', (string)$id, null, ['name' => $data['name']]);
        Session::flash('success', 'เพิ่มผลิตภัณฑ์เงินกู้เรียบร้อยแล้ว');
        $this->redirect(url('admin/loans'));
    }

    public function destroy(string $id): void
    {
        $p = Database::first("SELECT * FROM loan_products WHERE id = ? LIMIT 1", [(int) $id]);
        if ($p) {
            Database::execute("UPDATE loan_products SET deleted_at = NOW(), updated_by = ? WHERE id = ?", [Auth::id(), (int) $id]);
            AuditService::log('loan_products', 'delete', (string)$id, $p);
        }

        if ($this->request->isAjax()) {
            $this->json(['success' => true, 'message' => 'ลบผลิตภัณฑ์เงินกู้เรียบร้อยแล้ว']);
        } else {
            Session::flash('success', 'ลบผลิตภัณฑ์เงินกู้เรียบร้อยแล้ว');
            $this->redirect(url('admin/loans'));
        }
    }
}
