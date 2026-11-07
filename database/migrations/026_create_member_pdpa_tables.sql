-- =========================================================================
-- RAYONGCOOP DIGITAL PORTAL - Member PDPA Privacy Notice & Acknowledgement Tables
-- Rayong Public Health Savings and Credit Cooperative Limited
-- =========================================================================

-- 1. MEMBER PRIVACY POLICIES
CREATE TABLE IF NOT EXISTS `member_privacy_policies` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `version` VARCHAR(20) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `content` LONGTEXT NOT NULL,
    `content_sha256` CHAR(64) NOT NULL,
    `effective_at` DATETIME NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_policy_version` (`version`),
    INDEX `idx_policy_active` (`is_active`, `effective_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. MEMBER PDPA ACKNOWLEDGEMENTS
CREATE TABLE IF NOT EXISTS `member_pdpa_acknowledgements` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `member_id` BIGINT UNSIGNED NOT NULL,
    `policy_id` BIGINT UNSIGNED NOT NULL,
    `policy_version` VARCHAR(20) NOT NULL,
    `login_nonce_hash` CHAR(64) NOT NULL,
    `acknowledged_at` DATETIME NOT NULL,
    `ip_hash` CHAR(64) DEFAULT NULL,
    `user_agent_hash` CHAR(64) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_user_nonce_policy` (`user_id`, `login_nonce_hash`, `policy_id`),
    INDEX `idx_pdpa_ack_member` (`member_id`),
    INDEX `idx_pdpa_ack_user` (`user_id`),
    INDEX `idx_pdpa_ack_time` (`acknowledged_at`),
    CONSTRAINT `fk_pdpa_ack_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pdpa_ack_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pdpa_ack_policy` FOREIGN KEY (`policy_id`) REFERENCES `member_privacy_policies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed approved initial cooperative privacy policy version 3.0
INSERT INTO `member_privacy_policies` (`id`, `version`, `title`, `content`, `content_sha256`, `effective_at`, `is_active`, `created_at`)
SELECT 1, '3.0', 'ประกาศความเป็นส่วนตัวสำหรับสมาชิกสหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด',
'สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด ให้ความสำคัญอย่างยิ่งต่อการคุ้มครองข้อมูลส่วนบุคคลของสมาชิก เพื่อให้การดำเนินงานและการให้บริการสมาชิกออนไลน์เป็นไปอย่างโปร่งใส มั่นคงปลอดภัย และสอดคล้องกับพระราชบัญญัติคุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562 (PDPA)

1. วัตถุประสงค์ในการประมวลผลข้อมูลส่วนบุคคล
สหกรณ์ประมวลผลข้อมูลส่วนบุคคลของสมาชิกเพื่อการบริหารจัดการข้อมูลสมาชิกภาพ การให้บริการเงินฝาก เงินกู้ ทุนเรือนหุ้น สวัสดิการ การคำนวณและจัดสรรเงินปันผล-เฉลี่ยคืน การตรวจสอบสิทธิประโยชน์ และการติดต่อแจ้งข้อมูลสำคัญตามสัญญาสมาชิกภาพและหน้าที่ตามกฎหมาย

2. การรักษาความมั่นคงปลอดภัยของข้อมูล
สหกรณ์กำหนดมาตรการรักษาความปลอดภัยทางเทคนิคและการบริหารจัดการที่เข้มงวด ได้แก่ การเข้ารหัสข้อมูล (Encryption), การควบคุมการเข้าถึงตามบทบาท (Role-Based Access Control), การจัดเก็บประวัติการเข้าถึง (Audit Trail) และการยืนยันตัวตนก่อนเข้าถึงข้อมูลทางการเงิน

3. สิทธิของเจ้าของข้อมูลส่วนบุคคล
สมาชิกมีสิทธิตามกฎหมาย ได้แก่ สิทธิในการขอเข้าถึงและรับสำเนาข้อมูล, สิทธิขอแก้ไขข้อมูลให้ถูกต้องเป็นปัจจุบัน, สิทธิคัดค้านการประมวลผล, สิทธิขอให้ลบหรือระงับการใช้ข้อมูลภายใต้เงื่อนไขกฎหมาย และสิทธิยื่นเรื่องร้องเรียนตาม พ.ร.บ. คุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562

4. คำเตือนความปลอดภัยในการใช้งานระบบ
- สมาชิกต้องเก็บรักษารหัสผ่านและข้อมูลประจำตัวไว้เป็นความลับ ห้ามเปิดเผยแก่บุคคลอื่น
- สหกรณ์ไม่มีนโยบายสอบถามรหัสผ่านส่วนตัวของสมาชิกผ่านทางโทรศัพท์ SMS หรือสื่อสังคมออนไลน์
- กรุณาออกจากระบบ (Logout) ทุกครั้งหลังเสร็จสิ้นการใช้งาน โดยเฉพาะการใช้งานผ่านอุปกรณ์ร่วมหรืออุปกรณ์สาธารณะ

5. ช่องทางการติดต่อสหกรณ์
สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด
เลขที่ 428/8 ถนนสุขุมวิท ตำบลเนินพระ อำเภอเมืองระยอง จังหวัดระยอง 21000
โทรศัพท์: 038-611178, 038-617042 | อีเมล: dpo@rayongcoop.org | เว็บไซต์: /privacy/policy',
'a6589369a62ddae8ef47d47aa7f454f501090ff714ec397b7bc1fcf651ea6b4f',
'2026-10-31 00:00:00', 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM `member_privacy_policies` WHERE `version` = '3.0');
