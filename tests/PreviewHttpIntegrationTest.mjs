import {test} from 'node:test';import assert from 'node:assert/strict';import {spawnSync} from 'node:child_process';
for(const pathname of ['/login','/api/auth/me','/api/admin/members','/test/api/member/dividends'])test('real PHP denies insecure preview before DB/session '+pathname,()=>{
 const code='$_SERVER["REQUEST_URI"]=$argv[1];$_SERVER["REMOTE_ADDR"]="203.0.113.7";$_SERVER["SERVER_PORT"]="80";$_SERVER["REQUEST_METHOD"]="GET";$_SERVER["HTTP_X_FORWARDED_PROTO"]="https";require "public/index.php";';
 const result=spawnSync('php',['-r',code,pathname],{encoding:'utf8',env:{...process.env,APP_ENV:'production',DB_HOST:'no-database.invalid',APP_DEBUG:'false'}});
 assert.equal(result.status,0,result.stderr);assert.equal(JSON.parse(result.stdout).code,'HTTPS_REQUIRED');
});
