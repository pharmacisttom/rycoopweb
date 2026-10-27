-- =========================================================================
-- Migration 020: Sync Deposit Rates with Official RayongCoop Announcement
-- Rayong Public Health Savings and Credit Cooperative Limited
-- =========================================================================

-- 1. Update Special Savings Rate to match official announcement (1.750%)
UPDATE `interest_rates`
SET `rate` = 1.750,
    `min_amount` = 1000.00,
    `condition_note` = 'เปิดบัญชีขั้นต่ำ 1,000 บาท จ่ายดอกเบี้ยปีละ 2 ครั้ง',
    `effective_date` = '2026-01-01',
    `status` = 'active',
    `updated_at` = NOW()
WHERE `product_type` = 'deposit'
  AND `product_name` = 'เงินฝากออมทรัพย์พิเศษ';

-- 2. Ensure Regular Savings (1.500%) exists in interest_rates
INSERT INTO `interest_rates` (
    `product_type`, `product_name`, `rate`, `min_amount`, `condition_note`,
    `effective_date`, `status`, `sort_order`, `created_by`, `created_at`, `updated_at`
)
SELECT 'deposit', 'เงินฝากออมทรัพย์', 1.500, 100.00, 'เปิดบัญชีขั้นต่ำ 100 บาท ไม่จำกัดจำนวนครั้งการฝาก-ถอน',
       '2026-01-01', 'active', 0, 1, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `interest_rates`
    WHERE `product_type` = 'deposit' AND `product_name` = 'เงินฝากออมทรัพย์'
);

-- Update rate if it already existed
UPDATE `interest_rates`
SET `rate` = 1.500,
    `min_amount` = 100.00,
    `condition_note` = 'เปิดบัญชีขั้นต่ำ 100 บาท ไม่จำกัดจำนวนครั้งการฝาก-ถอน',
    `effective_date` = '2026-01-01',
    `status` = 'active',
    `updated_at` = NOW()
WHERE `product_type` = 'deposit'
  AND `product_name` = 'เงินฝากออมทรัพย์';

-- 3. Sync deposit_products rates
UPDATE `deposit_products`
SET `interest_rate` = 1.750,
    `min_deposit` = 1000.00,
    `updated_at` = NOW()
WHERE `slug` = 'special-savings' OR `name` LIKE '%ออมทรัพย์พิเศษ%';

-- Ensure standard savings exists in deposit_products
INSERT INTO `deposit_products` (
    `name`, `slug`, `short_description`, `interest_rate`, `min_deposit`,
    `withdrawal_condition`, `is_featured`, `sort_order`, `status`, `created_at`, `updated_at`
)
SELECT 'เงินฝากออมทรัพย์ (Savings Account)', 'savings-account', 'ฝาก-ถอนได้ทุกวันทำการ คล่องตัวสูง พร้อมผลตอบแทนคุ้มค่า',
       1.500, 100.00, 'ถอนได้ไม่จำกัดจำนวนครั้ง', 1, 0, 'active', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `deposit_products` WHERE `slug` = 'savings-account'
);
