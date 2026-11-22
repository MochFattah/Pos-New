<?php

declare(strict_types=1);

namespace Tests\Feature\Install;

use App\Core\Application;
use App\Core\Database\Connection;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Middleware\SessionMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Security\ArraySessionStorage;
use App\Core\Security\Csrf;
use App\Core\Security\Session;
use App\Modules\Install\InstallAttemptLimiter;
use App\Modules\Install\InstallController;
use App\Modules\Install\InstallService;
use App\Modules\Install\PreflightChecker;
use RuntimeException;
use Tests\Support\Db;
use Tests\Support\TenantScopeScanner;
use Tests\Support\TestCase;

final class InstallTest extends TestCase
{
    private const TOKEN = 'token-pasang-rahasia-123';
    private const DB_SECRET = 'sandi-db-super-rahasia';

    private string $base = '';
    private int $now = 1_800_000_000;
    private Connection $db;
    private Session $session;
    private Application $app;
    private InstallService $service;
    /** @var (callable(): Connection)|null */
    private $dbFactory = null;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/pos-install-' . bin2hex(random_bytes(5));
        mkdir($this->base . '/storage/logs', 0777, true);
        mkdir($this->base . '/migrations', 0777, true);
        file_put_contents($this->base . '/.env', "APP_ENV=testing\n");
        // Fixture portabel: DDL MySQL asli tidak dapat dijalankan di SQLite; skema SQLite tes dipakai sebagai migrasi.
        copy(dirname(__DIR__, 2) . '/Support/schema.sqlite.sql', $this->base . '/migrations/0001_fixture.sql');
        $this->db = Db::memory();
        $this->dbFactory = null;
        $this->boot(self::TOKEN);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->base);
    }

    private function boot(string $token): void
    {
        $this->service = new InstallService(
            $this->base . '/storage/installed.lock',
            $token,
            fn (): Connection => $this->dbFactory !== null ? ($this->dbFactory)() : $this->db,
            $this->base . '/migrations',
            new PreflightChecker($this->base, $this->base . '/migrations', PHP_VERSION_ID, static fn (string $e): bool => true),
            $this->base . '/storage/logs/install.log',
            [self::DB_SECRET]
        );
        $limiter = new InstallAttemptLimiter($this->base . '/storage/cache/attempts.json', 5, 30, 900, fn (): int => $this->now);
        $controller = new InstallController($this->service, $limiter);
        $this->session = new Session(new ArraySessionStorage(), fn (): int => $this->now);
        $router = new Router(static fn (string $class): object => $class === InstallController::class
            ? $controller
            : throw new \LogicException($class));
        $router->addGlobalMiddleware(new SessionMiddleware(fn (): Session => $this->session, 28800, fn (): int => $this->now));
        $router->addGlobalMiddleware(new CsrfMiddleware());
        // Sama seperti bootstrap/app.php: rute hanya terdaftar selama penginstal aktif.
        if ($this->service->isActive()) {
            (require dirname(__DIR__, 3) . '/routes/install.php')($router);
        }
        $this->app = new Application($router, $this->base . '/storage/logs/app.log');
    }

    /** @param array<string, mixed> $data */
    private function send(string $method, string $uri, array $data = [], bool $csrf = true): Response
    {
        if ($csrf && $method === 'POST') {
            $this->session->start();
            $data += ['_csrf' => Csrf::token($this->session)];
        }
        return $this->app->handle(Request::create($method, $uri, $data));
    }

    private function install(string $token = self::TOKEN): Response
    {
        return $this->send('POST', '/install', ['token' => $token]);
    }

    private function unknownRouteBody(): string
    {
        return $this->app->handle(Request::create('GET', '/tidak-ada-halaman'))->body();
    }

    public function testInstallRequiresValidToken(): void
    {
        $form = $this->send('GET', '/install');
        $this->assertSame(200, $form->status());
        $this->assertContains('name="token"', $form->body());
        $this->assertContains('type="password"', $form->body());

        $this->assertSame(422, $this->install('salah')->status());
        $this->assertSame(422, $this->install('')->status());
        $this->assertSame(422, $this->send('POST', '/install', [])->status());
        // Token di URL tidak pernah dipakai, hanya badan formulir.
        $this->assertSame(422, $this->send('POST', '/install?token=' . self::TOKEN)->status());
        $this->assertSame(200, $this->send('GET', '/install?token=' . self::TOKEN)->status(), 'GET hanya menampilkan formulir');
        $this->assertFalse($this->service->isInstalled());
        $this->assertSame(0, $this->tableCount('schema_migrations', true));
        // Tanpa CSRF ditolak sebelum token diperiksa.
        $this->assertSame(419, $this->send('POST', '/install', ['token' => self::TOKEN], false)->status());
        $this->assertFalse($this->service->isInstalled());
    }

    public function testInstallRunsMigrationsAndWritesLock(): void
    {
        $r = $this->install();
        $this->assertSame(200, $r->status());
        $this->assertContains('Pemasangan selesai', $r->body());
        $this->assertContains('create-platform-admin.php', $r->body());
        $this->assertTrue($this->service->isInstalled());
        $this->assertTrue(is_file($this->base . '/storage/installed.lock'));
        $this->assertSame(1, $this->tableCount('schema_migrations'));
        $this->assertTrue($this->tableCount('permissions') > 0);
        $this->assertSame(1, $this->tableCount('demo_settings'));
        $this->assertSame(0, $this->tableCount('platform_users'), 'Super Admin tidak dibuat oleh penginstal');
        $this->assertFalse(is_file($this->base . '/storage/logs/install.log'));
    }

    public function testInstallRoutesReturn404AfterLock(): void
    {
        $unknown = $this->unknownRouteBody();
        $this->assertSame(200, $this->install()->status());

        // Proses baru setelah kunci terpasang: rute tidak terdaftar sama sekali.
        $this->boot(self::TOKEN);
        foreach (['/install', '/install/', '/install/x', '/install?token=' . self::TOKEN] as $uri) {
            $get = $this->send('GET', $uri);
            $this->assertSame(404, $get->status(), "GET $uri");
            $this->assertSame($unknown, $get->body(), "GET $uri sama dengan rute tak dikenal");
            $post = $this->send('POST', $uri, ['token' => self::TOKEN]);
            $this->assertSame(404, $post->status(), "POST $uri");
        }
        // Pelindung kedua di controller: proses yang rutenya sudah terdaftar sebelum kunci muncul.
        $stale = new InstallController($this->service, new InstallAttemptLimiter($this->base . '/storage/cache/a2.json'));
        $this->assertThrows(\App\Core\Exceptions\NotFoundException::class, fn () => $stale->showForm(Request::create('GET', '/install')));
    }

    public function testShortInstallTokenDisablesInstaller(): void
    {
        $this->boot('pendek-14-char');
        $this->assertSame(14, strlen('pendek-14-char'));
        $this->assertFalse($this->service->isActive());
        $this->assertFalse($this->service->tokenMatches('pendek-14-char'));
        $this->assertSame(404, $this->send('GET', '/install')->status());
        $this->boot('x1234567890123456');
        $this->assertTrue($this->service->isActive());
        $this->boot(str_repeat('a', 15));
        $this->assertFalse($this->service->isActive());
        $this->boot(str_repeat('a', 16));
        $this->assertTrue($this->service->isActive());
    }

    public function testEmptyInstallTokenDisablesInstaller(): void
    {
        $this->boot('');
        $this->assertFalse($this->service->isActive());
        $unknown = $this->unknownRouteBody();
        $get = $this->send('GET', '/install');
        $this->assertSame(404, $get->status());
        $this->assertSame($unknown, $get->body());
        $this->assertSame(404, $this->send('POST', '/install', ['token' => ''])->status());
        $this->assertFalse($this->service->tokenMatches(''));
        $this->assertFalse($this->service->isInstalled());
        $this->assertSame(0, $this->tableCount('permissions', true));
    }

    public function testWrongTokenIsRateLimited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(422, $this->install('tebak-' . $i)->status());
        }
        $locked = $this->install(self::TOKEN);
        $this->assertSame(429, $locked->status(), 'token benar pun ditolak saat terkunci');
        $this->assertFalse($this->service->isInstalled());
        $this->now += 901;
        $this->assertSame(200, $this->install(self::TOKEN)->status());
        $this->assertTrue($this->service->isInstalled());
    }

    public function testDoublePostRunsOnce(): void
    {
        $first = $this->install();
        $second = $this->install();
        $this->assertSame(200, $first->status());
        $this->assertSame(404, $second->status());
        $this->assertSame(1, $this->tableCount('schema_migrations'));
        $this->assertSame(9, $this->tableCount('permissions'));

        // Pemanggilan langsung tidak menjalankan ulang dan tidak menimpa kunci.
        $lock = (string) file_get_contents($this->base . '/storage/installed.lock');
        $again = $this->service->install();
        $this->assertSame(InstallService::ALREADY, $again['status']);
        $this->assertSame($lock, (string) file_get_contents($this->base . '/storage/installed.lock'));
    }

    public function testConcurrentInstallIsRejectedWhileGuardHeld(): void
    {
        $held = fopen($this->base . '/storage/installing.lock', 'c');
        $this->assertTrue(flock($held, LOCK_EX | LOCK_NB));
        $r = $this->service->install();
        $this->assertSame(InstallService::BUSY, $r['status']);
        $this->assertFalse($this->service->isInstalled());
        flock($held, LOCK_UN);
        fclose($held);
        $this->assertSame(InstallService::OK, $this->service->install()['status']);
    }

    public function testFailureDoesNotLeakCredentialsAndAllowsRetry(): void
    {
        $this->dbFactory = static function (): Connection {
            throw new RuntimeException("SQLSTATE[HY000] [1045] Access denied for user 'pos_user'@'localhost' (using password: " . 'sandi-db-super-rahasia' . ')');
        };
        $r = $this->install();
        $this->assertSame(500, $r->status());
        $body = $r->body();
        foreach ([self::DB_SECRET, 'pos_user', 'SQLSTATE', 'RuntimeException', '.php:', 'Stack trace'] as $needle) {
            $this->assertFalse(str_contains($body, $needle), "halaman memuat $needle");
        }
        $this->assertContains('storage/logs/install.log', $body);
        $log = (string) file_get_contents($this->base . '/storage/logs/install.log');
        $this->assertContains('migrasi/seeder gagal', $log);
        $this->assertFalse(str_contains($log, self::DB_SECRET), 'log memuat kata sandi');
        $this->assertFalse($this->service->isInstalled(), 'gagal tidak menulis kunci');

        $this->dbFactory = null;
        $this->assertSame(200, $this->install()->status());
        $this->assertTrue($this->service->isInstalled());
    }

    public function testPreflightFailureBlocksInstallWithIndonesianMessage(): void
    {
        $checker = new PreflightChecker($this->base, $this->base . '/migrations', 80200, static fn (string $e): bool => $e !== 'fileinfo');
        $checks = $checker->run();
        $this->assertFalse(PreflightChecker::allPassed($checks));
        $messages = implode("\n", array_column($checks, 'message'));
        $this->assertContains('PHP 8.3 atau lebih baru', $messages);
        $this->assertContains('Ekstensi fileinfo belum aktif', $messages);

        unlink($this->base . '/.env');
        $r = $this->install();
        $this->assertSame(422, $r->status());
        $this->assertContains('Berkas .env belum ada', $r->body());
        $this->assertFalse(is_file($this->base . '/.env'), 'penginstal tidak membuat .env');
        $this->assertFalse($this->service->isInstalled());
        $this->assertSame(0, $this->tableCount('permissions', true));
    }

    public function testRootHtaccessBlocksEnvAppStorageVendor(): void
    {
        $path = dirname(__DIR__, 3) . '/.htaccess';
        $this->assertTrue(is_file($path), '.htaccess root ada');
        $content = (string) file_get_contents($path);

        $deny = [];
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            if (preg_match('/^\s*RewriteRule\s+(\S+)\s+-\s+\[([^\]]*)\]/', $line, $m) === 1 && str_contains($m[2], 'F')) {
                $deny[] = '~' . str_replace('~', '\~', $m[1]) . '~' . (str_contains($m[2], 'NC') ? 'i' : '');
            }
        }
        $this->assertTrue($deny !== [], 'ada aturan blokir');
        $blocked = static function (string $path) use ($deny): bool {
            foreach ($deny as $re) {
                if (preg_match($re, $path) === 1) {
                    return true;
                }
            }
            return false;
        };
        $mustBlock = [
            '.env', '.env.example', '.git/config', 'app/Core/Router.php', 'bootstrap/app.php', 'config/database.php',
            'database/migrations/0001_foundation_schema.sql', 'routes/web.php', 'storage/installed.lock', 'storage/logs/app.log',
            'tests/run.php', 'scripts/migrate.php', 'resources/views/install/index.php', 'docs/deployment/cpanel.md', 'vendor/autoload.php',
            'node_modules/x/index.js', 'README.md', 'PRD.md', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
            'phpunit.xml', 'phpstan.neon', '.php-cs-fixer.php', 'APP/Core/Router.php', 'public/../.env',
        ];
        foreach ($mustBlock as $p) {
            $this->assertTrue($blocked($p), "harus diblokir: $p");
        }
        foreach (['assets/css/app.css', 'install', 'masuk', 'manifest.webmanifest', '.well-known/security.txt', 'favicon.ico'] as $p) {
            $this->assertFalse($blocked($p), "tidak boleh diblokir: $p");
        }
        $this->assertContains('RewriteRule ^ public/index.php [L]', $content, 'sisanya ke front controller');
        $this->assertContains('Options -Indexes', $content);
        $this->assertContains('<IfModule !mod_rewrite.c>', $content, 'gagal tertutup tanpa mod_rewrite');
        $this->assertTrue(strpos($content, '[F') < strpos($content, 'public/index.php [L]'), 'blokir mendahului rewrite');
    }

    public function testInstallerCodeIsTenantScopeClean(): void
    {
        $tables = TenantScopeScanner::tenantTables((string) file_get_contents(dirname(__DIR__, 2) . '/Support/schema.sqlite.sql'));
        foreach (glob(dirname(__DIR__, 3) . '/app/Modules/Install/*.php') ?: [] as $file) {
            $found = TenantScopeScanner::violations((string) file_get_contents($file), $tables);
            $this->assertSame([], $found, basename($file) . ': ' . implode(' | ', $found));
        }
    }

    private function tableCount(string $table, bool $maybeMissing = false): int
    {
        try {
            return (int) $this->db->fetchOne("SELECT COUNT(*) AS n FROM $table")['n'];
        } catch (\Throwable $e) {
            if ($maybeMissing) {
                return 0;
            }
            throw $e;
        }
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
