<?php
declare(strict_types=1);
function storage_upload_path(?string $value = ''): string {
    return __DIR__.'/../storage/tmp/gallery-access-'.getmypid().'/'.($value ?? '');
}
require __DIR__.'/WorkflowFixture.php';
use App\Core\{Auth, Request, Router};
use App\Controllers\Api\NewsGalleryImageController;
$router = new Router(); require __DIR__.'/../config/routes.php';
$filename = str_repeat('a',32).'-src.webp';
$directory = storage_upload_path('news/gallery');
mkdir($directory,0700,true);
$image = imagecreatetruecolor(2,2); imagewebp($image,$directory.'/'.$filename); imagedestroy($image);
$pdo->exec("CREATE TABLE news(id INTEGER PRIMARY KEY,gallery_images TEXT,deleted_at TEXT);
INSERT INTO permissions VALUES(1,'news','view'); INSERT INTO role_permissions VALUES(2,1);");
$pdo->prepare('INSERT INTO news VALUES(1,?,NULL)')->execute([json_encode([['src'=>'/storage/uploads/news/gallery/'.$filename]])]);
function galleryDispatch(Router $router,string $filename): array {
    $_SERVER=['REQUEST_METHOD'=>'GET','REQUEST_URI'=>'/api/admin/news/1/gallery/'.$filename,'SCRIPT_NAME'=>'/index.php','HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest'];
    $_GET=$_POST=[]; $response=new WorkflowResponse(); http_response_code(200);
    ob_start(); $router->dispatch(new Request(),$response); $body=ob_get_clean();
    return [$response,$body,http_response_code()];
}
try {
    Auth::logout(); [$response,$body]=galleryDispatch($router,$filename);
    workflowCheck($response->status===401&&$body==='', 'gallery route rejects unauthenticated requests without image bytes');
    workflowLogin(1,false,true); [$response,$body]=galleryDispatch($router,$filename);
    workflowCheck($response->status===403&&$body==='', 'gallery route requires MFA enrollment completion');
    workflowLogin(4); [$response,$body]=galleryDispatch($router,$filename);
    workflowCheck($response->status===403&&$body==='', 'verified user without news.view cannot read gallery');
    http_response_code(200); ob_start();
    (new NewsGalleryImageController(new Request(),new WorkflowResponse()))->admin('1',$filename);
    workflowCheck(ob_get_clean()===''&&http_response_code()===403,'controller retains independent permission enforcement');
    foreach([1,2] as $id) {
        workflowLogin($id); [$response,$body,$status]=galleryDispatch($router,$filename);
        workflowCheck($status===200&&$body===file_get_contents($directory.'/'.$filename),'verified authorized identity reads actual gallery bytes: fixture '.$id);
    }
    [$response,$body,$status]=galleryDispatch($router,str_repeat('b',32).'-src.webp');
    workflowCheck($status===404&&$body==='', 'authorized user cannot read an image outside article membership');
} finally {
    unlink($directory.'/'.$filename); rmdir($directory); rmdir(dirname($directory)); rmdir(dirname($directory,2));
    workflowFinish();
}
