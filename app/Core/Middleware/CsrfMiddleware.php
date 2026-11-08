<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Security\Csrf;
use App\Core\Security\Session;

final class CsrfMiddleware implements MiddlewareInterface
{
    private const UNSAFE = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, callable $next): Response
    {
        if (!in_array($request->method(), self::UNSAFE, true)) {
            return $next($request);
        }

        $session = $request->attribute('session');
        $token = $request->input('_csrf') ?? $request->header('X-CSRF-Token');
        if ($session instanceof Session && is_string($token) && Csrf::isValid($session, $token)) {
            return $next($request);
        }

        return Response::html('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.', 419);
    }
}
