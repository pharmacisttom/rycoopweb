<?php
declare(strict_types=1);
require __DIR__.'/DynamicLayoutFixture.php';
use App\Core\{Database,Session};
use App\Services\{DynamicLayoutService as Layout,LayoutSchema,LayoutImageService as Image,AuditService};

$created=[];
try{
    workflowLogin(1);$draft=Layout::draft();workflowCheck(count($draft['state']['sections'])===8,'all eight legacy homepage blocks are available without seed writes');
    $changes=Database::value('SELECT total_changes()');workflowCheck(Layout::published()===null,'public has no draft fallback exposure');Layout::draft();workflowCheck($changes===Database::value('SELECT total_changes()'),'draft and public GET are write-free');
    $initial=layoutInput();workflowLogin(4);workflowFail(fn()=>Layout::section([...$initial,'title'=>'No','slug'=>'no']),403,'member cannot create layout');
    workflowLogin(2,false);workflowFail(fn()=>Layout::draft(),401,'pending MFA cannot read drafts');workflowLogin(2);
    $root=Layout::section(layoutInput(['title'=>'รูปกิจกรรมของสหกรณ์','slug'=>'activity-gallery','type'=>'gallery','navigation'=>true]));$gallery=$root['item'];
    workflowCheck(Layout::published()===null,'creating gallery leaves public website unchanged');
    workflowFail(fn()=>Layout::section([...$initial,'title'=>'Stale','slug'=>'stale']),409,'stale concurrent edit rejected');
    workflowFail(fn()=>Layout::section(layoutInput(['title'=>'Duplicate','slug'=>'activity-gallery'])),422,'duplicate section slug rejected');
    $child=Layout::section(layoutInput(['title'=>'Child','slug'=>'child','parent_id'=>$gallery['id']]));
    workflowFail(fn()=>Layout::section(layoutInput(['parent_id'=>$child['item']['id']]),$gallery['id']),422,'hierarchy cycles rejected');
    workflowFail(fn()=>Layout::section(layoutInput(),$gallery['id'],'delete'),422,'deleting a parent with children rejected');
    workflowFail(fn()=>Layout::section(layoutInput(['title'=>'Orphan','slug'=>'orphan','parent_id'=>str_repeat('f',32)])),422,'foreign parent rejected');
    $cat=Layout::category(layoutInput(['title'=>'กิจกรรมสมาชิก']))['item'];$nested=Layout::category(layoutInput(['title'=>'อบรม','parent_id'=>$cat['id']]))['item'];
    workflowFail(fn()=>Layout::category(layoutInput(['title'=>'Cycle','parent_id'=>$nested['id']]),$cat['id']),422,'category cycle rejected');
    $album=Layout::saveAlbum(layoutInput(['title'=>'กิจกรรมอบรม','slug'=>'training','activity_date'=>'2026-11-18','category_id'=>$nested['id']]))['item'];$albumId=$album['id'];
    workflowFail(fn()=>Layout::category(layoutInput(),$nested['id'],true),422,'cannot delete referenced category');
    workflowFail(fn()=>Layout::saveAlbum(layoutInput(['title'=>'Bad','slug'=>'bad','activity_date'=>'2026-02-31'])),422,'invalid activity date rejected');
    workflowFail(fn()=>Layout::saveAlbum(layoutInput(['title'=>'Dup','slug'=>'training'])),422,'duplicate album slug rejected');
    workflowFail(fn()=>Layout::section(layoutInput(['title'=>'XSS','slug'=>'xss','type'=>'banner','config'=>['image'=>'javascript:alert(1)']])),422,'unsafe image URL rejected');
    $rich=Layout::section(layoutInput(['title'=>'ข้อความ','slug'=>'rich','type'=>'rich_text','config'=>['html'=>'<p>Hello<script>alert(1)</script><a href="javascript:alert(2)">safe</a></p>']]))['item'];
    workflowCheck(!str_contains($rich['config']['html'],'<script')&&!str_contains($rich['config']['html'],'javascript:'),'stored rich text sanitized by HTMLPurifier');
    foreach(['<?php echo 1; ?>','<svg onload="alert(1)"/>',str_repeat('x',5*1024*1024+1)] as $bad)workflowFail(fn()=>Image::store(bin2hex(random_bytes(16)),$bad,$created),422,'signature/unsupported/size upload rejection');
    workflowFail(fn()=>Image::path('../../secret','full'),422,'image path traversal rejected');
    $canvas=imagecreatetruecolor(2400,1200);ob_start();imagepng($canvas);$bytes=ob_get_clean();imagedestroy($canvas);$image=bin2hex(random_bytes(16));Image::store($image,$bytes.'<?php malicious_tail ?>',$created);
    workflowCheck(getimagesize(Image::path($image,'full'))[0]===1920 && getimagesize(Image::path($image,'thumb'))[0]===480,'decoded full and thumbnail resized');
    workflowCheck(!str_contains(file_get_contents(Image::path($image,'full')),'malicious_tail'),'safe re-encoding removes appended payload and metadata');
    Database::insert('INSERT INTO dynamic_gallery_images(id,album_id,alt,created_by) VALUES(?,?,?,?)',[$image,$albumId,'ภาพกิจกรรม',2]);
    $jpegCanvas=imagecreatetruecolor(30,20);ob_start();imagejpeg($jpegCanvas);$jpeg=ob_get_clean();imagedestroy($jpegCanvas);$metadata="Exif\0\0GPS-fixture-sensitive-location";$jpeg=substr($jpeg,0,2)."\xFF\xE1".pack('n',strlen($metadata)+2).$metadata.substr($jpeg,2);$exifImage=bin2hex(random_bytes(16));Image::store($exifImage,$jpeg,$created);
    workflowCheck(!str_contains(file_get_contents(Image::path($exifImage,'full')),'GPS-fixture-sensitive-location'),'JPEG EXIF metadata stripped by pixel re-encoding');
    workflowFail(fn()=>Layout::imageAllowed($image,999),404,'unpublished image denied to public');
    workflowFail(fn()=>Layout::publish(layoutInput()),403,'editor cannot publish');workflowLogin(3);
    workflowFail(fn()=>Layout::publish(layoutInput()),403,'login MFA alone cannot publish');layoutStep('settings.update');workflowFail(fn()=>Layout::publish(layoutInput()),403,'step-up scope must match layout publication');
    layoutStep();$pub=Layout::publish(layoutInput());workflowCheck(Layout::published()['revision_id']===$pub['revision_id'],'fresh real TOTP permits transactional publication');
    workflowCheck(Layout::publicAlbums(1,$cat['id'])['total']===1,'parent category includes nested album');Layout::imageAllowed($image,$pub['revision_id']);workflowCheck(true,'published image membership grants access');
    workflowCheck(count(Layout::publicAlbums(1,null,'training')['items'][0]['images'])===1,'public album detail snapshot contract');
    workflowFail(fn()=>Layout::publish(layoutInput()),403,'consumed TOTP grant cannot be replayed');
    workflowLogin(2);Layout::saveAlbum(layoutInput(['title'=>'Changed draft']),$albumId);Layout::image($albumId,$image,layoutInput(['alt'=>'Changed draft alt']));
    workflowCheck(Layout::publicAlbums(1,null,'training')['items'][0]['title']==='กิจกรรมอบรม','draft album edit cannot change published snapshot');
    workflowCheck(Layout::publicAlbums(1,null,'training')['items'][0]['images'][0]['alt']==='ภาพกิจกรรม','draft image edit cannot change published metadata');
    workflowFail(fn()=>Layout::orderImages($albumId,layoutInput(['ids'=>[$image,$image]])),422,'duplicate image order rejected');
    Layout::image($albumId,$image,layoutInput(),true);Layout::imageAllowed($image,$pub['revision_id']);workflowCheck(is_file(Image::path($image,'full')),'draft delete preserves historical published bytes');
    $ids=array_column(Layout::draft()['state']['sections'],'id');Layout::reorder(layoutInput(['ids'=>array_reverse($ids)]));workflowCheck(Layout::draft()['state']['sections'][0]['id']===end($ids),'bulk ordering persists');
    workflowFail(fn()=>Layout::reorder(layoutInput(['ids'=>[$ids[0],$ids[0]]])),422,'incomplete and duplicate bulk order rejected');
    workflowLogin(3);$stale=layoutInput();layoutStep();Layout::publish(layoutInput());layoutStep();workflowFail(fn()=>Layout::publish($stale),409,'concurrent publication stale version rejected');
    $before=Layout::published();$version=Layout::draft()['version'];layoutStep();AuditService::setTestFaultInjector(function($m,$a){if($m==='dynamic_layout'&&$a==='publish')throw new RuntimeException('injected publication audit fault');});
    try{Layout::publish(layoutInput());throw new LogicException('fault not raised');}catch(RuntimeException $e){workflowCheck($e->getMessage()==='injected publication audit fault','required audit fault propagated');}finally{AuditService::setTestFaultInjector(null);}
    workflowCheck(Layout::published()===$before&&Layout::draft()['version']===$version,'failed publication rolls back revision pointer and version');
    $draftBefore=Layout::draft()['state'];layoutStep('layout.rollback');$restored=Layout::publish(layoutInput(),$pub['revision_id']);workflowCheck($restored['revision_id']!==$pub['revision_id'],'rollback creates new immutable publication');
    workflowCheck(Layout::publicAlbums(1,null,'training')['items'][0]['title']==='กิจกรรมอบรม','rollback restores album content');workflowCheck(Layout::draft()['state']===$draftBefore,'rollback preserves working draft');
    $changes=Database::value('SELECT total_changes()');Layout::published();Layout::publicAlbums();Layout::imageAllowed($image,$restored['revision_id']);workflowCheck($changes===Database::value('SELECT total_changes()'),'all public reads remain write-free');
    workflowLogin(2);$v=layoutInput();AuditService::setTestFaultInjector(function($m){if($m==='dynamic_layout')throw new RuntimeException('edit fault');});try{Layout::section([...$v,'title'=>'Rollback edit','slug'=>'rollback-edit']);}catch(RuntimeException $e){}finally{AuditService::setTestFaultInjector(null);}
    workflowCheck(Layout::draft()['version']===$v['version'],'draft audit failure rolls back edit');
    for($i=0;$i<21;$i++)Layout::saveAlbum(layoutInput(['title'=>'Pagination fixture '.$i,'slug'=>'pagination-'.$i]));
    workflowCheck(Layout::albums(1)['total']===22&&count(Layout::albums(1)['items'])===20&&count(Layout::albums(2)['items'])===2,'albums paginate beyond first twenty without an album-count cap');
    workflowCheck(Layout::publicAlbums()['total']===1,'unpublished new album pages do not leak to public');
    $router=new \App\Core\Router();require __DIR__.'/../config/dynamic-layout-routes.php';$routes=(new ReflectionProperty($router,'routes'))->getValue($router);
    foreach($routes as $route)if(str_starts_with($route['path'],'/api/admin/v1/layout')){workflowCheck(in_array(\App\Middlewares\AuthMiddleware::class,$route['middlewares'],true),'authenticated route '.$route['method'].' '.$route['path']);if(in_array($route['method'],['POST','PATCH','DELETE']))workflowCheck(in_array(\App\Middlewares\CsrfMiddleware::class,$route['middlewares'],true),'CSRF route '.$route['method'].' '.$route['path']);}
}finally{Image::cleanup($created);workflowFinish();}
