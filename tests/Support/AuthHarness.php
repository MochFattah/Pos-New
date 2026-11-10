<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database\Connection;
use App\Core\Middleware\AccessGateMiddleware;
use App\Core\Middleware\AuthMiddleware;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Middleware\SessionMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Security\ArraySessionStorage;
use App\Core\Security\Csrf;
use App\Core\Security\PasswordHasher;
use App\Core\Security\RateLimiter;
use App\Core\Security\Session;
use App\Core\Security\TokenService;
use App\Modules\AuditLogs\Repositories\AuditLogRepository;
use App\Modules\AuditLogs\Services\AuditLogger;
use App\Modules\Auth\Controllers\HomeController;
use App\Modules\Auth\Controllers\LoginController;
use App\Modules\Auth\Controllers\PasswordResetController;
use App\Modules\Auth\Repositories\EmailVerificationRepository;
use App\Modules\Auth\Repositories\PasswordResetRepository;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\PasswordResetService;
use App\Modules\Auth\Services\VerificationEmailSender;
use App\Modules\Auth\Services\VerifyEmailService;
use App\Modules\Tenants\DemoService;
use App\Modules\Tenants\Repositories\RoleRepository;
use App\Modules\Tenants\Repositories\TenantRepository;
use App\Modules\Tenants\Repositories\UserRepository;
use App\Modules\Tenants\Services\RegisterTenantService;
use Tests\Support\Fixtures\CapturingMailer;

/** Perakitan bersama tes fitur auth: satu "browser" (satu Session), jam yang bisa dimajukan. */
abstract class AuthHarness extends TestCase
{
    protected const APP_URL = 'https://pos.example.test';
    protected const PASSWORD = 'rahasia-banget';

    protected Connection $db;
    protected CapturingMailer $mailer;
    protected int $now = 1_800_000_000;
    protected Session $session;
    protected ArraySessionStorage $storage;
    protected Router $router;
    protected UserRepository $users;
    protected AuthService $auth;
    protected PasswordResetService $resets;
    protected RegisterTenantService $register;
    protected VerifyEmailService $verify;
    protected PasswordHasher $hasher;
    protected int $loginMax = 5;
    protected int $ipMax = 50;

    protected function setUp(): void
    {
        $this->db = Db::fresh();
        $this->mailer = new CapturingMailer();
        $this->hasher = $this->makeHasher();
        $this->wire();
    }

    protected function makeHasher(): PasswordHasher
    {
        return new PasswordHasher();
    }

    protected function wire(): void
    {
        $clock = fn (): int => $this->now;
        $audit = new AuditLogger(new AuditLogRepository($this->db), static fn (): array => ['ip' => '127.0.0.1'], $clock);
        $this->users = new UserRepository($this->db);
        $tenants = new TenantRepository($this->db);
        $verifications = new EmailVerificationRepository($this->db);
        $sender = new VerificationEmailSender($verifications, new TokenService(), $this->mailer, self::APP_URL, 86400, $clock);
        $this->register = new RegisterTenantService($this->db, $tenants, $this->users, new RoleRepository($this->db), $sender, $this->hasher, $audit, $clock);
        $this->verify = new VerifyEmailService($this->db, $verifications, $this->users, $tenants, new DemoService($this->db, $clock), $sender, $audit, $clock);

        $this->auth = new AuthService(
            $this->db,
            $this->users,
            new RateLimiter($this->db, $this->loginMax, 900, $clock),
            new RateLimiter($this->db, $this->ipMax, 900, $clock),
            $this->hasher,
            $audit,
            $clock
        );
        $this->resets = new PasswordResetService(
            $this->db,
            new PasswordResetRepository($this->db),
            $this->users,
            new TokenService(),
            $this->hasher,
            $this->mailer,
            $audit,
            self::APP_URL,
            3600,
            $clock
        );
        $demo = new DemoService($this->db, $clock);
        $login = new LoginController($this->auth);
        $reset = new PasswordResetController($this->resets, new RateLimiter($this->db, 3, 3600, $clock));
        $home = new HomeController($demo, 'bantuan@example.test');
        $authMw = new AuthMiddleware($this->auth);
        $gate = new AccessGateMiddleware($demo);

        $this->session = new Session($this->storage = new ArraySessionStorage(), $clock);
        $this->router = new Router(static fn (string $class): object => match ($class) {
            LoginController::class => $login,
            PasswordResetController::class => $reset,
            HomeController::class => $home,
            AuthMiddleware::class => $authMw,
            AccessGateMiddleware::class => $gate,
        });
        $this->router->addGlobalMiddleware(new SessionMiddleware(fn (): Session => $this->session, 28800, $clock));
        $this->router->addGlobalMiddleware(new CsrfMiddleware());
        (require dirname(__DIR__, 2) . '/routes/web.php')($this->router);
    }

    /** Registrasi + verifikasi email: pengguna Owner aktif, demo berjalan. @return array{tenantId: int, userId: int} */
    protected function activeOwner(string $email = 'budi@example.com', string $password = self::PASSWORD): array
    {
        $r = $this->register->register([
            'owner_name' => 'Budi Santoso', 'email' => $email, 'phone' => '081234567890',
            'password' => $password, 'business_name' => 'Toko ' . $email, 'business_type' => '', 'address' => '',
        ]);
        $this->assertTrue($this->verify->verify($this->mailer->lastToken()));
        return $r;
    }

    /** @return array{tenantId: int, userId: int} pengguna Owner yang belum diverifikasi */
    protected function pendingOwner(string $email = 'pending@example.com'): array
    {
        return $this->register->register([
            'owner_name' => 'Belum Verif', 'email' => $email, 'phone' => '081234567890',
            'password' => self::PASSWORD, 'business_name' => 'Toko Pending', 'business_type' => '', 'address' => '',
        ]);
    }

    /** @param array<string, mixed> $data */
    protected function send(string $method, string $uri, array $data = [], bool $csrf = true): Response
    {
        if ($csrf && $method === 'POST') {
            $this->session->start();
            $data += ['_csrf' => Csrf::token($this->session)];
        }
        return $this->router->dispatch(Request::create($method, $uri, $data));
    }

    protected function login(string $email = 'budi@example.com', string $password = self::PASSWORD): Response
    {
        return $this->send('POST', '/masuk', ['email' => $email, 'password' => $password]);
    }

    protected function count(string $sql, array $params = []): int
    {
        return (int) $this->db->fetchOne($sql, $params)['n'];
    }

    /** Memajukan jam tanpa melewati batas idle 8 jam: sesi tetap hidup lewat request ringan di antaranya. */
    protected function advanceKeepingSessionAlive(int $seconds): void
    {
        while ($seconds > 0) {
            $step = min($seconds, 25000);
            $this->now += $step;
            $seconds -= $step;
            if ($seconds > 0) {
                $this->send('GET', '/bantuan');
            }
        }
    }
}
