<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Exceptions\SecurityGateException;
use App\Services\AdminControlCenterService;
use App\Services\AuditService;
use App\Services\DualControlSecurityService;

class UserController extends Controller
{
    public function index(): void
    {
        $users = Database::query("SELECT u.*, r.name as role_name, r.slug as role_slug 
                                 FROM users u 
                                 LEFT JOIN user_roles ur ON u.id = ur.user_id 
                                 LEFT JOIN roles r ON ur.role_id = r.id 
                                 WHERE u.deleted_at IS NULL 
                                 ORDER BY u.created_at ASC");

        $roles = Database::query("SELECT * FROM roles ORDER BY id ASC");

        $this->render('admin.users.index', [
            'title' => 'จัดการผู้ใช้งานและสิทธิ์ (User & RBAC)',
            'users' => $users,
            'roles' => $roles,
        ], 'layouts.admin');
    }

    public function store(): void
    {
        $correlationId = AdminControlCenterService::correlationId();
        try {
            $actorId = (int) (Auth::id() ?? 0);
            if ($actorId <= 0 || !DualControlSecurityService::hasExecutePermission($actorId, 'users.create')) {
                throw new SecurityGateException('คุณไม่มีสิทธิ์สร้างผู้ใช้งานระบบ', 403, 'PERMISSION_DENIED');
            }
            $inlineTotp = $this->request->input('totp_code') !== null ? (string) $this->request->input('totp_code') : null;
            DualControlSecurityService::requireFreshStepUpMfa($actorId, $inlineTotp, 'users.create', true, $this->request->ip());
        } catch (SecurityGateException $e) {
            if ($this->request->isAjax() || $this->request->wantsJson()) {
                $this->json(['success' => false, 'code' => $e->getErrorCode(), 'correlation_id' => $correlationId, 'message' => $e->getMessage()], $e->getHttpStatus());
                return;
            }
            Session::flash('error', $e->getMessage());
            $this->redirect(url('admin/users'));
            return;
        }

        $data = $this->validate([
            'name' => 'required',
            'username' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8',
            'role_id' => 'required|numeric',
        ]);

        $pwConfig = config('security.password');
        $hashed = password_hash($data['password'], $pwConfig['algo'] ?? PASSWORD_ARGON2ID, $pwConfig['options'] ?? []);
        $uuid = bin2hex(random_bytes(16));

        $sql = "INSERT INTO users (uuid, name, username, email, password, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'active', NOW())";

        $userId = Database::insert($sql, [
            $uuid,
            $data['name'],
            $data['username'],
            $data['email'],
            $hashed,
        ]);

        // Assign role
        Database::execute("INSERT INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())", [
            (int) $userId,
            (int) $data['role_id'],
        ]);

        AuditService::log('users', 'create', (string) $userId, null, [
            'email' => $data['email'],
            'role_id' => $data['role_id'],
            'correlation_id' => $correlationId,
        ]);
        Session::flash('success', 'เพิ่มผู้ใช้งานระบบเรียบร้อยแล้ว');
        $this->redirect(url('admin/users'));
    }

    public function destroy(string $id): void
    {
        $correlationId = AdminControlCenterService::correlationId();
        try {
            $actorId = (int) (Auth::id() ?? 0);
            if ($actorId <= 0 || !DualControlSecurityService::hasExecutePermission($actorId, 'users.suspend')) {
                throw new SecurityGateException('คุณไม่มีสิทธิ์ลบผู้ใช้งานระบบ', 403, 'PERMISSION_DENIED');
            }
            $inlineTotp = $this->request->input('totp_code') !== null ? (string) $this->request->input('totp_code') : null;
            DualControlSecurityService::requireFreshStepUpMfa($actorId, $inlineTotp, 'users.delete', true, $this->request->ip());
        } catch (SecurityGateException $e) {
            if ($this->request->isAjax() || $this->request->wantsJson()) {
                $this->json(['success' => false, 'code' => $e->getErrorCode(), 'correlation_id' => $correlationId, 'message' => $e->getMessage()], $e->getHttpStatus());
                return;
            }
            Session::flash('error', $e->getMessage());
            $this->redirect(url('admin/users'));
            return;
        }

        if ((int) $id === (int) Auth::id()) {
            Session::flash('error', 'ไม่สามารถลบบัญชีของตัวเองได้');
            $this->redirect(url('admin/users'));
            return;
        }

        $user = Database::first("SELECT id, name, username, email, status FROM users WHERE id = ? LIMIT 1", [(int) $id]);
        if ($user) {
            Database::execute("UPDATE users SET deleted_at = NOW() WHERE id = ?", [(int) $id]);
            AuditService::log('users', 'delete', (string) $id, $user, ['correlation_id' => $correlationId]);
        }

        if ($this->request->isAjax()) {
            $this->json(['success' => true, 'correlation_id' => $correlationId]);
        } else {
            Session::flash('success', 'ลบผู้ใช้งานเรียบร้อยแล้ว');
            $this->redirect(url('admin/users'));
        }
    }
}
