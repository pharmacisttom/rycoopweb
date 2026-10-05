#!/usr/bin/env php
<?php
declare(strict_types=1);
require_once __DIR__.'/../vendor/autoload.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

use App\Core\Database;
use App\Services\MemberSystemHealth;

try {
    $health=MemberSystemHealth::inspect();
    $health['php_version']=PHP_VERSION;
    $health['database_version']=Database::value('SELECT VERSION()');
    $health['counts']=[];
    $columns=MemberSystemHealth::columns();
    foreach(['members','member_dividends','member_import_runs','member_data_changes'] as $table) {
        $health['counts'][$table]=isset($columns[$table])?(int)Database::value('SELECT COUNT(*) FROM `'.$table.'`'):null;
    }
    if(isset($columns['member_dividends']) && in_array('details_json',$columns['member_dividends'],true)) {
        $invalid=(int)Database::value('SELECT COUNT(*) FROM member_dividends WHERE JSON_VALID(details_json)=0');
        $health['invalid_dividend_json']=$invalid;
        if($invalid)$health['ready']=false;
    }
    $health['storage_writable']=[];
    foreach(['logs','sessions','private'] as $directory)$health['storage_writable'][$directory]=is_writable(__DIR__.'/../storage/'.$directory);
    $health['next_step']=$health['ready']?'Schema and extensions ready. Verify the authenticated API and PHP-FPM logs.':'Back up first, run php bin/console db:migrate, resolve listed missing fields/extensions, then recheck. Never run db:seed or reset passwords.';
    echo json_encode($health,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
    exit($health['ready']?0:1);
} catch(Throwable $e) {
    App\Core\Logger::error('Member system CLI diagnostic: '.$e->getMessage());
    fwrite(STDERR,"Cannot complete checks. See storage/logs/error.log. No member data or passwords have been changed.\n");exit(1);
}
