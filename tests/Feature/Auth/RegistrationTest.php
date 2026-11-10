<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Database\Connection;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Middleware\SessionMiddleware;
use App\Core\Request;
use App\Core\Router;
use App\Core\Security\ArraySessionStorage;
use App\Core\Security\Csrf;
use App\Core\Security\PasswordHasher;
use App\Core\Security\RateLimiter;
use App\Core\Security\Session;
use App\Core\Security\TokenService;
use App\Modules\AuditLogs\Repositories\AuditLogRepository;
use App\Modules\AuditLogs\Services\AuditLogger;
use App\Modules\Auth\Controllers\RegisterController;
use App\Modules\Auth\Repositories\EmailVerificationRepository;
use App\Modules\Auth\Services\VerificationEmailSender;
use App\Modules\Auth\Services\VerifyEmailService;
use App\Modules\Tenants\DemoService;
use App\Modules\Tenants\Exceptions\DuplicateEmailException;
use App\Modules\Tenants\Repositories\RoleRepository;
use App\Modules\Tenants\Repositories\TenantRepository;
use App\Modules\Tenants\Repositories\UserRepository;
use App\Modules\Tenants\Services\RegisterTenantService;
use App\Core\Mail\MailException;
use Tests\Support\Db;
use Tests\Support\Fixtures\CapturingMailer;
use Tests\Support\TestCase;

final class RegistrationTest extends TestCase
{
    private const APP_URL = 'https://pos.example.test';
    private const ROLES = ['owner', 'manager', 'supervisor', 'kasir', 'staf-gudang', 'staf-pembelian', 'staf-keuangan', 'akuntan'];

    private Connection $db;
    private CapturingMailer $mailer;
    private int $now;
    private RegisterTenantService $register;
    private VerifyEmailService $verify;
    private RateLimiter $limiter;
    private Session $session;
    private Router $router;
    private UserRepository $users;

    protected function setUp(): void
    {
        $this->now = 1_800_000_000;
        $this->db = Db::fresh();
        $this->mailer = new CapturingMailer();
        $this->users = new UserRepository($this->db);
        $this->wire($this->users);
    }

    private function wire(UserRepository $users): void
    {
        $clock = fn (): int => $this->now;
        $audit = new AuditLogger(new AuditLogRepository($this->db), static fn (): array => ['ip' => '127.0.0.1'], $clock);
        $sender = new VerificationEmailSender(
            new EmailVerificationRepository($this->db),
            new TokenService(),
            $this->mailer,
            self::APP_URL . '/',
            86400,
            $clock
        );
        $this->register = new RegisterTenantService(
            $this->db,
            new TenantRepository($this->db),
            $users,
            new RoleRepository($this->db),
            $sender,
            new PasswordHasher(),
            $audit,
            $clock
        );
        $this->verify = new VerifyEmailService(
            $this->db,
            new EmailVerificationRepository($this->db),
            $users,
            new TenantRepository($this->db),
            new DemoService($this->db, $clock),
            $sender,
            $audit,
            $clock
        );
        $this->limiter = new RateLimiter($this->db, 3, 900, $clock);

        $this->session = new Session(new ArraySessionStorage(), $clock);
        $controller = new RegisterController($this->register, $this->verify, $this->limiter);
        $this->router = new Router(static fn (string $class): object => $controller);
        $this->router->addGlobalMiddleware(new SessionMiddleware(fn (): Session => $this->session, 28800, $clock));
        $this->router->addGlobalMiddleware(new CsrfMiddleware());
        (require dirname(__DIR__, 3) . '/routes/web.php')($this->router);
    }

    /** @return array<string, string> */
    private function input(array $override = []): array
    {
        return $override + [
            'owner_name' => 'Budi Santoso',
            'email' => 'Budi@Example.com',
            'phone' => '0812-3456-7890',
            'password' => 'rahasia-banget',
            'password_confirmation' => 'rahasia-banget',
            'business_name' => 'Toko Maju',
            'business_type' => 'Minimarket',
            'address' => 'Jl. Merdeka 1, Bandung',
        ];
    }

    private function count(string $table): int
    {
        return (int) $this->db->fetchOne("SELECT COUNT(*) AS n FROM $table")['n'];
    }

    /** @return array{tenantId: int, userId: int} */
    private function registerDefault(array $override = []): array
    {
        return $this->register->register($this->input($override));
    }

    public function testRegisterCreatesTenantBranchOwnerAndEightRoles(): void
    {
        $r = $this->registerDefault();
        $tenant = $this->db->fetchOne('SELECT * FROM tenants WHERE id = ?', [$r['tenantId']]);
        $this->assertSame('pending_verification', $tenant['status']);
        $this->assertSame(36, strlen($tenant['uuid']));
        $this->assertSame('Toko Maju', $tenant['name']);
        $profile = $this->db->fetchOne('SELECT * FROM tenant_profiles WHERE tenant_id = ?', [$r['tenantId']]);
        $this->assertSame('Minimarket', $profile['business_type']);
        $this->assertSame('08123456789' . '0', $profile['phone']);
        $this->assertSame(1, $this->count('tenant_settings'));
        $branch = $this->db->fetchOne('SELECT * FROM branches WHERE tenant_id = ?', [$r['tenantId']]);
        $this->assertSame('UTAMA', $branch['code']);
        $this->assertSame('Cabang Utama', $branch['name']);
        $this->assertSame(1, (int) $branch['is_main']);
        $this->assertSame('Jl. Merdeka 1, Bandung', $branch['address']);
        $user = $this->db->fetchOne('SELECT * FROM users WHERE id = ?', [$r['userId']]);
        $this->assertSame('budi@example.com', $user['email']);
        $this->assertSame(1, (int) $user['is_owner']);
        $this->assertSame('pending_verification', $user['status']);
        $this->assertTrue(password_verify('rahasia-banget', $user['password_hash']));
        $slugs = array_column($this->db->query('SELECT slug FROM roles WHERE tenant_id = ? ORDER BY id', [$r['tenantId']]), 'slug');
        $this->assertSame(self::ROLES, $slugs);
        $this->assertSame(8, (int) $this->db->fetchOne('SELECT COUNT(*) AS n FROM roles WHERE is_system = 1')['n']);
        $assigned = $this->db->query(
            'SELECT r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?',
            [$r['userId']]
        );
        $this->assertSame([['slug' => 'owner']], $assigned);
        $audit = $this->db->fetchOne("SELECT * FROM audit_logs WHERE action = 'tenant.registered'");
        $this->assertSame($r['tenantId'], (int) $audit['tenant_id']);
        $this->assertSame('tenant_user', $audit['actor_type']);
        $this->assertSame(1, count($this->mailer->sent));
        $this->assertSame('budi@example.com', $this->mailer->sent[0]['to']);
    }

    public function testOwnerRoleHasNoRowsInRolePermissionsButIsOwnerFlagged(): void
    {
        $r = $this->registerDefault();
        $this->assertSame(0, $this->count('role_permissions'));
        $this->assertSame(1, (int) $this->users->findById($r['tenantId'], $r['userId'])['is_owner']);
    }

    public function testOtherRolesAreCreatedEmpty(): void
    {
        $r = $this->registerDefault();
        $n = (int) $this->db->fetchOne(
            'SELECT COUNT(*) AS n FROM user_roles WHERE tenant_id = ? AND role_id IN (SELECT id FROM roles WHERE slug <> ?)',
            [$r['tenantId'], 'owner']
        )['n'];
        $this->assertSame(0, $n);
        $this->assertSame(0, $this->count('role_permissions'));
    }

    public function testDuplicateEmailIgnoringCaseAndWhitespaceIsRejected(): void
    {
        $this->registerDefault();
        $this->assertThrows(DuplicateEmailException::class, fn () => $this->registerDefault(['email' => '  BUDI@example.COM ']));
        $this->assertSame(1, $this->count('tenants'));
        $this->assertSame(1, $this->count('users'));
        $this->assertSame('budi@example.com', $this->users->findByEmail("  BUDI@EXAMPLE.com\t")['email']);
    }

    public function testDuplicateEmailRaceMapsToValidationNotServerError(): void
    {
        $this->registerDefault();
        // Pemeriksaan awal "lolos" (balapan): hanya batas unik DB yang menolak.
        $blind = new class ($this->db) extends UserRepository {
            public function findByEmail(string $email): ?array
            {
                return null;
            }
        };
        $this->wire($blind);
        $this->assertThrows(DuplicateEmailException::class, fn () => $this->registerDefault());
        $this->assertSame(1, $this->count('tenants'));
        $res = $this->post('/daftar', $this->input());
        $this->assertSame(422, $res->status());
        $this->assertContains('Email sudah terdaftar.', $res->body());
    }

    public function testNoDemoBeforeVerification(): void
    {
        $this->registerDefault();
        $this->assertSame(0, $this->count('tenant_demos'));
    }

    public function testVerifyActivatesUserTenantAndStartsDemoOnce(): void
    {
        $r = $this->registerDefault();
        $this->now += 60;
        $this->assertTrue($this->verify->verify($this->mailer->lastToken()));
        $user = $this->db->fetchOne('SELECT * FROM users WHERE id = ?', [$r['userId']]);
        $this->assertSame('active', $user['status']);
        $this->assertSame(gmdate('Y-m-d H:i:s', $this->now), $user['email_verified_at']);
        $this->assertSame('active', $this->db->fetchOne('SELECT status FROM tenants WHERE id = ?', [$r['tenantId']])['status']);
        $demos = $this->db->query('SELECT * FROM tenant_demos');
        $this->assertSame(1, count($demos));
        $this->assertSame($r['tenantId'], (int) $demos[0]['tenant_id']);
        $this->assertSame('registration', $demos[0]['source']);
        $this->assertSame(gmdate('Y-m-d H:i:s', $this->now), $demos[0]['starts_at']);
        $this->assertSame(gmdate('Y-m-d H:i:s', $this->now + 86400), $demos[0]['ends_at']);
        $this->assertSame(1, (int) $this->db->fetchOne("SELECT COUNT(*) AS n FROM audit_logs WHERE action = 'auth.email_verified'")['n']);
    }

    public function testVerifyTokenSecondUseReturnsFalse(): void
    {
        $this->registerDefault();
        $token = $this->mailer->lastToken();
        $this->assertTrue($this->verify->verify($token));
        $this->assertFalse($this->verify->verify($token));
        $this->assertSame(1, $this->count('tenant_demos'));
        $this->assertSame(1, (int) $this->db->fetchOne("SELECT COUNT(*) AS n FROM audit_logs WHERE action = 'auth.email_verified'")['n']);
    }

    public function testExpiredOrRandomTokenReturnsFalseWithoutSideEffects(): void
    {
        $this->registerDefault();
        $token = $this->mailer->lastToken();
        $before = $this->snapshot();
        $this->assertFalse($this->verify->verify(bin2hex(random_bytes(32))));
        $this->assertFalse($this->verify->verify('../../etc/passwd'));
        $this->assertFalse($this->verify->verify(''));
        $this->now += 86400 + 1;
        $this->assertFalse($this->verify->verify($token));
        $this->assertSame($before, $this->snapshot());
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            $this->db->query('SELECT * FROM users'),
            $this->db->query('SELECT * FROM tenants'),
            $this->db->query('SELECT * FROM email_verifications'),
            $this->count('tenant_demos'),
            $this->count('audit_logs'),
        ];
    }

    public function testRegistrationRollsBackWhenMailerFails(): void
    {
        $this->mailer->fail = true;
        $this->assertThrows(MailException::class, fn () => $this->registerDefault());
        foreach (['tenants', 'tenant_profiles', 'tenant_settings', 'users', 'branches', 'roles', 'user_roles', 'email_verifications', 'audit_logs'] as $table) {
            $this->assertSame(0, $this->count($table), $table);
        }
    }

    public function testPlainTokenNeverStored(): void
    {
        $r = $this->registerDefault();
        $token = $this->mailer->lastToken();
        $this->verify->verify($token);
        $this->verify->resend('budi@example.com', '127.0.0.1');
        $tables = array_column($this->db->query("SELECT name FROM sqlite_master WHERE type = 'table'"), 'name');
        foreach ($tables as $table) {
            foreach ($this->db->query("SELECT * FROM \"$table\"") as $row) {
                foreach ($row as $value) {
                    $this->assertFalse(is_string($value) && str_contains($value, $token), "token polos ada di $table");
                    $this->assertFalse(is_string($value) && str_contains($value, 'rahasia-banget'), "password polos ada di $table");
                }
            }
        }
        $this->assertSame($r['userId'], (int) $this->db->fetchOne('SELECT user_id FROM email_verifications LIMIT 1')['user_id']);
    }

    public function testResendIsNeutralRateLimitedAndInvalidatesOldToken(): void
    {
        $this->registerDefault();
        $old = $this->mailer->lastToken();
        $sent = count($this->mailer->sent);

        $this->verify->resend('nobody@example.com', '127.0.0.1');
        $this->assertSame($sent, count($this->mailer->sent));

        $this->now += 10;
        $this->verify->resend(' BUDI@example.com ', '127.0.0.1');
        $this->assertSame($sent + 1, count($this->mailer->sent));
        $new = $this->mailer->lastToken();
        $this->assertTrue($new !== $old);
        $this->assertSame(1, (int) $this->db->fetchOne("SELECT COUNT(*) AS n FROM audit_logs WHERE action = 'auth.verification_resent'")['n']);
        $this->assertFalse($this->verify->verify($old));
        $this->assertTrue($this->verify->verify($new));

        // Sudah terverifikasi: netral, tidak mengirim apa pun.
        $this->verify->resend('budi@example.com', '127.0.0.1');
        $this->assertSame($sent + 1, count($this->mailer->sent));

        // Pembatasan laju lewat controller: limit 3 per jendela.
        $codes = [];
        for ($i = 0; $i < 4; $i++) {
            $codes[] = $this->post('/verifikasi-email/kirim-ulang', ['email' => 'x@example.com'])->status();
        }
        $this->assertSame([200, 200, 200, 429], $codes);
        $this->assertContains('Terlalu banyak percobaan. Coba lagi beberapa saat lagi.', $this->post('/verifikasi-email/kirim-ulang', ['email' => 'x@example.com'])->body());
    }

    public function testDemoDurationComesFromDemoSettings(): void
    {
        $this->db->execute('UPDATE demo_settings SET duration_hours = 48');
        $r = $this->registerDefault();
        $this->verify->verify($this->mailer->lastToken());
        $demo = $this->db->fetchOne('SELECT * FROM tenant_demos WHERE tenant_id = ?', [$r['tenantId']]);
        $this->assertSame(gmdate('Y-m-d H:i:s', $this->now + 48 * 3600), $demo['ends_at']);

        $this->db->execute('DELETE FROM tenant_demos');
        $this->db->execute('UPDATE demo_settings SET is_enabled = 0');
        (new DemoService($this->db, fn (): int => $this->now))->startFor($r['tenantId'], 'registration');
        $this->assertSame(0, $this->count('tenant_demos'));
    }

    public function testVerificationLinkInEmailUsesAppUrl(): void
    {
        $this->registerDefault();
        $body = $this->mailer->sent[0]['body'];
        $this->assertContains(self::APP_URL . '/verifikasi-email/' . $this->mailer->lastToken(), $body);
        $this->assertFalse(str_contains($body, self::APP_URL . '//'));
        $this->assertFalse(str_contains($body, 'rahasia-banget'));
    }

    private function csrf(): string
    {
        $this->session->start();
        return Csrf::token($this->session);
    }

    private function post(string $path, array $data, bool $withCsrf = true): \App\Core\Response
    {
        if ($withCsrf) {
            $data['_csrf'] = $this->csrf();
        }
        return $this->router->dispatch(Request::create('POST', $path, $data));
    }

    public function testPostRegisterSuccessRedirectsAndWithoutCsrfIs419(): void
    {
        $res = $this->post('/daftar', $this->input());
        $this->assertSame(302, $res->status());
        $this->assertSame('/daftar/cek-email', $res->header('Location'));
        $this->assertSame(1, $this->count('tenants'));

        $res = $this->post('/daftar', $this->input(['email' => 'lain@example.com']), false);
        $this->assertSame(419, $res->status());
        $this->assertSame(1, $this->count('tenants'));

        $page = $this->router->dispatch(Request::create('GET', '/daftar/cek-email'));
        $this->assertSame(200, $page->status());
        $this->assertContains('Cek email Anda', $page->body());
    }

    public function testPostRegisterInvalidInputReturns422WithErrorsAndKeepsValuesExceptPasswords(): void
    {
        $res = $this->post('/daftar', $this->input([
            'email' => 'bukan-email',
            'business_name' => '<script>alert(1)</script>',
            'password' => 'pendek-rahasia',
            'password_confirmation' => 'beda',
        ]));
        $this->assertSame(422, $res->status());
        $body = $res->body();
        $this->assertContains('Format email tidak valid.', $body);
        $this->assertContains('Konfirmasi kata sandi tidak cocok.', $body);
        $this->assertContains('value="bukan-email"', $body);
        $this->assertContains('Budi Santoso', $body);
        $this->assertContains('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        $this->assertFalse(str_contains($body, '<script>'));
        $this->assertFalse(str_contains($body, 'pendek-rahasia'));
        $this->assertFalse(str_contains($body, 'beda'));
        $this->assertSame(0, $this->count('tenants'));
    }

    public function testRegisterPostIsRateLimitedPerIp(): void
    {
        $codes = [];
        for ($i = 0; $i < 4; $i++) {
            $codes[] = $this->post('/daftar', $this->input(['email' => 'bukan-email']))->status();
        }
        $this->assertSame([422, 422, 422, 429], $codes);
    }

    public function testMailerFailureOnRegisterShowsFriendlyErrorAndStoresNothing(): void
    {
        $this->mailer->fail = true;
        $res = $this->post('/daftar', $this->input());
        $this->assertSame(503, $res->status());
        $this->assertContains('Gagal mengirim email verifikasi', $res->body());
        $this->assertFalse(str_contains($res->body(), 'SMTP down'));
        $this->assertSame(0, $this->count('tenants'));
    }

    public function testGetVerifyGarbageTokenShowsNeutralPageAndValidTokenSucceeds(): void
    {
        $res = $this->router->dispatch(Request::create('GET', '/verifikasi-email/garbage%27%20OR%201=1'));
        $this->assertSame(404, $res->status());
        $this->assertContains('Tautan tidak valid atau sudah digunakan.', $res->body());
        $this->assertFalse(str_contains(strtolower($res->body()), 'sql'));

        $this->registerDefault();
        $token = $this->mailer->lastToken();
        $ok = $this->router->dispatch(Request::create('GET', '/verifikasi-email/' . $token));
        $this->assertSame(200, $ok->status());
        $this->assertContains('href="/masuk"', $ok->body());
        $again = $this->router->dispatch(Request::create('GET', '/verifikasi-email/' . $token));
        $this->assertContains('Tautan tidak valid atau sudah digunakan.', $again->body());
        $this->assertSame(1, $this->count('tenant_demos'));
    }

    public function testResendFormRenders(): void
    {
        $res = $this->router->dispatch(Request::create('GET', '/verifikasi-email'));
        $this->assertSame(200, $res->status());
        $this->assertContains('name="_csrf"', $res->body());
    }

    public function testTemplatesHaveLabelsAndNoInlineScript(): void
    {
        $dir = dirname(__DIR__, 3) . '/resources/views';
        foreach (['auth/register', 'auth/resend-verification', 'auth/verify-result', 'auth/verification-sent', 'auth/notice', 'layouts/public'] as $name) {
            $src = (string) file_get_contents("$dir/$name.php");
            $this->assertFalse(str_contains(strtolower($src), '<script'), "$name script");
            $this->assertFalse(preg_match('/\son[a-z]+\s*=/i', $src) === 1, "$name handler inline");
            $this->assertFalse(preg_match('#(src|href)="https?://#i', $src) === 1, "$name aset eksternal");
        }
        $html = $this->router->dispatch(Request::create('GET', '/daftar'))->body();
        $this->assertContains('<html lang="id">', $html);
        preg_match_all('/<input\b[^>]*\bid="([^"]+)"[^>]*>/', $html, $m);
        $this->assertTrue(count($m[1]) >= 8);
        foreach ($m[1] as $id) {
            $this->assertContains('<label for="' . $id . '">', $html);
        }
        $this->assertContains('name="_csrf"', $html);
    }
}
