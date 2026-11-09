<?php

declare(strict_types=1);

namespace App\Modules\AuditLogs\Services;

use App\Modules\AuditLogs\Repositories\AuditLogRepository;
use InvalidArgumentException;
use JsonException;
use JsonSerializable;

/**
 * Pencatat audit. Batas yang diketahui: penyaringan berbasis kunci tidak bisa
 * mendeteksi rahasia di dalam nilai yang berada di bawah kunci biasa; pemanggil
 * tidak boleh menyerahkan rahasia mentah (kata sandi, token, nomor kartu) sebagai metadata.
 */
final class AuditLogger
{
    public const ACTOR_TENANT_USER = 'tenant_user';
    public const ACTOR_PLATFORM_USER = 'platform_user';
    public const ACTOR_SYSTEM = 'system';

    private const ACTOR_TYPES = [self::ACTOR_TENANT_USER, self::ACTOR_PLATFORM_USER, self::ACTOR_SYSTEM];
    private const ACTION_PATTERN = '/^[a-z][a-z0-9_.]{1,99}$/';
    /** Dicocokkan sebagai substring pada kunci yang sudah dinormalkan. */
    private const SENSITIVE_FRAGMENTS = ['password', 'passwd', 'pwd', 'token', 'secret', 'authorization', 'cookie', 'csrf', 'api_key', 'apikey'];
    /** Nama pendek yang hanya cocok sebagai segmen kata utuh (hindari discard_reason, footprint). */
    private const SENSITIVE_WORDS = ['otp', 'card', 'pin', 'jwt', 'session'];
    private const MAX_METADATA_BYTES = 8192;
    private const MAX_DEPTH = 8;
    private const MAX_NODES = 500;
    private const DEPTH_MARKER = '[dalam]';
    private const MAX_ENTITY_TYPE = 100;
    private const MAX_IP = 45;
    private const MAX_REQUEST_ID = 64;

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

        if ($entityType !== null && strlen($entityType) > self::MAX_ENTITY_TYPE) {
            throw new InvalidArgumentException('entity_type audit terlalu panjang.');
        }

        $ctx = ($this->context)();
        $ip = isset($ctx['ip']) ? substr((string) $ctx['ip'], 0, self::MAX_IP) : null;
        $requestId = isset($ctx['request_id']) ? substr((string) $ctx['request_id'], 0, self::MAX_REQUEST_ID) : null;
        $this->repository->insert([
            'tenant_id' => $tenantId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $this->encodeMetadata($metadata),
            'ip' => $ip,
            'request_id' => $requestId,
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
            $nodes = 0;
            $truncated = false;
            $clean = self::sanitize($metadata, 0, $nodes, $truncated);
            if ($truncated && is_array($clean)) {
                $clean['_truncated'] = true;
            }
            $json = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '{"_unserializable":true}';
        }
        return strlen($json) > self::MAX_METADATA_BYTES ? '{"_truncated":true}' : $json;
    }

    /**
     * Objek diubah menjadi array dahulu agar kunci sensitif di dalamnya ikut dibuang.
     * Kedalaman > MAX_DEPTH diganti DEPTH_MARKER (juga menghentikan referensi melingkar);
     * lebih dari MAX_NODES simpul total menghentikan penelusuran dan menyalakan $truncated.
     *
     * @throws JsonException untuk resource dan closure
     */
    private static function sanitize(mixed $value, int $depth, int &$nodes, bool &$truncated): mixed
    {
        if (is_resource($value) || gettype($value) === 'resource (closed)' || $value instanceof \Closure) {
            throw new JsonException('Tidak dapat diserialisasi.');
        }
        if (is_object($value)) {
            $value = $value instanceof JsonSerializable ? $value->jsonSerialize() : get_object_vars($value);
            if (!is_array($value)) {
                return self::sanitize($value, $depth + 1, $nodes, $truncated);
            }
        }
        if (!is_array($value)) {
            return $value;
        }
        if ($depth >= self::MAX_DEPTH) {
            return self::DEPTH_MARKER;
        }
        $clean = [];
        foreach ($value as $key => $item) {
            if ($nodes >= self::MAX_NODES) {
                $truncated = true;
                break;
            }
            $nodes++;
            if (is_string($key) && self::isSensitiveKey($key)) {
                continue;
            }
            $clean[$key] = self::sanitize($item, $depth + 1, $nodes, $truncated);
        }
        return $clean;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $snake = strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $key));
        $normalized = trim((string) preg_replace('/[^a-z0-9]+/', '_', $snake), '_');
        foreach (self::SENSITIVE_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }
        return array_intersect(explode('_', $normalized), self::SENSITIVE_WORDS) !== [];
    }
}
