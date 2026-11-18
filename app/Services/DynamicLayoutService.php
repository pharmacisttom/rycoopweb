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
                if (!is_string($id) || !preg_match('/^[a-z0-9-]{8,64}$/D', $id) || isset($ids[$id])
                    || !is_string($title) || trim($title) === '' || mb_strlen($title) > 120
                    || !in_array($type, self::TYPES, true)
                    || !in_array($layout, self::LAYOUTS, true)
                    || !is_bool($visible)
                    || !is_string($description) || mb_strlen($description) > 1000
                    || !is_string($link) || strlen($link) > 255
                    || ($link !== '' && !preg_match('~^/(?:activities(?:/[0-9]+)?|news|documents|about|welfare|contact)/?$~D', $link))
                    || !is_array($children)) {
                    throw new InvalidArgumentException('Invalid section fields');
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
