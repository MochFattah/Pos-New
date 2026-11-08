<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Core\Application;
use App\Core\Middleware\SecurityHeadersMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Security\SecurityHeaders;
use Tests\Support\TestCase;

final class SecurityHeadersTest extends TestCase
{
    private function app(bool $force = false): Application
    {
        $router = new Router();
        $router->get('/boom', function (): Response {
            throw new \RuntimeException('x');
        });
        return new Application($router, sys_get_temp_dir() . '/pos-test-' . bin2hex(random_bytes(4)) . '.log', false, $force);
    }

    private function assertHeaders(Response $r): void
    {
        $this->assertContains("default-src 'self'", (string) $r->header('Content-Security-Policy'));
        $this->assertSame('DENY', $r->header('X-Frame-Options'));
        $this->assertSame('nosniff', $r->header('X-Content-Type-Options'));
    }

    public function testErrorResponsesCarrySecurityHeaders(): void
    {
        $app = $this->app();
        $this->assertHeaders($app->handle(Request::create('GET', '/nope')));
        $this->assertHeaders($app->handle(Request::create('POST', '/boom')));
        $r = $app->handle(Request::create('GET', '/boom'));
        $this->assertSame(500, $r->status());
        $this->assertHeaders($r);
    }

    public function testHstsFollowsForceFlagOrRequestHttps(): void
    {
        $this->assertSame(null, $this->app()->handle(Request::create('GET', '/nope'))->header('Strict-Transport-Security'));
        $this->assertTrue($this->app(true)->handle(Request::create('GET', '/nope'))->header('Strict-Transport-Security') !== null);
        $https = Request::create('GET', '/nope')->withAttribute('https', true);
        $this->assertTrue($this->app()->handle($https)->header('Strict-Transport-Security') !== null);
    }

    public function testForwardedProtoAloneNeverEnablesHsts(): void
    {
        $req = Request::create('GET', '/nope', [], ['X-Forwarded-Proto' => 'https']);
        $this->assertSame(null, $this->app()->handle($req)->header('Strict-Transport-Security'));
        $mw = new SecurityHeadersMiddleware();
        $res = $mw->handle($req, fn () => Response::html('ok'));
        $this->assertSame(null, $res->header('Strict-Transport-Security'));
    }

    public function testMiddlewareForceFlagEnablesHstsOnPlainHttp(): void
    {
        $res = (new SecurityHeadersMiddleware(true))->handle(Request::create('GET', '/'), fn () => Response::html('ok'));
        $this->assertTrue($res->header('Strict-Transport-Security') !== null);
        $this->assertSame(null, SecurityHeaders::apply(Response::html(''), false)->header('Strict-Transport-Security'));
    }
}
