<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Exceptions\SecurityGateException;
use App\Services\AccessService;
use App\Services\AdminControlCenterService;
use App\Services\DualControlSecurityService;

class AccessController extends \App\Core\Controller
{
    private function run(callable $work): void
    {
        $correlationId = AdminControlCenterService::correlationId();
        if (!headers_sent()) {
            header('Cache-Control: no-store, private');
            header('X-Correlation-Id: ' . $correlationId);
        }

        try {
            $this->json([
                'success' => true,
                'correlation_id' => $correlationId,
                ...$work(),
            ]);
        } catch (SecurityGateException $e) {
            $this->json([
                'success' => false,
                'code' => $e->getErrorCode(),
                'correlation_id' => $correlationId,
                'message' => $e->getMessage(),
                'context' => $e->getContext(),
            ], $e->getHttpStatus());
        } catch (\InvalidArgumentException $e) {
            $this->json([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'correlation_id' => $correlationId,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\PDOException $e) {
            \App\Core\Logger::error('Access management [' . $correlationId . ']: ' . $e->getMessage());
            $this->json([
                'success' => false,
                'code' => 'DATABASE_ERROR',
                'correlation_id' => $correlationId,
                'message' => $e->getCode() === '23000'
                    ? 'ชื่อผู้ใช้ อีเมล หรือรหัส Role ซ้ำ'
                    : 'ฐานข้อมูลสิทธิ์ไม่พร้อม กรุณาตรวจ migration',
            ], 422);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Access management [' . $correlationId . ']: ' . $e->getMessage());
            $this->json([
                'success' => false,
                'code' => 'INTERNAL_ERROR',
                'correlation_id' => $correlationId,
                'message' => 'ดำเนินการไม่สำเร็จ',
            ], 500);
        }
    }

    private function assertReadAccess(array $requiredPermissions): void
    {
        if (!Auth::check() || !Auth::user()) {
            throw new SecurityGateException('กรุณาเข้าสู่ระบบและยืนยันตัวตนก่อนดำเนินการ', 401, 'UNAUTHORIZED');
        }
        if (!AdminControlCenterService::canAny($requiredPermissions)) {
            throw new SecurityGateException('คุณไม่มีสิทธิ์เข้าถึงการจัดการสิทธิ์และผู้ใช้งาน', 403, 'FORBIDDEN');
        }
    }

    private function assertStepUpGate(string $permission, string $scope): void
    {
        if (!Auth::check() || !Auth::user()) {
            throw new SecurityGateException('กรุณาเข้าสู่ระบบและยืนยันตัวตนก่อนดำเนินการ', 401, 'UNAUTHORIZED');
        }
        $userId = (int) Auth::id();
        if (!DualControlSecurityService::hasExecutePermission($userId, $permission)) {
            throw new SecurityGateException('คุณไม่มีสิทธิ์ดำเนินการเปลี่ยนบทบาทหรือสิทธิ์ผู้ใช้งาน', 403, 'PERMISSION_DENIED');
        }
        $inlineTotp = $this->request->input('totp_code') !== null
            ? (string) $this->request->input('totp_code')
            : ($this->request->input('step_up_code') !== null ? (string) $this->request->input('step_up_code') : null);
        DualControlSecurityService::requireFreshStepUpMfa($userId, $inlineTotp, $scope, true, $this->request->ip());
    }

    public function me(): void
    {
        $this->run(function () {
            if (!Auth::check() || !Auth::user()) {
                throw new SecurityGateException('ไม่พบบัญชีที่ใช้งานได้', 401, 'UNAUTHORIZED');
            }
            return [
                'permissions' => AccessService::permissions((int) Auth::id()),
                'user' => [
                    'id' => Auth::id(),
                    'name' => Auth::user()['name'],
                    'role' => Auth::user()['role_slug'],
                ],
            ];
        });
    }

    public function index(): void
    {
        $this->run(function () {
            $this->assertReadAccess(['roles.view', 'roles.manage', 'permissions.manage']);
            return [
                'roles' => array_map(fn($r) => AccessService::role((int) $r['id']), Database::query('SELECT id FROM roles ORDER BY id')),
                'modules' => AccessService::MODULES,
                'actions' => AccessService::ACTIONS,
                'catalog' => AccessService::CATALOG,
            ];
        });
    }

    public function createRole(): void
    {
        $this->run(function () {
            $this->assertStepUpGate('roles.manage', 'roles.create');
            return ['role' => AccessService::saveRole($this->request->all())];
        });
    }

    public function updateRole(string $id): void
    {
        $this->run(function () use ($id) {
            $this->assertStepUpGate('roles.manage', 'roles.update');
            return ['role' => AccessService::saveRole($this->request->all(), (int) $id)];
        });
    }

    public function deleteRole(string $id): void
    {
        $this->run(function () use ($id) {
            $this->assertStepUpGate('roles.manage', 'roles.delete');
            AccessService::deleteRole((int) $id, $this->request->all());
            return [];
        });
    }

    public function users(): void
    {
        $this->run(function () {
            $this->assertReadAccess(['users.view', 'users.update', 'roles.manage']);
            $q = mb_substr(trim((string) $this->request->query('q', '')), 0, 100);
            $page = max(1, (int) $this->request->query('page', 1));
            $where = 'u.deleted_at IS NULL AND NOT EXISTS(SELECT 1 FROM members m WHERE m.user_id=u.id)';
            $args = [];
            if ($q !== '') {
                $where .= ' AND (u.name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
                $args = array_fill(0, 3, '%' . $q . '%');
            }
            $total = (int) Database::value("SELECT COUNT(*) FROM users u WHERE $where", $args);
            $page = min($page, max(1, (int) ceil($total / 25)));
            $offset = ($page - 1) * 25;
            $users = Database::query("SELECT u.id FROM users u WHERE $where ORDER BY u.id DESC LIMIT 25 OFFSET $offset", $args);
            return [
                'users' => array_map(fn($u) => AccessService::account((int) $u['id']), $users),
                'total' => $total,
                'page' => $page,
            ];
        });
    }

    public function createUser(): void
    {
        $this->run(function () {
            $this->assertStepUpGate('users.update', 'users.create');
            return ['user' => AccessService::saveAccount($this->request->all())];
        });
    }

    public function updateUser(string $id): void
    {
        $this->run(function () use ($id) {
            $this->assertStepUpGate('users.update', 'users.update');
            return ['user' => AccessService::saveAccount($this->request->all(), (int) $id)];
        });
    }

    public function deleteUser(string $id): void
    {
        $this->run(function () use ($id) {
            $this->assertStepUpGate('users.update', 'users.delete');
            AccessService::deleteAccount((int) $id, $this->request->all());
            return [];
        });
    }
}
