<?php
declare(strict_types=1);
require __DIR__.'/WorkflowFixture.php';
use App\Core\{Auth,Database,Session};
use App\Controllers\Admin\MfaController;
use App\Services\TwoFactorService as Mfa;
function callMfa(string $method,array $body=[]):WorkflowResponse {$r=new WorkflowResponse();(new MfaController(workflowRequest($body),$r))->$method();return $r;}
try {
 workflowCheck(!Auth::check(),'anonymous is denied');
 workflowCheck(callMfa('enroll')->status===401,'anonymous cannot enroll');
 workflowLogin(1,true);workflowCheck(callMfa('enroll')->status===401,'completed session cannot replace MFA');
 workflowLogin(1,false,true);$r=callMfa('status');workflowCheck($r->data['pending_enrollment']===true,'partial session reaches enrollment status');
 $r=callMfa('enroll');workflowCheck($r->status===200&&strlen($r->data['secret'])===32,'enrollment issues a 160-bit secret');
 $secret=$r->data['secret'];$calculate=new ReflectionMethod(Mfa::class,'calculateCode');$code=$calculate->invoke(null,$secret,(int)floor(time()/30));
 $r=callMfa('confirm',['code'=>$code]);workflowCheck($r->status===200&&Auth::check(),'confirmed enrollment completes the session');workflowCheck(count($r->data['recovery_codes'])===8,'enrollment returns recovery codes once');$recovery=$r->data['recovery_codes'][0];
 workflowCheck(Database::value('SELECT two_factor_secret FROM users WHERE id=1')!==$secret,'TOTP secret is encrypted in database');
 workflowCheck(callMfa('confirm',['code'=>$code])->status===401,'enrollment cannot be replayed');
 workflowLogin(1,false);$nextCode=$calculate->invoke(null,$secret,(int)floor(time()/30)+1);$r=callMfa('verify',['code'=>$nextCode]);workflowCheck($r->status===200&&Auth::check(),'valid TOTP completes partial login');
 workflowCheck(!Mfa::verifyCode($secret,$nextCode,1,(int)Database::value('SELECT two_factor_last_used_slice FROM users WHERE id=1')),'TOTP replay is denied');
 workflowCheck(Database::execute('UPDATE users SET two_factor_last_used_slice=? WHERE id=1 AND (two_factor_last_used_slice IS NULL OR two_factor_last_used_slice < ?)',[(int)floor(time()/30)+1,(int)floor(time()/30)+1])===0,'atomic replay update rejects a concurrent stale challenge');
 workflowLogin(1,false);$r=callMfa('recovery',['recovery_code'=>$recovery]);workflowCheck($r->status===200&&Auth::check(),'single-use recovery completes partial login');workflowCheck(!Mfa::verifyRecoveryCode(1,$recovery),'consumed recovery code cannot be reused');
 workflowLogin(1,false);Session::set('mfa_started_at',time()-601);workflowCheck(callMfa('verify',['code'=>$code])->status===401,'expired challenge denied');
 workflowLogin(1,false);Database::execute('UPDATE users SET session_version=2 WHERE id=1');workflowCheck(callMfa('recovery',['recovery_code'=>$recovery])->status===401,'revoked partial session denied');
 Database::execute('UPDATE users SET session_version=1 WHERE id=1');workflowLogin(1,false);Database::execute("UPDATE users SET status='suspended' WHERE id=1");(new ReflectionProperty(Auth::class,'user'))->setValue(null,null);workflowCheck(callMfa('verify',['code'=>$code])->status===401,'suspended account cannot complete MFA');
 workflowCheck(Mfa::decryptSecret('not-a-valid-ciphertext')==='','invalid ciphertext fails closed');
 Database::execute("UPDATE users SET status='active' WHERE id=1");workflowLogin(1,true);Session::set('authenticated_at',time()-7201);workflowCheck(!Auth::check(),'absolute session lifetime cannot be extended by activity');
}finally {workflowFinish();}
