<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\{Auth, Controller, Logger};
use App\Services\{DynamicLayoutService, DualControlSecurityService};

final class DynamicLayoutController extends Controller
{
    private function respond(callable $work): void
    {
        header('Cache-Control: no-store');
        try {
            $this->json(['success' => true, 'data' => $work()]);
        } catch (\App\Exceptions\SecurityGateException $e) {
            $this->json(['success' => false, 'code' => $e->getErrorCode(), 'message' => $e->getMessage()], $e->getHttpStatus());
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            $status = (int) $e->getCode();
            if (!in_array($status, [401,403,409], true)) $status = 500;
            if ($status === 500) Logger::error('Dynamic layout API: '.get_class($e));
            $this->json(['success' => false, 'code' => $status === 409 ? 'VERSION_CONFLICT' : 'LAYOUT_ERROR',
                'message' => $status === 409 ? 'ข้อมูลเปลี่ยนไปแล้ว กรุณาโหลดใหม่' : ($status === 500 ? 'ระบบจัดการ Layout ยังไม่พร้อม' : $e->getMessage())], $status);
        }
    }

    private function authorize(bool $write = false): void
    {
        if (!Auth::check() || !Auth::user()) throw new \RuntimeException('UNAUTHORIZED', 401);
        if ((Auth::user()['role_slug'] ?? '') !== 'super_admin') throw new \RuntimeException('FORBIDDEN', 403);
        if ($write) {
            DualControlSecurityService::requireFreshStepUpMfa(
                (int) (Auth::id() ?? 0),
                $this->request->input('totp_code') !== null ? (string) $this->request->input('totp_code') : null,
                'settings.update', true, $this->request->ip()
            );
        }
    }

    public function published(): void
    {
        header('Cache-Control: no-cache');
        $this->respond(static fn() => DynamicLayoutService::published());
    }
    public function show(): void
    {
        $this->respond(function () {
            $this->authorize();
            return DynamicLayoutService::state();
        });
    }
    public function draft(): void
    {
        $this->respond(function () {
            $this->authorize(true);
            return DynamicLayoutService::draft($this->request->all());
        });
    }
    public function publish(): void
    {
        $this->respond(function () {
            $this->authorize(true);
            return DynamicLayoutService::publish($this->request->all());
        });
    }
}
