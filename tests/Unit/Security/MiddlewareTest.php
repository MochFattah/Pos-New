<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Core\Middleware\CsrfMiddleware;
use App\Core\Middleware\SecurityHeadersMiddleware;
use App\Core\Middleware\SessionMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Security\ArraySessionStorage;
use App\Core\Security\Csrf;
use App\Core\Security\Session;
use Tests\Support\TestCase;

final class MiddlewareTest extends TestCase
{
    private int $now = 5000;

    private function ok(Request $r): Response
    {
        return Response::html('ok');
    }

    private function session(): Session
    {
        $s = new Session(new ArraySessionStorage(), fn (): int => $this->now);
        $s->start();
        return $s;
    }

    public function testPostWithoutCsrfReturns419(): void
    {
        $mw = new CsrfMiddleware();
        $s = $this->session();
        $req = Request::create('POST', '/x', ['a' => 1])->withAttribute('session', $s);
        $res = $mw->handle($req, fn (Request $r) => $this->ok($r));
        $this->assertSame(419, $res->status());
        $this->assertContains('Sesi formulir kedaluwarsa', $res->body());

        $bad = Request::create('DELETE', '/x', ['_csrf' => 'salah'])->withAttribute('session', $s);
        $this->assertSame(419, $mw->handle($bad, fn (Request $r) => $this->ok($r))->status());
    }

    public function testCsrfAcceptsFieldHeaderAndSafeMethods(): void
    {
        $mw = new CsrfMiddleware();
        $s = $this->session();
        $t = Csrf::token($s);
        $next = fn (Request $r) => $this->ok($r);
        $f = Request::create('POST', '/x', ['_csrf' => $t])->withAttribute('session', $s);
        $this->assertSame(200, $mw->handle($f, $next)->status());
        $h = Request::create('PATCH', '/x', [], ['X-CSRF-Token' => $t])->withAttribute('session', $s);
        $this->assertSame(200, $mw->handle($h, $next)->status());
        foreach (['GET', 'HEAD', 'OPTIONS'] as $m) {
            $this->assertSame(200, $mw->handle(Request::create($m, '/x')->withAttribute('session', $s), $next)->status());
        }
    }

    public function testHeadersContainCsp(): void
    {
        $mw = new SecurityHeadersMiddleware();
        $res = $mw->handle(Request::create('GET', '/'), fn (Request $r) => $this->ok($r));
        $this->assertContains("default-src 'self'", (string) $res->header('Content-Security-Policy'));
        $this->assertContains("frame-ancestors 'none'", (string) $res->header('Content-Security-Policy'));
        $this->assertSame('DENY', $res->header('X-Frame-Options'));
        $this->assertSame('nosniff', $res->header('X-Content-Type-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $res->header('Referrer-Policy'));
        $this->assertSame('camera=(self), microphone=(), geolocation=()', $res->header('Permissions-Policy'));
        $this->assertSame(null, $res->header('Strict-Transport-Security'));

        $https = $mw->handle(Request::create('GET', '/')->withAttribute('https', true), fn (Request $r) => $this->ok($r));
        $this->assertContains('max-age=', (string) $https->header('Strict-Transport-Security'));
    }

    public function testSessionMiddlewareAttachesSessionAndTouches(): void
    {
        $st = new ArraySessionStorage();
        $mw = new SessionMiddleware(fn () => new Session($st, fn (): int => $this->now), 100, fn (): int => $this->now);
        $seen = null;
        $mw->handle(Request::create('GET', '/'), function (Request $r) use (&$seen) {
            $seen = $r->attribute('session');
            $seen->set('uid', 3);
            return $this->ok($r);
        });
        $this->assertTrue($seen instanceof Session);
        $this->assertSame($this->now, $seen->get('_last_activity'));

        $this->now += 50;
        $mw->handle(Request::create('GET', '/'), function (Request $r) use (&$seen) {
            $seen = $r->attribute('session');
            return $this->ok($r);
        });
        $this->assertSame(3, $seen->get('uid'));

        $this->now += 101;
        $mw->handle(Request::create('GET', '/'), function (Request $r) use (&$seen) {
            $seen = $r->attribute('session');
            return $this->ok($r);
        });
        $this->assertSame(null, $seen->get('uid'));
    }

    public function testRouterAppliesGlobalMiddlewareBeforeRouteMiddleware(): void
    {
        $router = new Router();
        $order = [];
        $mk = function (string $n) use (&$order) {
            return new class ($n, $order) implements \App\Core\Middleware\MiddlewareInterface {
                public function __construct(private string $n, private array &$order)
                {
                }

                public function handle(Request $request, callable $next): Response
                {
                    $this->order[] = $this->n;
                    return $next($request);
                }
            };
        };
        $router->addGlobalMiddleware($mk('g1'));
        $router->addGlobalMiddleware($mk('g2'));
        $router->get('/a', function () use (&$order) {
            $order[] = 'h';
            return Response::html('x');
        }, [$mk('r')]);
        $router->dispatch(Request::create('GET', '/a'));
        $this->assertSame(['g1', 'g2', 'r', 'h'], $order);
    }
}
