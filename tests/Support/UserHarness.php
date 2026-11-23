<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Authorization\AccessDeniedRecorder;
use App\Core\Security\RateLimiter;
use App\Core\Security\TokenService;
use App\Modules\Auth\Repositories\EmailVerificationRepository;
use App\Modules\Auth\Services\InvitationEmailSender;
use App\Modules\Roles\Controllers\RoleController;
use App\Modules\Roles\Repositories\PermissionRepository;
use App\Modules\Roles\Services\RoleService;
use App\Modules\Tenants\Repositories\RoleRepository;
use App\Modules\Users\Controllers\InvitationController;
use App\Modules\Users\Controllers\UserController;
use App\Modules\Users\Services\UserService;

/** BranchHarness + layanan pengguna/role/undangan dan pembantu untuk menyiapkan data. */
abstract class UserHarness extends BranchHarness
{
    protected UserService $userService;
    protected RoleService $roleService;
    protected RoleRepository $roleRepo;
    protected int $resendMax = 3;
    protected int $inviteMax = 20;
    protected int $pendingMax = 50;

    protected function extraServices(): array
    {
        $services = parent::extraServices();
        $this->buildUserService();
        $denied = new AccessDeniedRecorder($this->makeAudit());
        $this->roleRepo = new RoleRepository($this->db);
        $this->roleService = new RoleService($this->db, $this->roleRepo, new PermissionRepository($this->db), $this->policy, $this->makeAudit(), $denied);
        return $services + [
            UserController::class => new UserController($this->userService, $this->policy),
            RoleController::class => new RoleController($this->roleService, $this->policy),
            InvitationController::class => new InvitationController($this->verify),
        ];
    }

    /** Membangun ulang UserService (mis. setelah mengubah $inviteMax/$pendingMax/$resendMax di dalam tes). */
    protected function buildUserService(): void
    {
        $clock = fn (): int => $this->now;
        $denied = new AccessDeniedRecorder($this->makeAudit());
        $this->roleRepo = new RoleRepository($this->db);
        $sender = new InvitationEmailSender(new EmailVerificationRepository($this->db), new TokenService(), $this->mailer, self::APP_URL, 86400, $clock);
        $this->userService = new UserService(
            $this->db,
            $this->users,
            $this->roleRepo,
            $this->branchRepo,
            $this->policy,
            $this->makeAudit(),
            $denied,
            $sender,
            $this->hasher,
            new RateLimiter($this->db, $this->resendMax, 3600, $clock),
            new RateLimiter($this->db, $this->inviteMax, 3600, $clock),
            $this->pendingMax,
            $clock
        );
    }

    protected function rebuild(): void
    {
        $this->buildUserService();
    }

    protected function nowStr(): string
    {
        return gmdate('Y-m-d H:i:s', $this->now);
    }

    protected function roleId(int $tenantId, string $slug): int
    {
        return (int) $this->db->fetchOne('SELECT id FROM roles WHERE tenant_id = ? AND slug = ?', [$tenantId, $slug])['id'];
    }

    /** Menjalankan stack penuh; HttpException menjadi status seperti Application. */
    protected function status(string $method, string $uri, array $data = []): int
    {
        try {
            return $this->send($method, $uri, $data)->status();
        } catch (\App\Core\Exceptions\HttpException $e) {
            return $e->status();
        }
    }

    protected function auditCount(string $action): int
    {
        return $this->count('SELECT COUNT(*) AS n FROM audit_logs WHERE action = ?', [$action]);
    }

    /** @return array<string, mixed> metadata audit terakhir untuk aksi tertentu */
    protected function lastAudit(string $action): array
    {
        $row = $this->db->fetchOne('SELECT metadata FROM audit_logs WHERE action = ? ORDER BY id DESC LIMIT 1', [$action]);
        $this->assertTrue($row !== null, "Audit $action tidak ada");
        return (array) json_decode((string) $row['metadata'], true);
    }

    protected function ownerLoggedIn(string $email = 'budi@example.com'): array
    {
        $r = $this->activeOwner($email);
        $this->assertSame(302, $this->login($email)->status());
        return $r;
    }
}
