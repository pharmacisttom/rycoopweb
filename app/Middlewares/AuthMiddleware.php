<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class AuthMiddleware
{
    public function handle(Request $request, Response $response): bool
    {
        if (!Auth::check()) {
            if (Auth::checkPendingMfaEnrollment()) {
                if ($request->isAjax()) {
                    $response->json([
                        'success' => false,
                        'code' => 'MFA_ENROLLMENT_REQUIRED',
                        'requiresMfaEnrollment' => true,
                        'redirect' => '/admin/mfa/enroll',
                        'message' => 'จำเป็นต้องลงทะเบียน 2FA ก่อนเข้าใช้งาน'
                    ], 403);
                    return false;
                }
                $response->redirect(url('admin/mfa/enroll'));
                return false;
            }

            if (Auth::checkPending2FA()) {
                if ($request->isAjax()) {
                    $response->json([
                        'success' => false,
                        'code' => 'TWO_FACTOR_REQUIRED',
                        'requiresTwoFactor' => true,
                        'redirect' => '/admin/mfa/verify',
                        'message' => 'กรุณายืนยันรหัสความปลอดภัย 2FA'
                    ], 403);
                    return false;
                }
                $response->redirect(url('admin/mfa/verify'));
                return false;
            }

            if ($request->isAjax()) {
                $response->json([
                    'success' => false,
                    'code' => 'UNAUTHORIZED',
                    'message' => 'เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่'
                ], 401);
                return false;
            }

            Session::flash('error', 'กรุณาเข้าสู่ระบบก่อนเข้าใช้งาน');
            $response->redirect(url('login'));
            return false;
        }

        return true;
    }
}
