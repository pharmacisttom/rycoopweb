<?php
declare(strict_types=1);
// Read-only contracts: keep historical categories, respect member security, no migration.
$root=dirname(__DIR__);
$source=file_get_contents($root.'/app/Controllers/Admin/DocumentCategoryController.php');
$routes=file_get_contents($root.'/config/routes.php');
$docs=file_get_contents($root.'/app/Controllers/Api/SpaApiController.php');
$admin=file_get_contents($root.'/src/pages/AdminStoredContentPage.jsx');
$public=file_get_contents($root.'/src/pages/DocumentsPage.jsx');
$controller=file_get_contents($root.'/app/Controllers/Admin/DocumentController.php');
foreach(['document-categories','DocumentCategoryController@store','DocumentCategoryController@update'] as $term){
 if(!str_contains($routes,$term))throw new RuntimeException("Missing category route $term");
}
if(!str_contains($source,"document_categories") || !str_contains($source,"AuditService::log")
 || str_contains($source,"DELETE FROM document_categories"))
 throw new RuntimeException('Unsafe category CMS persistence');
if(!str_contains($docs,'c.sort_order AS category_sort_order')
 || !str_contains($docs,'ORDER BY COALESCE(c.sort_order,0) ASC'))
 throw new RuntimeException('Missing public category ordering');
if(!str_contains($admin,'saveCategory') || !str_contains($admin,'categoryFilter')
 || !str_contains($admin,'category_id') || !str_contains($admin,'แก้ไขหมวดหมู่'))
 throw new RuntimeException('Admin category management or document move missing');
if(!str_contains($controller,'SELECT id,name,sort_order FROM document_categories'))
 throw new RuntimeException('Current categories not loaded');
if(!str_contains($public,'groups.map(group') || !str_contains($public,'category_sort_order'))
 throw new RuntimeException('Public category grouping missing');
echo "DOCUMENT_CATEGORIES_CONTRACT=PASS\n";
