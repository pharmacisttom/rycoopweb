<?php
declare(strict_types=1);
// Run before Composer/bootstrap so a production .env cannot silently select a DB.
$database=getenv('DB_DATABASE')?:'';
if (getenv('APP_ENV')!=='testing' || !preg_match('/^(?:rycoop_test_[a-z0-9_]+|rayongcoop_ci)$/D',$database)) {
    throw new RuntimeException('Database tests require APP_ENV=testing and an explicitly isolated DB_DATABASE (rycoop_test_* or rayongcoop_ci). No database connection was opened.');
}
