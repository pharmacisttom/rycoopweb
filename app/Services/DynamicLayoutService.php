<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;
use RuntimeException;

/**
 * Independent dynamic homepage content tree. Does not alter the six legacy
 * layout visibility keys. Only the approved published snapshot is public.
 */
final class DynamicLayoutService
{
    private const LIVE = 'dynamic_home_sections_v1';
    private const DRAFT = 'dynamic_home_sections_draft_v1';
    private const TYPES = ['gallery', 'links', 'text'];
    private const LAYOUTS = ['grid', 'cards', 'carousel'];
    private const DEFAULT_APPEARANCE = [
        'background' => '#ffffff',
        'text' => '#10294b',
        'accent' => '#175cd3',
    ];
    private static function validateAppearance(mixed $input): array
    {
        if ($input === null) return self::DEFAULT_APPEARANCE;
        if (!is_array($input) || array_keys($input) !== array_keys(self::DEFAULT_APPEARANCE)) {
            throw new InvalidArgumentException('Invalid section color settings');
        }
        foreach ($input as $color) {
            if (!is_string($color) || !preg_match('/^#[0-9a-fA-F]{6}$/D', $color)) {
                throw new InvalidArgumentException('Section colors must be 6-digit hex values');
            }
        }
        if (self::contrastRatio($input['background'], $input['text']) < 4.5) {
            throw new InvalidArgumentException('Section text/background contrast must be at least 4.5:1');
        }
        return array_map('strtolower', $input);
    }

    private static function contrastRatio(string $background, string $text): float
    {
        $luminance = static function (string $color): float {
            $channels = [];
            foreach ([1, 3, 5] as $position) {
                $channel = hexdec(substr($color, $position, 2)) / 255;
                $channels[] = $channel <= 0.04045
                    ? $channel / 12.92
                    : (($channel + 0.055) / 1.055) ** 2.4;
            }
            return $channels[0] * 0.2126 + $channels[1] * 0.7152 + $channels[2] * 0.0722;
        };
        $a = $luminance($background);
        $b = $luminance($text);
        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    private static function read(string $key, bool $lock = false): ?array
    {
        $sql = 'SELECT ' . chr(96) . 'value' . chr(96) . ' FROM site_settings WHERE ' . chr(96) . 'key' . chr(96) . '=?'
            . ($lock && Database::connect()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '');
        $row = Database::first($sql, [$key]);
        if (!$row) return null;
        $tree = json_decode((string) $row['value'], true, 512, JSON_THROW_ON_ERROR);
        return self::validate($tree);
    }

    public static function published(): array
    {
        return self::read(self::LIVE) ?? [];
    }

    public static function state(bool $lock = false): array
    {
        $published = self::read(self::LIVE, $lock) ?? [];
        $draft = self::read(self::DRAFT, $lock);
        return [
            'published' => $published,
            'draft' => $draft,
            'version' => hash('sha256', json_encode([$published, $draft], JSON_THROW_ON_ERROR)),
        ];
    }

    public static function validate(mixed $tree): array
    {
        if (!is_array($tree) || !array_is_list($tree) || count($tree) > 40) {
            throw new InvalidArgumentException('Invalid section list');
        }
        $ids = [];
        $walk = static function (array $items, int $depth) use (&$walk, &$ids): array {
            if ($depth > 3 || count($items) > 40 || !array_is_list($items)) {
                throw new InvalidArgumentException('Section hierarchy limit exceeded');
            }
            $output = [];
            foreach ($items as $node) {
                if (!is_array($node)) throw new InvalidArgumentException('Invalid section');
                $id = $node['id'] ?? null;
                $title = $node['title'] ?? null;
                $type = $node['type'] ?? null;
                $layout = $node['layout'] ?? null;
                $visible = $node['visible'] ?? null;
                $description = $node['description'] ?? '';
                $link = $node['link'] ?? '';
                $children = $node['children'] ?? [];
                $albumIds = $node['album_ids'] ?? [];
                $appearance = self::validateAppearance($node['appearance'] ?? null);
                if (!is_string($id) || !preg_match('/^[a-z0-9-]{8,64}$/D', $id) || isset($ids[$id])
                    || !is_string($title) || trim($title) === '' || mb_strlen($title) > 120
                    || !in_array($type, self::TYPES, true)
                    || !in_array($layout, self::LAYOUTS, true)
                    || !is_bool($visible)
                    || !is_string($description) || mb_strlen($description) > 1000
                    || !is_string($link) || strlen($link) > 255
                    || ($link !== '' && !preg_match('~^/(?:activities(?:/[0-9]+)?|news|documents|about|welfare|contact)/?$~D', $link))
                    || !is_array($children) || !is_array($albumIds) || !array_is_list($albumIds)
                    || count($albumIds) > 50 || count(array_unique($albumIds)) !== count($albumIds)) {
                    throw new InvalidArgumentException('Invalid section fields');
                }
                foreach ($albumIds as $albumId) {
                    if (!is_int($albumId) || $albumId < 1) throw new InvalidArgumentException('Invalid album selection');
                }
                $ids[$id] = true;
                $output[] = [
                    'id' => $id,
                    'title' => trim($title),
                    'type' => $type,
                    'layout' => $layout,
                    'visible' => $visible,
                    'description' => trim($description),
                    'link' => $link,
                    'album_ids' => $albumIds,
                    'appearance' => $appearance,
                    'children' => $walk($children, $depth + 1),
                ];
                if (count($ids) > 120) throw new InvalidArgumentException('Too many sections');
            }
            return $output;
        };
        return $walk($tree, 1);
    }

    private static function store(string $key, array $tree): void
    {
        Database::execute(
            "INSERT INTO site_settings (" . chr(96) . "key" . chr(96) . "," . chr(96) . "value" . chr(96) . "," . chr(96) . "group" . chr(96) . ",is_public,updated_at)
             VALUES (?,?,'layout',0,NOW()) ON DUPLICATE KEY UPDATE " . chr(96) . "value" . chr(96) . "=VALUES(" . chr(96) . "value" . chr(96) . "),updated_at=NOW()",
            [$key, json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]
        );
    }

    public static function draft(array $input): array
    {
        $tree = self::validate($input['sections'] ?? null);
        return Database::transaction(static function () use ($input, $tree): array {
            $old = self::state(true);
            if (!hash_equals($old['version'], (string) ($input['version'] ?? ''))) {
                throw new RuntimeException('LAYOUT_VERSION_CONFLICT', 409);
            }
            self::store(self::DRAFT, $tree);
            AuditService::log('dynamic_layout', 'draft', null, $old['draft'], $tree, required: true);
            return self::state();
        });
    }

    public static function publish(array $input): array
    {
        return Database::transaction(static function () use ($input): array {
            $old = self::state(true);
            if (!hash_equals($old['version'], (string) ($input['version'] ?? ''))) {
                throw new RuntimeException('LAYOUT_VERSION_CONFLICT', 409);
            }
            if ($old['draft'] === null) throw new InvalidArgumentException('No draft to publish');
            self::store(self::LIVE, $old['draft']);
            Database::execute('DELETE FROM site_settings WHERE ' . chr(96) . 'key' . chr(96) . '=?', [self::DRAFT]);
            AuditService::log('dynamic_layout', 'publish', null, $old['published'], $old['draft'], required: true);
            return self::state();
        });
    }
}
