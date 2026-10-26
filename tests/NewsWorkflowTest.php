<?php
declare(strict_types=1);
require __DIR__.'/WorkflowFixture.php';
use App\Core\Database;
use App\Services\NewsWorkflowService as News;
$pdo->exec("CREATE TABLE news_categories(id INTEGER PRIMARY KEY,name TEXT,status TEXT);INSERT INTO news_categories VALUES(1,'ข่าวประชาสัมพันธ์','active');
CREATE TABLE news(id INTEGER PRIMARY KEY,category_id INTEGER,title TEXT,slug TEXT,summary TEXT,content TEXT,cover_image TEXT,workflow_status TEXT,author_id INTEGER,created_by INTEGER,updated_by INTEGER,publish_at TEXT,created_at TEXT,updated_at TEXT,deleted_at TEXT);
INSERT INTO permissions VALUES(1,'news','view'),(2,'news','create'),(3,'news','update'),(4,'news','publish');INSERT INTO role_permissions VALUES(2,1),(2,2),(2,3),(3,1),(3,4);");
$input=['title'=>'Synthetic news','excerpt'=>'Fixture','content'=>'<p>Fixture news only</p>','category'=>'ข่าวประชาสัมพันธ์'];
try {
 workflowLogin(4);workflowFail(fn()=>News::listing(),403,'member cannot read admin news');
 workflowLogin(2);$row=News::save($input,'/assets/img/news_placeholder.jpg');workflowCheck($row['workflow_status']==='draft'&&$row['publish_at']===null,'creation is draft without public date');
 workflowFail(fn()=>News::publish((int)$row['id'],['version'=>$row['version']]),403,'editor cannot publish');
 workflowFail(fn()=>News::save([...$input,'workflow_status'=>'published'],'/assets/img/news_placeholder.jpg'),422,'editor cannot bypass review with input status');
 $row=News::save([...$input,'version'=>$row['version'],'workflow_status'=>'submitted'],'/assets/img/news_placeholder.jpg',(int)$row['id']);workflowCheck($row['workflow_status']==='submitted','editor submits draft for review');
 workflowLogin(3);workflowFail(fn()=>News::save($input,''),403,'publisher cannot edit without permission');workflowFail(fn()=>News::publish((int)$row['id'],['version'=>'stale']),409,'stale preview cannot publish');
 News::publish((int)$row['id'],['version'=>$row['version']]);workflowCheck(Database::value('SELECT workflow_status FROM news WHERE id=?',[$row['id']])==='published','publisher publishes submitted news');
 workflowFail(fn()=>News::publish((int)$row['id'],['version'=>$row['version']]),409,'repeat publish denied');
 workflowLogin(2);$current=News::listing()[0];$draft=News::save([...$input,'version'=>$current['version']],'',(int)$row['id']);workflowCheck($draft['workflow_status']==='draft','editing published news requires review again');
 $submitted=News::save([...$input,'version'=>$draft['version'],'workflow_status'=>'submitted'],'',(int)$row['id']);$pdo->exec('DROP TABLE audit_logs');workflowLogin(3);try{News::publish((int)$row['id'],['version'=>$submitted['version']]);throw new RuntimeException('expected audit failure');}catch(PDOException $e){}
 workflowCheck(Database::value('SELECT workflow_status FROM news WHERE id=?',[$row['id']])==='submitted','audit failure rolls back publication');
}finally{workflowFinish();}
