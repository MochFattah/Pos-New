<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Security\Session;
use App\Modules\Platform\Services\PlatformAuthService;

/**
 * Mewajibkan sesi Super Admin yang valid (kunci `platform_user`); sesi tenant tidak lolos di sini,
 * dan sesi platform tidak lolos AuthMiddleware tenant. Mengisi attribute('platform_user').
 */
final class PlatformAuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly PlatformAuthService $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $session = $request->attribute('session');
        $user = $session instanceof Session ? $this->auth->userFromSession($session) : null;
        if ($user === null) {
            if ($session instanceof Session) {
                PlatformAuthService::forgetKeys($session);
            }
            return Response::redirect('/platform/masuk');
        }
        return $next($request->withAttribute('platform_user', $user));
    }
}
