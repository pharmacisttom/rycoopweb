-- =========================================================================
-- Migration 008: Update Loan Products & Interest Rates to Master Data (10 Types)
-- Rayong Public Health Savings and Credit Cooperative Limited
-- =========================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. UPSERT THE 10 MASTER DATA ITEMS WITHOUT REMOVING ADMIN-CREATED RECORDS

INSERT INTO `loan_products` (
    `id`, `category`, `name`, `slug`, `short_description`, `full_description`, 
    `interest_rate`, `max_loan_limit`, `max_term_months`, `calculation_type`, 
    `eligibility`, `guarantor_requirement`, `collateral`, `documents`, 
    `is_featured`, `is_calculator_enabled`, `sort_order`, `status`, `created_at`, `updated_at`
) VALUES
(1, 'general', 'เงินกู้สามัญ', 'ordinary-loan', 'เสริมสภาพคล่องทางการเงิน ลงทุน และพัฒนาคุณภาพชีวิต', 'สินเชื่อสามัญเพื่อการครองชีพ ชำระหนี้สินภายนอก หรือเสริมสภาพคล่องทางการเงิน อัตราดอกเบี้ย 6.15% ต่อปี แบบลดต้นลดดอก ยื่นกู้ง่าย อนุมัติรวดเร็วตามรอบประชุม', 6.150, 3000000.00, 180, 'effective', 'เป็นสมาชิกสหกรณ์มาแล้วไม่น้อยกว่า 1 ปี', 'สมาชิกสหกรณ์ค้ำประกัน 1-3 คน หรือใช้มูลค่าหุ้นค้ำ', 'ไม่มี', 'คำขอกู้เงินสามัญ, สำเนาบัตรประชาชน, สลิปเงินเดือน 3 เดือนล่าสุด', 1, 1, 1, 'active', NOW(), NOW()),
(2, 'emergency', 'เงินกู้ฉุกเฉิน', 'emergency-loan', 'อนุมัติไว ภายใน 24 ชม. แก้ปัญหาเฉพาะหน้าทันใจ', 'เงินกู้สำหรับสมาชิกที่มีความจำเป็นเร่งด่วน ใช้จ่ายฉุกเฉินทางการแพทย์ ซ่อมแซมบ้าน หรือภาระเร่งด่วน อัตราดอกเบี้ย 4.75% ต่อปี ไม่ต้องมีบุคคลค้ำประกัน', 4.750, 100000.00, 12, 'effective', 'เป็นสมาชิกสหกรณ์มาแล้วไม่น้อยกว่า 6 เดือน', 'ไม่ต้องใช้บุคคลค้ำประกัน (ใช้หุ้นหรือเงินเดือนค้ำ)', 'ไม่มี', 'คำขอกู้เงินฉุกเฉิน, สลิปเงินเดือนล่าสุด', 1, 1, 2, 'active', NOW(), NOW()),
(3, 'special', 'เงินกู้พิเศษ', 'special-loan', 'เพื่อซื้อที่ดิน สร้างบ้าน รีไฟแนนซ์ หรือลงทุนอสังหาริมทรัพย์', 'สินเชื่อพิเศษเพื่อการเคหะและลงทุน วงเงินสูงสุด 5,000,000 บาท อัตราดอกเบี้ย 5.25% ต่อปี ผ่อนชำระได้นานสูงสุด 360 งวด', 5.250, 5000000.00, 360, 'effective', 'เป็นสมาชิกสหกรณ์มาแล้วไม่น้อยกว่า 2 ปี', 'จดทะเบียนจำนองอสังหาริมทรัพย์เป็นประกัน', 'โฉนดที่ดิน, สัญญาจะซื้อจะขาย', 'คำขอกู้พิเศษ, เอกสารหลักทรัพย์, สลิปเงินเดือน, ทะเบียนบ้าน', 1, 1, 3, 'active', NOW(), NOW()),
(4, 'general', 'เงินกู้ผ่อนชำระสินค้า ฯ', 'goods-installment-loan', 'ผ่อนชำระเครื่องใช้ไฟฟ้า อุปกรณ์ไอที และสินค้าอุปโภคบริโภค', 'สินเชื่อเพื่อการจัดหาสินค้าและอุปกรณ์จำเป็นในการดำรงชีพ อัตราดอกเบี้ย 5.50% ต่อปี ผ่อนสบายสูงสุด 36 งวด', 5.500, 200000.00, 36, 'effective', 'เป็นสมาชิกสหกรณ์มาแล้วไม่น้อยกว่า 6 เดือน', 'สมาชิกสหกรณ์ค้ำประกัน 1 คน หรือหลักฐานการซื้อสินค้า', 'ไม่มี', 'คำขอกู้ผ่อนชำระสินค้า, ใบเสนอราคา/ใบเสร็จสินค้า', 0, 1, 4, 'active', NOW(), NOW()),
(5, 'special', 'เงินกู้เพื่อการศึกษา', 'education-loan', 'สนับสนุนทุนการศึกษาของสมาชิกและบุตร ดอกเบี้ยผ่อนปรนพิเศษ', 'สินเชื่อเพื่อสนับสนุนการศึกษาทุกระดับชั้น อัตราดอกเบี้ยผ่อนปรนพิเศษ 2.00% ต่อปี เพื่อแบ่งเบาภาระค่าใช้จ่ายทางการศึกษา', 2.000, 500000.00, 60, 'effective', 'เป็นสมาชิกสหกรณ์มาแล้วไม่น้อยกว่า 6 เดือน', 'สมาชิกสหกรณ์ค้ำประกัน / หุ้นค้ำประกัน', 'ไม่มี', 'คำขอกู้เพื่อการศึกษา, หลักฐานการศึกษาหรือหนังสือรับรองจากสถาบัน', 0, 1, 5, 'active', NOW(), NOW()),
(6, 'general', 'เงินกู้เพื่อพัฒนาคุณภาพชีวิต', 'life-development-loan', 'เพื่อการปรับปรุงที่อยู่อาศัย ซ่อมแซมยานพาหนะ และพัฒนาคุณภาพชีวิตครอบครัว', 'สินเชื่อเพื่อส่งเสริมคุณภาพชีวิตความเป็นอยู่ที่ดีของครอบครัวสมาชิก อัตราดอกเบี้ย 5.50% ต่อปี ผ่อนชำระสูงสุด 120 งวด', 5.500, 1000000.00, 120, 'effective', 'เป็นสมาชิกสหกรณ์มาแล้วไม่น้อยกว่า 1 ปี', 'สมาชิกสหกรณ์ค้ำประกัน 1-2 คน', 'ไม่มี', 'คำขอกู้เงิน, สลิปเงินเดือน, สำเนาทะเบียนบ้าน', 0, 1, 6, 'active', NOW(), NOW()),
(7, 'special', 'เงินกู้เพื่อจัดซื้อรถยนต์', 'car-purchase-loan', 'สินเชื่อเพื่อซื้อยานพาหนะใหม่หรือมือสอง ดอกเบี้ยประหยัด', 'สินเชื่อจัดซื้อรถยนต์หรือจักรยานยนต์สำหรับบุคลากรสาธารณสุข อัตราดอกเบี้ยต่ำ 4.00% ต่อปี ผ่อนชำระสูงสุด 84 งวด', 4.000, 1500000.00, 84, 'effective', 'เป็นสมาชิกสหกรณ์มาแล้วไม่น้อยกว่า 1 ปี', 'โอนสิทธิทะเบียนรถยนต์ หรือสมาชิกสหกรณ์ค้ำประกัน', 'เล่มทะเบียนรถยนต์', 'คำขอกู้เพื่อจัดซื้อรถยนต์, เอกสารรถยนต์, สลิปเงินเดือน', 0, 1, 7, 'active', NOW(), NOW()),
(8, 'special', 'เงินกู้พิเศษเพื่อความมั่นคงในชีวิต', 'life-security-special-loan', 'สร้างความมั่นคงในระยะยาวและเตรียมพร้อมสู่วัยเกษียณ', 'สินเชื่อระยะยาวเพื่อสร้างหลักประกันและความมั่นคงในชีวิต อัตราดอกเบี้ย 5.25% ต่อปี ผ่อนชำระสูงสุด 240 งวด', 5.250, 3000000.00, 240, 'effective', 'เป็นสมาชิกสหกรณ์มาแล้วไม่น้อยกว่า 2 ปี', 'อสังหาริมทรัพย์ / สมาชิกค้ำประกัน', 'โฉนดที่ดินหรือหุ้นสหกรณ์', 'คำขอกู้พิเศษ, สลิปเงินเดือน, เอกสารหลักประกัน', 0, 1, 8, 'active', NOW(), NOW()),
(9, 'special', 'เงินกู้เพื่อปรับปรุงโครงสร้างหนี้', 'debt-restructuring-loan', 'รวมหนี้ภายนอก ลดภาระดอกเบี้ยรายเดือน ผ่อนชำระทางเดียว', 'สินเชื่อรวมหนี้สินสถาบันการเงินภายนอกมาไว้ที่สหกรณ์ เพื่อลดภาระดอกเบี้ยจ่ายและผ่อนชำระทางเดียว ดอกเบี้ย 4.75% ต่อปี สูงสุด 180 งวด', 4.750, 2500000.00, 180, 'effective', 'เป็นสมาชิกสหกรณ์มาแล้วไม่น้อยกว่า 1 ปี', 'สมาชิกสหกรณ์ค้ำประกัน / หุ้นค้ำประกัน', 'ไม่มี', 'คำขอกู้ปรับโครงสร้างหนี้, หนังสือรับรองภาระหนี้ภายนอก', 0, 1, 9, 'active', NOW(), NOW()),
(10, 'special', 'เงินกู้รับการค้ำประกัน', 'guaranteed-loan', 'กู้เงินโดยใช้เงินฝากหรือสิทธิเรียกร้องเป็นประกัน ดอกเบี้ยต่ำพิเศษสุด', 'สินเชื่อที่ใช้สมุดเงินฝากหรือหลักทรัพย์สิทธิเรียกร้องค้ำประกันเต็มวงเงิน อนุมัติรวดเร็ว อัตราดอกเบี้ยต่ำพิเศษ 2.00% ต่อปี', 2.000, 1000000.00, 60, 'effective', 'เป็นสมาชิกสหกรณ์และมีบัญชีเงินฝากหรือสิทธิเรียกร้องกับสหกรณ์', 'เงินฝากหรือสิทธิเรียกร้องเป็นประกัน', 'สมุดบัญชีเงินฝากสหกรณ์', 'คำขอกู้เงิน, สำเนาสมุดเงินฝากสหกรณ์', 0, 1, 10, 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`), `interest_rate` = VALUES(`interest_rate`),
    `max_loan_limit` = VALUES(`max_loan_limit`), `max_term_months` = VALUES(`max_term_months`),
    `sort_order` = VALUES(`sort_order`), `status` = VALUES(`status`), `updated_at` = NOW();

-- 2. MERGE LOAN INTEREST RATES WITHOUT DELETING HISTORY OR CUSTOM RATES
CREATE TEMPORARY TABLE `_seed_loan_rates` (
    `product_type` VARCHAR(50), `product_name` VARCHAR(255), `rate` DECIMAL(8,3),
    `effective_date` DATE, `status` VARCHAR(20), `sort_order` INT,
    `created_by` BIGINT UNSIGNED, `created_at` DATETIME, `updated_at` DATETIME
);
INSERT INTO `_seed_loan_rates` (`product_type`, `product_name`, `rate`, `effective_date`, `status`, `sort_order`, `created_by`, `created_at`, `updated_at`) VALUES
('loan', 'เงินกู้สามัญ', 6.150, '2026-01-01', 'active', 1, 1, NOW(), NOW()),
('loan', 'เงินกู้ฉุกเฉิน', 4.750, '2026-01-01', 'active', 2, 1, NOW(), NOW()),
('loan', 'เงินกู้พิเศษ', 5.250, '2026-01-01', 'active', 3, 1, NOW(), NOW()),
('loan', 'เงินกู้ผ่อนชำระสินค้า ฯ', 5.500, '2026-01-01', 'active', 4, 1, NOW(), NOW()),
('loan', 'เงินกู้เพื่อการศึกษา', 2.000, '2026-01-01', 'active', 5, 1, NOW(), NOW()),
('loan', 'เงินกู้เพื่อพัฒนาคุณภาพชีวิต', 5.500, '2026-01-01', 'active', 6, 1, NOW(), NOW()),
('loan', 'เงินกู้เพื่อจัดซื้อรถยนต์', 4.000, '2026-01-01', 'active', 7, 1, NOW(), NOW()),
('loan', 'เงินกู้พิเศษเพื่อความมั่นคงในชีวิต', 5.250, '2026-01-01', 'active', 8, 1, NOW(), NOW()),
('loan', 'เงินกู้เพื่อปรับปรุงโครงสร้างหนี้', 4.750, '2026-01-01', 'active', 9, 1, NOW(), NOW()),
('loan', 'เงินกู้รับการค้ำประกัน', 2.000, '2026-01-01', 'active', 10, 1, NOW(), NOW());

UPDATE `interest_rates` AS target
JOIN `_seed_loan_rates` AS source
  ON target.`product_type` = source.`product_type`
 AND target.`product_name` = source.`product_name`
 AND target.`effective_date` = source.`effective_date`
SET target.`rate` = source.`rate`, target.`status` = source.`status`,
    target.`sort_order` = source.`sort_order`, target.`updated_at` = NOW();

INSERT INTO `interest_rates`
    (`product_type`, `product_name`, `rate`, `effective_date`, `status`, `sort_order`, `created_by`, `created_at`, `updated_at`)
SELECT source.`product_type`, source.`product_name`, source.`rate`, source.`effective_date`,
       source.`status`, source.`sort_order`, source.`created_by`, source.`created_at`, source.`updated_at`
FROM `_seed_loan_rates` AS source
WHERE NOT EXISTS (
    SELECT 1 FROM `interest_rates` AS target
    WHERE target.`product_type` = source.`product_type`
      AND target.`product_name` = source.`product_name`
      AND target.`effective_date` = source.`effective_date`
);
DROP TEMPORARY TABLE `_seed_loan_rates`;

SET FOREIGN_KEY_CHECKS = 1;
