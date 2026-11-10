<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Security\Session;
use App\Modules\Auth\Services\AuthService;

/**
 * Mewajibkan sesi masuk yang masih valid dan mengisi attribute('user') dengan AuthUser.
 * Idle 8 jam dijaga SessionMiddleware (global, sebelum ini) yang mengosongkan sesi kedaluwarsa,
 * sehingga di sini sesi yang kedaluwarsa tampil sebagai "tidak masuk".
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $session = $request->attribute('session');
        $user = $session instanceof Session ? $this->auth->userFromSession($session) : null;
        if ($user === null) {
            if ($session instanceof Session) {
                $session->forget(AuthService::SESSION_USER);
                $session->forget(AuthService::SESSION_TENANT);
                $session->forget(AuthService::SESSION_FINGERPRINT);
            }
            return Response::redirect('/masuk');
        }
        return $next($request->withAttribute('user', $user));
    }
}
