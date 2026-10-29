<?php
declare(strict_types=1);
// Isolated in-memory database and a dedicated temporary upload root: no real uploads touched.
function storage_upload_path(?string $value = ''): string {
    return __DIR__.'/../storage/tmp/gallery-test-'.getmypid().'/'.($value ?? '');
}
require __DIR__.'/WorkflowFixture.php';
use App\Core\Database;
use App\Services\NewsGalleryService as Gallery;
use App\Services\NewsWorkflowService as News;
$pdo->exec("CREATE TABLE news_categories(id INTEGER PRIMARY KEY,name TEXT,status TEXT);
INSERT INTO news_categories VALUES(1,'ข่าวประชาสัมพันธ์','active');
CREATE TABLE news(id INTEGER PRIMARY KEY,category_id INTEGER,title TEXT,slug TEXT,summary TEXT,content TEXT,cover_image TEXT,gallery_images TEXT,is_pinned INTEGER DEFAULT 0,workflow_status TEXT,author_id INTEGER,created_by INTEGER,updated_by INTEGER,publish_at TEXT,created_at TEXT,updated_at TEXT,deleted_at TEXT);
INSERT INTO permissions VALUES(1,'news','view'),(2,'news','create'),(3,'news','update'),(4,'news','publish');
INSERT INTO role_permissions VALUES(2,1),(2,2),(2,3),(3,1),(3,4);");
$input=['title'=>'Synthetic gallery','content'=>'<p>Synthetic activity only</p>','category'=>'ข่าวประชาสัมพันธ์'];
$canvas=imagecreatetruecolor(2400,1200);imagefill($canvas,0,0,imagecolorallocate($canvas,20,80,160));
ob_start();imagepng($canvas);$binary=ob_get_clean();imagedestroy($canvas);
$upload=['src'=>'data:image/png;base64,'.base64_encode($binary.'<?php synthetic_payload ?>'),'alt'=>'Synthetic alt','caption'=>'Synthetic caption'];
$created=[];
try {
    workflowLogin(4);workflowFail(fn()=>News::save([...$input,'gallery_images'=>[$upload]],null),403,'member cannot upload activity images');
    workflowLogin(2);
    foreach (['data:image/png;base64,'.base64_encode('<svg/>'),'/storage/uploads/news/gallery/other-user.webp','../../secret.php','https://external.invalid/image.jpg'] as $invalid) {
        workflowFail(fn()=>News::save([...$input,'gallery_images'=>[['src'=>$invalid]]],null),422,'reject forged, unsupported or foreign image reference');
    }
    workflowFail(fn()=>News::save([...$input,'gallery_images'=>array_fill(0,21,$upload)],null),422,'reject more than 20 images');
    $oversized=['src'=>'data:image/png;base64,'.base64_encode(str_repeat('x',5*1024*1024+1))];
    workflowFail(fn()=>News::save([...$input,'gallery_images'=>[$oversized]],null),422,'reject file above 5 MiB');
    $large=['src'=>'data:image/png;base64,'.base64_encode(str_pad($binary,4500000,'x'))];
    workflowFail(fn()=>News::save([...$input,'gallery_images'=>[$large,$large,$large]],null),422,'reject aggregate upload above 12 MiB');
    workflowCheck((glob(storage_upload_path('news/gallery').'/*')?:[])===[],'aggregate rejection cleans earlier files from same save');
    $row=News::save([...$input,'gallery_images'=>[$upload]],null);$images=Gallery::decode($row['gallery_images']);
    $created=glob(storage_upload_path('news/gallery').'/*')?:[];
    workflowCheck(count($created)===2&&count($images)===1,'creates display image and thumbnail');
    $file=storage_upload_path('news/gallery/'.basename($images[0]['src']));
    $size=getimagesize($file);workflowCheck($size[0]===1920&&$size[1]===960&&$size['mime']==='image/webp','resizes image preserving aspect ratio');
    workflowCheck(!str_contains(file_get_contents($file),'synthetic_payload'),'re-encoding strips appended content and metadata');
    workflowCheck($row['cover_image']===$images[0]['src']&&$row['workflow_status']==='draft','first image is cover and upload stays draft');
    $row=News::save([...$input,'gallery_images'=>[...$images,$upload],'version'=>$row['version']],null,(int)$row['id']);
    $images=Gallery::decode($row['gallery_images']);
    $row=News::save([...$input,'gallery_images'=>array_reverse($images),'version'=>$row['version'],'workflow_status'=>'submitted'],null,(int)$row['id']);
    workflowCheck($row['cover_image']===$images[1]['src'],'reordering changes cover');
    $response=new WorkflowResponse();$public=new \App\Controllers\Api\SpaApiController(workflowRequest(),$response);
    $public->news();workflowCheck($response->status===200&&$response->data['data']===[],'public API hides submitted albums');
    workflowLogin(3);News::publish((int)$row['id'],['version'=>$row['version']]);
    workflowCheck(Database::value('SELECT workflow_status FROM news WHERE id=?',[$row['id']])==='published','publisher can publish reviewed album');
    $public->news();workflowCheck(count($response->data['data'])===1&&count($response->data['data'][0]['gallery_images'])===2,'public API returns published album captions and images');
    Database::execute("UPDATE news SET publish_at='2999-01-01' WHERE id=?",[$row['id']]);
    $public->news();workflowCheck($response->data['data']===[],'public API hides future publication');
    Database::execute("UPDATE news SET publish_at=CURRENT_TIMESTAMP,deleted_at=CURRENT_TIMESTAMP WHERE id=?",[$row['id']]);
    $public->news();workflowCheck($response->data['data']===[],'public API hides deleted albums');
    Database::execute('UPDATE news SET deleted_at=NULL WHERE id=?',[$row['id']]);
    workflowLogin(2);$current=News::listing()[0];
    $row=News::save([...$input,'gallery_images'=>[],'version'=>$current['version']],null,(int)$row['id']);
    workflowCheck(Gallery::decode($row['gallery_images'])===[]&&$row['workflow_status']==='draft'&&is_file($file),'removal withdraws publication and preserves shared files');
    workflowCheck(Gallery::decode('["news/legacy.jpg"]')[0]['src']==='/storage/uploads/news/legacy.jpg','legacy string gallery remains readable');
    $before=glob(storage_upload_path('news/gallery').'/*')?:[];
    $pdo->exec('DROP TABLE audit_logs');
    try { News::save([...$input,'gallery_images'=>[$upload]],null);throw new RuntimeException('Expected audit failure'); } catch(PDOException $e) {}
    workflowCheck((glob(storage_upload_path('news/gallery').'/*')?:[])===$before,'failed transaction removes only its newly created files');
    workflowCheck((int)Database::value('SELECT COUNT(*) FROM news')===1,'failed audit rolls back article');
} finally {
    Gallery::cleanup(glob(storage_upload_path('news/gallery').'/*')?:[]);
    foreach ([storage_upload_path('news/gallery'),storage_upload_path('news'),rtrim(storage_upload_path(),'/')] as $directory) if(is_dir($directory))rmdir($directory);
    workflowFinish();
}
