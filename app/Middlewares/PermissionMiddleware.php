<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

class PermissionMiddleware
{
    private array $modules;

    public function __construct(string|array $module, private string $action)
    {
        $this->modules = is_array($module) ? $module : [$module];
    }

    public function handle(Request $request, Response $response): bool
    {
        if (!Auth::check() || !Auth::user()) {
            $response->json([
                'success' => false,
                'code' => 'UNAUTHORIZED',
                'message' => 'กรุณาเข้าสู่ระบบใหม่'
            ], 401);
            return false;
        }

        foreach ($this->modules as $mod) {
            if (Auth::hasPermission($mod, $this->action)) {
                return true;
            }
        }

        $response->json([
            'success' => false,
            'code' => 'FORBIDDEN',
            'message' => 'ไม่มีสิทธิ์ดำเนินการนี้'
        ], 403);
        return false;
    }
}
