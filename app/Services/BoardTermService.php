<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Services\AuditService;
use InvalidArgumentException;
use RuntimeException;

class BoardTermService
{
    /**
     * Check if board_terms table exists.
     */
    public static function isTableAvailable(): bool
    {
        try {
            $pdo = Database::connect();
            $stmt = $pdo->query("SHOW TABLES LIKE 'board_terms'");
            return $stmt !== false && $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Reconcile Term 38 from legacyReference.json if not present or forced.
     */
    public static function reconcileTerm38(bool $force = false): array
    {
        if (!self::isTableAvailable()) {
            throw new RuntimeException('ตาราง board_terms ยังไม่พร้อมใช้งาน กรุณารัน migration ก่อน');
        }

        $existing = Database::first("SELECT id, term_number, status, is_current FROM board_terms WHERE term_number = 38");
        if ($existing && !$force) {
            return [
                'status' => 'already_exists',
                'term_id' => (int)$existing['id'],
                'term_number' => 38,
                'message' => 'วาระที่ 38 มีอยู่ในระบบแล้ว'
            ];
        }

        $refPath = dirname(__DIR__, 2) . '/src/data/legacyReference.json';
        if (!file_exists($refPath)) {
            throw new RuntimeException('ไม่พบไฟล์ legacyReference.json');
        }

        $legacy = json_decode(file_get_contents($refPath), true, 512, JSON_THROW_ON_ERROR);
        $source = $legacy['sources']['board'] ?? [];

        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $termId = null;
            if ($existing) {
                $termId = (int)$existing['id'];
                Database::execute(
                    "UPDATE board_terms SET term_name = ?, year_start_be = ?, year_end_be = ?, source_url = ?, note = ?, status = 'published', is_current = 1, published_at = NOW(), updated_at = NOW() WHERE id = ?",
                    ['ชุดที่ 38', 2567, 2568, $source['url'] ?? 'https://rayongcoop.com/page.php?pid=3', $source['note'] ?? null, $termId]
                );
                // Clean existing sub-records for clean rebuild
                Database::execute("DELETE FROM board_term_members WHERE term_id = ?", [$termId]);
                Database::execute("DELETE FROM board_term_groups WHERE term_id = ?", [$termId]);
            } else {
                $termId = (int)Database::insert(
                    "INSERT INTO board_terms (term_number, term_name, year_start_be, year_end_be, status, is_current, source_url, note, published_at, created_at, updated_at)
                     VALUES (38, 'ชุดที่ 38', 2567, 2568, 'published', 1, ?, ?, NOW(), NOW(), NOW())",
                    [$source['url'] ?? 'https://rayongcoop.com/page.php?pid=3', $source['note'] ?? null]
                );
            }

            // 1. Insert 15 directors
            $memberIdMap = []; // legacy id => new DB id
            $boardMembers = $legacy['boardMembers'] ?? [];
            foreach ($boardMembers as $index => $bm) {
                $legacyId = (int)($bm['id'] ?? ($index + 1));
                $name = trim((string)($bm['name'] ?? ''));
                $position = trim((string)($bm['position'] ?? 'กรรมการ'));
                $image = $bm['image'] ?? null;
                $isChair = ($index === 0 || str_contains($position, 'ประธานกรรมการ')) ? 1 : 0;

                $roleType = 'director';
                if (str_contains($position, 'ประธานกรรมการ') && !str_contains($position, 'รอง')) {
                    $roleType = 'president';
                } elseif (str_contains($position, 'รองประธาน')) {
                    $roleType = 'vice_president';
                } elseif (str_contains($position, 'เหรัญญิก')) {
                    $roleType = 'treasurer';
                } elseif (str_contains($position, 'เลขานุการ')) {
                    $roleType = 'secretary';
                }

                $dbMemberId = (int)Database::insert(
                    "INSERT INTO board_term_members (term_id, person_id, category, name, position, role_type, workplace, department, image_url, sort_order, is_chairperson, created_at, updated_at)
                     VALUES (?, ?, 'director', ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    [$termId, $legacyId, $name, $position, $roleType, $bm['workplace'] ?? null, $bm['department'] ?? null, $image, $index, $isChair]
                );
                $memberIdMap[$legacyId] = $dbMemberId;
            }

            // 2. Insert Advisors
            $advisors = $legacy['advisors'] ?? [];
            foreach ($advisors as $index => $adv) {
                $name = is_array($adv) ? ($adv[0] ?? '') : ($adv['name'] ?? '');
                $position = is_array($adv) ? ($adv[1] ?? 'ที่ปรึกษา') : ($adv['position'] ?? 'ที่ปรึกษา');
                Database::insert(
                    "INSERT INTO board_term_members (term_id, category, name, position, role_type, sort_order, created_at, updated_at)
                     VALUES (?, 'advisor', ?, ?, 'advisor', ?, NOW(), NOW())",
                    [$termId, trim((string)$name), trim((string)$position), $index]
                );
            }

            // 3. Insert Auditors
            $auditors = $legacy['auditors'] ?? [];
            foreach ($auditors as $index => $aud) {
                $name = is_array($aud) ? ($aud[0] ?? '') : ($aud['name'] ?? '');
                $position = is_array($aud) ? ($aud[1] ?? 'ผู้ตรวจสอบกิจการ') : ($aud['position'] ?? 'ผู้ตรวจสอบกิจการ');
                Database::insert(
                    "INSERT INTO board_term_members (term_id, category, name, position, role_type, sort_order, created_at, updated_at)
                     VALUES (?, 'auditor', ?, ?, 'auditor', ?, NOW(), NOW())",
                    [$termId, trim((string)$name), trim((string)$position), $index]
                );
            }

            // 4. Insert Subgroups
            $boardGroups = $legacy['boardGroups'] ?? [];
            foreach ($boardGroups as $gIndex => $bg) {
                $title = trim((string)($bg['title'] ?? ''));
                $groupId = (int)Database::insert(
                    "INSERT INTO board_term_groups (term_id, title, sort_order, created_at, updated_at)
                     VALUES (?, ?, ?, NOW(), NOW())",
                    [$termId, $title, $gIndex]
                );

                $members = $bg['members'] ?? [];
                foreach ($members as $mIndex => $m) {
                    $legId = is_array($m) ? (int)$m[0] : (int)($m['id'] ?? 0);
                    $roleInGroup = is_array($m) ? (string)$m[1] : (string)($m['role'] ?? 'กรรมการ');
                    $dbMemberId = $memberIdMap[$legId] ?? null;
                    if ($dbMemberId) {
                        Database::insert(
                            "INSERT INTO board_term_group_members (group_id, member_id, role_in_group, sort_order, created_at, updated_at)
                             VALUES (?, ?, ?, ?, NOW(), NOW())",
                            [$groupId, $dbMemberId, $roleInGroup, $mIndex]
                        );
                    }
                }
            }

            $pdo->commit();
            AuditService::log('board_terms', 'reconcile', (string)$termId, null, ['term_number' => 38, 'action' => 'reconcile_from_legacy']);

            return [
                'status' => 'success',
                'term_id' => $termId,
                'term_number' => 38,
                'directors' => count($boardMembers),
                'advisors' => count($advisors),
                'auditors' => count($auditors),
                'groups' => count($boardGroups),
                'message' => 'เทียบข้อมูลวาระที่ 38 เรียบร้อยแล้ว'
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Get all board terms with member and group counts.
     */
    public static function getAllTerms(): array
    {
        if (!self::isTableAvailable()) {
            return [];
        }

        $sql = "SELECT bt.*,
                       (SELECT COUNT(*) FROM board_term_members m WHERE m.term_id = bt.id AND m.category = 'director') AS director_count,
                       (SELECT COUNT(*) FROM board_term_members m WHERE m.term_id = bt.id AND m.category = 'advisor') AS advisor_count,
                       (SELECT COUNT(*) FROM board_term_members m WHERE m.term_id = bt.id AND m.category = 'auditor') AS auditor_count,
                       (SELECT COUNT(*) FROM board_term_groups g WHERE g.term_id = bt.id) AS group_count
                FROM board_terms bt
                ORDER BY bt.term_number DESC, bt.id DESC";

        $terms = Database::query($sql);
        return array_map(function (array $t): array {
            $t['version'] = AccessService::version($t);
            $t['is_current'] = (int)$t['is_current'] === 1;
            return $t;
        }, $terms);
    }

    /**
     * Get full details of a specific term.
     */
    public static function getTerm(int $termId): ?array
    {
        if (!self::isTableAvailable()) {
            return null;
        }

        $term = Database::first("SELECT * FROM board_terms WHERE id = ?", [$termId]);
        if (!$term) {
            return null;
        }

        $term['is_current'] = (int)$term['is_current'] === 1;
        $term['version'] = AccessService::version($term);

        // Fetch members categorized
        $members = Database::query(
            "SELECT * FROM board_term_members WHERE term_id = ? ORDER BY category ASC, sort_order ASC, id ASC",
            [$termId]
        );

        $directors = [];
        $advisors = [];
        $auditors = [];

        foreach ($members as $m) {
            $m['is_chairperson'] = (int)$m['is_chairperson'] === 1;
            $m['photo_resolved'] = resolve_media_url($m['image_url'] ?? null);
            $m['version'] = AccessService::version($m);

            if ($m['category'] === 'director') {
                $directors[] = $m;
            } elseif ($m['category'] === 'advisor') {
                $advisors[] = $m;
            } elseif ($m['category'] === 'auditor') {
                $auditors[] = $m;
            }
        }

        // Fetch groups and group members
        $groups = Database::query(
            "SELECT * FROM board_term_groups WHERE term_id = ? ORDER BY sort_order ASC, id ASC",
            [$termId]
        );

        foreach ($groups as &$group) {
            $group['version'] = AccessService::version($group);
            $groupMembers = Database::query(
                "SELECT gm.id AS link_id, gm.group_id, gm.member_id, gm.role_in_group, gm.sort_order,
                        m.name, m.position, m.image_url, m.role_type, m.is_chairperson
                 FROM board_term_group_members gm
                 JOIN board_term_members m ON gm.member_id = m.id
                 WHERE gm.group_id = ?
                 ORDER BY gm.sort_order ASC, gm.id ASC",
                [(int)$group['id']]
            );
            $group['members'] = array_map(function (array $gm): array {
                $gm['photo_resolved'] = resolve_media_url($gm['image_url'] ?? null);
                return $gm;
            }, $groupMembers);
        }
        unset($group);

        return [
            'term' => $term,
            'directors' => $directors,
            'advisors' => $advisors,
            'auditors' => $auditors,
            'groups' => $groups,
        ];
    }

    /**
     * Create a new term in draft status.
     */
    public static function createTerm(array $data, ?int $userId = null): int
    {
        $termNumber = (int)($data['term_number'] ?? 0);
        if ($termNumber <= 0) {
            throw new InvalidArgumentException('กรุณาระบุหมายเลขวาระที่ถูกต้อง (ตัวเลขมากกว่า 0)');
        }

        $existing = Database::first("SELECT id FROM board_terms WHERE term_number = ?", [$termNumber]);
        if ($existing) {
            throw new InvalidArgumentException("มีวาระชุดที่ {$termNumber} อยู่ในระบบแล้ว");
        }

        $termName = trim((string)($data['term_name'] ?? "ชุดที่ {$termNumber}"));
        if ($termName === '') {
            $termName = "ชุดที่ {$termNumber}";
        }

        $yearStart = !empty($data['year_start_be']) ? (int)$data['year_start_be'] : null;
        $yearEnd = !empty($data['year_end_be']) ? (int)$data['year_end_be'] : null;
        $startDate = !empty($data['start_date']) ? (string)$data['start_date'] : null;
        $endDate = !empty($data['end_date']) ? (string)$data['end_date'] : null;
        $sourceUrl = !empty($data['source_url']) ? trim((string)$data['source_url']) : null;
        $note = !empty($data['note']) ? trim((string)$data['note']) : null;

        $id = (int)Database::insert(
            "INSERT INTO board_terms (term_number, term_name, year_start_be, year_end_be, start_date, end_date, status, is_current, source_url, note, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 'draft', 0, ?, ?, ?, NOW(), NOW())",
            [$termNumber, $termName, $yearStart, $yearEnd, $startDate, $endDate, $sourceUrl, $note, $userId ?? Auth::id()]
        );

        AuditService::log('board_terms', 'create', (string)$id, null, ['term_number' => $termNumber, 'term_name' => $termName]);
        return $id;
    }

    /**
     * Copy an existing term as a new draft term (deep-cloning members and groups).
     */
    public static function copyTerm(int $sourceTermId, int $newTermNumber, ?string $newTermName = null, ?int $userId = null): int
    {
        $source = self::getTerm($sourceTermId);
        if (!$source) {
            throw new InvalidArgumentException('ไม่พบวาระต้นฉบับที่ต้องการคัดลอก');
        }

        if ($newTermNumber <= 0) {
            throw new InvalidArgumentException('กรุณาระบุหมายเลขวาระใหม่ที่ถูกต้อง');
        }

        $existing = Database::first("SELECT id FROM board_terms WHERE term_number = ?", [$newTermNumber]);
        if ($existing) {
            throw new InvalidArgumentException("มีวาระชุดที่ {$newTermNumber} อยู่ในระบบแล้ว");
        }

        $termName = trim((string)($newTermName ?? "ชุดที่ {$newTermNumber}"));
        if ($termName === '') {
            $termName = "ชุดที่ {$newTermNumber}";
        }

        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $sourceTerm = $source['term'];
            $newYearStart = $sourceTerm['year_start_be'] ? (int)$sourceTerm['year_start_be'] + ($newTermNumber - (int)$sourceTerm['term_number']) : null;
            $newYearEnd = $sourceTerm['year_end_be'] ? (int)$sourceTerm['year_end_be'] + ($newTermNumber - (int)$sourceTerm['term_number']) : null;

            $newTermId = (int)Database::insert(
                "INSERT INTO board_terms (term_number, term_name, year_start_be, year_end_be, status, is_current, source_url, note, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 'draft', 0, ?, ?, ?, NOW(), NOW())",
                [$newTermNumber, $termName, $newYearStart, $newYearEnd, $sourceTerm['source_url'] ?? null, "คัดลอกจาก {$sourceTerm['term_name']}", $userId ?? Auth::id()]
            );

            // Copy all members and map old ID => new ID
            $memberIdMap = [];
            $allMembers = array_merge($source['directors'], $source['advisors'], $source['auditors']);

            foreach ($allMembers as $m) {
                $newMemId = (int)Database::insert(
                    "INSERT INTO board_term_members (term_id, person_id, category, name, position, role_type, workplace, department, image_url, bio, sort_order, is_chairperson, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    [
                        $newTermId,
                        $m['person_id'] ?? null,
                        $m['category'],
                        $m['name'],
                        $m['position'],
                        $m['role_type'] ?? 'director',
                        $m['workplace'] ?? null,
                        $m['department'] ?? null,
                        $m['image_url'] ?? null,
                        $m['bio'] ?? null,
                        $m['sort_order'] ?? 0,
                        $m['is_chairperson'] ? 1 : 0
                    ]
                );
                $memberIdMap[(int)$m['id']] = $newMemId;
            }

            // Copy all groups and their linked members
            foreach ($source['groups'] as $g) {
                $newGroupId = (int)Database::insert(
                    "INSERT INTO board_term_groups (term_id, title, description, sort_order, created_at, updated_at)
                     VALUES (?, ?, ?, ?, NOW(), NOW())",
                    [$newTermId, $g['title'], $g['description'] ?? null, $g['sort_order'] ?? 0]
                );

                foreach ($g['members'] as $gm) {
                    $oldMemId = (int)($gm['member_id'] ?? 0);
                    $newMemId = $memberIdMap[$oldMemId] ?? null;
                    if ($newMemId) {
                        Database::insert(
                            "INSERT INTO board_term_group_members (group_id, member_id, role_in_group, sort_order, created_at, updated_at)
                             VALUES (?, ?, ?, ?, NOW(), NOW())",
                            [$newGroupId, $newMemId, $gm['role_in_group'] ?? 'กรรมการ', $gm['sort_order'] ?? 0]
                        );
                    }
                }
            }

            $pdo->commit();
            AuditService::log('board_terms', 'copy', (string)$newTermId, null, [
                'source_term_id' => $sourceTermId,
                'new_term_number' => $newTermNumber,
                'cloned_members' => count($allMembers),
                'cloned_groups' => count($source['groups'])
            ]);

            return $newTermId;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Update term metadata.
     */
    public static function updateTerm(int $termId, array $data, ?int $userId = null): void
    {
        $term = Database::first("SELECT * FROM board_terms WHERE id = ?", [$termId]);
        if (!$term) {
            throw new InvalidArgumentException('ไม่พบวาระที่ต้องการแก้ไข');
        }

        $termName = trim((string)($data['term_name'] ?? $term['term_name']));
        if ($termName === '') {
            $termName = $term['term_name'];
        }

        $yearStart = array_key_exists('year_start_be', $data) ? ($data['year_start_be'] ? (int)$data['year_start_be'] : null) : $term['year_start_be'];
        $yearEnd = array_key_exists('year_end_be', $data) ? ($data['year_end_be'] ? (int)$data['year_end_be'] : null) : $term['year_end_be'];
        $startDate = array_key_exists('start_date', $data) ? ($data['start_date'] ?: null) : $term['start_date'];
        $endDate = array_key_exists('end_date', $data) ? ($data['end_date'] ?: null) : $term['end_date'];
        $sourceUrl = array_key_exists('source_url', $data) ? ($data['source_url'] ?: null) : $term['source_url'];
        $note = array_key_exists('note', $data) ? ($data['note'] ?: null) : $term['note'];

        Database::execute(
            "UPDATE board_terms
             SET term_name = ?, year_start_be = ?, year_end_be = ?, start_date = ?, end_date = ?, source_url = ?, note = ?, updated_at = NOW()
             WHERE id = ?",
            [$termName, $yearStart, $yearEnd, $startDate, $endDate, $sourceUrl, $note, $termId]
        );

        AuditService::log('board_terms', 'update', (string)$termId, $term, [
            'term_name' => $termName,
            'year_start_be' => $yearStart,
            'year_end_be' => $yearEnd,
        ]);
    }

    /**
     * Save members of a category (director, advisor, auditor) with referential safety.
     */
    public static function saveTermMembers(int $termId, array $members, string $category = 'director', ?int $userId = null): void
    {
        if (!in_array($category, ['director', 'advisor', 'auditor'], true)) {
            throw new InvalidArgumentException('ประเภทสมาชิกไม่ถูกต้อง');
        }

        $term = Database::first("SELECT * FROM board_terms WHERE id = ?", [$termId]);
        if (!$term) {
            throw new InvalidArgumentException('ไม่พบวาระที่ระบุ');
        }

        $existingMembers = Database::query(
            "SELECT id, name FROM board_term_members WHERE term_id = ? AND category = ?",
            [$termId, $category]
        );
        $existingMap = [];
        foreach ($existingMembers as $em) {
            $existingMap[(int)$em['id']] = $em['name'];
        }

        $incomingIds = [];
        foreach ($members as $m) {
            if (!empty($m['id'])) {
                $incomingIds[] = (int)$m['id'];
            }
        }

        // Check for deletions
        $deletedIds = array_diff(array_keys($existingMap), $incomingIds);
        if (!empty($deletedIds)) {
            // Referential integrity check: are any of these members currently assigned to a subgroup in this term?
            $placeholders = implode(',', array_fill(0, count($deletedIds), '?'));
            $assigned = Database::query(
                "SELECT gm.member_id, m.name, g.title
                 FROM board_term_group_members gm
                 JOIN board_term_members m ON gm.member_id = m.id
                 JOIN board_term_groups g ON gm.group_id = g.id
                 WHERE gm.member_id IN ($placeholders)",
                array_values($deletedIds)
            );

            if (!empty($assigned)) {
                $firstName = $assigned[0]['name'];
                $groupTitle = $assigned[0]['title'];
                throw new InvalidArgumentException("ไม่สามารถลบ '{$firstName}' ได้ เนื่องจากได้รับมอบหมายอยู่ใน '{$groupTitle}' กรุณานำออกจากคณะย่อยก่อน");
            }
        }

        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            // Delete removed
            if (!empty($deletedIds)) {
                $placeholders = implode(',', array_fill(0, count($deletedIds), '?'));
                Database::execute("DELETE FROM board_term_members WHERE id IN ($placeholders)", array_values($deletedIds));
            }

            // Upsert / Insert
            foreach ($members as $index => $m) {
                $name = trim((string)($m['name'] ?? ''));
                $position = trim((string)($m['position'] ?? ''));
                if ($name === '' || $position === '') {
                    throw new InvalidArgumentException('กรุณากรอกชื่อและตำแหน่งให้ครบถ้วน');
                }

                $roleType = trim((string)($m['role_type'] ?? ($category === 'director' ? 'director' : $category)));
                $workplace = !empty($m['workplace']) ? trim((string)$m['workplace']) : null;
                $department = !empty($m['department']) ? trim((string)$m['department']) : null;
                $imageUrl = !empty($m['image_url']) ? trim((string)$m['image_url']) : (!empty($m['image']) ? trim((string)$m['image']) : null);
                $bio = !empty($m['bio']) ? trim((string)$m['bio']) : null;
                $sortOrder = isset($m['sort_order']) ? (int)$m['sort_order'] : $index;
                $isChair = !empty($m['is_chairperson']) ? 1 : 0;
                $personId = !empty($m['person_id']) ? (int)$m['person_id'] : null;

                $memId = !empty($m['id']) ? (int)$m['id'] : null;

                if ($memId && isset($existingMap[$memId])) {
                    Database::execute(
                        "UPDATE board_term_members
                         SET name = ?, position = ?, role_type = ?, workplace = ?, department = ?, image_url = ?, bio = ?, sort_order = ?, is_chairperson = ?, person_id = ?, updated_at = NOW()
                         WHERE id = ?",
                        [$name, $position, $roleType, $workplace, $department, $imageUrl, $bio, $sortOrder, $isChair, $personId, $memId]
                    );
                } else {
                    Database::insert(
                        "INSERT INTO board_term_members (term_id, person_id, category, name, position, role_type, workplace, department, image_url, bio, sort_order, is_chairperson, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                        [$termId, $personId, $category, $name, $position, $roleType, $workplace, $department, $imageUrl, $bio, $sortOrder, $isChair]
                    );
                }
            }

            Database::execute("UPDATE board_terms SET updated_at = NOW() WHERE id = ?", [$termId]);
            $pdo->commit();

            AuditService::log('board_terms', 'save_members', (string)$termId, null, [
                'category' => $category,
                'count' => count($members)
            ]);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Save groups and subgroup member assignments for a term.
     */
    public static function saveTermGroups(int $termId, array $groups, ?int $userId = null): void
    {
        $term = Database::first("SELECT * FROM board_terms WHERE id = ?", [$termId]);
        if (!$term) {
            throw new InvalidArgumentException('ไม่พบวาระที่ระบุ');
        }

        // Validate all member IDs exist in this term
        $validMembers = Database::query("SELECT id, name FROM board_term_members WHERE term_id = ?", [$termId]);
        $validMemberIds = array_column($validMembers, 'name', 'id');

        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            // Delete existing groups for this term (cascade deletes group_members)
            Database::execute("DELETE FROM board_term_groups WHERE term_id = ?", [$termId]);

            foreach ($groups as $gIndex => $g) {
                $title = trim((string)($g['title'] ?? ''));
                if ($title === '') {
                    throw new InvalidArgumentException('กรุณาระบุชื่อคณะกรรมการย่อย');
                }

                $desc = !empty($g['description']) ? trim((string)$g['description']) : null;
                $sortOrder = isset($g['sort_order']) ? (int)$g['sort_order'] : $gIndex;

                $groupId = (int)Database::insert(
                    "INSERT INTO board_term_groups (term_id, title, description, sort_order, created_at, updated_at)
                     VALUES (?, ?, ?, ?, NOW(), NOW())",
                    [$termId, $title, $desc, $sortOrder]
                );

                $members = $g['members'] ?? [];
                $seenMember = [];
                foreach ($members as $mIndex => $m) {
                    $memberId = is_array($m) ? (int)($m[0] ?? $m['member_id'] ?? 0) : (int)($m['member_id'] ?? 0);
                    $roleInGroup = is_array($m) ? (string)($m[1] ?? 'กรรมการ') : (string)($m['role_in_group'] ?? 'กรรมการ');

                    if (!isset($validMemberIds[$memberId])) {
                        throw new InvalidArgumentException("ไม่พบกรรมการ ID #{$memberId} ในวาระนี้");
                    }
                    if (isset($seenMember[$memberId])) {
                        throw new InvalidArgumentException("กรรมการ '{$validMemberIds[$memberId]}' ถูกเพิ่มซ้ำในคณะย่อยเดียวกัน");
                    }
                    $seenMember[$memberId] = true;

                    Database::insert(
                        "INSERT INTO board_term_group_members (group_id, member_id, role_in_group, sort_order, created_at, updated_at)
                         VALUES (?, ?, ?, ?, NOW(), NOW())",
                        [$groupId, $memberId, trim($roleInGroup), $mIndex]
                    );
                }
            }

            Database::execute("UPDATE board_terms SET updated_at = NOW() WHERE id = ?", [$termId]);
            $pdo->commit();

            AuditService::log('board_terms', 'save_groups', (string)$termId, null, [
                'groups_count' => count($groups)
            ]);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Atomically publish a term, setting it as current and archiving former current.
     */
    public static function publishTerm(int $termId, ?int $userId = null): void
    {
        $term = Database::first("SELECT * FROM board_terms WHERE id = ?", [$termId]);
        if (!$term) {
            throw new InvalidArgumentException('ไม่พบวาระที่ต้องการเผยแพร่');
        }

        // Preflight validation: must have at least 1 director
        $directorCount = (int)Database::value(
            "SELECT COUNT(*) FROM board_term_members WHERE term_id = ? AND category = 'director'",
            [$termId]
        );
        if ($directorCount === 0) {
            throw new InvalidArgumentException('ไม่สามารถเผยแพร่วาระที่ไม่มีรายชื่อคณะกรรมการได้');
        }

        $currentUserId = $userId ?? Auth::id();
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            // Demote previously active terms
            Database::execute(
                "UPDATE board_terms SET is_current = 0, status = 'archived', updated_at = NOW() WHERE is_current = 1 AND id != ?",
                [$termId]
            );

            // Promote target term
            Database::execute(
                "UPDATE board_terms
                 SET status = 'published', is_current = 1, publish_at = NULL, published_at = NOW(), published_by = ?, updated_at = NOW()
                 WHERE id = ?",
                [$currentUserId, $termId]
            );

            // Archive older boards revisions in public_content_revisions so newly published term takes effect
            Database::execute(
                "UPDATE public_content_revisions SET state = 'archived' WHERE section = 'boards' AND state = 'published'"
            );

            $pdo->commit();
            AuditService::log('board_terms', 'publish', (string)$termId, $term, [
                'term_number' => $term['term_number'],
                'term_name' => $term['term_name'],
                'published_by' => $currentUserId,
                'published_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Schedule a term for future publication.
     */
    public static function scheduleTerm(int $termId, string $publishAt, ?int $userId = null): void
    {
        $term = Database::first("SELECT * FROM board_terms WHERE id = ?", [$termId]);
        if (!$term) {
            throw new InvalidArgumentException('ไม่พบวาระที่ต้องการตั้งเวลา');
        }

        $targetTime = strtotime($publishAt);
        if ($targetTime === false || $targetTime <= time()) {
            throw new InvalidArgumentException('เวลาเผยแพร่ต้องเป็นเวลาในอนาคต');
        }

        // Validate at least 1 director
        $directorCount = (int)Database::value(
            "SELECT COUNT(*) FROM board_term_members WHERE term_id = ? AND category = 'director'",
            [$termId]
        );
        if ($directorCount === 0) {
            throw new InvalidArgumentException('ไม่สามารถตั้งเวลาเผยแพร่วาระที่ไม่มีรายชื่อคณะกรรมการได้');
        }

        $formatted = date('Y-m-d H:i:s', $targetTime);
        Database::execute(
            "UPDATE board_terms SET status = 'scheduled', publish_at = ?, is_current = 0, updated_at = NOW() WHERE id = ?",
            [$formatted, $termId]
        );

        AuditService::log('board_terms', 'schedule', (string)$termId, $term, [
            'publish_at' => $formatted,
            'scheduled_by' => $userId ?? Auth::id()
        ]);
    }

    /**
     * Check scheduled publication and return active public term payload.
     * Compatible with PublicContentService::catalog().
     */
    public static function getPublicActiveTerm(): ?array
    {
        if (!self::isTableAvailable()) {
            return null;
        }

        // Check if any scheduled term has reached its publication time
        $due = Database::first(
            "SELECT id FROM board_terms WHERE status = 'scheduled' AND publish_at <= NOW() ORDER BY publish_at ASC LIMIT 1"
        );
        if ($due) {
            try {
                self::publishTerm((int)$due['id'], null);
            } catch (\Throwable $e) {
                // If auto-transition encounters issue, proceed with current published
            }
        }

        $term = Database::first(
            "SELECT * FROM board_terms WHERE status = 'published' AND is_current = 1 ORDER BY published_at DESC, id DESC LIMIT 1"
        );
        if (!$term) {
            return null;
        }

        return self::formatTermForCatalog((int)$term['id']);
    }

    /**
     * Get preview payload for a term (even if draft or scheduled).
     */
    public static function getTermPreview(int $termId): ?array
    {
        return self::formatTermForCatalog($termId);
    }

    /**
     * Get list of historical terms (published or archived).
     */
    public static function getHistoricalTerms(): array
    {
        if (!self::isTableAvailable()) {
            return [];
        }

        $terms = Database::query(
            "SELECT id, term_number, term_name, year_start_be, year_end_be, status, is_current, published_at
             FROM board_terms
             WHERE status IN ('published', 'archived')
             ORDER BY term_number DESC, id DESC"
        );

        return array_map(function (array $t): array {
            $t['is_current'] = (int)$t['is_current'] === 1;
            return $t;
        }, $terms);
    }

    /**
     * Format a term into PublicContent catalog structure.
     */
    private static function formatTermForCatalog(int $termId): ?array
    {
        $term = Database::first("SELECT * FROM board_terms WHERE id = ?", [$termId]);
        if (!$term) {
            return null;
        }

        $members = Database::query(
            "SELECT * FROM board_term_members WHERE term_id = ? ORDER BY category ASC, sort_order ASC, id ASC",
            [$termId]
        );

        $boardMembers = [];
        $advisors = [];
        $auditors = [];
        $memberIdToPublicId = [];

        foreach ($members as $m) {
            $dbId = (int)$m['id'];
            $publicId = !empty($m['person_id']) ? (int)$m['person_id'] : $dbId;
            $memberIdToPublicId[$dbId] = $publicId;

            if ($m['category'] === 'director') {
                $boardMembers[] = [
                    'id' => $publicId,
                    'name' => $m['name'],
                    'position' => $m['position'],
                    'image' => resolve_media_url($m['image_url'] ?? null),
                    'term' => $term['term_name'],
                    'workplace' => $m['workplace'] ?? null,
                    'is_chairperson' => (int)$m['is_chairperson'] === 1,
                ];
            } elseif ($m['category'] === 'advisor') {
                $advisors[] = [$m['name'], $m['position']];
            } elseif ($m['category'] === 'auditor') {
                $auditors[] = [$m['name'], $m['position']];
            }
        }

        $groups = Database::query(
            "SELECT * FROM board_term_groups WHERE term_id = ? ORDER BY sort_order ASC, id ASC",
            [$termId]
        );

        $boardGroups = [];
        foreach ($groups as $g) {
            $groupMembers = Database::query(
                "SELECT gm.member_id, gm.role_in_group
                 FROM board_term_group_members gm
                 WHERE gm.group_id = ?
                 ORDER BY gm.sort_order ASC, gm.id ASC",
                [(int)$g['id']]
            );

            $gMembers = [];
            foreach ($groupMembers as $gm) {
                $memDbId = (int)$gm['member_id'];
                $pubId = $memberIdToPublicId[$memDbId] ?? $memDbId;
                $gMembers[] = [$pubId, $gm['role_in_group']];
            }

            $boardGroups[] = [
                'title' => $g['title'],
                'members' => $gMembers,
            ];
        }

        return [
            'term_id' => (int)$term['id'],
            'term_number' => (int)$term['term_number'],
            'term_name' => $term['term_name'],
            'status' => $term['status'],
            'is_current' => (int)$term['is_current'] === 1,
            'source_url' => $term['source_url'],
            'note' => $term['note'],
            'published_at' => $term['published_at'],
            'boardMembers' => $boardMembers,
            'boardGroups' => $boardGroups,
            'advisors' => $advisors,
            'auditors' => $auditors,
        ];
    }
}
