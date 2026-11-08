<?php

declare(strict_types=1);

namespace App\Core\Database\Seeders;

use App\Core\Database\Connection;

final class ReferenceDataSeeder
{
    /** @var array<string, array{0: string, 1: string}> kode => [modul, deskripsi] */
    private const PERMISSIONS = [
        'branches.view' => ['branches', 'Melihat daftar dan detail cabang'],
        'branches.manage' => ['branches', 'Menambah, mengubah, dan menonaktifkan cabang'],
        'users.view' => ['users', 'Melihat daftar dan detail pengguna'],
        'users.manage' => ['users', 'Menambah, mengubah, dan menonaktifkan pengguna'],
        'roles.view' => ['roles', 'Melihat daftar role dan izinnya'],
        'roles.manage' => ['roles', 'Mengatur izin pada role'],
        'tenant_settings.view' => ['tenant_settings', 'Melihat profil dan pengaturan bisnis'],
        'tenant_settings.manage' => ['tenant_settings', 'Mengubah profil dan pengaturan bisnis'],
        'audit_logs.view' => ['audit_logs', 'Melihat log audit'],
    ];

    /** Idempoten; hanya memakai SQL standar sehingga berjalan di MySQL dan SQLite. */
    public static function run(Connection $db): void
    {
        $db->transaction(static function () use ($db): void {
            foreach (self::PERMISSIONS as $code => [$module, $description]) {
                if ($db->fetchOne('SELECT id FROM permissions WHERE code = ?', [$code]) === null) {
                    $db->execute(
                        'INSERT INTO permissions (code, module, description) VALUES (?, ?, ?)',
                        [$code, $module, $description]
                    );
                }
            }
            if ($db->fetchOne('SELECT id FROM demo_settings LIMIT 1') === null) {
                $db->execute('INSERT INTO demo_settings (is_enabled, duration_hours) VALUES (1, 24)');
            }
        });
    }
}
