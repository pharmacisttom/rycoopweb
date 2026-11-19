<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;
use RuntimeException;

/**
 * Homepage section visibility only. The public endpoint never exposes draft data.
 * Existing sections remain visible when this feature has not been configured.
 */
final class HomepageLayoutService
{
    private const LIVE_KEY = 'site_layout_home_v1';
    private const DRAFT_KEY = 'site_layout_home_draft_v1';

    public const SECTIONS = [
        'hero' => 'Hero / ข่าวเด่น',
        'quick_services' => 'บริการด่วน',
        'statistics' => 'สถิติและผลการดำเนินงาน',
        'products' => 'ผลิตภัณฑ์สินเชื่อ',
        'calculator' => 'เครื่องคำนวณ',
        'welfare' => 'สวัสดิการ',
    ];

    public static function defaults(): array
    {
        return array_fill_keys(array_keys(self::SECTIONS), true);
    }

    private static function load(string $key, bool $lock = false): ?array
    {
        $sql = 'SELECT `value` FROM site_settings WHERE `key`=?'
            . ($lock && Database::connect()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '');
        $row = Database::first($sql, [$key]);
        if (!$row) return null;
        $data = json_decode((string) $row['value'], true);
        if (!is_array($data)) throw new RuntimeException('INVALID_LAYOUT_CONFIG');
        return self::validate($data);
    }

    public static function published(): array
    {
        return self::load(self::LIVE_KEY) ?? self::defaults();
    }

    public static function adminState(bool $lock = false): array
    {
        $published = self::load(self::LIVE_KEY, $lock) ?? self::defaults();
        $draft = self::load(self::DRAFT_KEY, $lock);
        return [
            'published' => $published,
            'draft' => $draft,
            'sections' => self::SECTIONS,
            'version' => hash('sha256', json_encode([$published, $draft], JSON_THROW_ON_ERROR)),
        ];
    }

    private static function validate(array $raw): array
    {
        if (array_keys($raw) !== array_keys(self::SECTIONS)) {
            throw new InvalidArgumentException('รายการส่วนหน้าเว็บไม่ตรงกับเวอร์ชันที่รองรับ');
        }
        foreach ($raw as $value) {
            if (!is_bool($value)) throw new InvalidArgumentException('สถานะการแสดงผลต้องเป็น true หรือ false');
        }
        return $raw;
    }

    private static function store(string $key, array $value): void
    {
        Database::execute(
            "INSERT INTO site_settings (`key`,`value`,`group`,is_public,updated_at) VALUES (?,?,'layout',0,NOW())
             ON DUPLICATE KEY UPDATE `value`=VALUES(`value`),updated_at=NOW()",
            [$key, json_encode($value, JSON_THROW_ON_ERROR)]
        );
    }

    public static function saveDraft(array $input): array
    {
        $draft = self::validate($input['sections'] ?? []);
        return Database::transaction(static function () use ($input, $draft) {
            $state = self::adminState(true);
            if (!hash_equals($state['version'], (string) ($input['version'] ?? ''))) {
                throw new RuntimeException('LAYOUT_VERSION_CONFLICT', 409);
            }
            self::store(self::DRAFT_KEY, $draft);
            AuditService::log('site_layout', 'draft', null, $state['draft'], $draft, required: true);
            return self::adminState();
        });
    }

    public static function publish(array $input): array
    {
        return Database::transaction(static function () use ($input) {
            $state = self::adminState(true);
            if (!hash_equals($state['version'], (string) ($input['version'] ?? ''))) {
                throw new RuntimeException('LAYOUT_VERSION_CONFLICT', 409);
            }
            if ($state['draft'] === null) throw new InvalidArgumentException('ยังไม่มีร่างที่จะเผยแพร่');
            self::store(self::LIVE_KEY, $state['draft']);
            Database::execute('DELETE FROM site_settings WHERE `key`=?', [self::DRAFT_KEY]);
            AuditService::log('site_layout', 'publish', null, $state['published'], $state['draft'], required: true);
            return self::adminState();
        });
    }
}
