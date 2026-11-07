<?php

declare(strict_types=1);

namespace Tests\Support\Fixtures;

use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

final class TraceMiddlewareB implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        \Tests\Unit\Core\RouterTest::$trace[] = 'B>';
        $res = $next($request);
        \Tests\Unit\Core\RouterTest::$trace[] = '<B';
        return $res;
    }
}
