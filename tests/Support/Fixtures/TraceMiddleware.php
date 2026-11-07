<?php

declare(strict_types=1);

namespace Tests\Support\Fixtures;

use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

final class TraceMiddleware implements MiddlewareInterface
{
    public function __construct(private string $name = 'A')
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        \Tests\Unit\Core\RouterTest::$trace[] = $this->name . '>';
        $res = $next($request);
        \Tests\Unit\Core\RouterTest::$trace[] = '<' . $this->name;
        return $res;
    }
}
