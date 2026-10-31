<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\MemberPortalService;

class MemberMiddleware
{
    /**
     * Enforce that the request is made by an authenticated user with 'member' role
     * AND who is bound to an active, valid record in the 'members' table.
     */
    public function handle(Request $request, Response $response): bool
    {
        $sessionUserId = (int)Session::get('user_id', 0);
        if ($sessionUserId > 0) {
            $rawUser = Database::first(
                "SELECT u.status, r.slug as role_slug FROM users u LEFT JOIN user_roles ur ON u.id = ur.user_id LEFT JOIN roles r ON ur.role_id = r.id WHERE u.id = ? AND u.deleted_at IS NULL",
                [$sessionUserId]
            );
            if ($rawUser && ($rawUser['status'] ?? '') === 'suspended') {
                if ($request->isAjax()) {
                    $response->json([
                        'success' => false,
                        'code' => 'ACCOUNT_SUSPENDED',
                        'message' => 'บัญชีผู้ใช้นี้ถูกระงับการใช้งาน กรุณาติดต่อสหกรณ์'
                    ], 403);
                    return false;
                }
                Session::flash('error', 'บัญชีผู้ใช้นี้ถูกระงับการใช้งาน กรุณาติดต่อสหกรณ์');
                $response->redirect(url('login'));
                return false;
            }
        }

        if (!Auth::check() || !Auth::user()) {
            if ($request->isAjax()) {
                $response->json([
                    'success' => false,
                    'code' => 'UNAUTHORIZED',
                    'message' => 'กรุณาเข้าสู่ระบบก่อนเข้าใช้งาน'
                ], 401);
                return false;
            }
            Session::flash('error', 'กรุณาเข้าสู่ระบบก่อนเข้าใช้งาน');
            $response->redirect(url('login'));
            return false;
        }

        $user = Auth::user();
        $roleSlug = (string)($user['role_slug'] ?? '');
        $userStatus = (string)($user['status'] ?? '');

        // 1. Must have the member role
        if ($roleSlug !== 'member') {
            if ($request->isAjax()) {
                $response->json([
                    'success' => false,
                    'code' => 'FORBIDDEN',
                    'message' => 'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้ (สำหรับสมาชิกเท่านั้น)'
                ], 403);
                return false;
            }
            Session::flash('error', 'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้ (สำหรับสมาชิกเท่านั้น)');
            $response->redirect(url('login'));
            return false;
        }

        // 2. User account in users table must be active
        if ($userStatus !== 'active') {
            if ($request->isAjax()) {
                $response->json([
                    'success' => false,
                    'code' => 'ACCOUNT_SUSPENDED',
                    'message' => 'บัญชีผู้ใช้นี้ถูกระงับการใช้งาน กรุณาติดต่อสหกรณ์'
                ], 403);
                return false;
            }
            Session::flash('error', 'บัญชีผู้ใช้นี้ถูกระงับการใช้งาน กรุณาติดต่อสหกรณ์');
            $response->redirect(url('login'));
            return false;
        }

        // 3. Must be bound to an active member record in members table
        $member = MemberPortalService::getActiveMemberByUserId((int)Auth::id());
        if (!$member) {
            if ($request->isAjax()) {
                $response->json([
                    'success' => false,
                    'code' => 'MEMBER_NOT_LINKED',
                    'message' => 'ไม่พบข้อมูลสมาชิกที่เปิดใช้งานหรือเชื่อมโยงกับบัญชีของคุณ กรุณาติดต่อเจ้าหน้าที่'
                ], 403);
                return false;
            }
            Session::flash('error', 'ไม่พบข้อมูลสมาชิกที่เปิดใช้งานหรือเชื่อมโยงกับบัญชีของคุณ กรุณาติดต่อเจ้าหน้าที่');
            $response->redirect(url('login'));
            return false;
        }

        MemberPortalService::setCurrentMember($member);
        return true;
    }
}
