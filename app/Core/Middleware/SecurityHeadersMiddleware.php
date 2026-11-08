<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Security\SecurityHeaders;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /** @param bool $forceHttps HSTS selalu aktif (mis. di balik proxy TLS); header X-Forwarded-Proto tidak dipercaya */
    public function __construct(private readonly bool $forceHttps = false)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        return SecurityHeaders::apply($next($request), $this->forceHttps || $request->attribute('https') === true);
    }
}
