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
        if(!$this->request->isAjax()){$this->redirect(url('admin/settings/content'));return;}
        \App\Services\AdminCrudService::respond($this->response,fn()=>\App\Services\ApprovedSettingsService::read());
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

        \App\Services\AdminCrudService::respond($this->response,fn()=>\App\Services\ApprovedSettingsService::save($this->request->all()));
    }
}
