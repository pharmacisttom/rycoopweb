<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\MemberPdpaService;
use App\Services\MemberPortalService;

/**
 * Member PDPA Session Gate Middleware.
 *
 * Ensures that ANY access to protected member endpoints requires
 * explicit PDPA acknowledgement for the CURRENT authenticated session.
 *
 * Enforces:
 * - Active session login nonce exists.
 * - Active privacy policy exists.
 * - Acknowledgement record exists in database for this (user, nonce, policy).
 * - Fails with HTTP 403 and code 'PDPA_ACK_REQUIRED' if not acknowledged.
 */
class MemberPdpaMiddleware
{
    public function handle(Request $request, Response $response): bool
    {
        // 1. Verify user is authenticated
        if (!Auth::check()) {
            if ($request->isAjax()) {
                $response->json([
                    'success' => false,
                    'code' => 'UNAUTHORIZED',
                    'message' => 'กรุณาเข้าสู่ระบบก่อนเข้าใช้งาน',
                ], 401);
                return false;
            }
            Session::flash('error', 'กรุณาเข้าสู่ระบบก่อนเข้าใช้งาน');
            $response->redirect(url('login'));
            return false;
        }

        $user = Auth::user();
        $roleSlug = (string) ($user['role_slug'] ?? '');

        // Only role=member is subject to this gate
        if ($roleSlug !== 'member') {
            return true;
        }

        $userId = (int) ($user['id'] ?? 0);
        $nonce = MemberPdpaService::getCurrentSessionNonce();

        // If nonce is completely missing from session, initialize it for this session
        if (empty($nonce)) {
            $nonce = MemberPdpaService::initMemberSession($user);
        }

        // 2. Discover active policy
        $activePolicy = MemberPdpaService::getActivePolicy();
        if (!$activePolicy) {
            // If no active policy is configured, fail closed for member security
            if ($request->isAjax()) {
                $response->json([
                    'success' => false,
                    'code' => 'PDPA_POLICY_UNAVAILABLE',
                    'message' => 'ระบบประกาศความเป็นส่วนตัวยังไม่พร้อมให้บริการ กรุณาติดต่อสหกรณ์',
                ], 503);
                return false;
            }
            Session::flash('error', 'ระบบประกาศความเป็นส่วนตัวยังไม่พร้อมให้บริการ กรุณาติดต่อสหกรณ์');
            $response->redirect(url('/'));
            return false;
        }

        // 3. Verify acknowledgement in database for current login nonce
        $acknowledged = MemberPdpaService::isAcknowledged($userId, $nonce, (int) $activePolicy['id']);

        if (!$acknowledged) {
            header('Cache-Control: no-store, private');
            if ($request->isAjax()) {
                $response->json([
                    'success' => false,
                    'code' => 'PDPA_ACK_REQUIRED',
                    'message' => 'กรุณารับทราบประกาศความเป็นส่วนตัวก่อนเข้าใช้งาน',
                    'redirect' => '/member/privacy-ack',
                ], 403);
                return false;
            }

            Session::flash('info', 'กรุณารับทราบประกาศความเป็นส่วนตัวก่อนเข้าใช้งาน');
            $response->redirect(url('member/privacy-ack'));
            return false;
        }

        return true;
    }
}
