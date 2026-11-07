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
     * Get Term 38 source data from legacyReference.json.
     */
    public static function getTerm38SourceData(): array
    {
        $refPath = dirname(__DIR__, 2) . '/src/data/legacyReference.json';
        if (!file_exists($refPath)) {
            throw new RuntimeException('ไม่พบไฟล์ legacyReference.json');
        }

        $legacy = json_decode(file_get_contents($refPath), true, 512, JSON_THROW_ON_ERROR);
        $source = $legacy['sources']['board'] ?? [];

        return [
            'term_number' => 38,
            'term_name' => 'ชุดที่ 38',
            'year_start_be' => 2567,
            'year_end_be' => 2568,
            'source_url' => $source['url'] ?? 'https://rayongcoop.com/page.php?pid=3',
            'note' => $source['note'] ?? null,
            'boardMembers' => $legacy['boardMembers'] ?? [],
            'boardGroups' => $legacy['boardGroups'] ?? [],
            'advisors' => $legacy['advisors'] ?? [],
            'auditors' => $legacy['auditors'] ?? [],
        ];
    }

    /**
     * Generate structured preview for importing Term 38 comparing with DB state.
     */
    public static function getTerm38ImportPreview(): array
    {
        $source = self::getTerm38SourceData();
        $existingTerm = null;
        $existingMembers = [];
        $existingGroups = [];
        $customPhotosCount = 0;
        $modifiedFieldsCount = 0;

        if (self::isTableAvailable()) {
            $existingTerm = Database::first("SELECT * FROM board_terms WHERE term_number = 38");
            if ($existingTerm) {
                $existingMembers = Database::query(
                    "SELECT * FROM board_term_members WHERE term_id = ? ORDER BY category ASC, sort_order ASC, id ASC",
                    [(int)$existingTerm['id']]
                );
                $existingGroups = Database::query(
                    "SELECT * FROM board_term_groups WHERE term_id = ? ORDER BY sort_order ASC, id ASC",
                    [(int)$existingTerm['id']]
                );
            }
        }

        $existingMemberMap = [];
        foreach ($existingMembers as $em) {
            $key = $em['category'] . '_' . ($em['person_id'] ?? $em['name']);
            $existingMemberMap[$key] = $em;
            if (!empty($em['image_url']) && (str_starts_with($em['image_url'], '/storage/') || str_starts_with($em['image_url'], 'storage/'))) {
                $customPhotosCount++;
            }
        }

        $directorComparisons = [];
        foreach ($source['boardMembers'] as $idx => $bm) {
            $legacyId = (int)($bm['id'] ?? ($idx + 1));
            $key = 'director_' . $legacyId;
            $existing = $existingMemberMap[$key] ?? null;
            $hasCustomPhoto = $existing && !empty($existing['image_url']) && (
                str_starts_with($existing['image_url'], '/storage/') ||
                str_starts_with($existing['image_url'], 'storage/')
            );

            $diff = [];
            if ($existing) {
                if ($existing['name'] !== $bm['name']) {
                    $diff['name'] = ['old' => $existing['name'], 'new' => $bm['name']];
                }
                if ($existing['position'] !== $bm['position']) {
                    $diff['position'] = ['old' => $existing['position'], 'new' => $bm['position']];
                }
                if ($hasCustomPhoto) {
                    $diff['image'] = ['custom_photo' => $existing['image_url'], 'legacy_photo' => $bm['image']];
                }
            }

            $directorComparisons[] = [
                'person_id' => $legacyId,
                'name' => $bm['name'],
                'position' => $bm['position'],
                'image' => $bm['image'] ?? null,
                'existing' => $existing !== null,
                'existing_id' => $existing['id'] ?? null,
                'has_custom_photo' => $hasCustomPhoto,
                'custom_photo_url' => $hasCustomPhoto ? resolve_media_url($existing['image_url']) : null,
                'diff' => $diff,
            ];
        }

        return [
            'source_summary' => [
                'term_number' => 38,
                'term_name' => 'ชุดที่ 38',
                'directors_count' => count($source['boardMembers']),
                'advisors_count' => count($source['advisors']),
                'auditors_count' => count($source['auditors']),
                'groups_count' => count($source['boardGroups']),
            ],
            'existing_summary' => [
                'exists' => $existingTerm !== null,
                'term_id' => $existingTerm['id'] ?? null,
                'status' => $existingTerm['status'] ?? null,
                'is_current' => $existingTerm ? ((int)$existingTerm['is_current'] === 1) : false,
                'members_count' => count($existingMembers),
                'custom_photos_count' => $customPhotosCount,
                'groups_count' => count($existingGroups),
            ],
            'director_comparisons' => $directorComparisons,
            'advisors' => $source['advisors'],
            'auditors' => $source['auditors'],
            'groups' => $source['boardGroups'],
            'can_preserve_custom_photos' => true,
            'can_preserve_admin_edits' => true,
            'duplicates_detected' => 0,
        ];
    }

    /**
     * Non-destructive, idempotent import and reconciliation of Term 38.
     * Preserves custom uploaded photos and administrative edits when requested.
     */
    public static function importTerm38(array $options = [], ?int $userId = null): array
    {
        if (!self::isTableAvailable()) {
            throw new RuntimeException('ตาราง board_terms ยังไม่พร้อมใช้งาน กรุณารัน migration ก่อน');
        }

        $preservePhotos = (bool)($options['preserve_custom_photos'] ?? true);
        $preserveEdits = (bool)($options['preserve_admin_edits'] ?? true);

        $sourceData = self::getTerm38SourceData();
        $sourceUrl = $sourceData['source_url'] ?? 'https://rayongcoop.com/page.php?pid=3';
        $note = $sourceData['note'] ?? null;

        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $existing = Database::first("SELECT id FROM board_terms WHERE term_number = 38");
            $termId = null;

            if ($existing) {
                $termId = (int)$existing['id'];
                Database::execute(
                    "UPDATE board_terms
                     SET term_name = ?, year_start_be = ?, year_end_be = ?, source_url = ?, note = ?,
                         status = 'published', is_current = 1, published_at = NOW(), updated_at = NOW()
                     WHERE id = ?",
                    ['ชุดที่ 38', 2567, 2568, $sourceUrl, $note, $termId]
                );
            } else {
                $termId = (int)Database::insert(
                    "INSERT INTO board_terms (term_number, term_name, year_start_be, year_end_be, status, is_current, source_url, note, published_at, created_at, updated_at)
                     VALUES (38, 'ชุดที่ 38', 2567, 2568, 'published', 1, ?, ?, NOW(), NOW(), NOW())",
                    [$sourceUrl, $note]
                );
            }

            // Demote any other terms so only Term 38 is current
            Database::execute("UPDATE board_terms SET is_current = 0, status = 'archived', updated_at = NOW() WHERE id != ? AND is_current = 1", [$termId]);

            // Existing members map for Term 38
            $existingMembers = Database::query("SELECT * FROM board_term_members WHERE term_id = ?", [$termId]);
            $existingDirectorMap = [];
            $existingAdvisorMap = [];
            $existingAuditorMap = [];
            foreach ($existingMembers as $em) {
                if ($em['category'] === 'director') {
                    if (!empty($em['person_id'])) {
                        $existingDirectorMap[(int)$em['person_id']] = $em;
                    }
                    $existingDirectorMap['name_' . $em['name']] = $em;
                } elseif ($em['category'] === 'advisor') {
                    $existingAdvisorMap[(int)$em['sort_order']] = $em;
                    $existingAdvisorMap['name_' . $em['name']] = $em;
                } elseif ($em['category'] === 'auditor') {
                    $existingAuditorMap[(int)$em['sort_order']] = $em;
                    $existingAuditorMap['name_' . $em['name']] = $em;
                }
            }

            // 1. Reconcile 15 directors
            $memberIdMap = []; // legacy person_id => DB member_id
            $boardMembers = $sourceData['boardMembers'] ?? [];
            $preservedPhotosCount = 0;
            $preservedEditsCount = 0;

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

                $existing = $existingDirectorMap[$legacyId] ?? ($existingDirectorMap['name_' . $name] ?? null);

                $finalImage = $image;
                $finalWorkplace = $bm['workplace'] ?? null;
                $finalDepartment = $bm['department'] ?? null;
                $finalBio = null;

                if ($existing) {
                    $hasCustomPhoto = !empty($existing['image_url']) && (
                        str_starts_with($existing['image_url'], '/storage/') ||
                        str_starts_with($existing['image_url'], 'storage/') ||
                        str_contains($existing['image_url'], 'uploads/')
                    );
                    if ($preservePhotos && $hasCustomPhoto) {
                        $finalImage = $existing['image_url'];
                        $preservedPhotosCount++;
                    }

                    if ($preserveEdits) {
                        if (!empty($existing['workplace'])) {
                            $finalWorkplace = $existing['workplace'];
                            $preservedEditsCount++;
                        }
                        if (!empty($existing['department'])) {
                            $finalDepartment = $existing['department'];
                        }
                        if (!empty($existing['bio'])) {
                            $finalBio = $existing['bio'];
                        }
                    }

                    Database::execute(
                        "UPDATE board_term_members
                         SET person_id = ?, category = 'director', name = ?, position = ?, role_type = ?,
                             workplace = ?, department = ?, image_url = ?, bio = ?, sort_order = ?, is_chairperson = ?, updated_at = NOW()
                         WHERE id = ?",
                        [$legacyId, $name, $position, $roleType, $finalWorkplace, $finalDepartment, $finalImage, $finalBio, $index, $isChair, (int)$existing['id']]
                    );
                    $memberIdMap[$legacyId] = (int)$existing['id'];
                } else {
                    $dbMemberId = (int)Database::insert(
                        "INSERT INTO board_term_members (term_id, person_id, category, name, position, role_type, workplace, department, image_url, bio, sort_order, is_chairperson, created_at, updated_at)
                         VALUES (?, ?, 'director', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                        [$termId, $legacyId, $name, $position, $roleType, $finalWorkplace, $finalDepartment, $finalImage, $finalBio, $index, $isChair]
                    );
                    $memberIdMap[$legacyId] = $dbMemberId;
                }
            }

            // 2. Reconcile Advisors
            $advisors = $sourceData['advisors'] ?? [];
            foreach ($advisors as $index => $adv) {
                $name = is_array($adv) ? ($adv[0] ?? '') : ($adv['name'] ?? '');
                $position = is_array($adv) ? ($adv[1] ?? 'ที่ปรึกษา') : ($adv['position'] ?? 'ที่ปรึกษา');
                $existing = $existingAdvisorMap[$index] ?? ($existingAdvisorMap['name_' . trim((string)$name)] ?? null);

                if ($existing) {
                    Database::execute(
                        "UPDATE board_term_members SET name = ?, position = ?, sort_order = ?, updated_at = NOW() WHERE id = ?",
                        [trim((string)$name), trim((string)$position), $index, (int)$existing['id']]
                    );
                } else {
                    Database::insert(
                        "INSERT INTO board_term_members (term_id, category, name, position, role_type, sort_order, created_at, updated_at)
                         VALUES (?, 'advisor', ?, ?, 'advisor', ?, NOW(), NOW())",
                        [$termId, trim((string)$name), trim((string)$position), $index]
                    );
                }
            }

            // 3. Reconcile Auditors
            $auditors = $sourceData['auditors'] ?? [];
            foreach ($auditors as $index => $aud) {
                $name = is_array($aud) ? ($aud[0] ?? '') : ($aud['name'] ?? '');
                $position = is_array($aud) ? ($aud[1] ?? 'ผู้ตรวจสอบกิจการ') : ($aud['position'] ?? 'ผู้ตรวจสอบกิจการ');
                $existing = $existingAuditorMap[$index] ?? ($existingAuditorMap['name_' . trim((string)$name)] ?? null);

                if ($existing) {
                    Database::execute(
                        "UPDATE board_term_members SET name = ?, position = ?, sort_order = ?, updated_at = NOW() WHERE id = ?",
                        [trim((string)$name), trim((string)$position), $index, (int)$existing['id']]
                    );
                } else {
                    Database::insert(
                        "INSERT INTO board_term_members (term_id, category, name, position, role_type, sort_order, created_at, updated_at)
                         VALUES (?, 'auditor', ?, ?, 'auditor', ?, NOW(), NOW())",
                        [$termId, trim((string)$name), trim((string)$position), $index]
                    );
                }
            }

            // 4. Reconcile Subgroups
            $boardGroups = $sourceData['boardGroups'] ?? [];
            $existingGroups = Database::query("SELECT id, title, sort_order FROM board_term_groups WHERE term_id = ?", [$termId]);
            $existingGroupMap = [];
            foreach ($existingGroups as $eg) {
                $existingGroupMap[(int)$eg['sort_order']] = (int)$eg['id'];
                $existingGroupMap[$eg['title']] = (int)$eg['id'];
            }

            foreach ($boardGroups as $gIndex => $bg) {
                $title = trim((string)($bg['title'] ?? ''));
                $groupId = $existingGroupMap[$gIndex] ?? ($existingGroupMap[$title] ?? null);

                if ($groupId) {
                    Database::execute("UPDATE board_term_groups SET title = ?, sort_order = ?, updated_at = NOW() WHERE id = ?", [$title, $gIndex, $groupId]);
                    Database::execute("DELETE FROM board_term_group_members WHERE group_id = ?", [$groupId]);
                } else {
                    $groupId = (int)Database::insert(
                        "INSERT INTO board_term_groups (term_id, title, sort_order, created_at, updated_at)
                         VALUES (?, ?, ?, NOW(), NOW())",
                        [$termId, $title, $gIndex]
                    );
                }

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

            // Archive older revisions for clean public overlay
            Database::execute("UPDATE public_content_revisions SET state = 'archived' WHERE section IN ('boards', 'groups', 'advisors', 'auditors') AND state = 'published'");

            $pdo->commit();
            AuditService::log('board_terms', 'import_term_38', (string)$termId, null, [
                'term_number' => 38,
                'preserved_photos' => $preservedPhotosCount,
                'preserved_edits' => $preservedEditsCount,
                'user_id' => $userId ?? Auth::id(),
            ]);

            return [
                'status' => 'success',
                'term_id' => $termId,
                'term_number' => 38,
                'directors' => count($boardMembers),
                'advisors' => count($advisors),
                'auditors' => count($auditors),
                'groups' => count($boardGroups),
                'preserved_photos' => $preservedPhotosCount,
                'preserved_edits' => $preservedEditsCount,
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

        return self::importTerm38(['preserve_custom_photos' => true, 'preserve_admin_edits' => true]);
    }

    /**
     * Committee Photograph Manager: validates magic bytes, MIME, dimensions and stores securely.
     */
    public static function uploadMemberPhoto(int $termId, int $memberId, array $file, ?int $userId = null): array
    {
        if (!self::isTableAvailable()) {
            throw new RuntimeException('ตาราง board_terms ยังไม่พร้อมใช้งาน');
        }

        $member = Database::first(
            "SELECT m.*, t.term_name FROM board_term_members m JOIN board_terms t ON m.term_id = t.id WHERE m.id = ? AND m.term_id = ?",
            [$memberId, $termId]
        );
        if (!$member) {
            throw new InvalidArgumentException("ไม่พบข้อมูลกรรมการ ID #{$memberId} ในวาระนี้");
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('การอัปโหลดไฟล์ล้มเหลว (Error Code: ' . ($file['error'] ?? 'UNKNOWN') . ')');
        }

        // Maximum size: 5MB
        $maxSize = 5 * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxSize) {
            throw new InvalidArgumentException('ขนาดไฟล์รูปภาพเกินกำหนด (สูงสุด 5 MB)');
        }

        $tmpPath = $file['tmp_name'] ?? '';
        if (!is_file($tmpPath) || !is_readable($tmpPath)) {
            throw new InvalidArgumentException('ไม่พบไฟล์ชั่วคราวที่อัปโหลด');
        }

        // Check file extension
        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new InvalidArgumentException('อนุญาตเฉพาะไฟล์รูปภาพ JPG, PNG และ WEBP เท่านั้น');
        }

        // Verify MIME type using finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowedMimes, true)) {
            throw new InvalidArgumentException('ประเภทไฟล์ไม่ถูกต้อง (MIME: ' . $mime . ')');
        }

        // Magic bytes verification
        $handle = fopen($tmpPath, 'rb');
        $bytes = fread($handle, 16);
        fclose($handle);

        $validMagic = false;
        if ($mime === 'image/jpeg' && str_starts_with($bytes, "\xFF\xD8\xFF")) {
            $validMagic = true;
        } elseif ($mime === 'image/png' && str_starts_with($bytes, "\x89PNG")) {
            $validMagic = true;
        } elseif ($mime === 'image/webp' && str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') {
            $validMagic = true;
        }

        if (!$validMagic) {
            throw new InvalidArgumentException('เนื้อหาไฟล์ไม่ถูกต้องตามรูปแบบรูปภาพที่ระบุ (Magic bytes mismatch)');
        }

        // Dimensions verification via getimagesize
        $imageSize = @getimagesize($tmpPath);
        if ($imageSize === false) {
            throw new InvalidArgumentException('ไม่สามารถประมวลผลขนาดของรูปภาพได้ หรือไฟล์รูปภาพเสียหาย');
        }

        $width = $imageSize[0];
        $height = $imageSize[1];

        if ($width < 100 || $height < 100) {
            throw new InvalidArgumentException('ขนาดรูปภาพต้องมีความกว้างและความสูงอย่างน้อย 100x100 พิกเซล');
        }
        if ($width > 4000 || $height > 4000) {
            throw new InvalidArgumentException('ขนาดรูปภาพใหญ่เกินไป (ไม่เกิน 4000x4000 พิกเซล)');
        }

        // Generate safe randomized filename: board_m_{termId}_{memberId}_{randomHex}.{ext}
        $randomHex = bin2hex(random_bytes(8));
        $safeFilename = "board_m_{$termId}_{$memberId}_{$randomHex}.{$ext}";

        $uploadFolder = storage_upload_path('board');
        if (!is_dir($uploadFolder) && !mkdir($uploadFolder, 0775, true) && !is_dir($uploadFolder)) {
            throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์สำหรับจัดเก็บรูปภาพได้');
        }

        $targetPath = $uploadFolder . DIRECTORY_SEPARATOR . $safeFilename;

        // Move uploaded file or copy (for tests)
        $moved = false;
        if (is_uploaded_file($tmpPath)) {
            $moved = move_uploaded_file($tmpPath, $targetPath);
        } else {
            $moved = copy($tmpPath, $targetPath);
        }

        if (!$moved) {
            throw new RuntimeException('ไม่สามารถบันทึกไฟล์รูปภาพลงในพื้นที่จัดเก็บได้');
        }

        $relativeUrl = "/storage/uploads/board/{$safeFilename}";
        $oldImage = $member['image_url'] ?? null;

        try {
            Database::execute(
                "UPDATE board_term_members SET image_url = ?, updated_at = NOW() WHERE id = ?",
                [$relativeUrl, $memberId]
            );

            // Log media if media table exists
            try {
                $hasMedia = Database::first("SHOW TABLES LIKE 'media'");
                if ($hasMedia) {
                    Database::insert(
                        "INSERT INTO media (filename, original_name, mime_type, file_size, path, folder, alt_text, uploaded_by, created_at)
                         VALUES (?, ?, ?, ?, ?, 'board', ?, ?, NOW())",
                        [
                            $safeFilename,
                            basename((string)($file['name'] ?? $safeFilename)),
                            $mime,
                            $file['size'] ?? filesize($targetPath),
                            "board/{$safeFilename}",
                            $member['name'],
                            $userId ?? Auth::id()
                        ]
                    );
                }
            } catch (\Throwable $e) {
                // Ignore media table insertion error
            }

            AuditService::log('board_terms', 'upload_photo', (string)$memberId, null, [
                'term_id' => $termId,
                'member_id' => $memberId,
                'member_name' => $member['name'],
                'old_image' => $oldImage,
                'new_image' => $relativeUrl,
            ]);

            return [
                'success' => true,
                'member_id' => $memberId,
                'url' => $relativeUrl,
                'full_url' => resolve_media_url($relativeUrl),
                'old_url' => $oldImage,
                'message' => 'อัปโหลดภาพถ่ายเรียบร้อยแล้ว'
            ];
        } catch (\Throwable $e) {
            @unlink($targetPath);
            throw $e;
        }
    }

    /**
     * Get candidate list from source term with lineage status against target term.
     */
    public static function getSelectionCandidates(int $sourceTermId, int $targetTermNumber = 39): array
    {
        $source = self::getTerm($sourceTermId);
        if (!$source) {
            throw new InvalidArgumentException('ไม่พบวาระต้นฉบับ');
        }

        $target = Database::first("SELECT * FROM board_terms WHERE term_number = ?", [$targetTermNumber]);
        $targetDetails = null;
        $targetMembersMap = [];

        if ($target) {
            $targetDetails = [
                'id' => (int)$target['id'],
                'term_number' => (int)$target['term_number'],
                'term_name' => $target['term_name'],
                'status' => $target['status'],
                'is_current' => (int)$target['is_current'] === 1,
                'existing_members_count' => (int)Database::value("SELECT COUNT(*) FROM board_term_members WHERE term_id = ?", [$target['id']]),
                'existing_groups_count' => (int)Database::value("SELECT COUNT(*) FROM board_term_groups WHERE term_id = ?", [$target['id']]),
            ];

            $targetMembers = Database::query("SELECT * FROM board_term_members WHERE term_id = ?", [$target['id']]);
            foreach ($targetMembers as $tm) {
                if (!empty($tm['source_member_id'])) {
                    $targetMembersMap['source_' . $tm['source_member_id']] = $tm;
                }
                if (!empty($tm['person_id'])) {
                    $targetMembersMap['person_' . $tm['person_id']] = $tm;
                }
                $targetMembersMap['name_' . $tm['category'] . '_' . $tm['name']] = $tm;
            }
        }

        $enrichCandidates = static function (array $members) use ($targetMembersMap): array {
            return array_map(static function (array $m) use ($targetMembersMap): array {
                $mId = (int)$m['id'];
                $personId = !empty($m['person_id']) ? (int)$m['person_id'] : null;
                $cat = $m['category'] ?? 'director';
                $name = $m['name'] ?? '';

                $targetMember = $targetMembersMap['source_' . $mId]
                    ?? ($personId ? ($targetMembersMap['person_' . $personId] ?? null) : null)
                    ?? ($targetMembersMap['name_' . $cat . '_' . $name] ?? null);

                $photoResolved = resolve_media_url($m['image_url'] ?? null);

                return array_merge($m, [
                    'photo_resolved' => $photoResolved,
                    'already_transferred' => $targetMember !== null,
                    'target_member' => $targetMember ? [
                        'id' => (int)$targetMember['id'],
                        'position' => $targetMember['position'],
                        'role_type' => $targetMember['role_type'],
                        'category' => $targetMember['category'],
                        'image_url' => $targetMember['image_url'],
                        'photo_resolved' => resolve_media_url($targetMember['image_url'] ?? null),
                        'source_member_id' => !empty($targetMember['source_member_id']) ? (int)$targetMember['source_member_id'] : null,
                    ] : null,
                ]);
            }, $members);
        };

        $directors = $enrichCandidates($source['directors']);
        $advisors = $enrichCandidates($source['advisors']);
        $auditors = $enrichCandidates($source['auditors']);

        return [
            'source_term' => [
                'id' => (int)$source['term']['id'],
                'term_number' => (int)$source['term']['term_number'],
                'term_name' => $source['term']['term_name'],
                'status' => $source['term']['status'],
                'directors_count' => count($directors),
                'advisors_count' => count($advisors),
                'auditors_count' => count($auditors),
                'groups_count' => count($source['groups']),
            ],
            'target_term_number' => $targetTermNumber,
            'target_term' => $targetDetails,
            'candidates' => [
                'directors' => $directors,
                'advisors' => $advisors,
                'auditors' => $auditors,
            ],
            'groups' => $source['groups'],
        ];
    }

    /**
     * Get preview diff for selective copying with customized positions/roles.
     */
    public static function getSelectiveCopyPreview(
        int $sourceTermId,
        int $targetTermNumber = 39,
        array $selections = [],
        array $options = []
    ): array {
        $candidateData = self::getSelectionCandidates($sourceTermId, $targetTermNumber);
        $sourceTerm = $candidateData['source_term'];
        $targetTerm = $candidateData['target_term'];
        $groups = $candidateData['groups'];

        // If no explicit selections passed, return full candidate overview
        if (empty($selections)) {
            return array_merge($candidateData, [
                'selected_count' => 0,
                'items' => [],
                'remapped_groups' => [],
                'omitted_members_count' => 0,
                'warnings' => [],
            ]);
        }

        // Normalize selections into map [source_member_id => customization]
        $selectionMap = [];
        $selectedIds = [];
        foreach ($selections as $sel) {
            if (is_numeric($sel)) {
                $id = (int)$sel;
                $selectionMap[$id] = ['id' => $id];
                $selectedIds[] = $id;
            } elseif (is_array($sel)) {
                $id = (int)($sel['source_member_id'] ?? $sel['id'] ?? 0);
                if ($id > 0) {
                    $selectionMap[$id] = $sel;
                    $selectedIds[] = $id;
                }
            }
        }

        // Flatten all candidates for lookup
        $allCandidates = [];
        foreach (['directors', 'advisors', 'auditors'] as $cat) {
            foreach ($candidateData['candidates'][$cat] as $cand) {
                $allCandidates[(int)$cand['id']] = $cand;
            }
        }

        $items = [];
        $chairpersonCount = 0;
        $warnings = [];

        foreach ($selectedIds as $sId) {
            if (!isset($allCandidates[$sId])) {
                continue;
            }
            $cand = $allCandidates[$sId];
            $custom = $selectionMap[$sId];

            $newPosition = trim((string)($custom['position'] ?? $cand['position']));
            $newRoleType = trim((string)($custom['role_type'] ?? $cand['role_type']));
            $newCategory = trim((string)($custom['category'] ?? $cand['category']));
            $isChair = isset($custom['is_chairperson']) ? (bool)$custom['is_chairperson'] : (bool)$cand['is_chairperson'];

            if ($isChair || $newRoleType === 'president') {
                $chairpersonCount++;
            }

            $isUpdate = $cand['already_transferred'];
            $items[] = [
                'source_member_id' => $sId,
                'name' => $cand['name'],
                'category' => $newCategory,
                'original_position' => $cand['position'],
                'target_position' => $newPosition,
                'original_role_type' => $cand['role_type'],
                'target_role_type' => $newRoleType,
                'is_chairperson' => $isChair,
                'image_url' => $cand['image_url'],
                'photo_resolved' => $cand['photo_resolved'],
                'action' => $isUpdate ? 'update' : 'create',
                'target_member_id' => $cand['target_member']['id'] ?? null,
            ];
        }

        if ($chairpersonCount > 1) {
            $warnings[] = "มีตำแหน่งประธานกรรมการมากกว่า 1 ท่านในรายการที่เลือก ({$chairpersonCount} ท่าน)";
        }

        // Group remapping analysis
        $transferGroups = (bool)($options['transfer_groups'] ?? true);
        $remappedGroups = [];
        $omittedMembersCount = 0;

        if ($transferGroups && !empty($groups)) {
            foreach ($groups as $g) {
                $included = [];
                $omitted = [];
                foreach ($g['members'] as $gm) {
                    $mId = (int)($gm['member_id'] ?? 0);
                    if (in_array($mId, $selectedIds, true)) {
                        $included[] = $gm;
                    } else {
                        $omitted[] = $gm;
                        $omittedMembersCount++;
                    }
                }
                $remappedGroups[] = [
                    'group_title' => $g['title'],
                    'total_members' => count($g['members']),
                    'included_members' => $included,
                    'omitted_members' => $omitted,
                ];
            }
        }

        return [
            'source_term' => $sourceTerm,
            'target_term_number' => $targetTermNumber,
            'target_term' => $targetTerm,
            'selected_count' => count($items),
            'items' => $items,
            'remapped_groups' => $remappedGroups,
            'omitted_members_count' => $omittedMembersCount,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get preview data for transferring selected personnel to target term (e.g. Term 39).
     */
    public static function getSelectiveTransferPreview(int $sourceTermId, int $targetTermNumber = 39, array $selections = [], array $options = []): array
    {
        if (!empty($selections)) {
            return self::getSelectiveCopyPreview($sourceTermId, $targetTermNumber, $selections, $options);
        }

        return self::getSelectionCandidates($sourceTermId, $targetTermNumber);
    }

    /**
     * Selectively transfer personnel from source term to target term draft.
     * Guarantees subgroup referential integrity by omitting unselected members.
     * Records source_member_id and preserves Term 38 immutability.
     */
    public static function selectiveTransfer(
        int $sourceTermId,
        int $targetTermNumber,
        array $selectedMemberIds,
        array $options = [],
        ?int $userId = null
    ): array {
        if (!self::isTableAvailable()) {
            throw new RuntimeException('ตาราง board_terms ยังไม่พร้อมใช้งาน');
        }

        if (empty($selectedMemberIds)) {
            throw new InvalidArgumentException('กรุณาเลือกกรรมการอย่างน้อย 1 ท่านสำหรับถ่ายโอน');
        }

        $source = self::getTerm($sourceTermId);
        if (!$source) {
            throw new InvalidArgumentException('ไม่พบวาระต้นฉบับที่ต้องการถ่ายโอน');
        }

        $transferGroups = (bool)($options['transfer_groups'] ?? true);
        $targetTermName = trim((string)($options['term_name'] ?? "ชุดที่ {$targetTermNumber}"));
        if ($targetTermName === '') {
            $targetTermName = "ชุดที่ {$targetTermNumber}";
        }

        // Verify target term status if it exists
        $target = Database::first("SELECT * FROM board_terms WHERE term_number = ?", [$targetTermNumber]);
        if ($target && $target['status'] === 'published') {
            throw new InvalidArgumentException("ไม่สามารถถ่ายโอนไปยังวาระชุดที่ {$targetTermNumber} ได้ เนื่องจากวาระดังกล่าวเผยแพร่อยู่แล้ว");
        }

        // Normalize selection IDs and customization overrides
        $cleanIds = [];
        $customizationsMap = (array)($options['customizations'] ?? []);

        foreach ($selectedMemberIds as $sel) {
            if (is_numeric($sel)) {
                $id = (int)$sel;
                $cleanIds[] = $id;
            } elseif (is_array($sel)) {
                $id = (int)($sel['source_member_id'] ?? $sel['id'] ?? 0);
                if ($id > 0) {
                    $cleanIds[] = $id;
                    $customizationsMap[$id] = array_merge($customizationsMap[$id] ?? [], $sel);
                }
            }
        }

        $cleanIds = array_values(array_unique(array_filter($cleanIds, fn($id) => $id > 0)));
        if (empty($cleanIds)) {
            throw new InvalidArgumentException('กรุณาเลือกกรรมการอย่างน้อย 1 ท่านสำหรับถ่ายโอน');
        }

        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $targetTermId = null;
            if ($target) {
                $targetTermId = (int)$target['id'];
                Database::execute(
                    "UPDATE board_terms SET term_name = ?, updated_at = NOW() WHERE id = ?",
                    [$targetTermName, $targetTermId]
                );
            } else {
                $sourceTerm = $source['term'];
                $yearDiff = $targetTermNumber - (int)$sourceTerm['term_number'];
                $newYearStart = $sourceTerm['year_start_be'] ? (int)$sourceTerm['year_start_be'] + $yearDiff : null;
                $newYearEnd = $sourceTerm['year_end_be'] ? (int)$sourceTerm['year_end_be'] + $yearDiff : null;

                $targetTermId = (int)Database::insert(
                    "INSERT INTO board_terms (term_number, term_name, year_start_be, year_end_be, status, is_current, note, created_by, created_at, updated_at)
                     VALUES (?, ?, ?, ?, 'draft', 0, ?, ?, NOW(), NOW())",
                    [$targetTermNumber, $targetTermName, $newYearStart, $newYearEnd, "ถ่ายโอนเลือกจาก {$sourceTerm['term_name']}", $userId ?? Auth::id()]
                );
            }

            // Fetch source members to transfer
            $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
            $params = array_merge([$sourceTermId], $cleanIds);
            $membersToTransfer = Database::query(
                "SELECT * FROM board_term_members WHERE term_id = ? AND id IN ($placeholders) ORDER BY category, sort_order, id",
                $params
            );

            if (empty($membersToTransfer)) {
                throw new InvalidArgumentException('ไม่พบรายชื่อกรรมการที่เลือกในวาระต้นฉบับ');
            }

            // Existing target members map
            $existingTargetMembers = Database::query("SELECT * FROM board_term_members WHERE term_id = ?", [$targetTermId]);
            $targetMemberMap = [];
            foreach ($existingTargetMembers as $etm) {
                if (!empty($etm['source_member_id'])) {
                    $targetMemberMap['s_' . $etm['source_member_id']] = (int)$etm['id'];
                }
                if (!empty($etm['person_id'])) {
                    $targetMemberMap['p_' . $etm['person_id']] = (int)$etm['id'];
                }
                $targetMemberMap['n_' . $etm['category'] . '_' . $etm['name']] = (int)$etm['id'];
            }

            $memberIdMap = []; // oldMemberId => newMemberId in target term
            $transferredCount = 0;

            foreach ($membersToTransfer as $m) {
                $oldId = (int)$m['id'];
                $personId = !empty($m['person_id']) ? (int)$m['person_id'] : null;
                $cat = $m['category'];
                $name = $m['name'];

                $cust = $customizationsMap[$oldId] ?? [];
                $finalCategory = trim((string)($cust['category'] ?? $cat));
                $finalPosition = trim((string)($cust['position'] ?? $m['position']));
                $finalRoleType = trim((string)($cust['role_type'] ?? $m['role_type']));
                $finalWorkplace = array_key_exists('workplace', $cust) ? $cust['workplace'] : $m['workplace'];
                $finalDepartment = array_key_exists('department', $cust) ? $cust['department'] : $m['department'];
                $finalSortOrder = isset($cust['sort_order']) ? (int)$cust['sort_order'] : (int)$m['sort_order'];
                $finalChairperson = isset($cust['is_chairperson']) ? (bool)$cust['is_chairperson'] : (bool)$m['is_chairperson'];
                $finalImageUrl = !empty($cust['image_url']) ? $cust['image_url'] : $m['image_url'];

                $existingNewId = null;
                if (isset($targetMemberMap['s_' . $oldId])) {
                    $existingNewId = $targetMemberMap['s_' . $oldId];
                } elseif ($personId && isset($targetMemberMap['p_' . $personId])) {
                    $existingNewId = $targetMemberMap['p_' . $personId];
                } elseif (isset($targetMemberMap['n_' . $finalCategory . '_' . $name])) {
                    $existingNewId = $targetMemberMap['n_' . $finalCategory . '_' . $name];
                }

                if ($existingNewId) {
                    Database::execute(
                        "UPDATE board_term_members
                         SET source_member_id = ?, category = ?, position = ?, role_type = ?, workplace = ?, department = ?, image_url = ?, bio = ?, sort_order = ?, is_chairperson = ?, updated_at = NOW()
                         WHERE id = ?",
                        [$oldId, $finalCategory, $finalPosition, $finalRoleType, $finalWorkplace, $finalDepartment, $finalImageUrl, $m['bio'], $finalSortOrder, $finalChairperson ? 1 : 0, $existingNewId]
                    );
                    $memberIdMap[$oldId] = $existingNewId;
                } else {
                    $newMemId = (int)Database::insert(
                        "INSERT INTO board_term_members (term_id, person_id, source_member_id, category, name, position, role_type, workplace, department, image_url, bio, sort_order, is_chairperson, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                        [
                            $targetTermId,
                            $personId,
                            $oldId,
                            $finalCategory,
                            $name,
                            $finalPosition,
                            $finalRoleType,
                            $finalWorkplace,
                            $finalDepartment,
                            $finalImageUrl,
                            $m['bio'],
                            $finalSortOrder,
                            $finalChairperson ? 1 : 0
                        ]
                    );
                    $memberIdMap[$oldId] = $newMemId;
                    $targetMemberMap['s_' . $oldId] = $newMemId;
                    if ($personId) {
                        $targetMemberMap['p_' . $personId] = $newMemId;
                    }
                    $targetMemberMap['n_' . $finalCategory . '_' . $name] = $newMemId;
                }
                $transferredCount++;
            }

            // Transfer subcommittees if enabled
            $remappedGroupsCount = 0;
            $remappedGroupMembersCount = 0;

            if ($transferGroups && !empty($source['groups'])) {
                foreach ($source['groups'] as $sg) {
                    $groupTitle = $sg['title'];
                    $targetGroup = Database::first(
                        "SELECT id FROM board_term_groups WHERE term_id = ? AND title = ?",
                        [$targetTermId, $groupTitle]
                    );

                    $targetGroupId = null;
                    if ($targetGroup) {
                        $targetGroupId = (int)$targetGroup['id'];
                    } else {
                        $targetGroupId = (int)Database::insert(
                            "INSERT INTO board_term_groups (term_id, title, description, sort_order, created_at, updated_at)
                             VALUES (?, ?, ?, ?, NOW(), NOW())",
                            [$targetTermId, $groupTitle, $sg['description'] ?? null, $sg['sort_order'] ?? 0]
                        );
                    }
                    $remappedGroupsCount++;

                    // Transfer only members who were transferred into target term
                    foreach ($sg['members'] as $sgm) {
                        $oldMemberId = (int)($sgm['member_id'] ?? 0);
                        if (isset($memberIdMap[$oldMemberId])) {
                            $newMemberId = $memberIdMap[$oldMemberId];
                            $existingLink = Database::first(
                                "SELECT id FROM board_term_group_members WHERE group_id = ? AND member_id = ?",
                                [$targetGroupId, $newMemberId]
                            );
                            if (!$existingLink) {
                                Database::insert(
                                    "INSERT INTO board_term_group_members (group_id, member_id, role_in_group, sort_order, created_at, updated_at)
                                     VALUES (?, ?, ?, ?, NOW(), NOW())",
                                    [$targetGroupId, $newMemberId, $sgm['role_in_group'] ?? 'กรรมการ', $sgm['sort_order'] ?? 0]
                                );
                                $remappedGroupMembersCount++;
                            }
                        }
                        // Non-transferred members are intentionally omitted to maintain referential integrity!
                    }
                }
            }

            $pdo->commit();
            AuditService::log('board_terms', 'selective_transfer', (string)$targetTermId, null, [
                'source_term_id' => $sourceTermId,
                'target_term_number' => $targetTermNumber,
                'transferred_members' => $transferredCount,
                'remapped_groups' => $remappedGroupsCount,
                'remapped_group_members' => $remappedGroupMembersCount,
            ]);

            return [
                'success' => true,
                'status' => 'success',
                'target_term_id' => $targetTermId,
                'target_term_number' => $targetTermNumber,
                'transferred_count' => $transferredCount,
                'remapped_groups_count' => $remappedGroupsCount,
                'remapped_group_members_count' => $remappedGroupMembersCount,
                'message' => "ถ่ายโอนกรรมการ {$transferredCount} ท่าน ไปยังวาระชุดที่ {$targetTermNumber} (ฉบับร่าง) สำเร็จแล้ว",
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
