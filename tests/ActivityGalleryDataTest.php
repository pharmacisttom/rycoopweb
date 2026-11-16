<?php
declare(strict_types=1);
// Disposable SQLite fixture only: NO network, HTTP upload, app DB or migration writes.
ob_start();
require __DIR__.'/../vendor/autoload.php';
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
(new ReflectionProperty(\App\Core\Database::class,'instance'))->setValue(null,$pdo);
$pdo->exec("CREATE TABLE activity_albums (id INTEGER PRIMARY KEY,title TEXT,description TEXT,event_date TEXT,location TEXT,status TEXT,cover_photo_id INTEGER,deleted_at TEXT);
CREATE TABLE activity_photos (id INTEGER PRIMARY KEY,album_id INTEGER,path TEXT,caption TEXT,sort_order INTEGER);
INSERT INTO activity_albums VALUES (38,'Draft activity','Hidden','2026-11-16','TEST','draft',NULL,NULL);
INSERT INTO activity_albums VALUES (39,'Public activity','Visible','2026-11-15','TEST','published',2,NULL);
INSERT INTO activity_albums VALUES (40,'Archived activity','Hidden','2026-11-14','TEST','published',NULL,'2026-11-16');
INSERT INTO activity_photos VALUES (1,38,'private1.jpg','Draft photo',1);
INSERT INTO activity_photos VALUES (2,39,'private2.jpg','Published photo',1);
INSERT INTO activity_photos VALUES (3,40,'private3.jpg','Archived photo',1);");
class ActivityResponse extends \App\Core\Response {
    public int $status=200;
    public array $payload=[];
    public function json(array $data,int $status=200):void{$this->status=$status;$this->payload=$data;}
}
function checkActivity(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo "PASS: $message\n";}
$_SERVER['REQUEST_METHOD']='GET';
$response=new ActivityResponse();
$controller=new \App\Controllers\Api\ActivityGalleryController(new \App\Core\Request(),$response);
$before=(int)$pdo->query('SELECT total_changes()')->fetchColumn();
$controller->publicAlbums();
checkActivity($response->status===200,'public album list succeeds');
checkActivity(count($response->payload['data'])===1 && (int)$response->payload['data'][0]['id']===39,'draft and archived albums hidden from public list');
$controller->publicAlbum('38');
checkActivity($response->status===404,'draft album detail hidden');
$controller->publicAlbum('40');
checkActivity($response->status===404,'archived album detail hidden');
$controller->publicAlbum('39');
checkActivity($response->status===200 && count($response->payload['data']['photos'])===1,'published album displays photo list');
checkActivity(!isset($response->payload['data']['photos'][0]['path']),'public photo payload does not reveal private path');
checkActivity(str_contains($response->payload['data']['photos'][0]['url'],'/api/public/activities/39/photos/2'),'public URL uses status-gated photo controller');
checkActivity((int)$pdo->query('SELECT total_changes()')->fetchColumn()===$before,'public listing and detail produce zero writes');
(new ReflectionProperty(\App\Core\Database::class,'instance'))->setValue(null,null);
