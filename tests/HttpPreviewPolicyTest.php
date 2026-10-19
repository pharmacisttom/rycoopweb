<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use App\Core\HttpPreviewPolicy;
foreach(['/login','/api/auth/me','/api/member/dividends','/test/api/admin/members','/admin/login','/staff/dashboard'] as $path){
    $server=['REQUEST_URI'=>$path,'REMOTE_ADDR'=>'203.0.113.5','SERVER_PORT'=>'80','HTTP_X_FORWARDED_PROTO'=>'https'];
    if(!HttpPreviewPolicy::denied($server))throw new RuntimeException('HTTP protected access allowed: '.$path);
    $server['HTTPS']='on';if(HttpPreviewPolicy::denied($server))throw new RuntimeException('HTTPS denied');
}
if(HttpPreviewPolicy::denied(['REQUEST_URI'=>'/api/public/news','REMOTE_ADDR'=>'203.0.113.5']))throw new RuntimeException('Public API unexpectedly blocked');
echo "HTTP preview policy passed without database access\n";
