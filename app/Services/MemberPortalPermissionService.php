<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Auth;

/** Bridge existing PHP authorization to the separate member application. */
final class MemberPortalPermissionService
{
    public static function current(): array
    {
        $user=Auth::user();
        if (!$user) return [];
        $keys=['members.view','members.update','dividends.update','dividends.import'];
        // These match the existing member_admin route guards, without granting
        // unrelated news, lending, welfare, audit, or account administration.
        if (($user['role_slug']??'')==='member_admin') return $keys;
        return array_values(array_filter($keys,static function(string $key): bool {
            [$module,$action]=explode('.',$key,2);
            return Auth::hasPermission($module,$action);
        }));
    }
}
