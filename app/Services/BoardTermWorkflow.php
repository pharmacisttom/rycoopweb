<?php

declare(strict_types=1);
namespace App\Services;

use App\Core\{Auth, Database};
use App\Exceptions\SecurityGateException;
use InvalidArgumentException;

/** Administrative workflow. Public readers never invoke these transitions. */
trait BoardTermWorkflow
{
    private static function requirePermission(string $action): void
    {
        if (!Auth::check()) throw new SecurityGateException('Session authentication and completed MFA required',401,'UNAUTHORIZED');
        if ((Auth::user()['role_slug'] ?? '') === 'member' || !Auth::hasPermission('people','view') || !Auth::hasPermission('people',$action)) {
            throw new SecurityGateException('Board workflow permission denied',403,'FORBIDDEN');
        }
    }

    private static function lockTerms(): void
    {
        // Always acquire in the same order, including publication and copy operations.
        Database::query('SELECT id FROM board_terms ORDER BY id'.AdminCrudService::lock());
    }

    private static function requireDraft(int $termId): array
    {
        $term = $termId > 0 ? Database::first('SELECT * FROM board_terms WHERE id=?',[$termId]) : null;
        if (!$term || $term['status'] !== 'draft' || (int)$term['is_current'] !== 0) {
            throw new InvalidArgumentException('A separate, non-current draft term is required');
        }
        return $term;
    }

    private static function fingerprint(int $termId): string
    {
        return AccessService::version([
            Database::first('SELECT * FROM board_terms WHERE id=?',[$termId]),
            Database::query('SELECT * FROM board_term_members WHERE term_id=? ORDER BY id',[$termId]),
            Database::query('SELECT * FROM board_term_groups WHERE term_id=? ORDER BY id',[$termId]),
            Database::query('SELECT gm.* FROM board_term_group_members gm JOIN board_term_groups g ON g.id=gm.group_id WHERE g.term_id=? ORDER BY gm.id',[$termId]),
        ]);
    }

    public static function approveTerm(int $termId): void
    {
        self::requirePermission('publish');
        Database::transaction(function () use ($termId): void {
            self::lockTerms();
            self::requireDraft($termId);
            if (!(int)Database::value("SELECT COUNT(*) FROM board_term_members WHERE term_id=? AND category='director'",[$termId])) {
                throw new InvalidArgumentException('At least one director is required');
            }
            AuditService::log('board_terms','approve',(string)$termId,null,['fingerprint'=>self::fingerprint($termId),'status'=>'draft'],required:true);
        });
    }

    private static function assertApproved(int $termId): void
    {
        $approval = Database::first("SELECT new_values FROM audit_logs WHERE module='board_terms' AND action='approve' AND record_id=? ORDER BY id DESC LIMIT 1",[(string)$termId]);
        $approved = $approval ? json_decode($approval['new_values'],true,512,JSON_THROW_ON_ERROR) : [];
        if (!hash_equals((string)($approved['fingerprint'] ?? ''),self::fingerprint($termId))) {
            throw new InvalidArgumentException('Approval required for the current draft; review and approve again after changes');
        }
    }

    public static function getTerm38ImportPreview(): array
    {
        self::requirePermission('view');
        $legacy = PublicContentService::reference();
        $existing = Database::first('SELECT * FROM board_terms WHERE term_number=38');
        return ['term_number'=>38,'status'=>'draft','existing_term'=>$existing,
            'directors'=>count($legacy['boardMembers']),'advisors'=>count($legacy['advisors']),
            'auditors'=>count($legacy['auditors']),'groups'=>count($legacy['boardGroups']),
            'boardMembers'=>$legacy['boardMembers'],'advisorMembers'=>$legacy['advisors'],
            'auditorMembers'=>$legacy['auditors'],'boardGroups'=>$legacy['boardGroups'],
            'message'=>$existing ? 'Existing records will be preserved without changes.' : 'Import creates a draft only. Approval and publication are separate actions.'];
    }

    public static function importTerm38(array $options = [], ?int $userId = null): array
    {
        self::requirePermission('update');
        if (($options['preserve_custom_photos'] ?? true) !== true || ($options['preserve_admin_edits'] ?? true) !== true) {
            throw new InvalidArgumentException('Import must preserve all existing records and photos');
        }
        return self::reconcileTerm38(false);
    }

    public static function getSelectionCandidates(int $sourceTermId, int $targetTermNumber = 39): array
    {
        self::requirePermission('view');
        if ($sourceTermId <= 0 || $targetTermNumber !== 39) {
            throw new InvalidArgumentException('Selective copy requires an existing Term 38 source and Draft Term 39 destination');
        }
        $source = self::getTerm($sourceTermId);
        if (!$source || (int)$source['term']['term_number'] !== 38) {
            throw new InvalidArgumentException('Selective copy source must be Term 38');
        }
        $target = Database::first('SELECT * FROM board_terms WHERE term_number=?',[$targetTermNumber]);
        if ($target) self::requireDraft((int)$target['id']);
        return ['source'=>$source,'target'=>$target,'members'=>array_merge($source['directors'],$source['advisors'],$source['auditors'])];
    }

    public static function getSelectiveTransferPreview(int $sourceTermId, int $targetTermNumber, array $selections, array $options = []): array
    {
        $data = self::getSelectionCandidates($sourceTermId,$targetTermNumber);
        $ids = [];
        foreach ($selections as $id) {
            if (!is_scalar($id) || !preg_match('/^[1-9][0-9]*$/D',(string)$id) || isset($ids[(int)$id])) throw new InvalidArgumentException('Select unique valid member IDs');
            $ids[(int)$id] = true;
        }
        if (!$ids) throw new InvalidArgumentException('Select at least one member');
        $members = array_values(array_filter($data['members'],fn($m)=>isset($ids[(int)$m['id']])));
        if (count($members) !== count($ids)) throw new InvalidArgumentException('Selected member does not belong to source term');
        $targetId = (int)($data['target']['id'] ?? 0);
        foreach ($members as $member) {
            if ($targetId && Database::first('SELECT id FROM board_term_members WHERE term_id=? AND category=? AND (name=? OR (person_id IS NOT NULL AND person_id=?))',[$targetId,$member['category'],$member['name'],$member['person_id'] ?? null])) {
                throw new InvalidArgumentException('Destination already contains a selected member');
            }
        }
        $groups = [];
        if ($options['transfer_groups'] ?? true) foreach ($data['source']['groups'] as $group) {
            $group['members'] = array_values(array_filter($group['members'],fn($m)=>isset($ids[(int)$m['member_id']])));
            if ($group['members']) $groups[] = $group;
        }
        $snapshot = ['source'=>self::fingerprint($sourceTermId),'target'=>$targetId ? self::fingerprint($targetId) : null,
            'target_number'=>$targetTermNumber,'ids'=>array_keys($ids),'groups'=>(bool)($options['transfer_groups'] ?? true)];
        return ['source_term_id'=>$sourceTermId,'target_term_id'=>$targetId ?: null,'target_term_number'=>$targetTermNumber,
            'members'=>$members,'groups'=>$groups,'status'=>'draft','version'=>AccessService::version($snapshot)];
    }

    public static function selectiveTransfer(int $sourceTermId, int $targetTermNumber, array $selections, array $options = [], ?int $userId = null): array
    {
        self::requirePermission('update');
        if (!empty($options['customizations'])) throw new InvalidArgumentException('Edit the destination draft after copying');
        return Database::transaction(function () use ($sourceTermId,$targetTermNumber,$selections,$options): array {
            self::lockTerms();
            $preview = self::getSelectiveTransferPreview($sourceTermId,$targetTermNumber,$selections,$options);
            if (!hash_equals($preview['version'],(string)($options['version'] ?? ''))) throw new SecurityGateException('Preview is stale; refresh and confirm again',409,'STALE_VERSION');
            $targetId = $preview['target_term_id'] ?? self::createTerm(['term_number'=>$targetTermNumber,'term_name'=>$options['term_name'] ?? "ชุดที่ {$targetTermNumber}"]);
            $map = [];
            foreach ($preview['members'] as $m) {
                $map[(int)$m['id']] = (int)Database::insert(
                    'INSERT INTO board_term_members (term_id,person_id,category,name,position,role_type,workplace,department,image_url,bio,sort_order,is_chairperson,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())',
                    [$targetId,$m['person_id'],$m['category'],$m['name'],$m['position'],$m['role_type'],$m['workplace'],$m['department'],$m['image_url'],$m['bio'],$m['sort_order'],(int)$m['is_chairperson']]);
            }
            foreach ($preview['groups'] as $g) {
                $found = Database::first('SELECT id FROM board_term_groups WHERE term_id=? AND title=?',[$targetId,$g['title']]);
                $groupId = $found ? (int)$found['id'] : (int)Database::insert('INSERT INTO board_term_groups (term_id,title,description,sort_order,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())',[$targetId,$g['title'],$g['description'],$g['sort_order']]);
                foreach ($g['members'] as $gm) Database::insert('INSERT INTO board_term_group_members (group_id,member_id,role_in_group,sort_order,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())',[$groupId,$map[(int)$gm['member_id']],$gm['role_in_group'],$gm['sort_order']]);
            }
            AuditService::log('board_terms','selective_copy',(string)$targetId,null,['source_term_id'=>$sourceTermId,'member_map'=>$map,'status'=>'draft'],required:true);
            return ['term_id'=>$targetId,'status'=>'draft','message'=>'Selected members copied to a separate draft; source preserved.'];
        });
    }
}
