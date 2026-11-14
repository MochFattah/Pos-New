<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Request;
use App\Core\Response;
use App\Core\Security\Csrf;
use App\Modules\Tenants\Controllers\SettingsController;
use App\Modules\Tenants\Repositories\TenantRepository;
use App\Modules\Tenants\Services\LogoUploader;
use App\Modules\Tenants\Services\TenantSettingsService;

/** BranchHarness + layanan pengaturan bisnis dengan direktori unggahan sementara. */
abstract class SettingsHarness extends BranchHarness
{
    protected string $tmp = '';
    protected string $logosDir = '';
    protected TenantSettingsService $settings;

    protected function extraServices(): array
    {
        $parent = parent::extraServices();
        $this->tmp = ImageFixtures::tempDir();
        $this->logosDir = $this->tmp . '/uploads/logos';
        $uploader = new LogoUploader($this->logosDir, static fn (string $f): bool => true, static fn (string $from, string $to): bool => copy($from, $to));
        $this->settings = new TenantSettingsService($this->db, new TenantRepository($this->db), $uploader, $this->makeAudit(), $this->policy, fn (): int => $this->now);
        return $parent + [SettingsController::class => new SettingsController($this->settings, $this->policy)];
    }

    protected function tearDown(): void
    {
        ImageFixtures::removeTree($this->tmp);
        parent::tearDown();
    }

    /** @param array<string, array<string, mixed>> $files */
    protected function sendWithFiles(string $uri, array $data, array $files, bool $csrf = true): Response
    {
        if ($csrf) {
            $this->session->start();
            $data += ['_csrf' => Csrf::token($this->session)];
        }
        return $this->router->dispatch(Request::create('POST', $uri, $data, [], $files));
    }

    protected function status(string $method, string $uri, array $data = []): int
    {
        try {
            return $this->send($method, $uri, $data)->status();
        } catch (\App\Core\Exceptions\HttpException $e) {
            return $e->status();
        }
    }
}
