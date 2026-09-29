-- Disable only member-facing entry points while preserving their records for
-- future activation. Public product information remains active.
UPDATE `eservice_links`
SET `status` = 'inactive', `updated_at` = NOW()
WHERE `category` = 'member';

UPDATE `hero_slides`
SET `button_text` = 'ระบบสมาชิกออนไลน์อยู่ระหว่างดำเนินการ',
    `button_url` = '/service-unavailable',
    `updated_at` = NOW()
WHERE `button_url` IN ('/eservice', '/member', '/member/dashboard', '/portal');

UPDATE `popups`
SET `button_text` = 'ระบบสมาชิกออนไลน์อยู่ระหว่างดำเนินการ',
    `button_url` = '/service-unavailable',
    `updated_at` = NOW()
WHERE `button_url` IN ('/eservice', '/member', '/member/dashboard', '/portal');

UPDATE `announcements`
SET `link_text` = 'ดูประกาศจากสหกรณ์',
    `link_url` = '/news',
    `updated_at` = NOW()
WHERE `link_url` IN ('/eservice', '/member', '/member/dashboard', '/portal');
