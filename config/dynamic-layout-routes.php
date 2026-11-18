<?php
declare(strict_types=1);
use App\Middlewares\{AuthMiddleware,CsrfMiddleware,PermissionMiddleware,RateLimitMiddleware};
use App\Core\Router;

$router->get('/api/public/v1/layout','Api\\DynamicLayoutPublicController@layout');
$router->get('/api/public/v1/layout/albums','Api\\DynamicLayoutPublicController@albums');
$router->get('/api/public/v1/layout/albums/{slug}','Api\\DynamicLayoutPublicController@album');
$router->get('/api/public/v1/layout/images/{revision}/{image}/{size}','Api\\DynamicLayoutPublicController@file');
$router->group(['prefix'=>'api/admin/v1/layout','middleware'=>[AuthMiddleware::class,new PermissionMiddleware('layout','view')]],function(Router $r):void{
    foreach(['/draft','/preview','/sections'] as $path)$r->get($path,'Admin\\DynamicLayoutController@draft');
    $r->get('/sections/{id}','Admin\\DynamicLayoutController@show');
    $r->get('/gallery/albums','Admin\\DynamicLayoutController@albums');
    $r->get('/gallery/albums/{id}','Admin\\DynamicLayoutController@album');
    $r->get('/images/{image}/{size}','Admin\\DynamicLayoutController@file');
    $write=[new PermissionMiddleware('layout','update'),CsrfMiddleware::class];
    $r->post('/draft','Admin\\DynamicLayoutController@saveDraft',$write);
    $r->post('/sections','Admin\\DynamicLayoutController@create',$write);
    $r->post('/sections/{id}/subsections','Admin\\DynamicLayoutController@subsection',$write);
    $r->patch('/sections/reorder','Admin\\DynamicLayoutController@reorder',$write);
    $r->patch('/sections/{id}','Admin\\DynamicLayoutController@update',$write);
    $r->delete('/sections/{id}','Admin\\DynamicLayoutController@delete',$write);
    $r->post('/categories','Admin\\DynamicLayoutController@category',$write);
    $r->patch('/categories/{id}','Admin\\DynamicLayoutController@updateCategory',$write);
    $r->delete('/categories/{id}','Admin\\DynamicLayoutController@deleteCategory',$write);
    $r->post('/gallery/albums','Admin\\DynamicLayoutController@createAlbum',$write);
    $r->patch('/gallery/albums/{id}','Admin\\DynamicLayoutController@updateAlbum',$write);
    $r->delete('/gallery/albums/{id}','Admin\\DynamicLayoutController@deleteAlbum',$write);
    $r->post('/gallery/albums/{id}/images','Admin\\DynamicLayoutController@upload',[...$write,new RateLimitMiddleware('layout-images',30,3600)]);
    $r->patch('/gallery/albums/{id}/images/order','Admin\\DynamicLayoutController@orderImages',$write);
    $r->patch('/gallery/albums/{id}/images/{image}','Admin\\DynamicLayoutController@image',$write);
    $r->delete('/gallery/albums/{id}/images/{image}','Admin\\DynamicLayoutController@removeImage',$write);
    $publish=[new PermissionMiddleware('layout','publish'),CsrfMiddleware::class,new RateLimitMiddleware('layout-publish',20,300)];
    $r->post('/publish','Admin\\DynamicLayoutController@publish',$publish);
    $r->post('/rollback/{id}','Admin\\DynamicLayoutController@rollback',$publish);
});
