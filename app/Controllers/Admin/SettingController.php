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

class SettingController extends Controller
{
    public function index(): void
    {
        $settingsRaw = Database::query("SELECT * FROM site_settings");
        $settings = [];
        foreach ($settingsRaw as $s) {
            $settings[$s['key']] = $s['value'];
        }

        $this->render('admin.settings.index', [
            'title' => 'ตั้งค่าระบบและองค์กร (Site Settings)',
            'settings' => $settings,
        ], 'layouts.admin');
    }

    public function update(): void
    {
        $correlationId = AdminControlCenterService::correlationId();
        try {
            $actorId = (int) (Auth::id() ?? 0);
            if ($actorId <= 0 || !DualControlSecurityService::hasExecutePermission($actorId, 'settings.update')) {
                throw new SecurityGateException('คุณไม่มีสิทธิ์แก้ไขการตั้งค่าระบบ', 403, 'PERMISSION_DENIED');
            }
            $inlineTotp = $this->request->input('totp_code') !== null ? (string) $this->request->input('totp_code') : null;
            DualControlSecurityService::requireFreshStepUpMfa($actorId, $inlineTotp, 'settings.update', true, $this->request->ip());
        } catch (SecurityGateException $e) {
            if ($this->request->isAjax() || $this->request->wantsJson()) {
                $this->json(['success' => false, 'code' => $e->getErrorCode(), 'correlation_id' => $correlationId, 'message' => $e->getMessage()], $e->getHttpStatus());
                return;
            }
            Session::flash('error', $e->getMessage());
            $this->redirect(url('admin/settings'));
            return;
        }

        $inputs = $this->request->all();
        unset($inputs['_csrf_token'], $inputs['totp_code'], $inputs['step_up_code'], $inputs['mfa_code']);

        foreach ($inputs as $k => $v) {
            Database::execute(
                "INSERT INTO site_settings (`key`, `value`, `group`, updated_at)
                 VALUES (?, ?, 'general', NOW())
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()",
                [$k, $v]
            );
        }

        $redactedInputs = AdminControlCenterService::redactMetadata($inputs);
        AuditService::log('settings', 'update', null, null, [
            'settings' => is_array($redactedInputs) ? $redactedInputs : [],
            'correlation_id' => $correlationId,
        ]);
        Session::flash('success', 'บันทึกการตั้งค่าระบบเรียบร้อยแล้ว');
        $this->redirect(url('admin/settings'));
    }
}
