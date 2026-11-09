<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Application;
use App\Core\Request;
use App\Core\RequestContext;
use App\Core\Router;
use RuntimeException;
use Tests\Support\TestCase;

final class RequestContextTest extends TestCase
{
    public function testEachBindGeneratesNewWellFormedId(): void
    {
        $ctx = new RequestContext();
        $ctx->bind(Request::create('GET', '/a'));
        $first = $ctx->toArray()['request_id'];
        $ctx->bind(Request::create('GET', '/b'));
        $second = $ctx->toArray()['request_id'];
        $this->assertTrue($first !== $second);
        $this->assertSame(1, preg_match('/^[0-9a-f]{12}$/', $first));
        $this->assertSame(1, preg_match('/^[0-9a-f]{12}$/', $second));
        $this->assertSame('127.0.0.1', $ctx->toArray()['ip']);
    }

    public function testClientSuppliedRequestIdHeaderIsIgnored(): void
    {
        $ctx = new RequestContext();
        $ctx->bind(Request::create('GET', '/a', [], ['X-Request-Id' => 'evil-id']));
        $this->assertTrue($ctx->toArray()['request_id'] !== 'evil-id');
    }

    public function test500PageIdEqualsLoggerContextId(): void
    {
        $ctx = new RequestContext();
        $router = new Router();
        $seen = null;
        $router->get('/boom', function () use ($ctx, &$seen): never {
            $seen = $ctx->toArray()['request_id'];
            throw new RuntimeException('x');
        });
        $log = sys_get_temp_dir() . '/rc-' . bin2hex(random_bytes(4)) . '.log';
        $app = new Application($router, $log, false, false, $ctx);
        $res = $app->handle(Request::create('GET', '/boom'));
        @unlink($log);
        $this->assertSame(500, $res->status());
        $this->assertTrue($seen !== null && str_contains($res->body(), $seen));
    }
}
