<?php
declare(strict_types=1);
require __DIR__.'/WorkflowFixture.php';
use App\Core\{Database,Auth};
use App\Controllers\Admin\AuthController;
$pdo->exec('ALTER TABLE users ADD COLUMN password TEXT;ALTER TABLE users ADD COLUMN username TEXT');
$old=' Fixture-password-0001 ';$next=' Next-password-0002 ';
Database::execute('UPDATE users SET password=?,username=? WHERE id=1',[password_hash($old,PASSWORD_DEFAULT),'fixture_admin']);Database::execute('UPDATE users SET password=?,username=? WHERE id=2',[password_hash('Other-user-0002',PASSWORD_DEFAULT),'other_user']);
function passwordCall(array $body):WorkflowResponse{$r=new WorkflowResponse();(new AuthController(workflowRequest($body),$r))->changePassword();return $r;}
$input=['user_id'=>2,'username'=>'other_user','current_password'=>$old,'new_password'=>$next,'confirm_password'=>$next,'ajax'=>'1'];
try {
 workflowCheck(passwordCall($input)->status===401,'guest cannot change a password');
 workflowLogin(1);$other=Database::value('SELECT password FROM users WHERE id=2');$r=passwordCall($input);workflowCheck($r->status===200,'owner can change password despite spoofed target input');workflowCheck(password_verify($next,Database::value('SELECT password FROM users WHERE id=1')),'password whitespace is preserved');workflowCheck($other===Database::value('SELECT password FROM users WHERE id=2'),'another account remains unchanged');
 workflowCheck(passwordCall([...$input,'current_password'=>'incorrect'])->status===400,'wrong current password rejected');
 Database::execute('UPDATE users SET password=NULL WHERE id=1');workflowCheck(passwordCall($input)->status===403,'empty password hash cannot bypass reauthentication');
}finally{workflowFinish();}
