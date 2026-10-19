<?php
declare(strict_types=1);
namespace App\Core;

final class HttpPreviewPolicy
{
    public static function protectedPath(string $path): bool
    {
        return (bool)preg_match('~^/(?:test/)?(?:api/(?:admin|member|staff|auth)(?:/|$)|admin(?:/|$)|staff(?:/|$)|member(?:/|$)|login(?:/|$)|logout(?:/|$)|csrf-token(?:/|$)|(?:api/)?change-password(?:/|$))~i',$path);
    }

    public static function denied(array $server): bool
    {
        $path=parse_url((string)($server['REQUEST_URI']??'/'),PHP_URL_PATH)?:'/';
        if (!self::protectedPath($path)) return false;
        $tls=(!empty($server['HTTPS']) && strtolower((string)$server['HTTPS'])!=='off') || (string)($server['SERVER_PORT']??'')==='443';
        if ($tls) return false;
        // Forwarded headers from arbitrary clients never prove a TLS connection.
        $loopback=in_array($server['REMOTE_ADDR']??'',['127.0.0.1','::1'],true);
        return !($loopback && in_array(env('APP_ENV','production'),['local','testing'],true));
    }
}
