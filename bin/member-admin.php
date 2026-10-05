#!/usr/bin/env php
<?php
declare(strict_types=1);
require_once __DIR__.'/../vendor/autoload.php';
use App\Core\Database;

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$username=$argv[1]??'memberadmin';
$password=(string)getenv('MEMBER_ADMIN_PASSWORD');
if(!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/D',$username) || strlen($password)<16) {
    fwrite(STDERR,"Usage: set MEMBER_ADMIN_PASSWORD (at least 16 characters), then php bin/member-admin.php [username] [--reset]\n");exit(1);
}
$pdo=Database::connect();$pdo->beginTransaction();
try {
    $role=Database::value("SELECT id FROM roles WHERE slug='member_admin'");
    if(!$role)throw new RuntimeException('Run database migrations first.');
    $user=Database::first('SELECT id FROM users WHERE username=? FOR UPDATE',[$username]);
    if($user) {
        $roles=Database::query('SELECT r.slug FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=?',[$user['id']]);
        if(count($roles)!==1 || $roles[0]['slug']!=='member_admin')throw new RuntimeException('Refusing to modify an account with other roles.');
        if(!in_array('--reset',$argv,true))throw new RuntimeException('Account exists. Use --reset explicitly to reset only this membership administrator.');
        Database::execute('UPDATE users SET password=?,status=?,deleted_at=NULL WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),'active',$user['id']]);
    } else {
        $hex=bin2hex(random_bytes(16));$uuid=substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-a'.substr($hex,17,3).'-'.substr($hex,20);
        $id=Database::insert('INSERT INTO users(uuid,name,username,email,password,status) VALUES (?,?,?,?,?,?)',[$uuid,'ผู้ดูแลระบบสมาชิก',$username,$username.'@member-admin.invalid',password_hash($password,PASSWORD_DEFAULT),'active']);
        Database::execute('INSERT INTO user_roles(user_id,role_id) VALUES (?,?)',[$id,$role]);
    }
    $pdo->commit();echo "Membership administrator ready: {$username}\n";
}catch(Throwable $e){$pdo->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);}
