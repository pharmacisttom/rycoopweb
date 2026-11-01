<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Services\MemberPdpaService;
use App\Services\MemberPortalService;
use Throwable;

/**
 * Controller for Member PDPA Status and Session Acknowledgement.
 */
class MemberPdpaController extends Controller
{
    /**
     * GET /api/member/pdpa/status
     *
     * Returns:
     * - required (bool)
     * - current_policy_version (string)
     * - policy_title (string)
     * - policy_content (string)
     * - policy_hash (string)
     * - acknowledged (bool)
     */
    public function status(): void
    {
        header('Cache-Control: no-store, private');

        if (!Auth::check()) {
            $this->response->json([
                'success' => false,
                'code' => 'UNAUTHORIZED',
                'message' => 'กรุณาเข้าสู่ระบบก่อนเข้าใช้งาน',
            ], 401);
            return;
        }

        $user = Auth::user();
        if (($user['role_slug'] ?? '') !== 'member') {
            $this->response->json([
                'success' => false,
                'code' => 'FORBIDDEN',
                'message' => 'สำหรับสมาชิกสหกรณ์เท่านั้น',
            ], 403);
            return;
        }

        $userId = (int) $user['id'];
        $policy = MemberPdpaService::getActivePolicy();

        if (!$policy) {
            $this->response->json([
                'success' => false,
                'code' => 'PDPA_POLICY_UNAVAILABLE',
                'message' => 'ระบบประกาศความเป็นส่วนตัวยังไม่พร้อมให้บริการ',
            ], 503);
            return;
        }

        $nonce = MemberPdpaService::getCurrentSessionNonce();
        if (empty($nonce)) {
            $nonce = MemberPdpaService::initMemberSession($user);
        }

        $acknowledged = MemberPdpaService::isAcknowledged($userId, $nonce, (int) $policy['id']);

        $this->response->json([
            'success' => true,
            'required' => true,
            'current_policy_version' => (string) $policy['version'],
            'policy_title' => (string) $policy['title'],
            'policy_content' => (string) $policy['content'],
            'policy_hash' => (string) $policy['content_sha256'],
            'effective_at' => (string) $policy['effective_at'],
            'acknowledged' => $acknowledged,
        ]);
    }

    /**
     * POST /api/member/pdpa/acknowledge
     *
     * Validates and records session PDPA acknowledgement.
     */
    public function acknowledge(): void
    {
        header('Cache-Control: no-store, private');

        if (!Auth::check()) {
            $this->response->json([
                'success' => false,
                'code' => 'UNAUTHORIZED',
                'message' => 'กรุณาเข้าสู่ระบบก่อนเข้าใช้งาน',
            ], 401);
            return;
        }

        $user = Auth::user();
        if (($user['role_slug'] ?? '') !== 'member') {
            $this->response->json([
                'success' => false,
                'code' => 'FORBIDDEN',
                'message' => 'สำหรับสมาชิกสหกรณ์เท่านั้น',
            ], 403);
            return;
        }

        $userId = (int) $user['id'];
        $member = MemberPortalService::getActiveMemberByUserId($userId);

        if (!$member) {
            $this->response->json([
                'success' => false,
                'code' => 'MEMBER_NOT_LINKED',
                'message' => 'ไม่พบข้อมูลสมาชิกที่เปิดใช้งานหรือเชื่อมโยงกับบัญชีของคุณ',
            ], 403);
            return;
        }

        // Validate explicit acknowledgement checkbox
        $ackInput = $this->request->input('acknowledgement') ?? $this->request->input('acknowledged');
        $isExplicitAck = $ackInput === true || $ackInput === 'true' || $ackInput === 1 || $ackInput === '1';

        if (!$isExplicitAck) {
            $this->response->json([
                'success' => false,
                'code' => 'ACKNOWLEDGEMENT_REQUIRED',
                'message' => 'กรุณาทำเครื่องหมายรับทราบประกาศความเป็นส่วนตัวและคำเตือนการใช้งานระบบ',
            ], 422);
            return;
        }

        $policyVersion = trim((string) ($this->request->input('policy_version') ?? ''));
        if ($policyVersion === '') {
            $this->response->json([
                'success' => false,
                'code' => 'MISSING_POLICY_VERSION',
                'message' => 'กรุณาระบุเวอร์ชันนโยบายความเป็นส่วนตัว',
            ], 422);
            return;
        }

        // Optional hash verification if provided by client
        $policyHash = trim((string) ($this->request->input('policy_hash') ?? ''));
        $activePolicy = MemberPdpaService::getActivePolicy();

        if (!$activePolicy) {
            $this->response->json([
                'success' => false,
                'code' => 'PDPA_POLICY_UNAVAILABLE',
                'message' => 'ระบบประกาศความเป็นส่วนตัวยังไม่พร้อมให้บริการ',
            ], 503);
            return;
        }

        if ($policyHash !== '' && !hash_equals((string) $activePolicy['content_sha256'], $policyHash)) {
            $this->response->json([
                'success' => false,
                'code' => 'POLICY_VERSION_MISMATCH',
                'message' => 'เนื้อหาประกาศความเป็นส่วนตัวมีการปรับปรุง กรุณาตรวจสอบและรับทราบนโยบายฉบับล่าสุด',
                'current_version' => $activePolicy['version'],
            ], 409);
            return;
        }

        $nonce = MemberPdpaService::getCurrentSessionNonce();
        if (empty($nonce)) {
            $nonce = MemberPdpaService::initMemberSession($user);
        }

        $ip = $this->request->ip();
        $userAgent = $this->request->userAgent();

        try {
            $result = MemberPdpaService::recordAcknowledgement(
                $userId,
                (int) $member['id'],
                $policyVersion,
                $nonce,
                $ip,
                $userAgent
            );

            if (!($result['success'] ?? false) && ($result['code'] ?? '') === 'POLICY_VERSION_MISMATCH') {
                $this->response->json($result, 409);
                return;
            }

            $this->response->json([
                'success' => true,
                'code' => 'PDPA_ACKNOWLEDGED',
                'message' => 'รับทราบประกาศความเป็นส่วนตัวเรียบร้อยแล้ว',
                'acknowledged' => true,
                'policy_version' => $result['policy_version'] ?? $policyVersion,
                'idempotent' => $result['idempotent'] ?? false,
            ]);
        } catch (Throwable $e) {
            // Fail closed: if error in DB or audit persistence, reject with 500
            $this->response->json([
                'success' => false,
                'code' => 'PERSISTENCE_FAILURE',
                'message' => 'ไม่สามารถบันทึกการรับทราบนโยบายได้ กรุณาลองใหม่อีกครั้ง',
            ], 500);
        }
    }

    /**
     * Fallback web view for direct URL navigation.
     */
    public function showPage(): void
    {
        // Redirect browser to React SPA route /member/privacy-ack
        $this->response->redirect('/member/privacy-ack');
    }
}
