<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Request;
use App\Core\Security\Csrf;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Platform\Services\PlatformAuthService;
use Tests\Support\PlatformHarness;
use Tests\Support\TenantScopeScanner;

final class PlatformAuthTest extends PlatformHarness
{
    private const TENANT_PROTECTED = ['/beranda', '/cabang', '/pengguna', '/role', '/pengaturan', '/pengaturan/logo', '/demo-berakhir'];

    public function testTenantSessionCannotAccessPlatformRoutes(): void
    {
        $this->activeOwner();
        $this->assertSame(302, $this->login()->status());
        $this->assertSame(200, $this->send('GET', '/beranda')->status());

        $dash = $this->send('GET', '/platform');
        $this->assertSame(302, $dash->status());
        $this->assertSame('/platform/masuk', $dash->header('Location'));

        $logout = $this->send('POST', '/platform/keluar');
        $this->assertSame('/platform/masuk', $logout->header('Location'));
        $this->assertSame(0, $this->count("SELECT COUNT(*) AS n FROM audit_logs WHERE action = 'platform.logout'"));
        $this->assertSame(200, $this->send('GET', '/beranda')->status(), 'sesi tenant tidak ikut terhapus oleh rute platform');
    }

    public function testPlatformSessionCannotAccessTenantRoutes(): void
    {
        $this->activeOwner('budi@example.com');
        $this->makePlatformAdmin();
        $this->assertSame(302, $this->platformLogin()->status());
        $this->assertSame(200, $this->send('GET', '/platform')->status());
        foreach (self::TENANT_PROTECTED as $path) {
            $r = $this->send('GET', $path);
            $this->assertSame(302, $r->status(), $path);
            $this->assertSame('/masuk', $r->header('Location'), $path);
        }
        $this->assertSame('/masuk', $this->send('POST', '/keluar')->header('Location'));
        $this->assertSame(200, $this->send('GET', '/platform')->status(), 'logout tenant tanpa sesi tenant tidak menyentuh sesi platform');
    }

    public function testPlatformLoginIsRateLimited(): void
    {
        $this->makePlatformAdmin();
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(422, $this->platformLogin('root@example.com', 'salah-total')->status());
        }
        $locked = $this->platformLogin();
        $this->assertSame(429, $locked->status());
        $this->assertSame(null, $this->session->get(PlatformAuthService::SESSION_USER));
        $this->assertSame(PlatformAuthService::LOCKED, $this->platform->attempt('root@example.com', self::PLATFORM_PASSWORD, '127.0.0.1', $this->session));
        // IP lain (per email+IP) tetap boleh; setelah jendela lewat, boleh lagi.
        $this->now += 901;
        $this->assertSame(302, $this->platformLogin()->status());
    }

    public function testPlatformLimiterNamespaceIsSeparateFromTenantLimiter(): void
    {
        $this->activeOwner('sama@example.com');
        $this->makePlatformAdmin('sama@example.com');
        for ($i = 0; $i < 5; $i++) {
            $this->login('sama@example.com', 'salah-total');
        }
        $this->assertSame(429, $this->login('sama@example.com')->status());
        $this->assertSame(302, $this->platformLogin('sama@example.com')->status(), 'gagal login tenant tidak mengunci jalur platform');
        $this->session->destroy();
        $this->session->start();
        for ($i = 0; $i < 5; $i++) {
            $this->platformLogin('sama@example.com', 'salah-total');
        }
        $this->assertSame(429, $this->platformLogin('sama@example.com')->status());
    }

    public function testPlatformAggregateIpLimiterStopsSpraying(): void
    {
        $this->platformIpMax = 3;
        $this->setUp();
        $this->makePlatformAdmin();
        foreach (['a', 'b', 'c'] as $i) {
            $this->platformLogin($i . '@example.com', 'salah-total');
        }
        $this->assertSame(429, $this->platformLogin()->status());
    }

    public function testPlatformLoginIsAudited(): void
    {
        $id = $this->makePlatformAdmin();
        $this->platformLogin('root@example.com', 'salah-total');
        $this->platformLogin('hantu@example.com', 'salah-total');
        $this->assertSame(302, $this->platformLogin()->status());
        $this->send('POST', '/platform/keluar');

        $rows = $this->db->query("SELECT * FROM audit_logs WHERE action LIKE 'platform.%' AND action <> 'platform.admin_created' ORDER BY id");
        $this->assertSame(['platform.login_failed', 'platform.login_failed', 'platform.login', 'platform.logout'], array_column($rows, 'action'));
        foreach ($rows as $row) {
            $this->assertSame(null, $row['tenant_id']);
        }
        $this->assertSame('platform_user', $rows[0]['actor_type']);
        $this->assertSame($id, (int) $rows[0]['actor_id']);
        $this->assertSame('system', $rows[1]['actor_type']);
        $this->assertSame(null, $rows[1]['actor_id']);
        $this->assertSame('platform_user', $rows[2]['actor_type']);
        $this->assertSame($id, (int) $rows[2]['actor_id']);
        $this->assertSame('platform_user', $rows[3]['actor_type']);
        $this->assertSame($id, (int) $rows[3]['actor_id']);
    }

    public function testAuditHasNoPasswordOrHash(): void
    {
        $this->makePlatformAdmin();
        $this->platformLogin('root@example.com', 'salah-total-xyz');
        $this->platformLogin();
        $dump = json_encode($this->db->query('SELECT * FROM audit_logs'), JSON_THROW_ON_ERROR);
        foreach (['salah-total-xyz', self::PLATFORM_PASSWORD, '$2y$'] as $secret) {
            $this->assertFalse(str_contains($dump, $secret), $secret);
        }
    }

    public function testWrongPasswordAndUnknownEmailGiveIdenticalResult(): void
    {
        $this->makePlatformAdmin();
        $wrong = $this->platformLogin('root@example.com', 'salah-total');
        $unknown = $this->platformLogin('tidak-ada@example.com', 'salah-total');
        $this->assertSame(422, $wrong->status());
        $this->assertSame($wrong->status(), $unknown->status());
        $strip = static fn (string $html): string => (string) preg_replace('/(name="_csrf" value=")[^"]*|(name="email" [^>]*value=")[^"]*/', '$1$2', $html);
        $this->assertSame($strip($wrong->body()), $strip($unknown->body()));
        $this->assertContains('Email atau kata sandi salah.', $wrong->body());
        $this->assertSame(PlatformAuthService::FAILED, $this->platform->attempt('root@example.com', 'x', '9.9.9.9', $this->session));
        $this->assertSame(PlatformAuthService::FAILED, $this->platform->attempt('nope@example.com', 'x', '9.9.9.9', $this->session));
    }

    public function testOverlongPasswordNeverAuthenticates(): void
    {
        $this->makePlatformAdmin();
        $this->assertSame(PlatformAuthService::FAILED, $this->platform->attempt('root@example.com', self::PLATFORM_PASSWORD . str_repeat('x', 80), '9.9.9.9', $this->session));
    }

    public function testSuccessfulLoginRegeneratesSessionIdAndRotatesCsrf(): void
    {
        $this->makePlatformAdmin();
        $this->session->start();
        $before = $this->storage->id();
        $token = Csrf::token($this->session);
        $r = $this->platformLogin(' Root@Example.COM ');
        $this->assertSame(302, $r->status());
        $this->assertSame('/platform', $r->header('Location'));
        $this->assertTrue($before !== $this->storage->id());
        $this->assertTrue($token !== Csrf::token($this->session));
        $page = $this->send('GET', '/platform');
        $this->assertSame(200, $page->status());
        $this->assertContains('Beranda platform', $page->body());
        $this->assertContains('Admin Platform', $page->body());
    }

    public function testInactivePlatformUserIsBlockedAtLoginAndOnEveryRequest(): void
    {
        $id = $this->makePlatformAdmin();
        $this->makePlatformAdmin('mati@example.com', self::PLATFORM_PASSWORD, 'Mati');
        $this->db->execute("UPDATE platform_users SET status = 'inactive' WHERE email = ?", ['mati@example.com']);
        $r = $this->platformLogin('mati@example.com');
        $this->assertSame(422, $r->status());
        $this->assertContains('Email atau kata sandi salah.', $r->body());
        $this->assertSame(null, $this->session->get(PlatformAuthService::SESSION_USER));

        $this->assertSame(302, $this->platformLogin()->status());
        $this->assertSame(200, $this->send('GET', '/platform')->status());
        $this->db->execute("UPDATE platform_users SET status = 'inactive' WHERE id = ?", [$id]);
        $this->assertSame('/platform/masuk', $this->send('GET', '/platform')->header('Location'));
    }

    public function testPasswordChangeRevokesPlatformSession(): void
    {
        $id = $this->makePlatformAdmin();
        $this->platformLogin();
        $this->assertSame(200, $this->send('GET', '/platform')->status());
        $this->db->execute('UPDATE platform_users SET password_hash = ? WHERE id = ?', [$this->hasher->hash('sandi-lain-12345'), $id]);
        $this->assertSame('/platform/masuk', $this->send('GET', '/platform')->header('Location'));
    }

    public function testPlatformSessionIdlesOutAfterEightHours(): void
    {
        $this->makePlatformAdmin();
        $this->platformLogin();
        $this->now += 28801;
        $this->assertSame('/platform/masuk', $this->send('GET', '/platform')->header('Location'));
    }

    public function testPlatformLoginClearsTenantKeysAndTenantLoginClearsPlatformKeys(): void
    {
        $this->activeOwner();
        $this->makePlatformAdmin();
        $this->login();
        $this->assertTrue($this->session->get(AuthService::SESSION_USER) !== null);
        $this->platformLogin();
        foreach ([AuthService::SESSION_USER, AuthService::SESSION_TENANT, AuthService::SESSION_FINGERPRINT] as $k) {
            $this->assertSame(null, $this->session->get($k), $k);
        }
        $this->assertTrue($this->session->get(PlatformAuthService::SESSION_USER) !== null);

        $this->login();
        $this->assertSame(null, $this->session->get(PlatformAuthService::SESSION_USER));
        $this->assertSame(null, $this->session->get(PlatformAuthService::SESSION_FINGERPRINT));
        $this->assertTrue($this->session->get(AuthService::SESSION_USER) !== null);
        $this->assertSame('/platform/masuk', $this->send('GET', '/platform')->header('Location'));
    }

    public function testFailedLoginOfOtherRealmDoesNotTouchExistingSession(): void
    {
        $this->activeOwner();
        $this->makePlatformAdmin();
        $this->platformLogin();
        $this->login('budi@example.com', 'salah-total');
        $this->assertSame(200, $this->send('GET', '/platform')->status());
    }

    public function testLogoutDestroysPlatformSession(): void
    {
        $this->makePlatformAdmin();
        $this->platformLogin();
        $r = $this->send('POST', '/platform/keluar');
        $this->assertSame('/platform/masuk', $r->header('Location'));
        $this->assertSame(null, $this->session->get(PlatformAuthService::SESSION_USER));
        $this->assertSame('/platform/masuk', $this->send('GET', '/platform')->header('Location'));
    }

    public function testPostsRequireCsrf(): void
    {
        $this->makePlatformAdmin();
        $this->assertSame(419, $this->send('POST', '/platform/masuk', ['email' => 'root@example.com', 'password' => self::PLATFORM_PASSWORD], false)->status());
        $this->assertSame(null, $this->session->get(PlatformAuthService::SESSION_USER));
        $this->platformLogin();
        $this->assertSame(419, $this->send('POST', '/platform/keluar', [], false)->status());
        $this->assertSame(200, $this->send('GET', '/platform')->status());
    }

    public function testLoginFormValidationAndAlreadyLoggedInRedirect(): void
    {
        $this->makePlatformAdmin();
        $r = $this->send('GET', '/platform/masuk');
        $this->assertSame(200, $r->status());
        $this->assertContains('lang="id"', $r->body());
        $this->assertContains('for="email"', $r->body());
        $this->assertContains('for="password"', $r->body());
        $this->assertSame(422, $this->send('POST', '/platform/masuk', ['email' => '', 'password' => ''])->status());
        $this->platformLogin();
        $this->assertSame('/platform', $this->send('GET', '/platform/masuk')->header('Location'));
    }

    public function testDashboardHasNoTenantOrBillingContent(): void
    {
        $this->activeOwner();
        $this->makePlatformAdmin();
        $this->platformLogin();
        $body = $this->send('GET', '/platform')->body();
        $this->assertFalse(str_contains($body, 'Toko budi@example.com'));
        foreach (['Midtrans', 'Paket', 'Langganan', 'Tagihan'] as $word) {
            $this->assertFalse(stripos($body, $word) !== false, $word);
        }
    }

    public function testPlatformPagesAreNotCachedAndAllUnderPlatformPrefix(): void
    {
        $this->makePlatformAdmin();
        $this->assertSame('no-store', $this->send('GET', '/platform/masuk')->header('Cache-Control'));
        $this->platformLogin();
        $this->assertSame('no-store', $this->send('GET', '/platform')->header('Cache-Control'));
        $src = (string) file_get_contents(dirname(__DIR__, 3) . '/routes/platform.php');
        preg_match_all("#->(?:get|post)\('([^']+)'#", $src, $m);
        $this->assertTrue(count($m[1]) >= 4);
        foreach ($m[1] as $path) {
            $this->assertTrue($path === '/platform' || str_starts_with($path, '/platform/'), $path);
        }
    }

    public function testPlatformUsersTableIsNotTenantScopedAndNewCodeIsScannerClean(): void
    {
        $root = dirname(__DIR__, 3);
        $tables = TenantScopeScanner::tenantTables((string) file_get_contents($root . '/tests/Support/schema.sqlite.sql'));
        $this->assertFalse(in_array('platform_users', $tables, true));
        $sql = 'SELECT * FROM platform_users WHERE email = ?';
        $this->assertSame([], TenantScopeScanner::violations("<?php \$db->query('$sql', []);", $tables));
        foreach (glob($root . '/app/Modules/Platform/*/*.php') as $file) {
            $this->assertSame([], TenantScopeScanner::violations((string) file_get_contents($file), $tables), $file);
        }
        $this->assertSame(0, $this->count("SELECT COUNT(*) AS n FROM pragma_table_info('platform_users') WHERE name = 'tenant_id'"));
    }
}
