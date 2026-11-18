<?php
declare(strict_types=1);
require __DIR__.'/WorkflowFixture.php';
use App\Core\Database;

$migration=file_get_contents(__DIR__.'/../database/migrations/032_dynamic_layout_gallery.sql');
preg_match_all('/CREATE TABLE IF NOT EXISTS ([a-z_]+) \((.*?)\) ENGINE=InnoDB[^;]*;/s',$migration,$matches,PREG_SET_ORDER);
foreach($matches as $table){$sql=$table[2];$sql=preg_replace('/BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY/','INTEGER PRIMARY KEY AUTOINCREMENT',$sql);$sql=preg_replace('/BIGINT UNSIGNED|INT UNSIGNED|TINYINT\(1\)/','INTEGER',$sql);$sql=preg_replace('/^\s*(?:UNIQUE KEY|KEY|CONSTRAINT)[^\n]*\n?/m','',$sql);$sql=preg_replace('/,\s*$/','',$sql);$pdo->exec('CREATE TABLE '.$table[1].'('.$sql.')');}
$pdo->exec("CREATE UNIQUE INDEX uq_dynamic_album_slug ON dynamic_gallery_albums(slug)");
Database::insert('INSERT INTO dynamic_layout_state(id,draft_json) VALUES(1,?)',['{"sections":[],"categories":[],"initialized":false}']);
$pdo->exec("INSERT INTO permissions VALUES(901,'layout','view'),(902,'layout','update'),(903,'layout','publish');INSERT INTO role_permissions VALUES(2,901),(2,902),(3,901),(3,903);");
function layoutInput(array $data=[]): array {return ['version'=>\App\Services\DynamicLayoutService::draft()['version'],...$data];}
function layoutStep(string $scope='layout.publish'): void {
    $secret='JBSWY3DPEHPK3PXP';Database::execute("UPDATE users SET two_factor_enabled=1,two_factor_confirmed_at=CURRENT_TIMESTAMP,two_factor_secret=?,two_factor_last_used_slice=NULL WHERE id=?",[$secret,\App\Core\Auth::id()]);
    $code=(new ReflectionMethod(\App\Services\TwoFactorService::class,'calculateCode'))->invoke(null,$secret,(int)floor(time()/30));
    \App\Services\DualControlSecurityService::verifyAndRecordStepUpMfa((int)\App\Core\Auth::id(),$code,$scope);
}
