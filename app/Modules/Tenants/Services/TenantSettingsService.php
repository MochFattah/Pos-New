<?php

declare(strict_types=1);

namespace App\Modules\Tenants\Services;

use App\Core\Authorization\AuthUser;
use App\Core\Authorization\PolicyService;
use App\Core\Database\Connection;
use App\Core\Exceptions\ValidationException;
use App\Modules\AuditLogs\Services\AuditLogger;
use App\Modules\Tenants\Repositories\TenantRepository;
use App\Modules\Tenants\Validators\SettingsValidator;

/**
 * Profil bisnis, pengaturan regional, dan logo. tenant_id selalu dari $actor (sesi server).
 * Audit hanya memuat nama field yang berubah, tidak pernah nilainya atau nama berkas.
 */
final class TenantSettingsService
{
    public const VIEW = 'tenant_settings.view';
    public const MANAGE = 'tenant_settings.manage';

    private const PROFILE_FIELDS = ['business_type', 'address', 'phone'];
    private const SETTING_FIELDS = ['timezone', 'currency_code', 'number_format'];

    /** @var callable(): int */
    private $clock;

    /** @param (callable(): int)|null $clock detik unix (dicadangkan untuk tes; audit memakai jam AuditLogger) */
    public function __construct(
        private readonly Connection $db,
        private readonly TenantRepository $tenants,
        private readonly LogoUploader $logos,
        private readonly AuditLogger $audit,
        private readonly PolicyService $policy,
        ?callable $clock = null
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    /** @return array{profile: array<string, mixed>, settings: array<string, mixed>, hasLogo: bool} */
    public function get(AuthUser $actor): array
    {
        $this->policy->authorize($actor, self::VIEW);
        $profile = $this->tenants->findProfile($actor->tenantId) ?? ['business_type' => null, 'address' => null, 'phone' => null, 'logo_path' => null];
        $settings = $this->tenants->findSettings($actor->tenantId) ?? ['timezone' => 'Asia/Jakarta', 'currency_code' => 'IDR', 'number_format' => 'id-ID'];
        return [
            'profile' => $profile,
            'settings' => $settings,
            'hasLogo' => is_string($profile['logo_path'] ?? null) && $this->logos->pathOf($actor->tenantId, (string) $profile['logo_path']) !== null,
        ];
    }

    /** @param array<string, mixed> $input @throws ValidationException */
    public function update(AuthUser $actor, array $input): void
    {
        $this->policy->authorize($actor, self::MANAGE);
        $result = SettingsValidator::validate($input);
        if ($result->fails()) {
            throw new ValidationException($result->errors());
        }
        $v = $result->validated();
        $new = [
            'business_type' => self::nullable($v['business_type'] ?? ''),
            'address' => self::nullable($v['address'] ?? ''),
            'phone' => self::nullable($v['phone'] ?? ''),
            'timezone' => $v['timezone'],
            'currency_code' => $v['currency_code'],
            'number_format' => $v['number_format'],
        ];

        $this->db->transaction(function () use ($actor, $new): void {
            [$profile, $settings] = $this->ensureRows($actor->tenantId);
            $changed = [];
            foreach (self::PROFILE_FIELDS as $f) {
                if (self::nullable((string) ($profile[$f] ?? '')) !== $new[$f]) {
                    $changed[] = $f;
                }
            }
            foreach (self::SETTING_FIELDS as $f) {
                if ((string) ($settings[$f] ?? '') !== $new[$f]) {
                    $changed[] = $f;
                }
            }
            if ($changed === []) {
                return;
            }
            sort($changed);
            $this->tenants->updateProfile($actor->tenantId, $new['business_type'], $new['address'], $new['phone']);
            $this->tenants->updateSettings($actor->tenantId, $new['timezone'], $new['currency_code'], $new['number_format']);
            $this->audit->record('tenant.settings_updated', $actor->tenantId, 'tenant_user', $actor->id, 'tenant', $actor->tenantId, ['changed_fields' => $changed]);
        });
    }

    /** @param array<string, mixed> $file entri $_FILES @throws ValidationException field 'logo' */
    public function uploadLogo(AuthUser $actor, array $file): void
    {
        $this->policy->authorize($actor, self::MANAGE);
        $name = $this->logos->store($actor->tenantId, $file);
        $old = null;
        try {
            $this->db->transaction(function () use ($actor, $name, &$old): void {
                [$profile] = $this->ensureRows($actor->tenantId);
                $old = is_string($profile['logo_path'] ?? null) ? $profile['logo_path'] : null;
                $this->tenants->setLogo($actor->tenantId, $name);
                $this->audit->record('tenant.logo_updated', $actor->tenantId, 'tenant_user', $actor->id, 'tenant', $actor->tenantId, ['operation' => $old === null ? 'added' : 'replaced']);
            });
        } catch (\Throwable $e) {
            $this->logos->delete($actor->tenantId, $name);
            throw $e;
        }
        if ($old !== null) {
            $this->logos->delete($actor->tenantId, $old);
        }
    }

    public function removeLogo(AuthUser $actor): void
    {
        $this->policy->authorize($actor, self::MANAGE);
        $old = null;
        $this->db->transaction(function () use ($actor, &$old): void {
            [$profile] = $this->ensureRows($actor->tenantId);
            $old = is_string($profile['logo_path'] ?? null) ? $profile['logo_path'] : null;
            if ($old === null) {
                return;
            }
            $this->tenants->setLogo($actor->tenantId, null);
            $this->audit->record('tenant.logo_updated', $actor->tenantId, 'tenant_user', $actor->id, 'tenant', $actor->tenantId, ['operation' => 'removed']);
        });
        if ($old !== null) {
            $this->logos->delete($actor->tenantId, $old);
        }
    }

    /** @return array{path: string, mime: string}|null logo tenant milik $actor saja; null bila tidak ada/tak sah */
    public function logo(AuthUser $actor): ?array
    {
        $this->policy->authorize($actor, self::VIEW);
        $profile = $this->tenants->findProfile($actor->tenantId);
        $name = $profile['logo_path'] ?? null;
        if (!is_string($name)) {
            return null;
        }
        $path = $this->logos->pathOf($actor->tenantId, $name);
        $mime = LogoUploader::mimeOf($name);
        return $path !== null && $mime !== null ? ['path' => $path, 'mime' => $mime] : null;
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} baris profil dan pengaturan (dibuat bila belum ada) */
    private function ensureRows(int $tenantId): array
    {
        $profile = $this->tenants->findProfile($tenantId);
        if ($profile === null) {
            $this->tenants->createProfile($tenantId, null, null, null);
            $profile = $this->tenants->findProfile($tenantId) ?? [];
        }
        $settings = $this->tenants->findSettings($tenantId);
        if ($settings === null) {
            $this->tenants->createDefaultSettings($tenantId);
            $settings = $this->tenants->findSettings($tenantId) ?? [];
        }
        return [$profile, $settings];
    }

    private static function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
