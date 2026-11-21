<?php
declare(strict_types=1);
// Synthetic SQLite identities and a fresh private session directory; never production storage/DB.
$boundaryDirectory=dirname(__DIR__).'/storage/tmp/auth-boundary-'.bin2hex(random_bytes(8));
mkdir($boundaryDirectory,0700,true);
require __DIR__.'/WorkflowFixture.php';
use App\Core\{Auth,Csrf,Database,Session};
putenv('SESSION_SAVE_PATH='.$boundaryDirectory);
function boundaryRequest(string $id): void {
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    (new ReflectionProperty(Session::class,'started'))->setValue(null,false);
    (new ReflectionProperty(Auth::class,'user'))->setValue(null,null);
    session_id($id);$_COOKIE[config('security.session.name')]=$id;Session::start();
}
function boundaryWorker(string $operation,string $id): array {
    global $boundaryDirectory;
    $pipes=[];
    $process=proc_open([PHP_BINARY,__DIR__.'/fixtures/AuthBoundaryWorker.php'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Worker creation failed');
    stream_set_timeout($pipes[1],15);
    fwrite($pipes[0],json_encode(['directory'=>$boundaryDirectory,'id'=>$id,'operation'=>$operation,'sharedDatabase'=>true])."\n");fflush($pipes[0]);
    boundarySignal($pipes,'READY');
    fwrite($pipes[0],"GO\n");fflush($pipes[0]);
    return [$process,$pipes];
}
function boundarySignal(array $pipes,string $expected): void {
    if(trim((string)fgets($pipes[1]))!==$expected)throw new RuntimeException('Worker synchronization failed: '.$expected);
}
function boundaryResult(array $worker): array {
    [$process,$pipes]=$worker;
    $result=json_decode((string)fgets($pipes[1]),true,512,JSON_THROW_ON_ERROR);
    foreach($pipes as $pipe)fclose($pipe);
    if(proc_close($process)!==0)throw new RuntimeException('Concurrent worker failed');
    return $result;
}
try {
    Session::start();$anonymous=session_id();$oldCsrf=Csrf::token();
    Auth::login(Database::first('SELECT u.*,r.slug AS role_slug FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE u.id=1'),true);$authenticated=session_id();
    workflowCheck($anonymous!==$authenticated,'login rotates the session identifier');
    boundaryRequest($anonymous);workflowCheck(!Auth::check()&&!Auth::checkPending2FA(),'anonymous old ID cannot reach successful login');
    boundaryRequest($authenticated);workflowCheck(Auth::check(),'new login session remains usable');
    workflowCheck(!Csrf::validate($oldCsrf),'pre-login CSRF token is not promoted');
    workflowLogin(1,false);$pending=session_id();Session::rotate(60);$pendingCurrent=session_id();
    Auth::verify2FA();$verified=session_id();
    boundaryRequest($pending);workflowCheck(!Auth::check(),'pre-rotation pending ID cannot forward across MFA success');
    boundaryRequest($pendingCurrent);workflowCheck(!Auth::check(),'MFA pending current ID cannot access verified state');
    boundaryRequest($verified);workflowCheck(Auth::check(),'verified current session is usable');
    Session::rotate(60);$rotated=session_id();
    boundaryRequest($verified);workflowCheck(Auth::check()&&session_id()===$rotated,'same-trust rotation forwards within grace');
    session_write_close();session_id($verified);session_start();
    workflowCheck(!isset($_SESSION['user_id']),'retired periodic record has no independent identity');
    boundaryRequest($verified);
    session_write_close();$workers=[];
    foreach(['rotate','check'] as $operation){
        $pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/fixtures/AuthBoundaryWorker.php'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('Cannot create isolated concurrency worker');
        fwrite($pipes[0],json_encode(['directory'=>$boundaryDirectory,'id'=>$verified,'operation'=>$operation])."\n");fflush($pipes[0]);
        $workers[]=[$process,$pipes];
    }
    foreach($workers as [$process,$pipes]){stream_set_timeout($pipes[1],15);if(trim((string)fgets($pipes[1]))!=='READY')throw new RuntimeException('Worker startup failed');}
    foreach($workers as [$process,$pipes]){fwrite($pipes[0],"GO\n");fflush($pipes[0]);}
    $workerIds=[];
    foreach($workers as [$process,$pipes]){
        $result=json_decode((string)fgets($pipes[1]),true,512,JSON_THROW_ON_ERROR);$workerIds[]=$result['id'];
        foreach($pipes as $pipe)fclose($pipe);if(proc_close($process)!==0)throw new RuntimeException('Worker failed');
        workflowCheck(is_bool($result['authenticated']),'concurrent request either retains same trust or fails closed');
    }
    boundaryRequest($verified);workflowCheck(Auth::check(),'same-trust forwarding converges after concurrent requests');
    Auth::logout();boundaryRequest($verified);workflowCheck(!Auth::check(),'logout reached by old ID invalidates forwarded authentication');
    foreach($workerIds as $workerId){boundaryRequest($workerId);workflowCheck(!Auth::check(),'logout invalidates identifiers returned by concurrent requests');}
    workflowLogin(1,true);$revokedOld=session_id();Session::rotate(60);
    Database::execute('UPDATE users SET session_version=session_version+1 WHERE id=1');
    boundaryRequest($revokedOld);workflowCheck(!Auth::check(),'forwarding cannot restore a revoked session');
    workflowLogin(1,true);$_SESSION['__last_activity']=time()-1201;$idle=session_id();
    boundaryRequest($idle);workflowCheck(!Auth::check(),'idle timeout survives a new request');
    workflowLogin(1,false);Session::set('mfa_started_at',time()-601);$expired=session_id();
    boundaryRequest($expired);workflowCheck(!Auth::checkPending2FA()&&!Auth::check(),'MFA challenge expires after 600 seconds');
    $chosen=bin2hex(random_bytes(16));boundaryRequest($chosen);
    workflowCheck(session_id()!==$chosen,'strict mode rejects an uninitialized chosen session ID');
    workflowLogin(1,true);$expiredForward=session_id();Session::rotate(0);$target=session_id();
    // Modify only a session created by this synthetic test to simulate time passage.
    session_write_close();session_id($expiredForward);session_start();$_SESSION['__rotated_at']=time()-61;session_write_close();
    boundaryRequest($expiredForward);workflowCheck(!Auth::check(),'expired forwarding record cannot authorize');
    boundaryRequest($target);workflowCheck(Auth::check(),'expired predecessor does not destroy a separately valid target');
    Session::rotate(60);$releaseSession=session_id();
    boundaryRequest($releaseSession);workflowCheck(Auth::check(),'ordinary release restart retains compatible current session state');
    workflowCheck(ini_get('session.use_strict_mode')==='1'&&ini_get('session.gc_maxlifetime')===(string)config('security.session.lifetime'),'effective isolated PHP session configuration remains aligned');

    // Share only the synthetic SQLite fixture so revocation is visible across processes.
    $pdo->exec('VACUUM INTO '.$pdo->quote($boundaryDirectory.'/fixture.sqlite'));
    $pdo=new PDO('sqlite:'.$boundaryDirectory.'/fixture.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA busy_timeout=5000');
    (new ReflectionProperty(Database::class,'instance'))->setValue(null,$pdo);
    foreach(['logout','revoke'] as $operation) {
        for($iteration=0;$iteration<5;$iteration++) {
            workflowLogin(1,true);$predecessor=session_id();session_write_close();
            $rotator=boundaryWorker('rotate-held',$predecessor);boundarySignal($rotator[1],'LOCKED');
            $other=boundaryWorker($operation,$predecessor);
            boundarySignal($other[1],$operation==='logout'?'ENTERING':'REVOKED');
            fwrite($rotator[1][0],"ROTATE\n");fflush($rotator[1][0]);
            $rotated=boundaryResult($rotator);$completed=boundaryResult($other);
            foreach([$predecessor,$rotated['id'],$completed['id']] as $identifier) {
                boundaryRequest($identifier);
                workflowCheck(!Auth::check(),$operation.' during periodic rotation cannot restore authentication: iteration '.$iteration);
            }
        }
    }
    workflowLogin(1,true);$_SESSION=['__rotation_in_progress'=>true];$interrupted=session_id();
    boundaryRequest($interrupted);workflowCheck(!Auth::check(),'interrupted handoff times out without authorizing a predecessor');
    foreach([false,true] as $cancelHandoff) {
        workflowLogin(1,true);$predecessor=session_id();session_write_close();
        $rotator=boundaryWorker('rotate-paused',$predecessor);boundarySignal($rotator[1],'HANDOFF');
        $logout=boundaryWorker('logout-aware',$predecessor);boundarySignal($logout[1],'ENTERING');boundarySignal($logout[1],'WAITING');
        if($cancelHandoff) $completed=boundaryResult($logout);
        fwrite($rotator[1][0],"RESUME\n");fflush($rotator[1][0]);
        $rotated=boundaryResult($rotator);
        if(!$cancelHandoff) $completed=boundaryResult($logout);
        foreach([$predecessor,$rotated['id'],$completed['id']] as $identifier) {
            boundaryRequest($identifier);
            workflowCheck(!Auth::check(),$cancelHandoff?'cancelled handoff discards target authentication':'logout inside handoff invalidates all reachable authentication');
        }
    }
}finally{workflowFinish();}
