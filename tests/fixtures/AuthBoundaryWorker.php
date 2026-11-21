<?php
declare(strict_types=1);
$input=json_decode((string)fgets(STDIN),true,512,JSON_THROW_ON_ERROR);
$allowed=realpath(dirname(__DIR__,2).'/storage/tmp');$directory=realpath($input['directory']);
if(!$allowed||!$directory||!str_starts_with($directory,$allowed.DIRECTORY_SEPARATOR)||!preg_match('/^auth-boundary-[a-f0-9]+$/',basename($directory)))exit(2);
require dirname(__DIR__).'/WorkflowFixture.php';
putenv('SESSION_SAVE_PATH='.$directory);
if (!empty($input['sharedDatabase'])) {
    $pdo = new PDO('sqlite:'.$directory.'/fixture.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA busy_timeout=5000');
    (new ReflectionProperty(\App\Core\Database::class,'instance'))->setValue(null,$pdo);
}
// Test-only barriers expose no identifiers and force the native file-handler handoff window.
if (in_array($input['operation'],['rotate-paused','logout-aware'],true)) {
    class BoundaryBarrierHandler extends SessionHandler implements SessionUpdateTimestampHandlerInterface {
        private bool $markerWritten=false;
        private bool $reported=false;
        public function validateId(string $id): bool {
            clearstatcache(true,$GLOBALS['directory'].'/sess_'.$id);
            return preg_match('/^[a-zA-Z0-9,-]{16,256}$/D',$id)===1
                && is_file($GLOBALS['directory'].'/sess_'.$id);
        }
        public function updateTimestamp(string $id,string $data): bool { return parent::write($id,$data); }
        public function read(string $id): string|false {
            $data=parent::read($id);
            if (!$this->reported && ($GLOBALS['input']['operation']==='logout-aware') && is_string($data) && str_contains($data,'__rotation_in_progress')) {
                $this->reported=true; fwrite(STDOUT,"WAITING\n");fflush(STDOUT);
            }
            return $data;
        }
        public function write(string $id,string $data): bool {
            if(str_contains($data,'__rotation_in_progress')) $this->markerWritten=true;
            elseif($this->markerWritten&&!$this->reported&&str_contains($data,'user_id')) {
                $this->reported=true; fwrite(STDOUT,"HANDOFF\n");fflush(STDOUT);
                if(trim((string)fgets(STDIN))!=='RESUME')throw new RuntimeException('Missing handoff resume');
            }
            return parent::write($id,$data);
        }
    }
    session_set_save_handler(new BoundaryBarrierHandler(),true);
}
fwrite(STDOUT,"READY\n");fflush(STDOUT);
if(trim((string)fgets(STDIN))!=='GO')exit(3);
if ($input['operation']==='revoke') {
    \App\Core\Database::execute('UPDATE users SET session_version=session_version+1 WHERE id=1');
    fwrite(STDOUT,"REVOKED\n");fflush(STDOUT);
} elseif (in_array($input['operation'],['logout','logout-aware'],true)) {
    fwrite(STDOUT,"ENTERING\n");fflush(STDOUT);
}
session_id($input['id']);\App\Core\Session::start();
if ($input['operation']==='rotate-held') {
    fwrite(STDOUT,"LOCKED\n");fflush(STDOUT);
    if(trim((string)fgets(STDIN))!=='ROTATE')exit(4);
    \App\Core\Session::rotate(60);
}
if(in_array($input['operation'],['rotate','rotate-paused'],true))\App\Core\Session::rotate(60);
if(in_array($input['operation'],['logout','logout-aware'],true))\App\Core\Auth::logout();
$result=['authenticated'=>\App\Core\Auth::check(),'id'=>session_id()];
session_write_close();ob_end_clean();echo json_encode($result)."\n";
