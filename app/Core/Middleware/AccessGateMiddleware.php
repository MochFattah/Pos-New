<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Authorization\AuthUser;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Tenants\DemoService;

/**
 * Memblokir rute operasional bila tenant tidak punya jendela demo yang berlaku
 * (belum ada konsep langganan di fondasi). Data tidak dihapus. Dipasang setelah AuthMiddleware.
 */
final class AccessGateMiddleware implements MiddlewareInterface
{
    public const EXEMPT_PATHS = ['/keluar', '/bantuan', '/demo-berakhir'];

    public function __construct(private readonly DemoService $demo)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (in_array(rtrim($request->rawPath(), '/'), self::EXEMPT_PATHS, true)) {
            return $next($request);
        }
        $user = $request->attribute('user');
        if (!$user instanceof AuthUser) {
            return Response::redirect('/masuk');
        }
        if (!$this->demo->isActive($user->tenantId)) {
            return Response::redirect('/demo-berakhir');
        }
        return $next($request);
    }
}
