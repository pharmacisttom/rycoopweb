<?php
declare(strict_types=1);

/**
 * Activity UI/API consistency gate.
 * Pure source/route validation: no database, credentials or production HTTP requests.
 */
require __DIR__ . '/../vendor/autoload.php';

function assertContract(bool $ok, string $reason): void {
    if (!$ok) {
        fwrite(STDERR, "FAIL: $reason\n");
        exit(1);
    }
    echo "PASS: $reason\n";
}
$ui = file_get_contents(__DIR__ . '/../src/pages/AdminActivitiesPage.jsx');
$public = file_get_contents(__DIR__ . '/../src/pages/ActivitiesPage.jsx');
$helper = file_get_contents(__DIR__ . '/../src/services/memberAdminApi.js');
$controller = file_get_contents(__DIR__ . '/../app/Controllers/Api/ActivityGalleryController.php');
$migration = file_get_contents(__DIR__ . '/../database/migrations/031_create_activity_photo_albums.sql');
foreach ([$ui, $public, $helper, $controller, $migration] as $source) {
    assertContract(is_string($source), 'Source file is readable');
}

$router = new \App\Core\Router();
require __DIR__ . '/../config/routes.php';
$routes = (new ReflectionProperty(\App\Core\Router::class, 'routes'))->getValue($router);

$cases = [
    ['GET','/api/admin/activities','adminAlbums','/api/admin/activities',null],
    ['GET','/api/admin/activities/{id}','adminAlbum','/api/admin/activities/','view'],
    ['POST','/api/admin/activities','create',"memberAdminApi('/api/admin/activities', { data: form })",'create'],
    ['POST','/api/admin/activities/{id}/update','update','/update','update'],
    ['POST','/api/admin/activities/{id}/photos','uploadPhoto','/photos','upload'],
    ['POST','/api/admin/activities/{id}/publish','publish','/publish','publish'],
    ['POST','/api/admin/activities/{id}/photos/{photo}/cover','cover','/cover','cover'],
    ['POST','/api/admin/activities/{id}/photos/{photo}/caption','caption','/caption','caption'],
    ['POST','/api/admin/activities/{id}/photos/{photo}/remove','removePhoto','/remove','remove'],
    ['POST','/api/admin/activities/{id}/reorder','reorder','/reorder','reorder'],
    ['POST','/api/admin/activities/{id}/archive','archive','/archive','archive'],
    ['GET','/api/admin/activities/{album}/photos/{photo}','adminPhoto','?size=thumb','image'],
    ['GET','/api/public/activities','publicAlbums','/api/public/activities','public list'],
    ['GET','/api/public/activities/{id}','publicAlbum','/api/public/activities/','public detail'],
    ['GET','/api/public/activities/{album}/photos/{photo}','photo','/api/public/activities/','public photo'],
];

foreach ($cases as [$method, $route, $handler, $uiToken, $kind]) {
    $matches = array_values(array_filter($routes, static fn (array $r):bool =>
        $r['method'] === $method && $r['path'] === $route
    ));
    assertContract(count($matches) === 1, "$method $route is registered once");
    assertContract(str_contains((string)$matches[0]['handler'], "ActivityGalleryController@$handler"), "Handler $handler matches route");
    assertContract(str_contains($controller, "function $handler("), "Backend implements $handler");
    $frontend = str_starts_with($route,'/api/public/') ? $public : $ui;
    assertContract(str_contains($frontend,$uiToken), "Frontend references $handler");
    if (str_starts_with($route, '/api/admin/')) {
        assertContract(in_array(\App\Middlewares\AuthMiddleware::class, $matches[0]['middlewares'], true), "$handler requires auth");
        $hasRbac = count(array_filter($matches[0]['middlewares'],static fn ($x):bool =>
            $x instanceof \App\Middlewares\PermissionMiddleware
        )) > 0;
        assertContract($hasRbac, "$handler requires RBAC");
        if ($method === 'POST') {
            assertContract(in_array(\App\Middlewares\CsrfMiddleware::class,$matches[0]['middlewares'],true), "$handler requires CSRF");
        }
    }
}
foreach (['activity_albums','activity_photos','cover_photo_id','sort_order','deleted_at'] as $column) {
    assertContract(str_contains($migration,$column), "Migration supplies $column");
}
assertContract(str_contains($helper,"credentials: 'include'"),'Frontend sends authenticated cookies');
assertContract(str_contains($helper,"X-CSRF-TOKEN"),'Frontend supports CSRF token');
assertContract(str_contains($helper,'data instanceof FormData'),'Frontend supports multi-file upload requests');
assertContract(str_contains($ui,"suffix !== '/archive'"),'Archive does not fetch deleted detail');
assertContract(str_contains($ui,'if (archived)'),'Failed archive preserves UI state');
assertContract(str_contains($ui,"await chooseAlbum({ id: selected.id })"),'Edit re-reads persisted album');
assertContract(str_contains($controller,"status='published'"),'Public albums filtered by published state');
echo "ACTIVITY_CRUD_CONTRACT=PASS (structural only; TEST HTTP/MySQL still required)\n";
