<?php
declare(strict_types=1);

// Real MySQL 8 integration test. Refuse live TEST and Production databases.
if (getenv('APP_ENV') !== 'testing'
    || getenv('GITHUB_ACTIONS') !== 'true'
    || getenv('CRUD_DISPOSABLE_MYSQL') !== '1'
    || getenv('DB_DATABASE') !== 'rayongcoop_ci'
    || !in_array(getenv('DB_HOST'), ['127.0.0.1', 'localhost'], true)
    || getenv('DB_USERNAME') !== 'rycoop_ci_app') {
    fwrite(STDERR, "REFUSED: disposable CI MySQL 8 only.\n");
    exit(2);
}

require dirname(__DIR__).'/vendor/autoload.php';

use App\Core\Database;
use App\Services\HomepageLayoutService as Layout;
use App\Services\SiteNavigationCopyService as Copy;

function assertLayout(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
    echo "PASS: $message\n";
}

$db = (string) Database::value('SELECT DATABASE()');
if ($db !== 'rayongcoop_ci') throw new RuntimeException('Wrong MySQL database');

$keys = [
    'site_layout_home_v1',
    'site_layout_home_draft_v1',
    'site_navigation_copy_v1',
    'site_navigation_copy_draft_v1',
];
$before = [];
foreach ($keys as $key) {
    $before[$key] = Database::first('SELECT * FROM site_settings WHERE `key`=?', [$key]);
}

try {
    foreach ($keys as $key) Database::execute('DELETE FROM site_settings WHERE `key`=?', [$key]);

    $baseline = Layout::published();
    assertLayout(count($baseline) === 6 && !in_array(false, $baseline, true), 'layout defaults show all sections');

    $initial = Layout::adminState();
    $draft = $baseline;
    $draft['welfare'] = false;
    $saved = Layout::saveDraft(['sections'=>$draft,'version'=>$initial['version']]);
    assertLayout($saved['draft']['welfare'] === false, 'layout draft persists on MySQL');
    assertLayout(Layout::published()['welfare'] === true, 'draft does not modify public layout');

    try {
        Layout::saveDraft(['sections'=>$draft,'version'=>$initial['version']]);
        throw new RuntimeException('Stale layout save was accepted');
    } catch (RuntimeException $e) {
        if ($e->getCode() !== 409) throw $e;
    }
    assertLayout(true, 'stale layout edit rejected');

    Layout::publish(['version'=>$saved['version']]);
    assertLayout(Layout::published()['welfare'] === false, 'publish activates homepage visibility');

    $nav = Copy::defaults();
    $state = Copy::state();
    assertLayout(isset($nav['board_desc']) && str_contains($nav['board_desc'], '{term}'), 'board description has dynamic term placeholder');

    $nav['board_desc'] = 'ทดสอบคณะกรรมการ {term}';
    $savedNav = Copy::draft(['values'=>$nav,'version'=>$state['version']]);
    assertLayout(Copy::published()['board_desc'] !== $nav['board_desc'], 'navigation draft remains private');
    Copy::publish(['version'=>$savedNav['version']]);
    assertLayout(Copy::published()['board_desc'] === $nav['board_desc'], 'navigation copy published to MySQL');

    $auditCount = (int) Database::value("SELECT COUNT(*) FROM audit_logs WHERE module IN ('site_layout','site_copy')");
    assertLayout($auditCount >= 4, 'layout/navigation audit records persist');

    $bad = $nav;
    $bad['unregistered_external_link'] = 'https://invalid.example';
    try {
        Copy::draft(['values'=>$bad,'version'=>Copy::state()['version']]);
        throw new RuntimeException('Unknown nav key accepted');
    } catch (InvalidArgumentException $e) {
        assertLayout(true, 'arbitrary navigation fields rejected');
    }
} finally {
    // Disposable CI only: restore settings for independent integration tests.
    foreach ($keys as $key) {
        Database::execute('DELETE FROM site_settings WHERE `key`=?', [$key]);
        $row = $before[$key];
        if ($row) {
            Database::execute(
                'INSERT INTO site_settings (`key`,`value`,`group`,is_public,created_at,updated_at) VALUES (?,?,?,?,?,?)',
                [$row['key'],$row['value'],$row['group'],$row['is_public'],$row['created_at'],$row['updated_at']]
            );
        }
    }
}
echo "PASS: real disposable MySQL 8 CMS round-trip complete\n";
