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
    private RateLimiter $registrationLimiter;
    private VerificationEmailSender $sender;
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
        $this->sender = $sender = new VerificationEmailSender(
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
        $this->registrationLimiter = new RateLimiter($this->db, 3, 900, $clock);

        $this->session = new Session(new ArraySessionStorage(), $clock);
        $controller = new RegisterController($this->register, $this->verify, $this->limiter, $this->registrationLimiter);
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

    public function testInvalidUtf8InputIsValidationErrorNotServerError(): void
    {
        foreach (['owner_name', 'business_name', 'address', 'business_type'] as $field) {
            $res = $this->post('/daftar', $this->input([$field => "Bud\xFFi"]));
            $this->assertSame(422, $res->status(), $field);
            $this->assertContains('teks tidak valid', $res->body(), $field);
        }
        $this->assertSame(0, $this->count('tenants'));
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

    public function testValidationFailuresDoNotCountTowardRegistrationLimit(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->assertSame(422, $this->post('/daftar', $this->input(['email' => 'bukan-email']))->status());
        }
        $this->assertSame(302, $this->post('/daftar', $this->input())->status());
    }

    public function testRegisterPostIsRateLimitedPerIpAfterValidAttempts(): void
    {
        $codes = [$this->post('/daftar', $this->input())->status()];
        for ($i = 0; $i < 3; $i++) {
            $codes[] = $this->post('/daftar', $this->input())->status();
        }
        $this->assertSame([302, 422, 422, 429], $codes);
        $this->assertSame(1, $this->count('tenants'));
    }

    public function testRegistrationLimitIsIndependentFromResendLimit(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/verifikasi-email/kirim-ulang', ['email' => "r$i@example.com"]);
        }
        $this->assertSame(429, $this->post('/verifikasi-email/kirim-ulang', ['email' => 'r9@example.com'])->status());
        $this->assertSame(302, $this->post('/daftar', $this->input())->status());
    }

    public function testRegistrationLimitConfigExists(): void
    {
        $cfg = require dirname(__DIR__, 3) . '/config/security.php';
        $this->assertTrue($cfg['registration']['max_attempts'] > $cfg['login']['max_attempts']);
        $this->assertTrue($cfg['registration']['window_seconds'] > 0);
    }

    public function testPasswordLimitIsSeventyTwoBytesOnRegister(): void
    {
        $ok = str_repeat('a', 72);
        $this->assertSame(302, $this->post('/daftar', $this->input(['password' => $ok, 'password_confirmation' => $ok]))->status());
        $multi = str_repeat('é', 37);
        $res = $this->post('/daftar', $this->input(['email' => 'lain@example.com', 'password' => $multi, 'password_confirmation' => $multi]));
        $this->assertSame(422, $res->status());
        $this->assertContains('maksimal 72 byte', $res->body());
        $edge = str_repeat('é', 36);
        $this->assertSame(302, $this->post('/daftar', $this->input(['email' => 'tiga@example.com', 'password' => $edge, 'password_confirmation' => $edge]))->status());
    }

    public function testOptionalFieldsAreNotMarkedRequiredInMarkup(): void
    {
        $html = $this->router->dispatch(Request::create('GET', '/daftar'))->body();
        foreach (['business_type', 'address'] as $id) {
            preg_match('/<input\b[^>]*\bid="' . $id . '"[^>]*>/', $html, $m);
            $this->assertFalse(str_contains($m[0], 'required'), $id);
        }
        foreach (['owner_name', 'email', 'phone', 'business_name', 'password', 'password_confirmation'] as $id) {
            preg_match('/<input\b[^>]*\bid="' . $id . '"[^>]*>/', $html, $m);
            $this->assertContains(' required', $m[0], $id);
        }
    }

    public function testRegisterViewEscapesRepopulatedValues(): void
    {
        $res = $this->post('/daftar', $this->input([
            'owner_name' => '"><script>alert(1)</script>',
            'address' => "' onfocus='alert(2)",
            'email' => 'bukan-email',
        ]));
        $this->assertSame(422, $res->status());
        $body = $res->body();
        $this->assertFalse(str_contains($body, '<script>'));
        $this->assertFalse(str_contains($body, '"><script>'));
        $this->assertContains('value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $body);
        $this->assertFalse(str_contains($body, "value=''"));
        $this->assertContains('&#039; onfocus=&#039;alert(2)', $body);
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
        foreach (['auth/register', 'auth/resend-verification', 'auth/verify-result', 'auth/verification-sent', 'auth/notice'] as $name) {
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
            $this->assertTrue(preg_match('/<label\b[^>]*\bfor="' . preg_quote($id, '/') . '"/', $html) === 1, "label untuk $id");
        }
        $this->assertContains('name="_csrf"', $html);
    }

    /** @return array{tenantId: int, userId: int, token: string} */
    private function invitedUser(int $tenantId): array
    {
        $now = gmdate('Y-m-d H:i:s', $this->now);
        $id = $this->users->create($tenantId, 'Sari', 'sari@example.com', null, (new PasswordHasher())->hash('placeholder-xyz'), false, 'pending_verification', $now);
        $this->sender->send($id, 'sari@example.com', 'Sari');
        return ['tenantId' => $tenantId, 'userId' => $id, 'token' => $this->mailer->lastToken()];
    }

    public function testVerifyAndSetPasswordActivatesInvitedUserWithoutDemo(): void
    {
        $r = $this->registerDefault();
        $inv = $this->invitedUser($r['tenantId']);
        $this->now += 30;
        $this->assertTrue($this->verify->verifyAndSetPassword($inv['token'], 'sandi-baru-123'));
        $user = $this->db->fetchOne('SELECT * FROM users WHERE id = ?', [$inv['userId']]);
        $this->assertSame('active', $user['status']);
        $this->assertTrue(password_verify('sandi-baru-123', $user['password_hash']));
        $this->assertSame(gmdate('Y-m-d H:i:s', $this->now), $user['email_verified_at']);
        $this->assertSame(0, $this->count('tenant_demos'));
        $this->assertSame('pending_verification', $this->db->fetchOne('SELECT status FROM tenants WHERE id = ?', [$r['tenantId']])['status']);
        $this->assertSame('pending_verification', $this->db->fetchOne('SELECT status FROM users WHERE id = ?', [$r['userId']])['status']);
        $this->assertFalse($this->verify->verifyAndSetPassword($inv['token'], 'sandi-baru-123'));
    }

    public function testVerifyAndSetPasswordRejectsOwnerTokenWithoutSideEffects(): void
    {
        $this->registerDefault();
        $before = $this->snapshot();
        $this->assertFalse($this->verify->verifyAndSetPassword($this->mailer->lastToken(), 'sandi-baru-123'));
        $this->assertSame($before, $this->snapshot());
    }

    public function testVerifyRejectsNonOwnerInvitationTokenWithoutSideEffects(): void
    {
        $r = $this->registerDefault();
        $inv = $this->invitedUser($r['tenantId']);
        $before = $this->snapshot();
        $this->assertFalse($this->verify->verify($inv['token']));
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(0, $this->count('tenant_demos'));
    }

    public function testVerifyAndSetPasswordInvalidExpiredUsedAndPolicy(): void
    {
        $r = $this->registerDefault();
        $inv = $this->invitedUser($r['tenantId']);
        $before = $this->snapshot();
        $this->assertFalse($this->verify->verifyAndSetPassword(bin2hex(random_bytes(32)), 'sandi-baru-123'));
        $this->assertFalse($this->verify->verifyAndSetPassword('bukan-token', 'sandi-baru-123'));
        $this->assertFalse($this->verify->verifyAndSetPassword($inv['token'], 'pendek'));
        $this->assertFalse($this->verify->verifyAndSetPassword($inv['token'], str_repeat('a', 73)));
        $this->assertFalse($this->verify->verifyAndSetPassword($inv['token'], str_repeat('é', 37)));
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(null, $this->db->fetchOne('SELECT used_at FROM email_verifications WHERE user_id = ?', [$inv['userId']])['used_at']);

        $this->now += 86400 + 1;
        $this->assertFalse($this->verify->verifyAndSetPassword($inv['token'], 'sandi-baru-123'));
        $this->now -= 86400 + 1;
        $this->assertTrue($this->verify->verifyAndSetPassword($inv['token'], str_repeat('a', 72)));
        $this->assertFalse($this->verify->verifyAndSetPassword($inv['token'], 'sandi-baru-123'));
    }

    public function testResendForNonOwnerPendingUserSendsNothing(): void
    {
        $r = $this->registerDefault();
        $this->invitedUser($r['tenantId']);
        $sent = count($this->mailer->sent);
        $this->verify->resend('sari@example.com', '127.0.0.1');
        $this->assertSame($sent, count($this->mailer->sent));
        $this->assertSame(0, (int) $this->db->fetchOne("SELECT COUNT(*) AS n FROM audit_logs WHERE action = 'auth.verification_resent'")['n']);
    }

    public function testOwnerCannotLoginStateBypassVerificationViaPasswordFlow(): void
    {
        $r = $this->registerDefault();
        $this->assertFalse($this->verify->verifyAndSetPassword($this->mailer->lastToken(), 'sandi-baru-123'));
        $this->assertSame('pending_verification', $this->db->fetchOne('SELECT status FROM users WHERE id = ?', [$r['userId']])['status']);
        $this->assertTrue(password_verify('rahasia-banget', $this->db->fetchOne('SELECT password_hash FROM users WHERE id = ?', [$r['userId']])['password_hash']));
    }

    public function testResendWithFailingMailerStaysNeutralAndKeepsOldTokenValid(): void
    {
        $this->registerDefault();
        $old = $this->mailer->lastToken();
        $this->mailer->fail = true;
        $res = $this->post('/verifikasi-email/kirim-ulang', ['email' => 'budi@example.com']);
        $this->assertSame(200, $res->status());
        $neutral = $this->post('/verifikasi-email/kirim-ulang', ['email' => 'nobody@example.com']);
        $this->assertSame(200, $neutral->status());
        $this->assertContains('Jika email terdaftar dan belum terverifikasi', $res->body());
        $this->assertFalse(str_contains($res->body(), 'SMTP down'));
        $this->assertSame(0, (int) $this->db->fetchOne("SELECT COUNT(*) AS n FROM audit_logs WHERE action = 'auth.verification_resent'")['n']);
        $this->mailer->fail = false;
        $this->assertTrue($this->verify->verify($old));
    }

    public function testResendIpCapAppliesAcrossDifferentEmails(): void
    {
        $codes = [];
        foreach (['a', 'b', 'c', 'd'] as $n) {
            $codes[] = $this->post('/verifikasi-email/kirim-ulang', ['email' => "$n@example.com"])->status();
        }
        $this->assertSame([200, 200, 200, 429], $codes);
    }

    public function testVerifyDoesNotStartDemoWhenTenantIsNotPending(): void
    {
        $r = $this->registerDefault();
        $this->db->execute("UPDATE tenants SET status = 'suspended' WHERE id = ?", [$r['tenantId']]);
        $this->assertTrue($this->verify->verify($this->mailer->lastToken()));
        $this->assertSame(0, $this->count('tenant_demos'));
        $this->assertSame('suspended', $this->db->fetchOne('SELECT status FROM tenants WHERE id = ?', [$r['tenantId']])['status']);
    }

    public function testEmailBodyStripsControlCharactersFromOwnerName(): void
    {
        $this->registerDefault(['owner_name' => "Budi\r\n\r\nKlik http://evil.test\x07 sekarang"]);
        $body = $this->mailer->sent[0]['body'];
        $this->assertTrue(str_starts_with($body, "Halo Budi Klik http://evil.test sekarang,\n\n"));
        $this->assertFalse(str_contains($body, "\r"));
        $this->assertFalse(str_contains($body, "\x07"));
    }

    public function testProductionRequiresPublicAppUrlAtLinkBuildTime(): void
    {
        $make = fn (string $url, bool $prod): VerificationEmailSender => new VerificationEmailSender(
            new EmailVerificationRepository($this->db),
            new TokenService(),
            $this->mailer,
            $url,
            86400,
            fn (): int => $this->now,
            $prod
        );
        $r = $this->registerDefault();
        $sent = count($this->mailer->sent);
        $tokens = $this->count('email_verifications');
        foreach (['', 'http://localhost:8000', 'http://127.0.0.1'] as $bad) {
            $this->assertThrows(\RuntimeException::class, fn () => $make($bad, true)->send($r['userId'], 'budi@example.com', 'Budi'));
        }
        $this->assertSame($sent, count($this->mailer->sent));
        $this->assertSame($tokens, $this->count('email_verifications'));
        $make('http://localhost:8000', false)->send($r['userId'], 'budi@example.com', 'Budi');
        $make('https://pos.example.test', true)->send($r['userId'], 'budi@example.com', 'Budi');
        $this->assertSame($sent + 2, count($this->mailer->sent));
    }

    public function testNulByteInPasswordIsRejectedOnRegisterAndInvite(): void
    {
        $nul = "abcd\0efghij";
        $res = $this->post('/daftar', $this->input(['password' => $nul, 'password_confirmation' => $nul]));
        $this->assertSame(422, $res->status());
        $this->assertSame(0, $this->count('users'));
        $r = $this->registerDefault();
        $inv = $this->invitedUser($r['tenantId']);
        $this->assertFalse($this->verify->verifyAndSetPassword($inv['token'], $nul));
        $this->assertThrows(\InvalidArgumentException::class, fn () => $this->register->register($this->input(['email' => 'nul@example.com', 'password' => $nul])));
    }

    public function testVerifyLinkPageIsNotCachedAndSendsNoReferrer(): void
    {
        $res = $this->router->dispatch(Request::create('GET', '/verifikasi-email/' . str_repeat('1', 64)));
        $this->assertSame('no-store', $res->header('Cache-Control'));
        $this->assertSame('no-referrer', $res->header('Referrer-Policy'));
    }
}
