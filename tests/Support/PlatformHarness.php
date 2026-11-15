<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Middleware\PlatformAuthMiddleware;
use App\Core\Security\PasswordHasher;
use App\Core\Security\RateLimiter;
use App\Modules\Platform\Controllers\PlatformAuthController;
use App\Modules\Platform\Controllers\PlatformDashboardController;
use App\Modules\Platform\Repositories\PlatformUserRepository;
use App\Modules\Platform\Services\PlatformAdminCreator;
use App\Modules\Platform\Services\PlatformAuthService;

/** AuthHarness + jalur platform (routes/platform.php) pada router dan sesi yang sama ("satu browser"). */
abstract class PlatformHarness extends AuthHarness
{
    protected const PLATFORM_PASSWORD = 'super-rahasia-1';

    protected PlatformAuthService $platform;
    protected PlatformUserRepository $platformUsers;
    protected PlatformAdminCreator $creator;
    protected int $platformMax = 5;
    protected int $platformIpMax = 50;

    protected function extraServices(): array
    {
        $clock = fn (): int => $this->now;
        $this->platformUsers = new PlatformUserRepository($this->db);
        $this->platform = new PlatformAuthService(
            $this->db,
            $this->platformUsers,
            new RateLimiter($this->db, $this->platformMax, 900, $clock, 3600),
            new RateLimiter($this->db, $this->platformIpMax, 900, $clock, 3600),
            $this->hasher,
            $this->makeAudit(),
            $clock
        );
        $this->creator = new PlatformAdminCreator($this->platformUsers, new PasswordHasher(), $this->makeAudit(), $clock);
        return [
            PlatformAuthController::class => new PlatformAuthController($this->platform),
            PlatformDashboardController::class => new PlatformDashboardController(),
            PlatformAuthMiddleware::class => new PlatformAuthMiddleware($this->platform),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        (require dirname(__DIR__, 2) . '/routes/platform.php')($this->router);
    }

    protected function makePlatformAdmin(string $email = 'root@example.com', string $password = self::PLATFORM_PASSWORD, string $name = 'Admin Platform'): int
    {
        return $this->creator->create($name, $email, $password);
    }

    protected function platformLogin(string $email = 'root@example.com', string $password = self::PLATFORM_PASSWORD): \App\Core\Response
    {
        return $this->send('POST', '/platform/masuk', ['email' => $email, 'password' => $password]);
    }
}
