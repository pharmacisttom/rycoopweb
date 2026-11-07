<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class RoleMiddleware
{
    private array $allowedRoles;

    public function __construct(string|array $roles = [])
    {
        $this->allowedRoles = (array) $roles;
    }

    public function handle(Request $request, Response $response): bool
    {
        if (!Auth::check()) {
            if ($request->isAjax()) {
                $response->json([
                    'success' => false,
                    'code' => 'UNAUTHORIZED',
                    'message' => 'เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่'
                ], 401);
                return false;
            }
            $response->redirect(url('admin/login'));
            return false;
        }

        if (!empty($this->allowedRoles) && !Auth::hasRole($this->allowedRoles)) {
            $user = Auth::user();
            $role = (string)($user['role_slug'] ?? '');
            $userId = (int)($user['id'] ?? 0);

            if ($role === 'member') {
                \App\Services\SecurityAuditService::recordMemberAccessDenied(
                    $userId,
                    'member',
                    $request->uri(),
                    'Member attempted access to restricted administrative route'
                );
            }
            if ($request->isAjax()) {
                $response->json([
                    'success' => false,
                    'code' => 'FORBIDDEN',
                    'message' => 'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้ (Permission Denied)'
                ], 403);
                return false;
            }

            Session::flash('error', 'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้ (Permission Denied)');
            $role = Auth::user()['role_slug'] ?? '';
            $fallbackUrl = match ($role) {
                'member' => url('member/dashboard'),
                'staff' => url('staff/dashboard'),
                default => url('login'),
            };
            $response->redirect($fallbackUrl);
            return false;
        }

        return true;
    }
}
