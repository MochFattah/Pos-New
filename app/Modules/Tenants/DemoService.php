<?php

declare(strict_types=1);

namespace App\Modules\Tenants;

use App\Core\Database\Connection;
use Closure;

final class DemoService
{
    private Closure $clock;

    /** @param (callable(): int)|null $clock */
    public function __construct(private readonly Connection $db, ?callable $clock = null)
    {
        $this->clock = $clock !== null ? Closure::fromCallable($clock) : static fn (): int => time();
    }

    /**
     * Memulai demo sesuai demo_settings. Tidak melakukan apa pun bila demo dinonaktifkan,
     * atau (untuk sumber 'registration') tenant sudah pernah memulainya.
     */
    public function startFor(int $tenantId, string $source): void
    {
        $settings = $this->db->fetchOne('SELECT is_enabled, duration_hours FROM demo_settings ORDER BY id LIMIT 1');
        if ($settings === null || (int) $settings['is_enabled'] !== 1) {
            return;
        }
        if ($source === 'registration'
            && $this->db->fetchOne('SELECT id FROM tenant_demos WHERE tenant_id = ? AND source = ?', [$tenantId, 'registration']) !== null) {
            return;
        }
        $now = ($this->clock)();
        $this->db->execute(
            'INSERT INTO tenant_demos (tenant_id, starts_at, ends_at, source, created_at) VALUES (?, ?, ?, ?, ?)',
            [
                $tenantId,
                gmdate('Y-m-d H:i:s', $now),
                gmdate('Y-m-d H:i:s', $now + (int) $settings['duration_hours'] * 3600),
                $source,
                gmdate('Y-m-d H:i:s', $now),
            ]
        );
    }

    /** Ada jendela demo yang berlaku: starts_at <= sekarang < ends_at. Data tidak pernah dihapus saat berakhir. */
    public function isActive(int $tenantId): bool
    {
        $now = gmdate('Y-m-d H:i:s', ($this->clock)());
        return $this->db->fetchOne(
            'SELECT id FROM tenant_demos WHERE tenant_id = ? AND starts_at <= ? AND ends_at > ? LIMIT 1',
            [$tenantId, $now, $now]
        ) !== null;
    }
}
