<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;
use PDO;

class DatabaseSeeder
{
    public static function run(): void
    {
        $pdo = Database::connect();
        echo "Starting Database Seeding...\n";

        // 1. SEED ROLES
        $rolesConfig = config('permissions.roles', []);
        $roleIds = [];
        foreach ($rolesConfig as $slug => $role) {
            $stmt = $pdo->prepare("INSERT INTO roles (name, slug, description, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description)");
            $stmt->execute([$role['name'], $slug, $role['description']]);
            $roleIds[$slug] = (int) $pdo->lastInsertId() ?: (int) Database::value("SELECT id FROM roles WHERE slug = ?", [$slug]);
        }
        echo "✓ Seeded 11 Roles\n";

        // 2. SEED PERMISSIONS
        $modules = config('permissions.modules', []);
        $actions = config('permissions.permissions', []);
        $permissionIds = [];

        foreach ($modules as $module) {
            foreach ($actions as $action => $desc) {
                $name = "{$action}_{$module}";
                $stmt = $pdo->prepare("INSERT INTO permissions (name, module, action, description, created_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name)");
                $stmt->execute([$name, $module, $action, "{$desc} สำหรับ {$module}"]);
                $permId = (int) $pdo->lastInsertId() ?: (int) Database::value("SELECT id FROM permissions WHERE module = ? AND action = ?", [$module, $action]);
                $permissionIds[] = $permId;
            }
        }
        echo "✓ Seeded " . count($permissionIds) . " Permissions\n";

        // 3. MAP SUPER_ADMIN TO ALL PERMISSIONS
        $superAdminRoleId = $roleIds['super_admin'] ?? 1;
        foreach ($permissionIds as $permId) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$superAdminRoleId, $permId]);
        }

        // 4. SEED SUPER ADMIN USER
        $adminUsername = trim((string) env('INITIAL_ADMIN_USERNAME', 'admin'));
        $adminEmail = trim((string) env('INITIAL_ADMIN_EMAIL', 'admin@rayongcoop.com'));
        $adminPasswordPlaintext = (string) env('INITIAL_ADMIN_PASSWORD', '');
        if (strlen($adminPasswordPlaintext) < 16) {
            throw new \RuntimeException(
                'INITIAL_ADMIN_PASSWORD must be set to a unique password of at least 16 characters before running db:seed.'
            );
        }

        $adminPassword = password_hash($adminPasswordPlaintext, PASSWORD_BCRYPT);
        $adminUuid = '00000000-0000-0000-0000-000000000001';

        $stmt = $pdo->prepare("INSERT INTO users (uuid, name, username, email, password, status, two_factor_enabled, created_at) 
                               VALUES (?, ?, ?, ?, ?, 'active', 0, NOW()) 
                               ON DUPLICATE KEY UPDATE password=VALUES(password)");
        $stmt->execute([$adminUuid, 'ผู้ดูแลระบบสูงสุด (Super Admin)', $adminUsername, $adminEmail, $adminPassword]);
        $adminId = (int) $pdo->lastInsertId() ?: (int) Database::value('SELECT id FROM users WHERE email = ?', [$adminEmail]);

        // Assign super_admin role
        $stmt = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$adminId, $superAdminRoleId]);
        echo "✓ Seeded Super Admin User ({$adminUsername})\n";

        // 5. SEED COOKIE CATEGORIES
        $cookieCategories = [
            ['necessary', 'คุกกี้ที่จำเป็น', 'Strictly Necessary Cookies', 'คุกกี้ที่มีความจำเป็นสำหรับการทำงานของเว็บไซต์ เพื่อให้ท่านสามารถใช้งานได้อย่างปลอดภัยและมีประสิทธิภาพ', 'Essential cookies for website security and core functionality', 1, 1, 1],
            ['functional', 'คุกกี้เพื่อการทำงานของเว็บไซต์', 'Functional Cookies', 'ช่วยจดจำการตั้งค่าและตัวเลือกของท่านเพื่อความสะดวกในการเข้าใช้งานในครั้งถัดไป', 'Remember your preferences and settings', 0, 1, 2],
            ['analytics', 'คุกกี้เพื่อการวิเคราะห์และวัดผล', 'Analytics Cookies', 'ช่วยให้เราทราบถึงการมีปฏิสัมพันธ์ของผู้ใช้งาน เพื่อนำมาปรับปรุงพัฒนาประสบการณ์การใช้งานให้ดียิ่งขึ้น', 'Help us measure traffic and improve portal performance', 0, 0, 3],
            ['marketing', 'คุกกี้เพื่อการตลาดและประชาสัมพันธ์', 'Marketing Cookies', 'ใช้เพื่อนำเสนอข้อมูล ข่าวสาร และสิทธิประโยชน์ของสหกรณ์ที่ตรงกับความสนใจของท่าน', 'Provide relevant cooperative news and benefits', 0, 0, 4],
            ['third_party', 'คุกกี้จากบุคคลภายนอก', 'Third-party Cookies', 'คุกกี้จากผู้ให้บริการภายนอก เช่น Google Maps หรือ YouTube เพื่อแสดงเนื้อหาสื่อมัลติมีเดีย', 'Cookies from third party services like maps and embedded media', 0, 0, 5],
        ];
        foreach ($cookieCategories as $c) {
            $stmt = $pdo->prepare("INSERT INTO cookie_categories (code, name_th, name_en, description_th, description_en, is_required, default_state, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name_th=VALUES(name_th)");
            $stmt->execute($c);
        }
        echo "✓ Seeded Cookie Categories\n";

        // 6. SEED DOCUMENT CATEGORIES
        $docCategories = [
            ['ระเบียบและข้อบังคับ', 'regulations', 'ระเบียบ ข้อบังคับ และกฎหมายที่เกี่ยวข้องกับสหกรณ์', 1],
            ['แบบฟอร์มเงินฝาก-ถอน', 'deposit-forms', 'แบบฟอร์มเปิดบัญชี ฝากเงิน และถอนเงิน', 2],
            ['แบบฟอร์มคำขอกู้เงิน', 'loan-forms', 'แบบฟอร์มยื่นกู้เงินสามัญ ฉุกเฉิน พิเศษ และสวัสดิการ', 3],
            ['แบบฟอร์มสวัสดิการสมาชิก', 'welfare-forms', 'แบบฟอร์มขอรับทุนและเงินสวัสดิการต่าง ๆ', 4],
            ['รายงานประจำปีและงบการเงิน', 'annual-reports', 'รายงานประจำปี งบแสดงฐานะการเงิน และผลการดำเนินงาน', 5],
            ['ประกาศสหกรณ์', 'announcements', 'ประกาศผลการสรรหา ประกาศอัตราดอกเบี้ย และประกาศสำคัญ', 6],
        ];
        foreach ($docCategories as $dc) {
            $stmt = $pdo->prepare("INSERT INTO document_categories (name, slug, description, sort_order) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name)");
            $stmt->execute($dc);
        }
        echo "✓ Seeded Document Categories\n";

        // 7. SEED NEWS CATEGORIES
        $newsCategories = [
            ['ข่าวประชาสัมพันธ์', 'pr-news', 'ข่าวสารประชาสัมพันธ์ทั่วไปของสหกรณ์', 1],
            ['ข่าวกิจกรรมและภาพถ่าย', 'activities', 'ประมวลภาพกิจกรรมและการอบรมสัมมนา', 2],
            ['ข่าวสารทางการเงินและเงินปันผล', 'financial-news', 'ข่าวแจ้งการจ่ายเงินปันผลและผลการดำเนินงาน', 3],
            ['สาระน่ารู้การออมและการลงทุน', 'knowledge', 'บทความให้ความรู้เรื่องการวางแผนทางการเงิน', 4],
        ];
        $newsCatIds = [];
        foreach ($newsCategories as $nc) {
            $stmt = $pdo->prepare("INSERT INTO news_categories (name, slug, description, sort_order) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name)");
            $stmt->execute($nc);
            $newsCatIds[$nc[1]] = (int) $pdo->lastInsertId() ?: (int) Database::value("SELECT id FROM news_categories WHERE slug = ?", [$nc[1]]);
        }
        echo "✓ Seeded News Categories\n";

        // 8. SEED HERO SLIDES
        $heroSlides = [
            [
                'มั่นคง โปร่งใส ทันสมัย เพื่อคุณภาพชีวิตที่ดีของสมาชิก',
                'สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด',
                'พร้อมเคียงข้างบุคลากรสาธารณสุขจังหวัดระยอง ด้วยบริการทางการเงินครบวงจรและอัตราผลตอบแทนที่คุ้มค่า',
                '/assets/img/hero_bg_coop.jpg',
                '/assets/img/hero_bg_coop.jpg',
                'เข้าสู่ระบบ E-Service',
                '/eservice',
                '_self',
                'left',
                'left',
                0.40,
                1,
                10,
                'active'
            ],
            [
                'สินเชื่ออัตราดอกเบี้ยพิเศษ เพื่อความมั่นคงของครอบครัว',
                'สินเชื่อสามัญและสินเชื่อเพื่อที่อยู่อาศัย',
                'อนุมัติไว วงเงินกู้สูง ผ่อนชำระสบาย พร้อมคำนวณเงินกู้ออนไลน์ได้ทันที',
                '/assets/img/hero_bg_default.jpg',
                '/assets/img/hero_bg_default.jpg',
                'คำนวณเงินกู้ออนไลน์',
                '/calculator',
                '_self',
                'left',
                'left',
                0.40,
                2,
                9,
                'active'
            ],
            [
                'ออมเงินมั่นคง ผลตอบแทนคุ้มค่า ปลอดภาษี',
                'เงินฝากออมทรัพย์พิเศษและเงินฝากประจำ',
                'เพิ่มพูนความมั่งคั่งสำหรับสมาชิกสหกรณ์สาธารณสุขระยอง ด้วยอัตราดอกเบี้ยเงินฝากสูง',
                '/assets/img/hero_bg_health.jpg',
                '/assets/img/hero_bg_health.jpg',
                'ดูอัตราดอกเบี้ยเงินฝาก',
                '/deposits',
                '_self',
                'left',
                'left',
                0.40,
                3,
                8,
                'active'
            ]
        ];
        foreach ($heroSlides as $hs) {
            if (Database::value("SELECT id FROM hero_slides WHERE title = ? LIMIT 1", [$hs[0]])) {
                continue;
            }
            $stmt = $pdo->prepare("INSERT INTO hero_slides (title, subtitle, description, desktop_image, mobile_image, button_text, button_url, button_target, text_position, text_alignment, overlay_opacity, sort_order, priority, status, start_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute($hs);
        }
        echo "✓ Seeded Hero Slides\n";

        // 9. SEED POPUP
        $stmt = $pdo->prepare("INSERT INTO popups (title, type, content, desktop_image, button_text, button_url, display_mode, delay_seconds, frequency, priority, status, start_at) SELECT ?, ?, ?, ?, ?, ?, 'load', 1, 'session', 'normal', 'active', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM popups WHERE desktop_image = '/assets/img/popup_dividend.jpg')");
        $stmt->execute([
            'ประกาศจ่ายเงินปันผลและเงินเฉลี่ยคืน ประจำปีบัญชี 2568',
            'image_text',
            'สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด ขอแจ้งกำหนดการจ่ายเงินปันผลและเงินเฉลี่ยคืนประจำปี สมาชิกสามารถตรวจสอบยอดเงินปันผลผ่านระบบ E-Service ได้ตั้งแต่วันนี้เป็นต้นไป',
            '/assets/img/popup_dividend.jpg',
            'ตรวจสอบเงินปันผลออนไลน์',
            '/eservice',
        ]);
        $popupId = (int) $pdo->lastInsertId();
        if ($popupId === 0) {
            $popupId = (int) Database::value("SELECT id FROM popups WHERE desktop_image = '/assets/img/popup_dividend.jpg' LIMIT 1");
        }
        if (!Database::value("SELECT id FROM popup_pages WHERE popup_id = ? AND page_path = '*' AND device_target = 'all' LIMIT 1", [$popupId])) {
            $pdo->prepare("INSERT INTO popup_pages (popup_id, page_path, device_target) VALUES (?, '*', 'all')")->execute([$popupId]);
        }
        echo "✓ Seeded Popup Campaign\n";

        // 10. SEED DEPOSIT PRODUCTS
        $depositProducts = [
            [
                'เงินฝากออมทรัพย์พิเศษ (Special Savings)',
                'special-savings',
                'ผลตอบแทนสูง ถอนได้เดือนละ 1 ครั้งโดยไม่เสียค่าธรรมเนียม ดอกเบี้ยไม่เสียภาษี',
                'บัญชีเงินฝากออมทรัพย์พิเศษสำหรับสมาชิกที่ต้องการออมเงินระยะสั้นถึงปานกลางและได้รับอัตราผลตอบแทนสูงกว่าบัญชีออมทรัพย์ทั่วไป คำนวณดอกเบี้ยเป็นรายวันและทบต้นเข้าบัญชีให้ปีละ 2 ครั้ง',
                3.100,
                500.00,
                10000000.00,
                'ถอนได้เดือนละ 1 ครั้งโดยไม่มีค่าธรรมเนียม หากถอนครั้งที่ 2 ในเดือนเดียวกัน มีค่าธรรมเนียมร้อยละ 1 ของยอดเงินที่ถอน',
                'เป็นสมาชิกสหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด',
                'สำเนาบัตรประชาชน, สำเนาทะเบียนบ้าน, บัญชีธนาคารรับโอน',
                1,
                1
            ],
            [
                'เงินฝากประจำ 12 เดือน (12-Month Fixed Deposit)',
                'fixed-12-months',
                'ออมมั่นใจ ล็อกดอกเบี้ยสูง 3.35% ต่อปี วางแผนอนาคตอย่างมั่นคง',
                'บัญชีเงินฝากประจำระยะเวลา 12 เดือน เหมาะสำหรับการวางแผนทางการเงินระยะยาว รับดอกเบี้ยเมื่อครบกำหนดระยะเวลาการฝาก',
                3.350,
                1000.00,
                20000000.00,
                'ถอนได้เมื่อครบกำหนดสัญญา 12 เดือน',
                'สมาชิกสามัญและสมาชิกสมทบ',
                'สำเนาบัตรประชาชน, สำเนาทะเบียนบ้าน',
                1,
                2
            ],
            [
                'เงินฝากออมทรัพย์สินมัธยัสถ์ (Monthly Accumulative)',
                'monthly-accumulative',
                'สร้างวินัยการออม ฝากเท่ากันทุกเดือน ปลอดภาษี รับดอกเบี้ยสูง 3.50%',
                'เงินฝากออมทรัพย์รายเดือน ปลอดภาษี ฝากจำนวนเท่ากันทุกเดือน ติดต่อกัน 24 เดือน สร้างวินัยทางการเงินที่ดีให้กับครอบครัว',
                3.500,
                1000.00,
                25000.00,
                'เมื่อฝากครบตามกำหนด 24 งวด',
                'สมาชิกสามัญทุกคน (1 คนเปิดได้ 1 บัญชี)',
                'สำเนาบัตรประชาชน, หนังสือยินยอมให้หักเงินได้รายเดือน',
                1,
                3
            ]
        ];
        foreach ($depositProducts as $dp) {
            $stmt = $pdo->prepare("INSERT INTO deposit_products (name, slug, short_description, full_description, interest_rate, min_deposit, max_deposit, withdrawal_condition, eligibility, required_documents, is_featured, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active') ON DUPLICATE KEY UPDATE name=VALUES(name), interest_rate=VALUES(interest_rate), status='active'");
            $stmt->execute($dp);
        }
        echo "✓ Seeded Deposit Products\n";

        // 11. SEED LOAN PRODUCTS (10 MASTER DATA ITEMS)
        $loanProducts = [
            [
                'general',
                'เงินกู้สามัญ',
                'ordinary-loan',
                'เสริมสภาพคล่องทางการเงิน ลงทุน และพัฒนาคุณภาพชีวิต',
                'สินเชื่อสามัญเพื่อการครองชีพ ชำระหนี้สินภายนอก หรือเสริมสภาพคล่องทางการเงิน อัตราดอกเบี้ย 6.15% ต่อปี แบบลดต้นลดดอก ยื่นกู้ง่าย อนุมัติรวดเร็วตามรอบประชุม',
                6.150,
                3000000.00,
                180,
                'effective',
                'เป็นสมาชิกสหกรณ์มาแล้วไม่น้อยกว่า 1 ปี',
                'สมาชิกสหกรณ์ค้ำประกัน 1-3 คน หรือใช้มูลค่าหุ้นค้ำ',
                'ไม่มี',
                'คำขอกู้เงินสามัญ, สำเนาบัตรประชาชน, สลิปเงินเดือน 3 เดือนล่าสุด',
                1,
                1,
                1
            ],
            [
                'emergency',
                'เงินกู้ฉุกเฉิน',
                'emergency-loan',
                'อนุมัติไว ภายใน 24 ชม. แก้ปัญหาเฉพาะหน้าทันใจ',
                'เงินกู้สำหรับสมาชิกที่มีความจำเป็นเร่งด่วน ใช้จ่ายฉุกเฉินทางการแพทย์ ซ่อมแซมบ้าน หรือภาระเร่งด่วน อัตราดอกเบี้ย 4.75% ต่อปี ไม่ต้องมีบุคคลค้ำประกัน',
                4.750,
                100000.00,
                12,
                'effective',
                'เป็นสมาชิกสหกรณ์มาแล้วไม่น้อยกว่า 6 เดือน',
                'ไม่ต้องใช้บุคคลค้ำประกัน (ใช้หุ้นหรือเงินเดือนค้ำ)',
                'ไม่มี',
                'คำขอกู้เงินฉุกเฉิน, สลิปเงินเดือนล่าสุด',
                1,
                1,
                2
            ],
            [
                'special',
                'เงินกู้พิเศษ',
                'special-loan',
                'เพื่อซื้อที่ดิน สร้างบ้าน รีไฟแนนซ์ หรือลงทุนอสังหาริมทรัพย์',
                'สินเชื่อพิเศษเพื่อการเคหะและลงทุน วงเงินสูงสุด 5,000,000 บาท อัตราดอกเบี้ย 5.25% ต่อปี ผ่อนชำระได้นานสูงสุด 360 งวด',
                5.250,
                5000000.00,
                360,
                'effective',
                'เป็นสมาชิกสหกรณ์มาแล้วไม่น้อยกว่า 2 ปี',
                'จดทะเบียนจำนองอสังหาริมทรัพย์เป็นประกัน',
                'โฉนดที่ดิน, สัญญาจะซื้อจะขาย',
                'คำขอกู้พิเศษ, เอกสารหลักทรัพย์, สลิปเงินเดือน, ทะเบียนบ้าน',
                1,
                1,
                3
            ],
            [
                'general',
                'เงินกู้ผ่อนชำระสินค้า ฯ',
                'goods-installment-loan',
                'ผ่อนชำระเครื่องใช้ไฟฟ้า อุปกรณ์ไอที และสินค้าอุปโภคบริโภค',
                'สินเชื่อเพื่อการจัดหาสินค้าและอุปกรณ์จำเป็นในการดำรงชีพ อัตราดอกเบี้ย 5.50% ต่อปี ผ่อนสบายสูงสุด 36 งวด',
                5.500,
                200000.00,
                36,
                'effective',
                'เป็นสมาชิกสหกรณ์มาแล้วไม่น้อยกว่า 6 เดือน',
                'สมาชิกสหกรณ์ค้ำประกัน 1 คน หรือหลักฐานการซื้อสินค้า',
                'ไม่มี',
                'คำขอกู้ผ่อนชำระสินค้า, ใบเสนอราคา/ใบเสร็จสินค้า',
                0,
                1,
                4
            ],
            [
                'special',
                'เงินกู้เพื่อการศึกษา',
                'education-loan',
                'สนับสนุนทุนการศึกษาของสมาชิกและบุตร ดอกเบี้ยผ่อนปรนพิเศษ',
                'สินเชื่อเพื่อสนับสนุนการศึกษาทุกระดับชั้น อัตราดอกเบี้ยผ่อนปรนพิเศษ 2.00% ต่อปี เพื่อแบ่งเบาภาระค่าใช้จ่ายทางการศึกษา',
                2.000,
                500000.00,
                60,
                'effective',
                'เป็นสมาชิกสหกรณ์มาแล้วไม่น้อยกว่า 6 เดือน',
                'สมาชิกสหกรณ์ค้ำประกัน / หุ้นค้ำประกัน',
                'ไม่มี',
                'คำขอกู้เพื่อการศึกษา, หลักฐานการศึกษาหรือหนังสือรับรองจากสถาบัน',
                0,
                1,
                5
            ],
            [
                'general',
                'เงินกู้เพื่อพัฒนาคุณภาพชีวิต',
                'life-development-loan',
                'เพื่อการปรับปรุงที่อยู่อาศัย ซ่อมแซมยานพาหนะ และพัฒนาคุณภาพชีวิตครอบครัว',
                'สินเชื่อเพื่อส่งเสริมคุณภาพชีวิตความเป็นอยู่ที่ดีของครอบครัวสมาชิก อัตราดอกเบี้ย 5.50% ต่อปี ผ่อนชำระสูงสุด 120 งวด',
                5.500,
                1000000.00,
                120,
                'effective',
                'เป็นสมาชิกสหกรณ์มาแล้วไม่น้อยกว่า 1 ปี',
                'สมาชิกสหกรณ์ค้ำประกัน 1-2 คน',
                'ไม่มี',
                'คำขอกู้เงิน, สลิปเงินเดือน, สำเนาทะเบียนบ้าน',
                0,
                1,
                6
            ],
            [
                'special',
                'เงินกู้เพื่อจัดซื้อรถยนต์',
                'car-purchase-loan',
                'สินเชื่อเพื่อซื้อยานพาหนะใหม่หรือมือสอง ดอกเบี้ยประหยัด',
                'สินเชื่อจัดซื้อรถยนต์หรือจักรยานยนต์สำหรับบุคลากรสาธารณสุข อัตราดอกเบี้ยต่ำ 4.00% ต่อปี ผ่อนชำระสูงสุด 84 งวด',
                4.000,
                1500000.00,
                84,
                'effective',
                'เป็นสมาชิกสหกรณ์มาแล้วไม่น้อยกว่า 1 ปี',
                'โอนสิทธิทะเบียนรถยนต์ หรือสมาชิกสหกรณ์ค้ำประกัน',
                'เล่มทะเบียนรถยนต์',
                'คำขอกู้เพื่อจัดซื้อรถยนต์, เอกสารรถยนต์, สลิปเงินเดือน',
                0,
                1,
                7
            ],
            [
                'special',
                'เงินกู้พิเศษเพื่อความมั่นคงในชีวิต',
                'life-security-special-loan',
                'สร้างความมั่นคงในระยะยาวและเตรียมพร้อมสู่วัยเกษียณ',
                'สินเชื่อระยะยาวเพื่อสร้างหลักประกันและความมั่นคงในชีวิต อัตราดอกเบี้ย 5.25% ต่อปี ผ่อนชำระสูงสุด 240 งวด',
                5.250,
                3000000.00,
                240,
                'effective',
                'เป็นสมาชิกสหกรณ์มาแล้วไม่น้อยกว่า 2 ปี',
                'อสังหาริมทรัพย์ / สมาชิกค้ำประกัน',
                'โฉนดที่ดินหรือหุ้นสหกรณ์',
                'คำขอกู้พิเศษ, สลิปเงินเดือน, เอกสารหลักประกัน',
                0,
                1,
                8
            ],
            [
                'special',
                'เงินกู้เพื่อปรับปรุงโครงสร้างหนี้',
                'debt-restructuring-loan',
                'รวมหนี้ภายนอก ลดภาระดอกเบี้ยรายเดือน ผ่อนชำระทางเดียว',
                'สินเชื่อรวมหนี้สินสถาบันการเงินภายนอกมาไว้ที่สหกรณ์ เพื่อลดภาระดอกเบี้ยจ่ายและผ่อนชำระทางเดียว ดอกเบี้ย 4.75% ต่อปี สูงสุด 180 งวด',
                4.750,
                2500000.00,
                180,
                'effective',
                'เป็นสมาชิกสหกรณ์มาแล้วไม่น้อยกว่า 1 ปี',
                'สมาชิกสหกรณ์ค้ำประกัน / หุ้นค้ำประกัน',
                'ไม่มี',
                'คำขอกู้ปรับโครงสร้างหนี้, หนังสือรับรองภาระหนี้ภายนอก',
                0,
                1,
                9
            ],
            [
                'special',
                'เงินกู้รับการค้ำประกัน',
                'guaranteed-loan',
                'กู้เงินโดยใช้เงินฝากหรือสิทธิเรียกร้องเป็นประกัน ดอกเบี้ยต่ำพิเศษสุด',
                'สินเชื่อที่ใช้สมุดเงินฝากหรือหลักทรัพย์สิทธิเรียกร้องค้ำประกันเต็มวงเงิน อนุมัติรวดเร็ว อัตราดอกเบี้ยต่ำพิเศษ 2.00% ต่อปี',
                2.000,
                1000000.00,
                60,
                'effective',
                'เป็นสมาชิกสหกรณ์และมีบัญชีเงินฝากหรือสิทธิเรียกร้องกับสหกรณ์',
                'เงินฝากหรือสิทธิเรียกร้องเป็นประกัน',
                'สมุดบัญชีเงินฝากสหกรณ์',
                'คำขอกู้เงิน, สำเนาสมุดเงินฝากสหกรณ์',
                0,
                1,
                10
            ]
        ];
        foreach ($loanProducts as $lp) {
            $stmt = $pdo->prepare("INSERT INTO loan_products (category, name, slug, short_description, full_description, interest_rate, max_loan_limit, max_term_months, calculation_type, eligibility, guarantor_requirement, collateral, documents, is_featured, is_calculator_enabled, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active') ON DUPLICATE KEY UPDATE name=VALUES(name), interest_rate=VALUES(interest_rate), max_loan_limit=VALUES(max_loan_limit), max_term_months=VALUES(max_term_months), status='active'");
            $stmt->execute($lp);
        }
        echo "✓ Seeded Loan Products\n";

        // 12. SEED INTEREST RATES & AUDIT HISTORY
        $rates = [
            ['deposit', 'เงินฝากออมทรัพย์พิเศษ', 3.100, '2026-01-01', 1],
            ['deposit', 'เงินฝากประจำ 12 เดือน', 3.350, '2026-01-01', 2],
            ['deposit', 'เงินฝากออมทรัพย์สินมัธยัสถ์ (24 เดือน)', 3.500, '2026-01-01', 3],
            ['loan', 'เงินกู้สามัญ', 6.150, '2026-01-01', 1],
            ['loan', 'เงินกู้ฉุกเฉิน', 4.750, '2026-01-01', 2],
            ['loan', 'เงินกู้พิเศษ', 5.250, '2026-01-01', 3],
            ['loan', 'เงินกู้ผ่อนชำระสินค้า ฯ', 5.500, '2026-01-01', 4],
            ['loan', 'เงินกู้เพื่อการศึกษา', 2.000, '2026-01-01', 5],
            ['loan', 'เงินกู้เพื่อพัฒนาคุณภาพชีวิต', 5.500, '2026-01-01', 6],
            ['loan', 'เงินกู้เพื่อจัดซื้อรถยนต์', 4.000, '2026-01-01', 7],
            ['loan', 'เงินกู้พิเศษเพื่อความมั่นคงในชีวิต', 5.250, '2026-01-01', 8],
            ['loan', 'เงินกู้เพื่อปรับปรุงโครงสร้างหนี้', 4.750, '2026-01-01', 9],
            ['loan', 'เงินกู้รับการค้ำประกัน', 2.000, '2026-01-01', 10],
        ];
        foreach ($rates as $r) {
            $rateId = (int) Database::value("SELECT id FROM interest_rates WHERE product_type = ? AND product_name = ? AND effective_date = ? LIMIT 1", [$r[0], $r[1], $r[3]]);
            if ($rateId === 0) {
                $stmt = $pdo->prepare("INSERT INTO interest_rates (product_type, product_name, rate, effective_date, status, sort_order, created_by) VALUES (?, ?, ?, ?, 'active', ?, ?)");
                $stmt->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $adminId]);
                $rateId = (int) $pdo->lastInsertId();
            } else {
                $pdo->prepare("UPDATE interest_rates SET rate = ?, status = 'active', sort_order = ? WHERE id = ?")
                    ->execute([$r[2], $r[4], $rateId]);
            }

            // Insert initial history
            if (!Database::value("SELECT id FROM interest_rate_history WHERE interest_rate_id = ? AND effective_date = ? LIMIT 1", [$rateId, $r[3]])) {
                $stmtHist = $pdo->prepare("INSERT INTO interest_rate_history (interest_rate_id, product_name, old_rate, new_rate, effective_date, changed_by, approved_by, note) VALUES (?, ?, ?, ?, ?, ?, ?, 'ประกาศอัตราดอกเบี้ยเริ่มต้นประจำปี')");
                $stmtHist->execute([$rateId, $r[1], $r[2], $r[2], $r[3], $adminId, $adminId]);
            }
        }
        echo "✓ Seeded Interest Rates & History\n";

        // 13. SEED WELFARE PROGRAMS
        $welfarePrograms = [
            [
                'สวัสดิการคุ้มครองและเกื้อกูลสมาชิก',
                'สวัสดิการสมาชิกถึงแก่กรรม (Funeral Welfare)',
                'death-benefit',
                'ช่วยเหลือครอบครัวสมาชิก มอบเงินสงเคราะห์ศพสูงสุด 200,000 บาท ตามอายุการเป็นสมาชิก',
                'สหกรณ์มอบเงินช่วยเหลือค่าจัดการศพแก่ทายาทของสมาชิกที่ถึงแก่กรรม เพื่อเป็นการบรรเทาความเดือดร้อนแก่ครอบครัว',
                'สูงสุด 200,000 บาท',
                'สมาชิกสามัญที่ถึงแก่กรรม',
                'มรณบัตร, สำเนาทะเบียนบ้านที่ประทับตาย, สำเนาบัตรประชาชนผู้รับผลประโยชน์',
                'ยื่นคำร้องขอรับเงินสงเคราะห์ภายใน 120 วันนับแต่วันที่สมาชิกถึงแก่กรรม',
                'ฝ่ายสวัสดิการ โทร. 038-611178 ต่อ 105',
                1
            ],
            [
                'สวัสดิการการศึกษาบุตร',
                'ทุนการศึกษาบุตรสมาชิกประจำปี (Child Education Grant)',
                'education-scholarship',
                'ส่งเสริมการศึกษาบุตรสมาชิก ตั้งแต่ระดับประถมศึกษาถึงระดับปริญญาตรี',
                'มอบทุนการศึกษาประจำปีแก่บุตรสมาชิกที่มีผลการเรียนดีหรือมีความประพฤติดี เพื่อแบ่งเบาภาระค่าใช้จ่ายทางการศึกษาของผู้ปกครอง',
                'ทุนละ 2,000 - 6,000 บาท (ตามระดับชั้น)',
                'บุตรสมาชิกสามัญที่มีอายุไม่เกิน 25 ปี และกำลังศึกษาอยู่',
                'สำเนาใบรายงานผลการศึกษา (เกรด), สำเนาสูติบัตรบุตร, สำเนาบัตรประชาชนสมาชิก',
                'เปิดรับสมัครยื่นขอรับทุนในช่วงเดือนพฤษภาคม - มิถุนายน ของทุกปี',
                'ฝ่ายสวัสดิการ โทร. 038-611178 ต่อ 106',
                2
            ],
            [
                'สวัสดิการมงคลสมรสและคลอดบุตร',
                'เงินรับขวัญบุตรใหม่และสวัสดิการมงคลสมรส',
                'newborn-marriage-grant',
                'ร่วมแสดงความยินดีในโอกาสเริ่มต้นชีวิตคู่และต้อนรับสมาชิกใหม่ของครอบครัว',
                'มอบเงินขวัญถุงให้แก่สมาชิกในโอกาสจดทะเบียนสมรส และเงินรับขวัญบุตรแรกเกิดเพื่อเป็นสิริมงคล',
                'มงคลสมรส 3,000 บาท / คลอดบุตร 2,000 บาทต่อคน',
                'สมาชิกสามัญที่เป็นสมาชิกติดต่อกันไม่น้อยกว่า 1 ปี',
                'สำเนาทะเบียนสมรส หรือ สำเนาสูติบัตรบุตรพร้อมสำเนาทะเบียนบ้าน',
                'ยื่นคำขอรับสวัสดิการภายใน 90 วันนับจากวันจดทะเบียนหรือวันคลอดบุตร',
                'ฝ่ายสวัสดิการ โทร. 038-611178 ต่อ 107',
                3
            ]
        ];
        foreach ($welfarePrograms as $wp) {
            $stmt = $pdo->prepare("INSERT INTO welfare (category, title, slug, short_description, full_description, benefit_amount, eligibility, required_documents, application_process, contact_info, sort_order, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?) ON DUPLICATE KEY UPDATE title=VALUES(title), status='active'");
            $stmt->execute([$wp[0], $wp[1], $wp[2], $wp[3], $wp[4], $wp[5], $wp[6], $wp[7], $wp[8], $wp[9], $wp[10], $adminId]);
        }
        echo "✓ Seeded Member Welfare Programs\n";

        // 14. SEED E-SERVICE GATEWAY (Legacy & External Services)
        $eservices = [
            ['ระบบตรวจสอบข้อมูลสมาชิกและเงินปันผล (Coop Member Online)', 'ตรวจสอบยอดหุ้น เงินฝาก เงินกู้ และเงินปันผลเฉลี่ยคืนออนไลน์ตลอด 24 ชม.', 'https://rayongcoop.com/member/', 'bi-person-badge', 'member', 0, 1, 1, 0, 1],
            ['ระบบตรวจสอบเงินฝากและสเตทเมนท์ (E-Passbook)', 'ดูรายการเคลื่อนไหวทางบัญชีเงินฝากออมทรัพย์และเงินฝากประจำ', 'https://rayongcoop.com/passbook/', 'bi-wallet2', 'deposit', 0, 1, 1, 0, 2],
            ['ระบบยื่นคำขอกู้เงินออนไลน์ (E-Loan Application)', 'ยื่นความประสงค์ขอกู้เงินสามัญและฉุกเฉินผ่านระบบดิจิทัล', 'https://rayongcoop.com/eloan/', 'bi-cash-coin', 'loan', 0, 1, 1, 0, 3],
            ['สมาคมฌาปนกิจสงเคราะห์ สสธท. (สมาคมสาธารณสุขไทย)', 'ระบบตรวจสอบสถานะเงินสงเคราะห์และสมาชิกภาพ สสธท.', 'https://www.ssdt.or.th/', 'bi-shield-check', 'external', 0, 1, 1, 0, 4],
            ['กองทุนสวัสดิการ กสธท.', 'ตรวจสอบสิทธิประโยชน์กองทุนสวัสดิการสมาชิกสาธารณสุข', 'https://www.gsdt.or.th/', 'bi-heart-pulse', 'external', 0, 1, 1, 0, 5],
        ];
        foreach ($eservices as $es) {
            if (Database::value("SELECT id FROM eservice_links WHERE name = ? AND url = ? LIMIT 1", [$es[0], $es[2]])) {
                continue;
            }
            $stmt = $pdo->prepare("INSERT INTO eservice_links (name, description, url, icon, category, is_internal, open_new_tab, confirm_before_redirect, is_maintenance, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute($es);
        }
        echo "✓ Seeded E-Service Gateway Links\n";

        // 15. SEED EXECUTIVE FINANCIAL STATISTICS
        $finStats = [
            [2567, 12, 5420, 1850000000.00, 2150000000.00, 3420000000.00, 4250000000.00, 285000000.00, 0.45, 125000000.00, 5.85, 22.50],
            [2568, 6, 5580, 1920000000.00, 2280000000.00, 3550000000.00, 4420000000.00, 298000000.00, 0.42, 68000000.00, 5.90, 23.10],
            [2568, 12, 5750, 2010000000.00, 2410000000.00, 3680000000.00, 4610000000.00, 312000000.00, 0.38, 138000000.00, 6.00, 24.20],
            [2569, 6, 5890, 2080000000.00, 2520000000.00, 3790000000.00, 4780000000.00, 325000000.00, 0.35, 74000000.00, 6.10, 24.80],
        ];
        foreach ($finStats as $fs) {
            $stmt = $pdo->prepare("INSERT INTO financial_statistics (year, month, total_members, share_capital, total_deposits, total_loans, total_assets, reserve_fund, npl_percentage, net_profit, dividend_rate, liquidity_ratio, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE total_members=VALUES(total_members), total_assets=VALUES(total_assets)");
            $stmt->execute(array_merge($fs, [$adminId]));
        }
        echo "✓ Seeded Executive Financial Statistics\n";

        // 16. SEED SITE SETTINGS
        $settings = [
            ['site_title', 'สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด', 'general', 1],
            ['site_subtitle', 'Rayong Public Health Savings and Credit Cooperative Limited', 'general', 1],
            ['site_phone', '038-611178, 038-617478', 'contact', 1],
            ['site_fax', '038-611179', 'contact', 1],
            ['site_email', 'contact@rayongcoop.com', 'contact', 1],
            ['site_address', 'เลขที่ 444 หมู่ 2 ถนนสุขุมวิท ตำบลเนินพระ อำเภอเมืองระยอง จังหวัดระยอง 21150', 'contact', 1],
            ['office_hours', 'จันทร์ - ศุกร์ 08.30 - 16.30 น. (เว้นวันหยุดราชการและวันหยุดนักขัตฤกษ์)', 'contact', 1],
            ['facebook_url', 'https://facebook.com/rayongcoop', 'social', 1],
            ['line_oa', '@rayongcoop', 'social', 1],
            ['maintenance_mode', '0', 'system', 0],
            ['maintenance_message', 'เว็บไซต์อยู่ระหว่างการปรับปรุงระบบชั่วคราว ขออภัยในความไม่สะดวก', 'system', 1],
            ['allowed_maintenance_ips', '127.0.0.1', 'system', 0],
            ['cookie_policy_version', '1.0', 'privacy', 1],
        ];
        foreach ($settings as $st) {
            $stmt = $pdo->prepare("INSERT INTO site_settings (`key`, `value`, `group`, is_public) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
            $stmt->execute($st);
        }
        echo "✓ Seeded Site Settings\n";

        // 17. SEED INITIAL NEWS
        $newsList = [
            [
                $newsCatIds['pr-news'] ?? 1,
                'แจ้งกำหนดการประชุมใหญ่สามัญประจำปี 2569 และการเลือกตั้งกรรมการดำเนินการ',
                'annual-general-meeting-2569',
                'ขอเชิญสมาชิกสหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด เข้าร่วมการประชุมใหญ่สามัญประจำปี 2569 ในวันเสาร์ที่ 24 ตุลาคม 2569 ณ ห้องประชุมใหญ่สาธารณสุขจังหวัดระยอง',
                '<p>สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด ขอเรียนเชิญสมาชิกทุกท่านเข้าร่วมการประชุมใหญ่สามัญประจำปี 2569 เพื่อพิจารณาอนุมัติงบการเงิน แผนการดำเนินงานประจำปี และเลือกตั้งคณะกรรมการดำเนินการชุดใหม่</p><p>สมาชิกที่เข้าร่วมประชุมจะได้รับของที่ระลึกและมีสิทธิ์ลุ้นรับรางวัลพิเศษมากมาย</p>',
                '/assets/news/sample_news_1.jpg',
                'ประชุมใหญ่, ประกาศ, สมาชิก',
                1,
                1,
                'published'
            ],
            [
                $newsCatIds['financial-news'] ?? 3,
                'สหกรณ์ประกาศอัตราการจ่ายเงินปันผล 6.10% และเงินเฉลี่ยคืน 15.00% ประจำปี 2568',
                'dividend-announcement-2568',
                'มติที่ประชุมใหญ่มีมติอนุมัติจัดสรรกำไรสุทธิ ประจำปี 2568 จ่ายเงินปันผลตามหุ้นในอัตราร้อยละ 6.10 และเงินเฉลี่ยคืนร้อยละ 15.00 โอนเข้าบัญชีสมาชิกทันที',
                '<p>สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด มีผลการดำเนินงานที่เติบโตอย่างมั่นคงต่อเนื่อง โดยในปี 2568 มีกำไรสุทธิรวม 138 ล้านบาท คณะกรรมการดำเนินการจึงมีมติเสนอจัดสรรเงินปันผลตามหุ้นในอัตราร้อยละ 6.10 ต่อปี และเงินเฉลี่ยคืนแก่สมาชิกผู้กู้ในอัตราร้อยละ 15.00</p>',
                '/assets/news/sample_news_2.jpg',
                'เงินปันผล, เงินเฉลี่ยคืน, ผลการดำเนินงาน',
                1,
                1,
                'published'
            ]
        ];
        foreach ($newsList as $nl) {
            $stmt = $pdo->prepare("INSERT INTO news (category_id, title, slug, summary, content, cover_image, tags, is_pinned, is_featured, workflow_status, author_id, publish_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE title=VALUES(title), cover_image=VALUES(cover_image)");
            $stmt->execute(array_merge($nl, [$adminId]));
        }
        echo "✓ Seeded News Articles\n";

        // 18. SEED TOP ANNOUNCEMENT BAR
        $stmt = $pdo->prepare("INSERT INTO announcements (title, message, link_url, link_text, priority, display_type, start_at, is_active, created_by) SELECT ?, ?, ?, ?, 'important', 'top_bar', NOW(), 1, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM announcements WHERE link_url = '/eservice' AND display_type = 'top_bar')");
        $stmt->execute([
            'แจ้งสมาชิกตรวจสอบเงินปันผล',
            'สหกรณ์ฯ ได้โอนเงินปันผลและเงินเฉลี่ยคืน ประจำปี 2568 เข้าบัญชีเงินฝากของท่านเรียบร้อยแล้ว ตรวจสอบยอดผ่านระบบ E-Service',
            '/eservice',
            'คลิกตรวจสอบ',
            $adminId
        ]);
        echo "✓ Seeded Active Top Announcement Bar\n";

        // 19. SEED FAQS
        $faqs = [
            ['general', 'ใครบ้างที่มีสิทธิสมัครเป็นสมาชิกสหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด?', 'ข้าราชการ ลูกจ้างประจำ พนักงานราชการ และพนักงานกระทรวงสาธารณสุข (พกส.) ที่ปฏิบัติงานในสังกัดกระทรวงสาธารณสุขในจังหวัดระยอง รวมถึงสมาชิกสมทบตามระเบียบที่กำหนด', 1],
            ['deposit', 'การเปิดบัญชีเงินฝากออมทรัพย์พิเศษต้องใช้เอกสารอะไรบ้างและมีขั้นต่ำเท่าไร?', 'ใช้สำเนาบัตรประชาชน และสำเนาทะเบียนบ้าน เปิดบัญชีขั้นต่ำเพียง 500 บาท ฝากถอนได้สะดวกที่สำนักงานสหกรณ์ หรือโอนผ่านธนาคาร', 2],
            ['loan', 'หากต้องการยื่นกู้เงินสามัญ ต้องเป็นสมาชิกมาแล้วกี่เดือน?', 'ต้องเป็นสมาชิกติดต่อกันมาแล้วไม่น้อยกว่า 1 ปี และมีค่าหุ้นสะสมตามเกณฑ์ที่ระเบียบสินเชื่อกำหนด', 3],
            ['welfare', 'เงินสวัสดิการสมาชิกถึงแก่กรรม ทายาทต้องยื่นเรื่องภายในกี่วัน?', 'ทายาทผู้มีสิทธิต้องยื่นคำร้องพร้อมหลักฐานใบมรณบัตรและสำเนาทะเบียนบ้านภายใน 120 วันนับแต่วันที่สมาชิกถึงแก่กรรม', 4],
        ];
        foreach ($faqs as $faq) {
            if (Database::value("SELECT id FROM faqs WHERE question = ? LIMIT 1", [$faq[1]])) {
                continue;
            }
            $stmt = $pdo->prepare("INSERT INTO faqs (category, question, answer, sort_order, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->execute($faq);
        }
        echo "✓ Seeded FAQs\n";

        // 20. SEED BOARDS & STAFF
        $boards = [
            ['นายแพทย์สาธารณสุขจังหวัดระยอง', 'ประธานกรรมการดำเนินการ', 'director', '2568 - 2569', 1, '/assets/img/committee/committee-01-president.webp', 1],
            ['ทันตแพทย์ชำนาญการพิเศษ', 'รองประธานกรรมการ คนที่ 1', 'director', '2568 - 2569', 1, '/assets/img/committee/committee-02-vice-president.webp', 2],
            ['เภสัชกรเชี่ยวชาญ', 'รองประธานกรรมการ คนที่ 2', 'director', '2568 - 2569', 1, '/assets/img/committee/committee-03-pochawan.webp', 3],
            ['นักวิชาการสาธารณสุขเชี่ยวชาญ', 'เหรัญญิก', 'director', '2568 - 2569', 1, '/assets/img/committee/committee-04-jatupol.webp', 4],
            ['นักจัดการงานทั่วไปชำนาญการ', 'เลขานุการ', 'director', '2568 - 2569', 1, '/assets/img/committee/committee-05-sunetra.webp', 5],
        ];
        foreach ($boards as $b) {
            if (Database::value("SELECT id FROM boards WHERE name = ? AND term_years = ? LIMIT 1", [$b[0], $b[3]])) {
                continue;
            }
            $stmt = $pdo->prepare("INSERT INTO boards (name, position, role_type, term_years, term_number, photo, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute($b);
        }
        echo "✓ Seeded Board Members\n";

        echo "========================================\n";
        echo "DATABASE SEEDING COMPLETED SUCCESSFULLY!\n";
        echo "========================================\n";
    }
}
