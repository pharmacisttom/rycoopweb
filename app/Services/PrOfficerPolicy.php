<?php
declare(strict_types=1);
namespace App\Services;

/** Content-only ceiling; does not add or rewrite database role mappings. */
final class PrOfficerPolicy
{
    public const PERMISSIONS = [
        'content.create', 'content.publish',
        'news.view', 'news.create', 'news.update', 'news.edit', 'news.delete', 'news.publish',
        'faqs.view', 'faqs.create', 'faqs.update', 'faqs.edit', 'faqs.delete',
        'announcements.view', 'announcements.create', 'announcements.edit', 'announcements.update', 'announcements.publish',
        'hero_slides.view', 'hero_slides.create', 'hero_slides.edit', 'hero_slides.update', 'hero_slides.delete',
        'coop_info.view', 'coop_info.update', 'people.view', 'people.update',
    ];

    public static function restrict(string $role, array $permissions): array
    {
        if ($role !== 'pr_officer') { return $permissions; }
        // Compatibility with existing 025 content grants, without writing role_permissions.
        if (in_array('news.view', $permissions, true)) { $permissions[] = 'announcements.view'; }
        if (in_array('content.create', $permissions, true)) {
            $permissions[] = 'announcements.create'; $permissions[] = 'announcements.edit';
        }
        if (in_array('content.publish', $permissions, true)) { $permissions[] = 'announcements.publish'; }
        return array_values(array_unique(array_intersect($permissions, self::PERMISSIONS)));
    }

    public static function requiresMfa(string $role): bool
    {
        return $role === 'pr_officer' || (bool) config('google.staff_mfa_required', true);
    }
}
