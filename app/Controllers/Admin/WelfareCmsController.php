<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditService;
use App\Services\AdminCrudService;
use InvalidArgumentException;
use PDOException;
use Throwable;

/**
 * Isolated CMS for published/public welfare information.
 * Router enforces completed admin login + super_admin + CSRF for POST.
 * All writes are atomic with required audit; no member or dividend tables touched.
 */
final class WelfareCmsController extends Controller
{
    private const FIELDS = [
        'category', 'title', 'slug', 'short_description', 'full_description',
        'benefit_amount', 'eligibility', 'required_documents',
        'application_process', 'contact_info', 'sort_order', 'status',
    ];
    private const READ_COLUMNS = 'id,category,title,slug,short_description,full_description,benefit_amount,eligibility,required_documents,application_process,contact_info,sort_order,status,created_at,updated_at,deleted_at';

    /** Version includes the actual row content to avoid timestamp-only collisions. */
    private static function version(array $row): string
    {
        $snapshot = [];
        foreach (['id', ...self::FIELDS, 'created_at', 'updated_at', 'deleted_at'] as $key) {
            $snapshot[$key] = $row[$key] ?? null;
        }
        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private static function present(array $row): array
    {
        $row['version'] = self::version($row);
        unset($row['deleted_at']);
        return $row;
    }

    private static function getRow(int $id, bool $forUpdate = false): ?array
    {
        $suffix = $forUpdate ? AdminCrudService::lock() : '';
        return Database::first('SELECT '.self::READ_COLUMNS.' FROM welfare WHERE id=? AND deleted_at IS NULL'.$suffix, [$id]);
    }

    public function index(): void
    {
        if (!headers_sent()) header('Cache-Control: private, no-store');
        try {
            $rows = Database::query('SELECT '.self::READ_COLUMNS.' FROM welfare WHERE deleted_at IS NULL ORDER BY sort_order,id');
            $this->json(['success'=>true, 'data'=>array_map(self::present(...), $rows)]);
        } catch (Throwable $e) {
            $this->json(['success'=>false, 'message'=>'อ่านข้อมูลสวัสดิการไม่สำเร็จ'], 503);
        }
    }

    private static function validated(array $input): array
    {
        $out = [];
        foreach (self::FIELDS as $key) {
            if ($key === 'sort_order') {
                $v = filter_var($input[$key] ?? 0, FILTER_VALIDATE_INT);
                if ($v === false || $v < 0 || $v > 100000) throw new InvalidArgumentException('ลำดับไม่ถูกต้อง');
                $out[$key] = $v;
                continue;
            }
            if ($key === 'status') {
                $v = (string)($input[$key] ?? 'inactive');
                if (!in_array($v, ['active', 'inactive'], true)) throw new InvalidArgumentException('สถานะไม่ถูกต้อง');
                $out[$key] = $v;
                continue;
            }
            if (!is_string($input[$key] ?? '')) throw new InvalidArgumentException('รูปแบบข้อมูลไม่ถูกต้อง');
            $v = trim($input[$key] ?? '');
            $max = match ($key) {
                'full_description' => 20000,
                'eligibility', 'required_documents', 'application_process' => 5000,
                'category' => 100,
                default => 255,
            };
            if (mb_strlen($v) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $v)) {
                throw new InvalidArgumentException('ข้อความไม่ถูกต้องหรือยาวเกินกำหนด');
            }
            if (in_array($key, ['category', 'title', 'slug'], true) && $v === '') {
                throw new InvalidArgumentException('กรุณากรอกหมวดหมู่ ชื่อ และ slug');
            }
            if ($key === 'slug' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $v)) {
                throw new InvalidArgumentException('slug ใช้ตัวอักษรอังกฤษพิมพ์เล็ก ตัวเลข และขีดกลาง');
            }
            $out[$key] = $v === '' && !in_array($key, ['category','title','slug'], true) ? null : $v;
        }
        return $out;
    }

    public function store(): void { $this->save(null); }
    public function update(string $id): void { $this->save((int)$id); }

    private function save(?int $id): void
    {
        try {
            $input = $this->request->all();
            $data = self::validated($input);
            $result = Database::transaction(function () use ($id, $input, $data): array {
                $old = null;
                if ($id !== null) {
                    $old = self::getRow($id, true);
                    if (!$old) throw new \DomainException('NOT_FOUND');
                    $suppliedVersion = $input['version'] ?? '';
                    if (!is_string($suppliedVersion) || !hash_equals(self::version($old), $suppliedVersion)) {
                        throw new \DomainException('STALE_VERSION');
                    }
                    $assignment = implode(',', array_map(static fn(string $field): string => $field.'=?', self::FIELDS));
                    Database::execute(
                        'UPDATE welfare SET '.$assignment.',updated_by=?,updated_at=NOW() WHERE id=? AND deleted_at IS NULL',
                        [...array_values($data), Auth::id(), $id]
                    );
                    $savedId = $id;
                } else {
                    // Publish must be an explicit subsequent operation.
                    $data['status'] = 'inactive';
                    $columns = [...self::FIELDS, 'created_by', 'updated_by'];
                    $marks = implode(',', array_fill(0, count($columns), '?'));
                    $savedId = (int)Database::insert(
                        'INSERT INTO welfare ('.implode(',', $columns).') VALUES ('.$marks.')',
                        [...array_values($data), Auth::id(), Auth::id()]
                    );
                }

                $new = self::getRow($savedId, false);
                if (!$new) throw new \RuntimeException('Welfare row missing after save');
                AuditService::log('welfare', $old === null ? 'create' : 'update', (string)$savedId, $old, $new, required:true);
                return ['id'=>$savedId, 'version'=>self::version($new)];
            });
            $this->json(['success'=>true, 'data'=>$result], $id===null ? 201 : 200);
        } catch (\DomainException $e) {
            $notFound = $e->getMessage() === 'NOT_FOUND';
            $this->json(['success'=>false, 'code'=>$e->getMessage(),
                'message'=>$notFound ? 'ไม่พบรายการ' : 'ข้อมูลถูกเปลี่ยนแล้ว กรุณาโหลดใหม่'], $notFound ? 404 : 409);
        } catch (InvalidArgumentException $e) {
            $this->json(['success'=>false, 'code'=>'VALIDATION_ERROR', 'message'=>$e->getMessage()], 422);
        } catch (PDOException $e) {
            $isDuplicate = $e->getCode() === '23000';
            $this->json(['success'=>false, 'code'=>$isDuplicate?'CONFLICT':'DATABASE_ERROR',
                'message'=>$isDuplicate?'Slug ซ้ำหรือข้อมูลขัดแย้ง':'บันทึกไม่ได้ กรุณาลองใหม่'], $isDuplicate?409:503);
        } catch (Throwable $e) {
            $this->json(['success'=>false, 'code'=>'WRITE_FAILED', 'message'=>'บันทึกไม่ได้ กรุณาลองใหม่'], 503);
        }
    }

    public function disable(string $id): void
    {
        try {
            $input = $this->request->all();
            Database::transaction(function () use ($id, $input): void {
                $old = self::getRow((int)$id, true);
                if (!$old) throw new \DomainException('NOT_FOUND');
                $version = $input['version'] ?? '';
                if (!is_string($version) || !hash_equals(self::version($old), $version)) {
                    throw new \DomainException('STALE_VERSION');
                }
                Database::execute(
                    "UPDATE welfare SET status='inactive', deleted_at=NOW(), updated_by=?, updated_at=NOW() WHERE id=? AND deleted_at IS NULL",
                    [Auth::id(), (int)$id]
                );
                AuditService::log('welfare','disable',(string)$id,$old,['deleted_at'=>date('Y-m-d H:i:s'),'status'=>'inactive'],required:true);
            });
            $this->json(['success'=>true, 'message'=>'ปิดรายการแล้ว']);
        } catch (\DomainException $e) {
            $notFound = $e->getMessage()==='NOT_FOUND';
            $this->json(['success'=>false, 'code'=>$e->getMessage(),
                'message'=>$notFound?'ไม่พบรายการ':'ข้อมูลถูกเปลี่ยนแล้ว กรุณาโหลดใหม่'], $notFound?404:409);
        } catch (Throwable $e) {
            $this->json(['success'=>false, 'code'=>'WRITE_FAILED', 'message'=>'ปิดรายการไม่สำเร็จ'], 503);
        }
    }
}
