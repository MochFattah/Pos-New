<?php

declare(strict_types=1);

namespace App\Modules\AuditLogs\Repositories;

use App\Core\Database\Connection;

final class AuditLogRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * @param array{tenant_id: ?int, actor_type: string, actor_id: ?int, action: string, entity_type: ?string,
     *              entity_id: ?int, metadata: ?string, ip: ?string, request_id: ?string, created_at: string} $row
     */
    public function insert(array $row): int
    {
        $this->db->execute(
            'INSERT INTO audit_logs (tenant_id, actor_type, actor_id, action, entity_type, entity_id, metadata, ip, request_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $row['tenant_id'], $row['actor_type'], $row['actor_id'], $row['action'], $row['entity_type'],
                $row['entity_id'], $row['metadata'], $row['ip'], $row['request_id'], $row['created_at'],
            ]
        );
        return $this->db->lastInsertId();
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function paginate(int $tenantId, int $page, int $perPage): array
    {
        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $count = $this->db->fetchOne('SELECT COUNT(*) AS c FROM audit_logs WHERE tenant_id = ?', [$tenantId]);
        $rows = $this->db->query(
            'SELECT id, tenant_id, actor_type, actor_id, action, entity_type, entity_id, metadata, ip, request_id, created_at
             FROM audit_logs WHERE tenant_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            [$tenantId]
        );
        return ['rows' => $rows, 'total' => (int) ($count['c'] ?? 0), 'page' => $page, 'per_page' => $perPage];
    }
}
