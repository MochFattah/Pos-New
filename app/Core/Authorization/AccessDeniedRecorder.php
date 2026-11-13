<?php

declare(strict_types=1);

namespace App\Core\Authorization;

use App\Modules\AuditLogs\Services\AuditLogger;
use Throwable;

/**
 * Mencatat akses yang ditolak (spec 6: "ditolak dan dicatat"). Entri selalu memakai tenant milik
 * pelaku (bukan tenant entitas yang dicoba), sehingga tidak ada query tanpa tenant dan respons 404
 * untuk entitas lintas tenant tetap identik dengan entitas yang tidak ada. Kegagalan pencatatan
 * tidak boleh mengubah hasil penolakan; dicatat ke log PHP saja.
 * Catatan: pengguna terautentikasi dapat menulis baris tanpa batas dengan menebak ID; beri pembatas laju kelak.
 */
final class AccessDeniedRecorder
{
    public const ENTITY_NOT_FOUND = 'security.entity_not_found';
    public const PERMISSION_DENIED = 'security.permission_denied';

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function entityNotFound(AuthUser $actor, string $entityType, int $attemptedId): void
    {
        $this->write(self::ENTITY_NOT_FOUND, $actor, $entityType, $attemptedId, ['attempted_id' => $attemptedId]);
    }

    public function permissionDenied(AuthUser $actor, string $permission, ?int $branchId = null): void
    {
        $meta = ['permission' => substr($permission, 0, 100)];
        if ($branchId !== null) {
            $meta['branch_id'] = $branchId;
        }
        $this->write(self::PERMISSION_DENIED, $actor, null, null, $meta);
    }

    /** @param array<string, mixed> $metadata */
    private function write(string $action, AuthUser $actor, ?string $entityType, ?int $entityId, array $metadata): void
    {
        try {
            $this->audit->record($action, $actor->tenantId, AuditLogger::ACTOR_TENANT_USER, $actor->id, $entityType, $entityId, $metadata);
        } catch (Throwable $e) {
            error_log('Gagal mencatat akses ditolak: ' . $e->getMessage());
        }
    }
}
