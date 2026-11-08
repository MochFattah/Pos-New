<?php

declare(strict_types=1);

namespace App\Modules\AuditLogs\Services;

use App\Modules\AuditLogs\Repositories\AuditLogRepository;
use InvalidArgumentException;
use JsonException;

final class AuditLogger
{
    public const ACTOR_TENANT_USER = 'tenant_user';
    public const ACTOR_PLATFORM_USER = 'platform_user';
    public const ACTOR_SYSTEM = 'system';

    private const ACTOR_TYPES = [self::ACTOR_TENANT_USER, self::ACTOR_PLATFORM_USER, self::ACTOR_SYSTEM];
    private const ACTION_PATTERN = '/^[a-z][a-z0-9_.]{1,99}$/';
    private const SENSITIVE_FRAGMENTS = ['password', 'token', 'secret', 'authorization', 'cookie', 'csrf', 'otp', 'api_key', 'card'];
    private const MAX_METADATA_BYTES = 8192;

    /** @var callable(): array{ip?: ?string, request_id?: ?string} */
    private $context;
    /** @var callable(): int */
    private $clock;

    /**
     * @param callable(): array{ip?: ?string, request_id?: ?string} $context
     * @param (callable(): int)|null $clock detik unix; default waktu sistem
     */
    public function __construct(private readonly AuditLogRepository $repository, callable $context, ?callable $clock = null)
    {
        $this->context = $context;
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * Pengecualian dari repository sengaja tidak ditelan; pemanggil yang memutuskan.
     *
     * @param array<int|string, mixed> $metadata
     */
    public function record(
        string $action,
        ?int $tenantId,
        string $actorType,
        ?int $actorId,
        ?string $entityType = null,
        ?int $entityId = null,
        array $metadata = []
    ): void {
        if (preg_match(self::ACTION_PATTERN, $action) !== 1) {
            throw new InvalidArgumentException('Format action audit tidak valid: ' . $action);
        }
        if (!in_array($actorType, self::ACTOR_TYPES, true)) {
            throw new InvalidArgumentException('actor_type audit tidak valid: ' . $actorType);
        }

        $ctx = ($this->context)();
        $this->repository->insert([
            'tenant_id' => $tenantId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $this->encodeMetadata($metadata),
            'ip' => $ctx['ip'] ?? null,
            'request_id' => $ctx['request_id'] ?? null,
            'created_at' => gmdate('Y-m-d H:i:s', ($this->clock)()),
        ]);
    }

    /** @param array<int|string, mixed> $metadata */
    private function encodeMetadata(array $metadata): ?string
    {
        if ($metadata === []) {
            return null;
        }
        try {
            $json = json_encode(self::sanitize($metadata), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '{"_unserializable":true}';
        }
        return strlen($json) > self::MAX_METADATA_BYTES ? '{"_truncated":true}' : $json;
    }

    /**
     * @param array<int|string, mixed> $data
     * @return array<int|string, mixed>
     */
    private static function sanitize(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $lower = strtolower($key);
                foreach (self::SENSITIVE_FRAGMENTS as $fragment) {
                    if (str_contains($lower, $fragment)) {
                        continue 2;
                    }
                }
            }
            $clean[$key] = is_array($value) ? self::sanitize($value) : $value;
        }
        return $clean;
    }
}
