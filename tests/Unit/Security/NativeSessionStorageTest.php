<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Core\Application;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Middleware\SessionMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Security\Csrf;
use App\Core\Security\NativeSessionStorage;
use App\Core\Security\Session;
use RuntimeException;
use Tests\Support\TestCase;

final class FakeNativeStorage extends NativeSessionStorage
{
    public bool $regenResult = true;
    public int $expired = 0;

    protected function nativeRegenerate(): bool
    {
        return $this->regenResult;
    }

    protected function expireCookie(): void
    {
        $this->expired++;
    }
}

final class NativeSessionStorageTest extends TestCase
{
    public function testOptionsSetGcMaxLifetimeAtLeastIdleSeconds(): void
    {
        $s = new NativeSessionStorage('/tmp/x', false, 28800, false);
        $o = $s->options();
        $this->assertTrue($o['gc_maxlifetime'] >= 28800);
        $this->assertSame(1, $o['use_strict_mode']);
        $this->assertSame('Lax', $o['cookie_samesite']);
        $this->assertSame(1, $o['cookie_httponly']);
    }

    public function testSecureFlagTrueForcesSecureOnPlainHttp(): void
    {
        $o = (new NativeSessionStorage('/tmp/x', true, 100, false))->options();
        $this->assertSame(1, $o['cookie_secure']);
    }

    public function testSecureAutoFollowsRequestHttps(): void
    {
        $this->assertSame(0, (new NativeSessionStorage('/tmp/x', false, 100, false))->options()['cookie_secure']);
        $this->assertSame(1, (new NativeSessionStorage('/tmp/x', false, 100, true))->options()['cookie_secure']);
    }

    public function testRegenerateThrowsWhenNativeFails(): void
    {
        $s = new FakeNativeStorage('/tmp/x', false, 100, false);
        $s->regenResult = false;
        $this->assertThrows(RuntimeException::class, fn () => $s->regenerateId());
        $s->regenResult = true;
        $s->regenerateId();
    }

    public function testDestroyExpiresCookie(): void
    {
        $s = new FakeNativeStorage('/tmp/x', false, 100, false);
        $s->destroy();
        $this->assertSame(1, $s->expired);
    }

    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pos-sess-' . bin2hex(random_bytes(5));
        mkdir($this->dir, 0770, true);
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_id('');
        $_COOKIE = [];
        $_SESSION = [];
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            unlink($f);
        }
        @rmdir($this->dir);
    }

    private function files(): int
    {
        return count(glob($this->dir . '/sess_*') ?: []);
    }

    private function storage(): NativeSessionStorage
    {
        return new NativeSessionStorage($this->dir, false, 100, false);
    }

    public function testOptionsEnableProbabilisticGc(): void
    {
        $o = $this->storage()->options();
        $this->assertSame(1, $o['gc_probability']);
        $this->assertSame(100, $o['gc_divisor']);
    }

    public function testCookielessRequestCreatesNoSessionFile(): void
    {
        $st = $this->storage();
        $st->start();
        $this->assertSame([], $st->all());
        $this->assertSame('', $st->id());
        $st->replace([]);
        $this->assertSame(0, $this->files());
    }

    public function testBogusCookieDoesNotCreateSessionFile(): void
    {
        $_COOKIE['pos_session'] = bin2hex(random_bytes(16));
        $st = $this->storage();
        $st->start();
        $this->assertSame([], $st->all());
        $this->assertSame(0, $this->files());
        $_COOKIE['pos_session'] = '../../etc/passwd';
        $st->start();
        $this->assertSame(0, $this->files());
    }

    public function testFirstWriteCreatesSessionAndCookieResumesIt(): void
    {
        $st = $this->storage();
        $st->start();
        $st->replace(['uid' => 7]);
        $this->assertSame(1, $this->files());
        $id = $st->id();
        $this->assertTrue($id !== '');
        session_write_close();
        $_COOKIE['pos_session'] = $id;
        $again = $this->storage();
        $again->start();
        $this->assertSame(['uid' => 7], $again->all());
        $this->assertSame(1, $this->files());
    }

    /** Router + middleware global seperti bootstrap, dengan penyimpanan sesi asli. */
    private function app(): Application
    {
        $router = new Router();
        $router->addGlobalMiddleware(new SessionMiddleware(fn (): Session => new Session($this->storage()), 100));
        $router->addGlobalMiddleware(new CsrfMiddleware());
        $router->get('/bantuan', fn (): Response => Response::html('bantuan'));
        $router->get('/form', function (Request $r): Response {
            return Response::html(Csrf::token($r->attribute('session')));
        });
        $router->post('/masuk', function (Request $r): Response {
            $s = $r->attribute('session');
            $s->regenerate();
            $s->set('uid', 5);
            return Response::redirect('/beranda');
        });
        return new Application($router, $this->dir . '/app.log');
    }

    public function testCookielessGetOf404AndPublicPageCreatesNoSessionFile(): void
    {
        $app = $this->app();
        $this->assertSame(404, $app->handle(Request::create('GET', '/tidak-ada'))->status());
        $this->assertSame(200, $app->handle(Request::create('GET', '/bantuan'))->status());
        $this->assertSame(419, $app->handle(Request::create('POST', '/masuk'))->status());
        $this->assertSame(0, $this->files());
    }

    public function testFormThenLoginStillWorksWithLazySession(): void
    {
        $app = $this->app();
        $form = $app->handle(Request::create('GET', '/form'));
        $token = $form->body();
        $this->assertSame(1, $this->files());
        $id = session_id();
        session_write_close();
        $_COOKIE['pos_session'] = $id;

        $login = $app->handle(Request::create('POST', '/masuk', ['_csrf' => $token]));
        $this->assertSame(302, $login->status());
        $newId = session_id();
        $this->assertTrue($newId !== $id, 'ID sesi harus diganti saat login');
        session_write_close();
        $_COOKIE['pos_session'] = $newId;

        $st = $this->storage();
        $st->start();
        $this->assertSame(5, $st->all()['uid']);
        $this->assertTrue(is_int($st->all()['_last_activity']));
        $this->assertSame(1, $this->files());
    }
}
