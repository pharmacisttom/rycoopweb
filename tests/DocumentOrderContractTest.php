<?php
declare(strict_types=1);
// Read-only source contract test; no database mutations.
$root=dirname(__DIR__);
$crud=file_get_contents($root.'/app/Services/AdminCrudService.php');
$api=file_get_contents($root.'/app/Controllers/Api/SpaApiController.php');
$admin=file_get_contents($root.'/src/pages/AdminStoredContentPage.jsx');
$legacy=file_get_contents($root.'/app/Controllers/Public/DocumentController.php');
if(!str_contains($crud,"'sort_order'=>(int)\$rawOrder")
  || !str_contains($crud,'COALESCE(sort_order, 0) ASC')
  || !str_contains($crud,"checkVersion(\$old,\$input)"))
  throw new RuntimeException('Admin document ordering/optimistic locking contract missing');
if(!str_contains($api,'COALESCE(d.sort_order,0) ASC')
  || !str_contains($api,'d.sort_order, d.created_at'))
  throw new RuntimeException('Public document API ordering missing');
if(!str_contains($admin,"'sort_order','status','version'")
  || !str_contains($admin,'label="ลำดับการแสดง"')
  || !str_contains($admin,"key:'sort_order'"))
  throw new RuntimeException('Admin UI order field/list missing');
if(!str_contains($legacy,'ORDER BY d.sort_order ASC'))
  throw new RuntimeException('Legacy public document ordering unexpectedly missing');
echo "DOCUMENT_ORDER_CONTRACT=PASS\n";
