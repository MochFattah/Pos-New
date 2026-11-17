<?php

declare(strict_types=1);

namespace App\Core\Layout;

use App\Core\Authorization\AuthUser;
use App\Core\Authorization\PolicyService;

/** Menu utama: hanya modul fondasi yang sudah dibangun, disaring lewat PolicyService (item tanpa izin disembunyikan). */
final class Navigation
{
    /** @var list<array{key: string, label: string, href: string, permission: ?string}> */
    public const ITEMS = [
        ['key' => 'beranda', 'label' => 'Beranda', 'href' => '/beranda', 'permission' => null],
        ['key' => 'cabang', 'label' => 'Cabang', 'href' => '/cabang', 'permission' => 'branches.view'],
        ['key' => 'pengguna', 'label' => 'Pengguna', 'href' => '/pengguna', 'permission' => 'users.view'],
        ['key' => 'role', 'label' => 'Role & izin', 'href' => '/role', 'permission' => 'roles.view'],
        ['key' => 'pengaturan', 'label' => 'Pengaturan', 'href' => '/pengaturan', 'permission' => 'tenant_settings.view'],
    ];

    /** @return list<array{key: string, label: string, href: string, current: bool}> */
    public static function for(AuthUser $user, PolicyService $policy, string $path): array
    {
        $path = '/' . trim($path, '/');
        $items = [];
        foreach (self::ITEMS as $item) {
            if ($item['permission'] !== null && !$policy->can($user, $item['permission'])) {
                continue;
            }
            $items[] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'href' => $item['href'],
                'current' => $path === $item['href'] || str_starts_with($path, $item['href'] . '/'),
            ];
        }
        return $items;
    }
}
