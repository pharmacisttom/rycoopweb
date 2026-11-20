<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\AdminCrudService as Crud;
use App\Services\AuditService;

/**
 * Managed categories for the existing documents table.
 * No category deletion: references must remain stable.
 */
final class DocumentCategoryController extends Controller
{
    private static function payload(array $input): array
    {
        $name = trim((string)($input['name'] ?? ''));
        $order = $input['sort_order'] ?? 1000;
        if ($name === '' || mb_strlen($name) > 150) {
            throw new \InvalidArgumentException('ชื่อหมวดหมู่ต้องมีความยาว 1–150 ตัวอักษร');
        }
        if (!is_scalar($order) || !preg_match('/^[0-9]{1,5}$/D', (string)$order) || (int)$order > 99999) {
            throw new \InvalidArgumentException('ลำดับหมวดหมู่ต้องเป็นเลข 0–99999');
        }
        return [$name, (int)$order];
    }

    public function index(): void
    {
        Crud::respond($this->response, static function (): array {
            if (!Auth::check() || !Auth::hasPermission('documents','view')) {
                throw new \App\Exceptions\SecurityGateException('ไม่มีสิทธิ์ดูหมวดเอกสาร',403,'FORBIDDEN');
            }
            $items=Database::query(
                "SELECT c.id,c.name,c.slug,c.sort_order,c.status,
                   (SELECT COUNT(*) FROM documents d WHERE d.category_id=c.id AND d.deleted_at IS NULL) AS document_count
                 FROM document_categories c
                 WHERE c.deleted_at IS NULL
                 ORDER BY c.sort_order ASC,c.id ASC"
            );
            return ['data'=>$items];
        });
    }

    public function store(): void
    {
        Crud::respond($this->response, function(): array {
            if (!Auth::check() || !Auth::hasPermission('documents','create')) {
                throw new \App\Exceptions\SecurityGateException('ไม่มีสิทธิ์สร้างหมวดเอกสาร',403,'FORBIDDEN');
            }
            [$name,$order]=self::payload($this->request->all());
            $id=Database::transaction(static function() use($name,$order): int {
                $slug='doc-category-'.bin2hex(random_bytes(8));
                $id=(int)Database::insert(
                    "INSERT INTO document_categories (name,slug,sort_order,status,created_at,updated_at)
                     VALUES (?,?,?,'active',NOW(),NOW())",
                    [$name,$slug,$order]
                );
                AuditService::log('document_categories','create',(string)$id,null,['name'=>$name,'sort_order'=>$order],required:true);
                return $id;
            });
            return ['id'=>$id];
        },201);
    }

    public function update(string $id): void
    {
        Crud::respond($this->response, function() use($id): array {
            if (!Auth::check() || !Auth::hasPermission('documents','edit')) {
                throw new \App\Exceptions\SecurityGateException('ไม่มีสิทธิ์แก้ไขหมวดเอกสาร',403,'FORBIDDEN');
            }
            if (!ctype_digit($id) || (int)$id<1) throw new \InvalidArgumentException('รหัสหมวดหมู่ไม่ถูกต้อง');
            [$name,$order]=self::payload($this->request->all());
            Database::transaction(static function() use($id,$name,$order): void {
                $old=Database::first('SELECT id,name,sort_order FROM document_categories WHERE id=? AND deleted_at IS NULL'.Crud::lock(),[(int)$id]);
                if (!$old) throw new \App\Exceptions\SecurityGateException('ไม่พบหมวดหมู่',404,'NOT_FOUND');
                Database::execute(
                    'UPDATE document_categories SET name=?,sort_order=?,updated_at=NOW() WHERE id=? AND deleted_at IS NULL',
                    [$name,$order,(int)$id]
                );
                AuditService::log('document_categories','update',$id,$old,['name'=>$name,'sort_order'=>$order],required:true);
            });
            return [];
        });
    }
}
