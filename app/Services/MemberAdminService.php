<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Auth;
use App\Core\Database;
use App\Services\MemberDataException as RuntimeException;

class MemberAdminService
{
    private static function lockClause(bool $lock = true): string
    {
        return $lock && Database::connect()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    }

    public static function version(array $data): string { return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)); }
    public static function recordChange(int $memberId, string $action, string $reason, ?array $before, array $after): void
    {
        Database::execute('INSERT INTO member_data_changes (member_id,user_id,action,reason,before_json,after_json) VALUES (?,?,?,?,?,?)', [$memberId, PHP_SAPI==='cli'?null:Auth::id(), $action, $reason, $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
    }
    public static function profile(int $id, bool $lock = false, ?array $columns = null): array
    {
        $fields=['id','user_id','member_no','id_card','prefix','first_name','last_name','department','phone','email','address','status'];
        if($columns!==null) {
            MemberSystemHealth::requireColumns(['members'=>['id','user_id','member_no','id_card','first_name','last_name','status']],$columns);
            $fields=array_map(static fn($field)=>in_array($field,$columns['members'],true)?$field:'NULL AS '.$field,$fields);
        }
        $member = Database::first('SELECT '.implode(',',$fields).' FROM members WHERE id=?' . self::lockClause($lock), [$id]);
        if (!$member) { throw new RuntimeException('ไม่พบสมาชิก'); }
        return $member;
    }
    public static function updateProfile(int $id, array $input): void
    {
        $reason = is_string($input['reason'] ?? null) ? trim($input['reason']) : '';
        if ($reason === '' || mb_strlen($reason) > 500) { throw new RuntimeException('กรุณาระบุเหตุผลการแก้ไขไม่เกิน 500 ตัวอักษร'); }
        $pdo = Database::connect(); $pdo->beginTransaction();
        try {
            $before = self::profile($id, true);
            if (!hash_equals(self::version($before), (string)($input['version'] ?? ''))) { throw new RuntimeException('ข้อมูลถูกแก้ไขแล้ว กรุณาโหลดข้อมูลล่าสุดก่อนบันทึก'); }
            $allowed = ['member_no'=>20,'id_card'=>13,'prefix'=>20,'first_name'=>100,'last_name'=>100,'department'=>150,'phone'=>20,'email'=>190,'address'=>2000,'status'=>20];
            $after = $before;
            foreach ($allowed as $field => $max) {
                if (!array_key_exists($field, $input)) { throw new RuntimeException('ข้อมูลไม่ครบ: ' . $field); }
                if (!is_scalar($input[$field]) && $input[$field] !== null) { throw new RuntimeException('รูปแบบข้อมูลไม่ถูกต้อง: ' . $field); }
                $value = trim((string)$input[$field]);
                if (mb_strlen($value) > $max) { throw new RuntimeException('ข้อมูลยาวเกินกำหนด: ' . $field); }
                $after[$field] = $value;
            }
            if (!preg_match('/^\d{13}$/D', $after['id_card']) || $after['id_card'] === '0000000000000') { throw new RuntimeException('เลขบัตรประชาชนต้องเป็นข้อความ 13 หลัก'); }
            if (!preg_match('/^\d{1,20}$/D', $after['member_no'])) { throw new RuntimeException('เลขสมาชิกไม่ถูกต้อง'); }
            $after['member_no'] = str_pad($after['member_no'], 5, '0', STR_PAD_LEFT);
            if ($after['first_name'] === '' || mb_strlen($after['prefix'] . $after['first_name'] . ' ' . $after['last_name']) > 100) { throw new RuntimeException('กรุณาระบุชื่อ โดยชื่อเต็มต้องไม่เกิน 100 ตัวอักษร'); }
            if (!in_array($after['status'], ['active','resigned','suspended'], true)) { throw new RuntimeException('สถานะไม่ถูกต้อง'); }
            if ($after['email'] !== '' && !filter_var($after['email'], FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('อีเมลไม่ถูกต้อง'); }
            if (Database::first('SELECT id FROM members WHERE id<>? AND (id_card=? OR member_no=?) LIMIT 1' . self::lockClause(), [$id,$after['id_card'],$after['member_no']])) { throw new RuntimeException('เลขบัตรประชาชนหรือเลขสมาชิกซ้ำกับบัญชีอื่น'); }
            if ($before['user_id']) {
                $user = Database::first('SELECT id,username,status FROM users WHERE id=?' . self::lockClause(), [$before['user_id']]);
                if (Database::first("SELECT ur.id FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.slug<>'member'", [$before['user_id']])) { throw new RuntimeException('บัญชีนี้มีสิทธิ์เจ้าหน้าที่ กรุณาจัดการผ่านระบบผู้ใช้งาน'); }
                if (Database::first('SELECT id FROM users WHERE id<>? AND username=?', [$before['user_id'],$after['id_card']])) { throw new RuntimeException('ชื่อผู้ใช้ซ้ำกับบัญชีอื่น'); }
                // Preserve account status consistency:
                // - inactive user remains inactive pending verification
                // - suspended user remains suspended (do not allow general profile update to silently reactivate)
                // - active user becomes suspended if member itself is suspended/resigned, else remains active
                $currentUserStatus = (string)($user['status'] ?? 'inactive');
                if ($currentUserStatus === 'suspended') {
                    $newStatus = 'suspended';
                } elseif ($currentUserStatus === 'inactive') {
                    $newStatus = ($after['status'] === 'suspended' || $after['status'] === 'resigned') ? 'suspended' : 'inactive';
                } else {
                    $newStatus = ($after['status'] === 'suspended' || $after['status'] === 'resigned') ? 'suspended' : 'active';
                }
                Database::execute('UPDATE users SET name=?,username=?,status=?,updated_at=NOW() WHERE id=?', [trim($after['prefix'].$after['first_name'].' '.$after['last_name']), $after['id_card'], $newStatus, $before['user_id']]);
            }
            Database::execute('UPDATE members SET member_no=?,id_card=?,prefix=?,first_name=?,last_name=?,department=?,phone=?,email=?,address=?,status=?,updated_at=NOW() WHERE id=?', [$after['member_no'],$after['id_card'],$after['prefix'],$after['first_name'],$after['last_name'],$after['department'],$after['phone'],$after['email'],$after['address'],$after['status'],$id]);
            if ($before !== $after) { self::recordChange($id,'profile_update',$reason,$before,$after); }
            $pdo->commit();
        } catch (\Throwable $e) { $pdo->rollBack(); throw $e; }
    }

    public static function activateAccount(int $memberId, array $input): array
    {
        $verificationMethod = trim((string)($input['verification_method'] ?? ''));
        $allowedMethods = ['in_person', 'official_document', 'call_verification', 'branch_visit'];
        if (!in_array($verificationMethod, $allowedMethods, true)) {
            throw new RuntimeException('กรุณาระบุวิธีการยืนยันตัวตนที่ถูกต้อง (in_person, official_document, call_verification, branch_visit)');
        }

        $reason = trim((string)($input['reason'] ?? $input['notes'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new RuntimeException('กรุณาระบุบันทึกเหตุผลการเปิดใช้งานบัญชีไม่เกิน 500 ตัวอักษร');
        }

        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $member = self::profile($memberId, true);
            if (($member['status'] ?? '') !== 'active') {
                throw new RuntimeException('สมาชิกต้องมีสถานะปกติ (active) จึงจะสามารถเปิดใช้งานบัญชีผู้ใช้ได้');
            }

            $userId = (int)($member['user_id'] ?? 0);
            if ($userId <= 0) {
                throw new RuntimeException('ไม่พบบัญชีผู้ใช้ที่เชื่อมโยงกับสมาชิกรายนี้');
            }

            $user = Database::first('SELECT id, username, status, password FROM users WHERE id = ?' . self::lockClause(), [$userId]);
            if (!$user) {
                throw new RuntimeException('ไม่พบบัญชีผู้ใช้ในระบบ');
            }

            // Verify user has the member role
            $hasMemberRole = (bool)Database::first(
                "SELECT ur.id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.slug = 'member'",
                [$userId]
            );
            if (!$hasMemberRole) {
                throw new RuntimeException('บัญชีผู้ใช้นี้ไม่มีสิทธิ์สมาชิก');
            }

            // Verify user does not have any staff role (never activate staff accounts here)
            $isStaff = (bool)Database::first(
                "SELECT ur.id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.slug <> 'member'",
                [$userId]
            );
            if ($isStaff) {
                throw new RuntimeException('ไม่สามารถเปิดใช้งานบัญชีเจ้าหน้าที่ผ่านระบบสมาชิกได้');
            }

            $beforeStatus = (string)($user['status'] ?? 'inactive');
            if ($beforeStatus === 'active') {
                $pdo->rollBack();
                return ['success' => true, 'already_active' => true, 'message' => 'บัญชีผู้ใช้นี้เปิดใช้งานอยู่แล้ว'];
            }

            if ($beforeStatus === 'suspended') {
                throw new RuntimeException('ไม่สามารถเปิดใช้งานบัญชีที่ถูกระงับได้ กรุณาใช้กระบวนการปลดระงับบัญชีโดยเฉพาะ');
            }

            if ($beforeStatus !== 'inactive') {
                throw new RuntimeException('สามารถเปิดใช้งานได้เฉพาะบัญชีที่รอการเปิดใช้งาน (inactive) เท่านั้น');
            }

            // Update user status to active and mark password change required
            if (MemberSecurityService::hasMustChangePasswordColumn()) {
                Database::execute("UPDATE users SET status = 'active', must_change_password = 1, updated_at = NOW() WHERE id = ?", [$userId]);
            } else {
                Database::execute("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?", [$userId]);
            }

            $staffId = PHP_SAPI === 'cli' ? null : Auth::id();

            // Record to member_data_changes
            self::recordChange(
                $memberId,
                'account_activation',
                $reason,
                ['user_id' => $userId, 'status' => $beforeStatus],
                ['user_id' => $userId, 'status' => 'active', 'verification_method' => $verificationMethod, 'activated_by' => $staffId]
            );

            // Record audit log (strictly required = true so audit failure rolls back transaction)
            AuditService::log(
                'member_management',
                'activate_account',
                (string)$memberId,
                ['user_id' => $userId, 'status' => $beforeStatus],
                ['user_id' => $userId, 'status' => 'active', 'verification_method' => $verificationMethod],
                true
            );

            $pdo->commit();
            return [
                'success' => true,
                'member_id' => $memberId,
                'user_id' => $userId,
                'previous_status' => $beforeStatus,
                'new_status' => 'active',
                'verification_method' => $verificationMethod,
                'message' => 'เปิดใช้งานบัญชีผู้ใช้เรียบร้อยแล้ว'
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function cents(mixed $value): int
    {
        if (!is_scalar($value)) { throw new RuntimeException('จำนวนเงินต้องเป็นตัวเลข'); }
        $text = trim((string)$value);
        if (!preg_match('/^-?\d{1,11}(?:\.\d{1,2})?$/D', $text)) { throw new RuntimeException('จำนวนเงินต้องเป็นตัวเลขทศนิยมไม่เกิน 2 ตำแหน่ง'); }
        $negative = str_starts_with($text,'-'); $parts = explode('.', ltrim($text,'-'));
        $cents = (int)$parts[0]*100 + (int)str_pad($parts[1]??'',2,'0');
        return $negative ? -$cents : $cents;
    }
    public static function amount(int $cents): string { return ($cents<0?'-':'') . intdiv(abs($cents),100) . '.' . str_pad((string)(abs($cents)%100),2,'0',STR_PAD_LEFT); }
    public static function updateDividend(int $memberId, int $year, array $input): void
    {
        $reason = is_string($input['reason'] ?? null) ? trim($input['reason']) : '';
        if ($reason === '' || mb_strlen($reason)>500) { throw new RuntimeException('กรุณาระบุเหตุผลการแก้ไขไม่เกิน 500 ตัวอักษร'); }
        $pdo = Database::connect(); $pdo->beginTransaction();
        try {
            self::profile($memberId,true);
            $row = Database::first('SELECT id,details_json FROM member_dividends WHERE member_id=? AND year=?' . self::lockClause(),[$memberId,$year]);
            if (!$row) { throw new RuntimeException('ไม่พบรายการปันผลปีที่เลือก'); }
            $before = json_decode($row['details_json'],true,512,JSON_THROW_ON_ERROR);
            if (!hash_equals(self::version($before),(string)($input['version']??''))) { throw new RuntimeException('รายการถูกแก้ไขแล้ว กรุณาโหลดข้อมูลล่าสุด'); }
            $after = $before; $totals = [];
            foreach (['income'=>DividendImportService::INCOME,'deductions'=>DividendImportService::DEDUCTIONS] as $type=>$labels) {
                $sum=0;
                foreach($labels as $label) {
                    if (!isset($input[$type][$label])) { throw new RuntimeException('ข้อมูลจำนวนเงินไม่ครบ'); }
                    $cents=self::cents($input[$type][$label]);
                    if ($cents<0) { throw new RuntimeException('รายรับและรายหักต้องไม่ติดลบ'); }
                    $after[$type][$label]=self::amount($cents); $sum+=$cents;
                }
                $totals[$type]=$sum;
            }
            $after['total_income']=self::amount($totals['income']); $after['total_deductions']=self::amount($totals['deductions']); $after['net']=self::amount($totals['income']-$totals['deductions']);
            foreach(['received','dividend_rate','refund_rate'] as $field) {
                if (!isset($input[$field])) { throw new RuntimeException('ข้อมูลไม่ครบ: '.$field); }
                $cents=self::cents($input[$field]);
                if (($field!=='received' && ($cents<0 || $cents>10000))) { throw new RuntimeException('อัตราต้องอยู่ระหว่าง 0–100'); }
                $after[$field]=self::amount($cents);
            }
            Database::execute('UPDATE member_dividends SET details_json=?,imported_at=NOW() WHERE id=?',[json_encode($after,JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),$row['id']]);
            self::recordChange($memberId,'dividend_update',$reason,$before,$after); $pdo->commit();
        } catch(\Throwable $e) { $pdo->rollBack(); throw $e; }
    }
}
