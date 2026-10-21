<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Services\AuditService;

class MemberRequestService
{
    private static function tableExists(string $table): bool
    {
        try {
            $count = Database::value("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", [$table]);
            return (int)$count > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function createProfileRequest(int $memberId, array $changes): array
    {
        $allowed = ['phone', 'email', 'address', 'emergencyContact', 'note', 'avatarFileName'];
        $cleanChanges = [];
        foreach ($allowed as $field) {
            if (isset($changes[$field]) && is_scalar($changes[$field])) {
                $cleanChanges[$field] = trim((string)$changes[$field]);
            }
        }

        if (empty($cleanChanges['phone']) && empty($cleanChanges['email']) && empty($cleanChanges['address'])) {
            throw new \InvalidArgumentException('กรุณาระบุข้อมูลที่ต้องการเปลี่ยนแปลงอย่างน้อยหนึ่งรายการ');
        }

        $buddhistYear = (int)date('Y') + 543;
        $randomSeq = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $requestNo = "REQ-{$buddhistYear}-{$randomSeq}";

        $history = [
            [
                'status' => 'submitted',
                'at' => date('c'),
                'note' => 'ยื่นคำขอแก้ไขข้อมูลส่วนตัว'
            ]
        ];

        $changesJson = json_encode($cleanChanges, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $historyJson = json_encode($history, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (self::tableExists('member_profile_requests')) {
            $id = (int)Database::insert(
                "INSERT INTO member_profile_requests (request_no, member_id, requested_changes_json, status, history_json, created_at) VALUES (?, ?, ?, 'submitted', ?, NOW())",
                [$requestNo, $memberId, $changesJson, $historyJson]
            );

            AuditService::log('member_requests', 'create', (string)$id, null, [
                'request_no' => $requestNo,
                'member_id' => $memberId,
                'changes' => $cleanChanges
            ]);

            return [
                'id' => (string)$id,
                'requestNumber' => $requestNo,
                'requestNo' => $requestNo,
                'status' => 'SUBMITTED',
                'requestedChanges' => $cleanChanges,
                'createdAt' => date('c')
            ];
        }

        // Fallback to online_requests if member_profile_requests table is not yet migrated
        if (self::tableExists('online_requests')) {
            $id = (int)Database::insert(
                "INSERT INTO online_requests (request_no, member_id, request_type, title, details, status, timeline_json, created_at) VALUES (?, ?, 'other', 'คำขอแก้ไขข้อมูลส่วนตัว', ?, 'submitted', ?, NOW())",
                [$requestNo, $memberId, $changesJson, $historyJson]
            );

            return [
                'id' => (string)$id,
                'requestNumber' => $requestNo,
                'requestNo' => $requestNo,
                'status' => 'SUBMITTED',
                'requestedChanges' => $cleanChanges,
                'createdAt' => date('c')
            ];
        }

        throw new \RuntimeException('ระบบบันทึกคำร้องยังไม่พร้อมใช้งาน กรุณาติดต่อผู้ดูแลระบบ');
    }

    public static function getMemberRequests(int $memberId): array
    {
        $results = [];

        if (self::tableExists('member_profile_requests')) {
            $rows = Database::query(
                "SELECT * FROM member_profile_requests WHERE member_id = ? ORDER BY id DESC LIMIT 50",
                [$memberId]
            );

            foreach ($rows as $row) {
                $changes = [];
                try {
                    $changes = json_decode((string)$row['requested_changes_json'], true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable $e) {}

                $results[] = [
                    'id' => (string)$row['id'],
                    'number' => $row['request_no'],
                    'requestNumber' => $row['request_no'],
                    'subject' => 'คำขอแก้ไขข้อมูลส่วนตัว',
                    'status' => strtoupper((string)$row['status']),
                    'details' => $changes,
                    'reviewNotes' => $row['review_notes'] ?? null,
                    'createdAt' => $row['created_at'],
                    'updatedAt' => $row['updated_at']
                ];
            }
        }

        if (self::tableExists('online_requests')) {
            $onlineRows = Database::query(
                "SELECT * FROM online_requests WHERE member_id = ? ORDER BY id DESC LIMIT 50",
                [$memberId]
            );

            foreach ($onlineRows as $row) {
                // Skip if duplicate request_no already added
                if (in_array($row['request_no'], array_column($results, 'number'), true)) {
                    continue;
                }

                $results[] = [
                    'id' => (string)$row['id'],
                    'number' => $row['request_no'],
                    'requestNumber' => $row['request_no'],
                    'subject' => $row['title'] ?? 'คำร้องทั่วไป',
                    'status' => strtoupper((string)$row['status']),
                    'createdAt' => $row['created_at'],
                    'updatedAt' => $row['updated_at'] ?? $row['created_at']
                ];
            }
        }

        return $results;
    }

    public static function listAdminRequests(string $status = '', int $page = 1, int $limit = 25): array
    {
        if (!self::tableExists('member_profile_requests')) {
            return ['requests' => [], 'total' => 0, 'page' => 1];
        }

        $where = ['1=1'];
        $params = [];
        if ($status !== '') {
            $where[] = 'r.status = ?';
            $params[] = strtolower($status);
        }

        $whereSql = implode(' AND ', $where);
        $total = (int)Database::value("SELECT COUNT(*) FROM member_profile_requests r WHERE {$whereSql}", $params);
        $offset = max(0, ($page - 1) * $limit);

        $sql = "SELECT r.*, m.member_no, m.first_name, m.last_name, m.prefix, u.name as reviewer_name
                FROM member_profile_requests r
                JOIN members m ON m.id = r.member_id
                LEFT JOIN users u ON u.id = r.reviewed_by
                WHERE {$whereSql}
                ORDER BY r.id DESC
                LIMIT {$limit} OFFSET {$offset}";

        $rows = Database::query($sql, $params);
        $formatted = array_map(function ($row) {
            $changes = [];
            try {
                $changes = json_decode((string)$row['requested_changes_json'], true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {}

            return [
                'id' => (string)$row['id'],
                'requestNumber' => $row['request_no'],
                'memberId' => (string)$row['member_id'],
                'memberNo' => $row['member_no'],
                'memberName' => trim(($row['prefix'] ?? '') . $row['first_name'] . ' ' . $row['last_name']),
                'requestedChanges' => $changes,
                'status' => strtoupper((string)$row['status']),
                'reviewerName' => $row['reviewer_name'] ?? null,
                'reviewedAt' => $row['reviewed_at'] ?? null,
                'reviewNotes' => $row['review_notes'] ?? null,
                'createdAt' => $row['created_at'],
                'updatedAt' => $row['updated_at']
            ];
        }, $rows);

        return [
            'requests' => $formatted,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ];
    }

    public static function approveProfileRequest(int $requestId, int $adminUserId, ?string $notes = null): array
    {
        if (!self::tableExists('member_profile_requests')) {
            throw new \RuntimeException('ตาราง member_profile_requests ไม่พร้อมใช้งาน');
        }

        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $request = Database::first("SELECT * FROM member_profile_requests WHERE id = ? FOR UPDATE", [$requestId]);
            if (!$request) {
                throw new \InvalidArgumentException('ไม่พบคำร้องที่ระบุ');
            }

            if (!in_array($request['status'], ['submitted', 'in_progress'], true)) {
                throw new \InvalidArgumentException('คำร้องนี้ได้รับการดำเนินการไปแล้ว (สถานะปัจจุบัน: ' . $request['status'] . ')');
            }

            $memberId = (int)$request['member_id'];
            $member = Database::first("SELECT * FROM members WHERE id = ? FOR UPDATE", [$memberId]);
            if (!$member) {
                throw new \InvalidArgumentException('ไม่พบข้อมูลสมาชิกของคำร้องนี้');
            }

            $changes = json_decode((string)$request['requested_changes_json'], true, 512, JSON_THROW_ON_ERROR);

            // Update member profile fields
            $updateFields = [];
            $updateParams = [];

            if (!empty($changes['phone'])) {
                $updateFields[] = "phone = ?";
                $updateParams[] = trim((string)$changes['phone']);
            }
            if (!empty($changes['email'])) {
                $updateFields[] = "email = ?";
                $updateParams[] = trim((string)$changes['email']);
            }
            if (!empty($changes['address'])) {
                $updateFields[] = "address = ?";
                $updateParams[] = trim((string)$changes['address']);
            }

            if (!empty($updateFields)) {
                $updateFields[] = "updated_at = NOW()";
                $updateParams[] = $memberId;
                Database::execute("UPDATE members SET " . implode(', ', $updateFields) . " WHERE id = ?", $updateParams);
            }

            // Record in member_data_changes if table exists
            if (self::tableExists('member_data_changes')) {
                $afterMember = Database::first("SELECT * FROM members WHERE id = ?", [$memberId]);
                Database::execute(
                    "INSERT INTO member_data_changes (member_id, user_id, action, reason, before_json, after_json, created_at) VALUES (?, ?, 'profile_update_approved', ?, ?, ?, NOW())",
                    [
                        $memberId,
                        $adminUserId,
                        "อนุมัติคำขอเลขที่ {$request['request_no']}" . ($notes ? ": {$notes}" : ''),
                        json_encode($member, JSON_UNESCAPED_UNICODE),
                        json_encode($afterMember, JSON_UNESCAPED_UNICODE)
                    ]
                );
            }

            // Update request history
            $history = [];
            try {
                $history = json_decode((string)$request['history_json'], true, 512, JSON_THROW_ON_ERROR) ?? [];
            } catch (\Throwable $e) {}

            $history[] = [
                'status' => 'approved',
                'at' => date('c'),
                'by_user_id' => $adminUserId,
                'note' => $notes ?? 'อนุมัติคำขอเรียบร้อย'
            ];

            Database::execute(
                "UPDATE member_profile_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), review_notes = ?, history_json = ?, updated_at = NOW() WHERE id = ?",
                [
                    $adminUserId,
                    $notes,
                    json_encode($history, JSON_UNESCAPED_UNICODE),
                    $requestId
                ]
            );

            AuditService::log('member_requests', 'approve', (string)$requestId, [
                'request_no' => $request['request_no'],
                'member_id' => $memberId,
                'old_status' => $request['status']
            ], [
                'status' => 'approved',
                'reviewed_by' => $adminUserId,
                'notes' => $notes
            ]);

            $pdo->commit();

            return [
                'success' => true,
                'id' => (string)$requestId,
                'requestNumber' => $request['request_no'],
                'status' => 'APPROVED'
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function rejectProfileRequest(int $requestId, int $adminUserId, string $reason): array
    {
        if (!self::tableExists('member_profile_requests')) {
            throw new \RuntimeException('ตาราง member_profile_requests ไม่พร้อมใช้งาน');
        }

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('กรุณาระบุเหตุผลการปฏิเสธคำร้อง');
        }

        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $request = Database::first("SELECT * FROM member_profile_requests WHERE id = ? FOR UPDATE", [$requestId]);
            if (!$request) {
                throw new \InvalidArgumentException('ไม่พบคำร้องที่ระบุ');
            }

            if (!in_array($request['status'], ['submitted', 'in_progress'], true)) {
                throw new \InvalidArgumentException('คำร้องนี้ได้รับการดำเนินการไปแล้ว');
            }

            $history = [];
            try {
                $history = json_decode((string)$request['history_json'], true, 512, JSON_THROW_ON_ERROR) ?? [];
            } catch (\Throwable $e) {}

            $history[] = [
                'status' => 'rejected',
                'at' => date('c'),
                'by_user_id' => $adminUserId,
                'note' => $reason
            ];

            Database::execute(
                "UPDATE member_profile_requests SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), review_notes = ?, history_json = ?, updated_at = NOW() WHERE id = ?",
                [
                    $adminUserId,
                    $reason,
                    json_encode($history, JSON_UNESCAPED_UNICODE),
                    $requestId
                ]
            );

            AuditService::log('member_requests', 'reject', (string)$requestId, [
                'request_no' => $request['request_no'],
                'old_status' => $request['status']
            ], [
                'status' => 'rejected',
                'reviewed_by' => $adminUserId,
                'reason' => $reason
            ]);

            $pdo->commit();

            return [
                'success' => true,
                'id' => (string)$requestId,
                'requestNumber' => $request['request_no'],
                'status' => 'REJECTED'
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
