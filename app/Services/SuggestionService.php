<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Services\AuditService;

class SuggestionService
{
    /**
     * Categories mapping
     */
    public static function getCategories(): array
    {
        return [
            'service' => [
                'name' => 'ด้านการบริการ',
                'icon' => 'bi-hospital',
                'color' => 'primary',
                'desc' => 'ข้อเสนอแนะเกี่ยวกับการบริการ ณ สำนักงาน, ความรวดเร็ว, อัธยาศัยไมตรี'
            ],
            'loan' => [
                'name' => 'ด้านสินเชื่อและการเงิน',
                'icon' => 'bi-cash-coin',
                'color' => 'danger',
                'desc' => 'ข้อเสนอแนะด้านประเภทเงินกู้, อัตราดอกเบี้ย, วงเงิน หรือเงื่อนไขการกู้'
            ],
            'welfare' => [
                'name' => 'ด้านสวัสดิการสมาชิก',
                'icon' => 'bi-heart-pulse-fill',
                'color' => 'success',
                'desc' => 'ข้อเสนอแนะสวัสดิการใหม่, กองทุนช่วยเหลือ, ทุนการศึกษาบุตร'
            ],
            'technology' => [
                'name' => 'ด้านดิจิทัลและเทคโนโลยี',
                'icon' => 'bi-laptop',
                'color' => 'info',
                'desc' => 'ข้อเสนอแนะเกี่ยวกับเว็บไซต์, ระบบสมาชิกออนไลน์, LINE Official'
            ],
            'general' => [
                'name' => 'ด้านทั่วไปและอื่นๆ',
                'icon' => 'bi-lightbulb-fill',
                'color' => 'warning',
                'desc' => 'ข้อเสนอแนะทั่วไปหรือข้อคิดเห็นเพื่อการพัฒนาสหกรณ์'
            ],
        ];
    }

    /**
     * Statuses mapping
     */
    public static function getStatuses(): array
    {
        return [
            'submitted' => [
                'name' => 'ได้รับเรื่องแล้ว',
                'badge' => 'bg-secondary',
                'icon' => 'bi-inbox',
                'desc' => 'ระบบได้รับข้อเสนอแนะแล้ว รอเจ้าหน้าที่ตรวจสอบ'
            ],
            'under_review' => [
                'name' => 'อยู่ระหว่างพิจารณา',
                'badge' => 'bg-warning text-dark',
                'icon' => 'bi-hourglass-split',
                'desc' => 'เรื่องอยู่ระหว่างรวบรวมข้อมูลหรือนำเสนอคณะกรรมการ'
            ],
            'approved' => [
                'name' => 'รับหลักการ / เตรียมผลักดัน',
                'badge' => 'bg-info text-dark',
                'icon' => 'bi-check2-circle',
                'desc' => 'ฝ่ายบริหารรับหลักการเพื่อนำไปพัฒนาหรือบรรจุในแผนงาน'
            ],
            'implemented' => [
                'name' => 'นำไปปฏิบัติจริงแล้ว',
                'badge' => 'bg-success',
                'icon' => 'bi-stars',
                'desc' => 'โครงการหรือข้อเสนอแนะได้รับการดำเนินการจริงเรียบร้อยแล้ว'
            ],
            'declined' => [
                'name' => 'ยุติเรื่อง / ชี้แจง',
                'badge' => 'bg-light text-muted border',
                'icon' => 'bi-x-circle',
                'desc' => 'ชี้แจงเหตุผลข้อจำกัดทางระเบียบหรือกฎหมาย'
            ],
        ];
    }

    /**
     * Create a new suggestion
     */
    public static function createSuggestion(int $memberId, array $data, ?array $file = null): array
    {
        $year = date('Y');
        $randNo = str_pad((string)rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $suggestionNo = "SG-{$year}-{$randNo}";

        $category = in_array($data['category'] ?? '', ['service', 'loan', 'welfare', 'technology', 'general']) ? $data['category'] : 'general';
        $title = trim((string)($data['title'] ?? ''));
        $content = trim((string)($data['content'] ?? ''));
        $isAnonymous = !empty($data['is_anonymous']) ? 1 : 0;

        $attachmentPath = null;
        if ($file && !empty($file['tmp_name']) && $file['error'] === UPLOAD_ERR_OK) {
            $uploadDir = storage_upload_path('suggestions');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'])) {
                $filename = "sug_{$suggestionNo}_" . time() . ".{$ext}";
                $dest = $uploadDir . '/' . $filename;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $attachmentPath = 'suggestions/' . $filename;
                }
            }
        }

        $id = Database::insert(
            "INSERT INTO member_suggestions (suggestion_no, member_id, category, title, content, attachment_path, is_anonymous, status, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, 'submitted', NOW(), NOW())",
            [$suggestionNo, $memberId, $category, $title, $content, $attachmentPath, $isAnonymous]
        );

        // Send confirmation notification to member
        Database::insert(
            "INSERT INTO notifications (member_id, title, message, type, link_url, is_read, created_at) 
             VALUES (?, 'ส่งข้อเสนอแนะสำเร็จ', ?, 'request_status', '/member/suggestions', 0, NOW())",
            [$memberId, "สหกรณ์ได้รับข้อเสนอแนะ '{$title}' เลขที่ {$suggestionNo} ของท่านเรียบร้อยแล้ว ขอบคุณที่ร่วมสร้างสรรค์สหกรณ์"]
        );

        AuditService::log('suggestion', 'create', (string)$id, null, [
            'suggestion_no' => $suggestionNo,
            'category' => $category,
            'title' => $title,
            'is_anonymous' => $isAnonymous,
        ]);

        return [
            'success' => true,
            'id' => $id,
            'suggestion_no' => $suggestionNo,
        ];
    }

    /**
     * Get suggestions by a specific member
     */
    public static function getMemberSuggestions(int $memberId): array
    {
        return Database::query(
            "SELECT s.*, u.name as responder_name 
             FROM member_suggestions s 
             LEFT JOIN users u ON s.responded_by = u.id 
             WHERE s.member_id = ? 
             ORDER BY s.created_at DESC",
            [$memberId]
        );
    }

    /**
     * Get all suggestions for staff/admin management
     */
    public static function getAllSuggestions(?string $status = null, ?string $category = null, ?string $search = null): array
    {
        $sql = "SELECT s.*, m.member_no, CONCAT(m.prefix, m.first_name, ' ', m.last_name) as member_name, m.department, m.phone, u.name as responder_name 
                FROM member_suggestions s 
                JOIN members m ON s.member_id = m.id 
                LEFT JOIN users u ON s.responded_by = u.id 
                WHERE 1=1";
        $params = [];

        if (!empty($status)) {
            $sql .= " AND s.status = ?";
            $params[] = $status;
        }

        if (!empty($category)) {
            $sql .= " AND s.category = ?";
            $params[] = $category;
        }

        if (!empty($search)) {
            $sql .= " AND (s.suggestion_no LIKE ? OR s.title LIKE ? OR s.content LIKE ? OR m.member_no LIKE ? OR m.first_name LIKE ? OR m.last_name LIKE ?)";
            $k = "%{$search}%";
            $params = array_merge($params, [$k, $k, $k, $k, $k, $k]);
        }

        $sql .= " ORDER BY s.created_at DESC";
        return Database::query($sql, $params);
    }

    /**
     * Get suggestion details by ID
     */
    public static function getSuggestionById(int $id): ?array
    {
        return Database::first(
            "SELECT s.*, m.member_no, CONCAT(m.prefix, m.first_name, ' ', m.last_name) as member_name, m.department, m.phone, m.email, u.name as responder_name 
             FROM member_suggestions s 
             JOIN members m ON s.member_id = m.id 
             LEFT JOIN users u ON s.responded_by = u.id 
             WHERE s.id = ?",
            [$id]
        );
    }

    /**
     * Update status and admin response comment
     */
    public static function updateStatusAndResponse(int $id, string $status, ?string $response, int $adminUserId): bool
    {
        $sug = self::getSuggestionById($id);
        if (!$sug) return false;

        Database::execute(
            "UPDATE member_suggestions 
             SET status = ?, admin_response = ?, responded_by = ?, responded_at = NOW(), updated_at = NOW() 
             WHERE id = ?",
            [$status, $response, $adminUserId, $id]
        );

        $statusTitles = [
            'under_review' => 'ข้อเสนอแนะอยู่ระหว่างการพิจารณา',
            'approved' => 'ข้อเสนอแนะของท่านได้รับการรับหลักการ',
            'implemented' => 'ข้อเสนอแนะของท่านได้รับการนำไปปฏิบัติจริงแล้ว',
            'declined' => 'ข้อเสนอแนะของท่านได้รับการตอบกลับคำชี้แจง',
            'submitted' => 'อัปเดตสถานะข้อเสนอแนะ',
        ];

        $title = $statusTitles[$status] ?? 'อัปเดตสถานะข้อเสนอแนะ';
        $msg = "ข้อเสนอแนะ '{$sug['title']}' (เลขที่ {$sug['suggestion_no']}) มีการอัปเดตสถานะและตอบกลับจากสหกรณ์";

        Database::insert(
            "INSERT INTO notifications (member_id, title, message, type, link_url, is_read, created_at) 
             VALUES (?, ?, ?, 'request_status', '/member/suggestions', 0, NOW())",
            [$sug['member_id'], $title, $msg]
        );

        AuditService::log('suggestion', 'update_status', (string)$id, ['old_status' => $sug['status']], [
            'new_status' => $status,
            'response' => $response,
            'admin_user_id' => $adminUserId,
        ]);

        return true;
    }

    /**
     * Get suggestions metrics
     */
    public static function getSuggestionStats(): array
    {
        $total = (int)Database::value("SELECT COUNT(*) FROM member_suggestions") ?: 0;
        $submitted = (int)Database::value("SELECT COUNT(*) FROM member_suggestions WHERE status = 'submitted'") ?: 0;
        $underReview = (int)Database::value("SELECT COUNT(*) FROM member_suggestions WHERE status = 'under_review'") ?: 0;
        $approved = (int)Database::value("SELECT COUNT(*) FROM member_suggestions WHERE status = 'approved'") ?: 0;
        $implemented = (int)Database::value("SELECT COUNT(*) FROM member_suggestions WHERE status = 'implemented'") ?: 0;
        $declined = (int)Database::value("SELECT COUNT(*) FROM member_suggestions WHERE status = 'declined'") ?: 0;

        return [
            'total' => $total,
            'submitted' => $submitted,
            'under_review' => $underReview,
            'approved' => $approved,
            'implemented' => $implemented,
            'declined' => $declined,
        ];
    }
}
