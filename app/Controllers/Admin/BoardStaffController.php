<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\BoardTermService;
use App\Services\MediaService;
use InvalidArgumentException;
use Throwable;

class BoardStaffController extends Controller
{
    public function apiIndex(): void
    {
        $catalog = \App\Services\PublicContentService::catalog();

        $boards = array_map(function (array $item, int $index): array {
            $photo = resolve_media_url($item['image'] ?? null);
            return [
                'id' => (int) $item['id'],
                'name' => $item['name'],
                'position' => $item['position'],
                'role_type' => 'director',
                'term_years' => $item['term'] ?? 'ชุดที่ 38',
                'term_number' => (int) preg_replace('/\D/', '', $item['term'] ?? '38') ?: 38,
                'photo' => $photo,
                'image' => $item['image'] ?? null,
                'workplace' => $item['workplace'] ?? null,
                'bio' => '',
                'sort_order' => $index + 1,
                'status' => 'active',
                'version' => \App\Services\AccessService::version($item),
            ];
        }, $catalog['boardMembers'], array_keys($catalog['boardMembers']));

        $staff = array_map(function (array $item, int $index): array {
            $photo = resolve_media_url($item['image'] ?? null);
            return [
                'id' => (int) $item['id'],
                'name' => $item['name'],
                'position' => $item['position'],
                'department' => $item['department'] ?? 'ฝ่ายจัดการ',
                'phone' => '',
                'email' => '',
                'photo' => $photo,
                'image' => $item['image'] ?? null,
                'sort_order' => $index + 1,
                'status' => 'active',
                'version' => \App\Services\AccessService::version($item),
            ];
        }, $catalog['staffMembers'], array_keys($catalog['staffMembers']));

        $terms = [];
        $activeTerm = null;
        if (BoardTermService::isTableAvailable()) {
            $terms = BoardTermService::getAllTerms();
            $activeTerm = BoardTermService::getPublicActiveTerm();
        }

        $this->response->json([
            'success' => true,
            'data' => [
                'boards' => $boards,
                'staff' => $staff,
                'advisors' => $catalog['advisors'] ?? [],
                'auditors' => $catalog['auditors'] ?? [],
                'groups' => $catalog['boardGroups'] ?? [],
                'terms' => $terms,
                'active_term' => $activeTerm,
            ]
        ]);
    }

    public function apiStoreBoard(): void
    {
        $name = trim((string)$this->request->input('name'));
        $position = trim((string)$this->request->input('position'));

        if ($name === '' || $position === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกชื่อและตำแหน่ง'], 422);
            return;
        }

        $sql = "INSERT INTO boards (name, position, role_type, term_years, photo, bio, sort_order, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $id = (int)Database::insert($sql, [
            $name,
            $position,
            $this->request->input('role_type', 'director'),
            $this->request->input('term_years', '2568-2569'),
            $this->request->input('photo', ''),
            $this->request->input('bio', ''),
            (int)$this->request->input('sort_order', 0),
            $this->request->input('status', 'active'),
        ]);

        AuditService::log('boards', 'create', (string)$id, null, ['name' => $name, 'position' => $position]);
        $row = Database::first("SELECT * FROM boards WHERE id = ?", [$id]);
        $row['version'] = \App\Services\AccessService::version($row);
        $this->response->json(['success' => true, 'message' => 'เพิ่มกรรมการเรียบร้อยแล้ว', 'data' => $row], 201);
    }

    public function apiUpdateBoard(string $id): void
    {
        $boardId = (int)$id;
        $old = Database::first("SELECT * FROM boards WHERE id = ? AND deleted_at IS NULL", [$boardId]);
        if (!$old) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลกรรมการ'], 404);
            return;
        }

        $name = trim((string)$this->request->input('name', $old['name']));
        $position = trim((string)$this->request->input('position', $old['position']));

        if ($name === '' || $position === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกชื่อและตำแหน่ง'], 422);
            return;
        }

        $sql = "UPDATE boards SET name = ?, position = ?, role_type = ?, term_years = ?, photo = ?, bio = ?, sort_order = ?, status = ?, updated_at = NOW() WHERE id = ?";
        Database::execute($sql, [
            $name,
            $position,
            $this->request->input('role_type', $old['role_type']),
            $this->request->input('term_years', $old['term_years']),
            $this->request->input('photo', $old['photo']),
            $this->request->input('bio', $old['bio']),
            (int)$this->request->input('sort_order', $old['sort_order']),
            $this->request->input('status', $old['status'] ?? 'active'),
            $boardId
        ]);

        AuditService::log('boards', 'update', (string)$boardId, $old, ['name' => $name, 'position' => $position]);
        $updated = Database::first("SELECT * FROM boards WHERE id = ?", [$boardId]);
        $updated['version'] = \App\Services\AccessService::version($updated);
        $this->response->json(['success' => true, 'message' => 'บันทึกการแก้ไขกรรมการเรียบร้อยแล้ว', 'data' => $updated]);
    }

    public function apiDestroyBoard(string $id): void
    {
        $boardId = (int)$id;
        $old = Database::first("SELECT * FROM boards WHERE id = ? AND deleted_at IS NULL", [$boardId]);
        if (!$old) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลกรรมการ'], 404);
            return;
        }

        Database::execute("UPDATE boards SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?", [$boardId]);
        AuditService::log('boards', 'delete', (string)$boardId, $old);
        $this->response->json(['success' => true, 'message' => 'ลบกรรมการเรียบร้อยแล้ว']);
    }

    public function apiStoreStaff(): void
    {
        $name = trim((string)$this->request->input('name'));
        $position = trim((string)$this->request->input('position'));

        if ($name === '' || $position === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกชื่อและตำแหน่ง'], 422);
            return;
        }

        $sql = "INSERT INTO staff (name, position, department, email, phone, photo, sort_order, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $id = (int)Database::insert($sql, [
            $name,
            $position,
            $this->request->input('department', ''),
            $this->request->input('email', ''),
            $this->request->input('phone', ''),
            $this->request->input('photo', ''),
            (int)$this->request->input('sort_order', 0),
            $this->request->input('status', 'active'),
        ]);

        AuditService::log('staff', 'create', (string)$id, null, ['name' => $name, 'position' => $position]);
        $row = Database::first("SELECT * FROM staff WHERE id = ?", [$id]);
        $row['version'] = \App\Services\AccessService::version($row);
        $this->response->json(['success' => true, 'message' => 'เพิ่มเจ้าหน้าที่เรียบร้อยแล้ว', 'data' => $row], 201);
    }

    public function apiUpdateStaff(string $id): void
    {
        $staffId = (int)$id;
        $old = Database::first("SELECT * FROM staff WHERE id = ? AND deleted_at IS NULL", [$staffId]);
        if (!$old) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลเจ้าหน้าที่'], 404);
            return;
        }

        $name = trim((string)$this->request->input('name', $old['name']));
        $position = trim((string)$this->request->input('position', $old['position']));

        if ($name === '' || $position === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกชื่อและตำแหน่ง'], 422);
            return;
        }

        $sql = "UPDATE staff SET name = ?, position = ?, department = ?, email = ?, phone = ?, photo = ?, sort_order = ?, status = ?, updated_at = NOW() WHERE id = ?";
        Database::execute($sql, [
            $name,
            $position,
            $this->request->input('department', $old['department']),
            $this->request->input('email', $old['email']),
            $this->request->input('phone', $old['phone']),
            $this->request->input('photo', $old['photo']),
            (int)$this->request->input('sort_order', $old['sort_order']),
            $this->request->input('status', $old['status'] ?? 'active'),
            $staffId
        ]);

        AuditService::log('staff', 'update', (string)$staffId, $old, ['name' => $name, 'position' => $position]);
        $updated = Database::first("SELECT * FROM staff WHERE id = ?", [$staffId]);
        $updated['version'] = \App\Services\AccessService::version($updated);
        $this->response->json(['success' => true, 'message' => 'บันทึกการแก้ไขเจ้าหน้าที่เรียบร้อยแล้ว', 'data' => $updated]);
    }

    public function apiDestroyStaff(string $id): void
    {
        $staffId = (int)$id;
        $old = Database::first("SELECT * FROM staff WHERE id = ? AND deleted_at IS NULL", [$staffId]);
        if (!$old) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลเจ้าหน้าที่'], 404);
            return;
        }

        Database::execute("UPDATE staff SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?", [$staffId]);
        AuditService::log('staff', 'delete', (string)$staffId, $old);
        $this->response->json(['success' => true, 'message' => 'ลบเจ้าหน้าที่เรียบร้อยแล้ว']);
    }

    public function index(): void
    {
        $catalog = \App\Services\PublicContentService::catalog();
        $boards = Database::query("SELECT * FROM boards WHERE deleted_at IS NULL ORDER BY sort_order ASC");
        if (empty($boards) && !empty($catalog['boardMembers'])) {
            $boards = array_map(static function (array $item, int $index): array {
                $item['photo'] = resolve_media_url($item['image'] ?? null);
                $item['term_years'] = $item['term'] ?? 'ชุดที่ 38';
                $item['sort_order'] = $index + 1;
                $item['status'] = 'active';
                return $item;
            }, $catalog['boardMembers'], array_keys($catalog['boardMembers']));
        } else {
            $boards = array_map(static function (array $item): array { $item['photo'] = resolve_media_url($item['photo'] ?? null); return $item; }, $boards);
        }

        $staff = Database::query("SELECT * FROM staff WHERE deleted_at IS NULL ORDER BY sort_order ASC");
        if (empty($staff) && !empty($catalog['staffMembers'])) {
            $staff = array_map(static function (array $item, int $index): array {
                $item['photo'] = resolve_media_url($item['image'] ?? null);
                $item['department'] = $item['department'] ?? 'ฝ่ายจัดการ';
                $item['sort_order'] = $index + 1;
                $item['status'] = 'active';
                return $item;
            }, $catalog['staffMembers'], array_keys($catalog['staffMembers']));
        } else {
            $staff = array_map(static function (array $item): array { $item['photo'] = resolve_media_url($item['photo'] ?? null); return $item; }, $staff);
        }

        $this->render('admin.board_staff.index', [
            'title' => 'จัดการคณะกรรมการและเจ้าหน้าที่',
            'boards' => $boards,
            'staff' => $staff,
            'advisors' => $catalog['advisors'] ?? [],
            'auditors' => $catalog['auditors'] ?? [],
            'groups' => $catalog['boardGroups'] ?? [],
        ], 'layouts.admin');
    }

    public function storeBoard(): void
    {
        $data = $this->validate([
            'name' => 'required',
            'position' => 'required',
        ]);

        Database::execute("INSERT INTO boards (name, position, role_type, term_years, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())", [
            $data['name'],
            $data['position'],
            $this->request->input('role_type', 'director'),
            $this->request->input('term_years', '2568-2569')
        ]);

        AuditService::log('boards', 'create', null, null, ['name' => $data['name']]);
        Session::flash('success', 'เพิ่มกรรมการเรียบร้อยแล้ว');
        $this->redirect(url('admin/board-staff'));
    }

    public function destroyBoard(string $id): void
    {
        Database::execute("UPDATE boards SET deleted_at = NOW() WHERE id = ?", [(int) $id]);
        if ($this->request->isAjax()) {
            $this->json(['success' => true]);
        } else {
            Session::flash('success', 'ลบกรรมการเรียบร้อยแล้ว');
            $this->redirect(url('admin/board-staff'));
        }
    }

    /**
     * GET /api/admin/board-terms
     */
    public function apiTermsIndex(): void
    {
        try {
            $terms = BoardTermService::getAllTerms();
            $this->response->json([
                'success' => true,
                'data' => $terms,
            ]);
        } catch (Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/admin/board-terms/{id}
     */
    public function apiTermShow(string $id): void
    {
        try {
            $term = BoardTermService::getTerm((int)$id);
            if (!$term) {
                $this->response->json(['success' => false, 'message' => 'ไม่พบวาระที่ต้องการ'], 404);
                return;
            }
            $this->response->json([
                'success' => true,
                'data' => $term,
            ]);
        } catch (Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/admin/board-terms/{id}/preview
     */
    public function apiTermPreview(string $id): void
    {
        try {
            $preview = BoardTermService::getTermPreview((int)$id);
            if (!$preview) {
                $this->response->json(['success' => false, 'message' => 'ไม่พบวาระที่ต้องการ'], 404);
                return;
            }
            $this->response->json([
                'success' => true,
                'data' => $preview,
            ]);
        } catch (Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/admin/board-terms
     */
    public function apiTermStore(): void
    {
        try {
            $id = BoardTermService::createTerm($this->request->all(), Auth::id());
            $term = BoardTermService::getTerm($id);
            $this->response->json([
                'success' => true,
                'message' => 'สร้างวาระใหม่สำเร็จ',
                'data' => $term,
            ], 201);
        } catch (InvalidArgumentException $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/admin/board-terms/{id}/copy
     */
    public function apiTermCopy(string $id): void
    {
        try {
            $sourceId = (int)$id;
            $newTermNumber = (int)$this->request->input('term_number');
            $newTermName = $this->request->input('term_name');

            $newId = BoardTermService::copyTerm($sourceId, $newTermNumber, $newTermName, Auth::id());
            $term = BoardTermService::getTerm($newId);
            $this->response->json([
                'success' => true,
                'message' => "คัดลอกวาระไปยังชุดที่ {$newTermNumber} สำเร็จ",
                'data' => $term,
            ], 201);
        } catch (InvalidArgumentException $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT/POST /api/admin/board-terms/{id}
     */
    public function apiTermUpdate(string $id): void
    {
        try {
            $termId = (int)$id;
            BoardTermService::updateTerm($termId, $this->request->all(), Auth::id());
            $term = BoardTermService::getTerm($termId);
            $this->response->json([
                'success' => true,
                'message' => 'บันทึกการแก้ไขข้อมูลวาระสำเร็จ',
                'data' => $term,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT/POST /api/admin/board-terms/{id}/members
     */
    public function apiTermSaveMembers(string $id): void
    {
        try {
            $termId = (int)$id;
            $members = (array)$this->request->input('members', []);
            $category = (string)$this->request->input('category', 'director');

            BoardTermService::saveTermMembers($termId, $members, $category, Auth::id());
            $term = BoardTermService::getTerm($termId);
            $this->response->json([
                'success' => true,
                'message' => 'บันทึกรายชื่อสำเร็จ',
                'data' => $term,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT/POST /api/admin/board-terms/{id}/groups
     */
    public function apiTermSaveGroups(string $id): void
    {
        try {
            $termId = (int)$id;
            $groups = (array)$this->request->input('groups', []);

            BoardTermService::saveTermGroups($termId, $groups, Auth::id());
            $term = BoardTermService::getTerm($termId);
            $this->response->json([
                'success' => true,
                'message' => 'บันทึกคณะกรรมการย่อยสำเร็จ',
                'data' => $term,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/admin/board-terms/{id}/publish
     */
    public function apiTermPublish(string $id): void
    {
        try {
            $termId = (int)$id;
            BoardTermService::publishTerm($termId, Auth::id());
            $term = BoardTermService::getTerm($termId);
            $this->response->json([
                'success' => true,
                'message' => 'เผยแพร่วาระสำเร็จ และอัปเดตหน้าสาธารณะแล้ว',
                'data' => $term,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/admin/board-terms/{id}/schedule
     */
    public function apiTermSchedule(string $id): void
    {
        try {
            $termId = (int)$id;
            $publishAt = (string)$this->request->input('publish_at');
            if (trim($publishAt) === '') {
                $this->response->json(['success' => false, 'message' => 'กรุณาระบุวันและเวลาที่ต้องการเผยแพร่'], 422);
                return;
            }

            BoardTermService::scheduleTerm($termId, $publishAt, Auth::id());
            $term = BoardTermService::getTerm($termId);
            $this->response->json([
                'success' => true,
                'message' => 'ตั้งเวลาเผยแพร่วาระสำเร็จ',
                'data' => $term,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/admin/board-terms/reconcile-38
     */
    public function apiReconcileTerm38(): void
    {
        try {
            $force = (bool)$this->request->input('force', false);
            $result = BoardTermService::reconcileTerm38($force);
            $this->response->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/admin/board-terms/import-38/preview
     */
    public function apiImportTerm38Preview(): void
    {
        try {
            $preview = BoardTermService::getTerm38ImportPreview();
            $this->response->json([
                'success' => true,
                'data' => $preview,
            ]);
        } catch (Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/admin/board-terms/import-38
     */
    public function apiImportTerm38(): void
    {
        try {
            $options = [
                'preserve_custom_photos' => (bool)$this->request->input('preserve_custom_photos', true),
                'preserve_admin_edits' => (bool)$this->request->input('preserve_admin_edits', true),
            ];
            $result = BoardTermService::importTerm38($options, Auth::id());
            $this->response->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/admin/board-terms/{id}/selection-candidates
     */
    public function apiSelectionCandidates(string $id): void
    {
        try {
            $sourceId = (int)$id;
            $targetTerm = (int)$this->request->input('target_term', $this->request->input('target_term_number', 39));
            $candidates = BoardTermService::getSelectionCandidates($sourceId, $targetTerm);
            $this->response->json([
                'success' => true,
                'data' => $candidates,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET/POST /api/admin/board-terms/{id}/selective-transfer/preview
     * POST /api/admin/board-terms/{id}/selective-copy/preview
     */
    public function apiSelectiveTransferPreview(string $id): void
    {
        try {
            $sourceId = (int)$id;
            $targetTerm = (int)$this->request->input('target_term', $this->request->input('target_term_number', 39));
            $selections = (array)$this->request->input('selections', $this->request->input('selected_member_ids', []));
            $options = [
                'transfer_groups' => (bool)$this->request->input('transfer_groups', true),
                'term_name' => $this->request->input('term_name'),
            ];
            $preview = BoardTermService::getSelectiveTransferPreview($sourceId, $targetTerm, $selections, $options);
            $this->response->json([
                'success' => true,
                'data' => $preview,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/admin/board-terms/{id}/selective-copy/preview
     */
    public function apiSelectiveCopyPreview(string $id): void
    {
        $this->apiSelectiveTransferPreview($id);
    }

    /**
     * POST /api/admin/board-terms/{id}/selective-transfer
     * POST /api/admin/board-terms/{id}/selective-copy
     */
    public function apiSelectiveTransfer(string $id): void
    {
        try {
            $sourceId = (int)$id;
            $targetTerm = (int)$this->request->input('target_term_number', $this->request->input('target_term', 39));
            $selectedIds = (array)$this->request->input('selections', $this->request->input('selected_member_ids', []));
            $options = [
                'transfer_groups' => (bool)$this->request->input('transfer_groups', true),
                'term_name' => $this->request->input('term_name'),
                'customizations' => (array)$this->request->input('customizations', []),
            ];

            $result = BoardTermService::selectiveTransfer($sourceId, $targetTerm, $selectedIds, $options, Auth::id());
            $this->response->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/admin/board-terms/{id}/selective-copy
     */
    public function apiSelectiveCopy(string $id): void
    {
        $this->apiSelectiveTransfer($id);
    }

    /**
     * POST /api/admin/board-terms/{id}/members/{memberId}/photo
     */
    public function apiUploadMemberPhoto(string $id, string $memberId): void
    {
        try {
            $termId = (int)$id;
            $mId = (int)$memberId;
            $file = $this->request->file('photo') ?? $this->request->file('file') ?? $this->request->file('image');
            if (!$file) {
                $this->response->json(['success' => false, 'message' => 'ไม่พบไฟล์รูปภาพที่อัปโหลด'], 422);
                return;
            }

            $result = BoardTermService::uploadMemberPhoto($termId, $mId, $file, Auth::id());
            $this->response->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/admin/board-terms/upload-photo
     */
    public function apiUploadPhoto(): void
    {
        try {
            $file = $this->request->file('photo') ?? $this->request->file('file') ?? $this->request->file('image');
            if (!$file) {
                $this->response->json(['success' => false, 'message' => 'ไม่พบไฟล์รูปภาพ'], 422);
                return;
            }

            $name = trim((string)$this->request->input('name', 'personnel'));
            $result = MediaService::upload($file, 'personnel', $name);

            $this->response->json([
                'success' => true,
                'message' => 'อัปโหลดรูปภาพสำเร็จ',
                'data' => [
                    'url' => $result['url'],
                    'relative_path' => $result['path'],
                    'full_url' => resolve_media_url($result['url']),
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->response->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
