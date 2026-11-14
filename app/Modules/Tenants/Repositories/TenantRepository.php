<?php

declare(strict_types=1);

namespace App\Modules\Tenants\Repositories;

use App\Core\Database\Connection;

final class TenantRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function create(string $name, string $status, string $now): int
    {
        $this->db->execute(
            'INSERT INTO tenants (uuid, name, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
            [self::uuid(), $name, $status, $now, $now]
        );
        return $this->db->lastInsertId();
    }

    public function createProfile(int $tenantId, ?string $businessType, ?string $address, ?string $phone): void
    {
        $this->db->execute(
            'INSERT INTO tenant_profiles (tenant_id, business_type, address, phone) VALUES (?, ?, ?, ?)',
            [$tenantId, $businessType, $address, $phone]
        );
    }

    /** Memakai nilai bawaan kolom (Asia/Jakarta, IDR, id-ID). */
    public function createDefaultSettings(int $tenantId): void
    {
        $this->db->execute('INSERT INTO tenant_settings (tenant_id) VALUES (?)', [$tenantId]);
    }

    public function createMainBranch(int $tenantId, ?string $address, ?string $phone, string $now): int
    {
        $this->db->execute(
            'INSERT INTO branches (tenant_id, code, name, address, phone, is_main, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)',
            [$tenantId, 'UTAMA', 'Cabang Utama', $address, $phone, 'active', $now, $now]
        );
        return $this->db->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $tenantId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM tenants WHERE id = ?', [$tenantId]);
    }

    /** Hanya mengaktifkan tenant yang masih menunggu verifikasi (tidak membuka suspend). */
    public function activateIfPending(int $tenantId, string $now): bool
    {
        return $this->db->execute(
            "UPDATE tenants SET status = 'active', updated_at = ? WHERE id = ? AND status = 'pending_verification'",
            [$now, $tenantId]
        ) === 1;
    }

    /** @return array<string, mixed>|null */
    public function findProfile(int $tenantId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM tenant_profiles WHERE tenant_id = ?', [$tenantId]);
    }

    /** @return array<string, mixed>|null */
    public function findSettings(int $tenantId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM tenant_settings WHERE tenant_id = ?', [$tenantId]);
    }

    public function updateProfile(int $tenantId, ?string $businessType, ?string $address, ?string $phone): void
    {
        $this->db->execute(
            'UPDATE tenant_profiles SET business_type = ?, address = ?, phone = ? WHERE tenant_id = ?',
            [$businessType, $address, $phone, $tenantId]
        );
    }

    public function updateSettings(int $tenantId, string $timezone, string $currencyCode, string $numberFormat): void
    {
        $this->db->execute(
            'UPDATE tenant_settings SET timezone = ?, currency_code = ?, number_format = ? WHERE tenant_id = ?',
            [$timezone, $currencyCode, $numberFormat, $tenantId]
        );
    }

    /** @param string|null $storedName nama berkas buatan server (bukan path); null menghapus logo */
    public function setLogo(int $tenantId, ?string $storedName): void
    {
        $this->db->execute('UPDATE tenant_profiles SET logo_path = ? WHERE tenant_id = ?', [$storedName, $tenantId]);
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        $h = bin2hex($b);
        return sprintf('%s-%s-%s-%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 12, 4), substr($h, 16, 4), substr($h, 20, 12));
    }
}
