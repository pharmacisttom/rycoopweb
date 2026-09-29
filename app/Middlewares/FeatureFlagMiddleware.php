<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Request;
use App\Core\Response;

final class FeatureFlagMiddleware
{
    public function __construct(private readonly string $feature)
    {
    }

    public function handle(Request $request, Response $response): bool
    {
        if ((bool) config('features.' . $this->feature, false)) {
            return true;
        }

        if ($request->isAjax() || str_starts_with($request->uri(), '/api/')) {
            $response->json([
                'success' => false,
                'message' => 'ระบบสมาชิกออนไลน์ยังไม่เปิดให้บริการ',
                'errors' => [],
            ], 403);
            return false;
        }

        $response->redirect(url('service-unavailable'));
        return false;
    }
}
