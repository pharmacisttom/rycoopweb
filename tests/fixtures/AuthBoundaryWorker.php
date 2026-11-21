<?php
declare(strict_types=1);
$input=json_decode((string)fgets(STDIN),true,512,JSON_THROW_ON_ERROR);
$allowed=realpath(dirname(__DIR__,2).'/storage/tmp');$directory=realpath($input['directory']);
if(!$allowed||!$directory||!str_starts_with($directory,$allowed.DIRECTORY_SEPARATOR)||!preg_match('/^auth-boundary-[a-f0-9]+$/',basename($directory)))exit(2);
require dirname(__DIR__).'/WorkflowFixture.php';
putenv('SESSION_SAVE_PATH='.$directory);
fwrite(STDOUT,"READY\n");fflush(STDOUT);
if(trim((string)fgets(STDIN))!=='GO')exit(3);
session_id($input['id']);\App\Core\Session::start();
if($input['operation']==='rotate')\App\Core\Session::rotate(60);
$result=['authenticated'=>\App\Core\Auth::check(),'id'=>session_id()];
session_write_close();ob_end_clean();echo json_encode($result)."\n";
