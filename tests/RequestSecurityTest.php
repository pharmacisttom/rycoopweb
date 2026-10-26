<?php
declare(strict_types=1);
putenv('TRUSTED_PROXY_IPS=127.0.0.1');require __DIR__.'/../vendor/autoload.php';
use App\Core\Request;
function checkRequest(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo 'PASS: '.$label.PHP_EOL;}
$_GET=$_POST=$_FILES=[];
$_SERVER=['REQUEST_METHOD'=>'GET','REQUEST_URI'=>'/','REMOTE_ADDR'=>'203.0.113.10','HTTP_X_FORWARDED_FOR'=>'192.0.2.99','HTTP_CF_CONNECTING_IP'=>'192.0.2.98'];
checkRequest((new Request())->ip()==='203.0.113.10','untrusted origin cannot spoof rate limit IP');
$_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['HTTP_X_FORWARDED_FOR']='192.0.2.99, 198.51.100.10';checkRequest((new Request())->ip()==='198.51.100.10','trusted proxy walks the chain from the right');
$_SERVER['HTTP_X_FORWARDED_FOR']='not-an-ip';checkRequest((new Request())->ip()==='127.0.0.1','invalid forwarded address fails closed');
$_SERVER['REQUEST_METHOD']='POST';$_POST=['password'=>' password with spaces ','title'=>' Title '];$r=new Request();checkRequest($r->input('password')===' password with spaces ','password is preserved exactly');checkRequest($r->input('title')==='Title','ordinary form text is trimmed');
