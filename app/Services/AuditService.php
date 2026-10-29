<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;

class AuditService
{
    public static function log(string $module, string $action, ?string $recordId = null, mixed $oldValues = null, mixed $newValues = null, bool $required = false): void
    {
        $userId = Auth::id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';

        $oldJson = $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
        $newJson = $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;

        $sql = "INSERT INTO audit_logs (user_id, module, action, record_id, old_values, new_values, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        try {
            Database::execute($sql, [
                $userId,
                $module,
                $action,
                $recordId,
                $oldJson,
                $newJson,
                $ip,
                $userAgent
            ]);

            Logger::log('audit', "Audit Action: [{$module}] [{$action}] Record: {$recordId} by User: {$userId}");
        } catch (\Throwable $e) {
            Logger::error("Audit Log Failure: " . $e->getMessage());
            if ($required) { throw $e; }
        }
    }
}
