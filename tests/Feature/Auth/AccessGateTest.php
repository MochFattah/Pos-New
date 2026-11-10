<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Middleware\AccessGateMiddleware;
use App\Core\Request;
use App\Core\Response;
use Tests\Support\AuthHarness;

final class AccessGateTest extends AuthHarness
{
    public function testExpiredDemoBlocksOperationalRoutesButKeepsData(): void
    {
        $r = $this->activeOwner();
        $this->login();
        $this->assertSame(200, $this->send('GET', '/beranda')->status());

        $this->advanceKeepingSessionAlive(24 * 3600);
        $blocked = $this->send('GET', '/beranda');
        $this->assertSame(302, $blocked->status());
        $this->assertSame('/demo-berakhir', $blocked->header('Location'));

        // Data tetap utuh.
        $this->assertSame(1, $this->count('SELECT COUNT(*) AS n FROM tenants WHERE id = ?', [$r['tenantId']]));
        $this->assertSame(1, $this->count('SELECT COUNT(*) AS n FROM users WHERE id = ?', [$r['userId']]));
        $this->assertSame(1, $this->count('SELECT COUNT(*) AS n FROM tenant_demos WHERE tenant_id = ?', [$r['tenantId']]));
        $this->assertSame(1, $this->count('SELECT COUNT(*) AS n FROM branches WHERE tenant_id = ?', [$r['tenantId']]));

        $page = $this->send('GET', '/demo-berakhir');
        $this->assertSame(403, $page->status());
        $this->assertContains('Masa demo berakhir', $page->body());
        $this->assertContains('tidak dihapus', $page->body());
    }

    public function testDemoBoundaryIsHalfOpen(): void
    {
        $this->activeOwner();
        $this->login();
        $this->advanceKeepingSessionAlive(24 * 3600 - 1);
        $this->assertSame(200, $this->send('GET', '/beranda')->status());
        $this->now += 1;
        $this->assertSame(302, $this->send('GET', '/beranda')->status());
    }

    public function testExtendedDemoRestoresAccess(): void
    {
        $r = $this->activeOwner();
        $this->login();
        $this->advanceKeepingSessionAlive(2 * 24 * 3600);
        $this->assertSame(302, $this->send('GET', '/beranda')->status());
        $this->db->execute(
            "INSERT INTO tenant_demos (tenant_id, starts_at, ends_at, source, created_at) VALUES (?, ?, ?, 'extension', ?)",
            [$r['tenantId'], gmdate('Y-m-d H:i:s', $this->now), gmdate('Y-m-d H:i:s', $this->now + 3600), gmdate('Y-m-d H:i:s', $this->now)]
        );
        $this->assertSame(200, $this->send('GET', '/beranda')->status());
    }

    public function testGateAllowsLogoutAndSupportPage(): void
    {
        $this->activeOwner();
        $this->login();
        $this->advanceKeepingSessionAlive(3 * 24 * 3600);
        $this->assertSame(200, $this->send('GET', '/bantuan')->status());
        $this->assertContains('bantuan@example.test', $this->send('GET', '/bantuan')->body());

        $next = static fn (Request $r): Response => Response::html('lolos');
        $gate = new AccessGateMiddleware(new \App\Modules\Tenants\DemoService($this->db, fn (): int => $this->now));
        foreach (['/keluar', '/bantuan', '/demo-berakhir'] as $path) {
            $user = $this->auth->userFromSession($this->session);
            $response = $gate->handle(Request::create('POST', $path)->withAttribute('user', $user), $next);
            $this->assertSame('lolos', $response->body(), "$path harus lolos gerbang");
        }
        $response = $gate->handle(Request::create('GET', '/beranda')->withAttribute('user', $this->auth->userFromSession($this->session)), $next);
        $this->assertSame(302, $response->status());

        $logout = $this->send('POST', '/keluar');
        $this->assertSame('/masuk', $logout->header('Location'));
        $this->assertSame(null, $this->session->get('auth_user_id'));
    }

    public function testGateWithoutUserRedirectsToLogin(): void
    {
        $gate = new AccessGateMiddleware(new \App\Modules\Tenants\DemoService($this->db));
        $response = $gate->handle(Request::create('GET', '/beranda'), static fn (Request $r): Response => Response::html('lolos'));
        $this->assertSame('/masuk', $response->header('Location'));
    }

    public function testDemoEndedPageRedirectsHomeWhileDemoStillActive(): void
    {
        $this->activeOwner();
        $this->login();
        $this->assertSame('/beranda', $this->send('GET', '/demo-berakhir')->header('Location'));
    }

    public function testDemoEndedPageRequiresLogin(): void
    {
        $this->assertSame('/masuk', $this->send('GET', '/demo-berakhir')->header('Location'));
    }

    public function testTenantWithoutAnyDemoIsBlockedAndOtherTenantIsNot(): void
    {
        $this->activeOwner('a@example.com');
        $this->activeOwner('b@example.com');
        $this->db->execute('DELETE FROM tenant_demos WHERE tenant_id = (SELECT tenant_id FROM users WHERE email = ?)', ['a@example.com']);
        $this->login('a@example.com');
        $this->assertSame('/demo-berakhir', $this->send('GET', '/beranda')->header('Location'));
        $this->send('POST', '/keluar');
        $this->login('b@example.com');
        $this->assertSame(200, $this->send('GET', '/beranda')->status());
    }

    public function testAnonymousVisitorGoesToLoginFromHome(): void
    {
        $this->assertSame('/masuk', $this->send('GET', '/beranda')->header('Location'));
    }
}
