<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Authorization\AuthUser;
use App\Core\Authorization\PolicyService;
use App\Core\Layout\AppShell;
use App\Core\Layout\Navigation;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security\Csrf;
use App\Core\Security\Session;
use App\Modules\Tenants\Repositories\TenantRepository;

/**
 * Menyiapkan atribut 'shell' (AppShell) untuk layouts/app: nama bisnis tenant sesi, nama pengguna,
 * token CSRF untuk tombol keluar, dan menu yang lolos PolicyService. Dipasang setelah AuthMiddleware
 * (dan setelah pemeriksaan izin rute, agar permintaan yang ditolak tidak membayar kueri tambahan).
 */
final class AppShellMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly PolicyService $policy, private readonly TenantRepository $tenants)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $request->attribute('user');
        if (!$user instanceof AuthUser) {
            return $next($request);
        }
        $session = $request->attribute('session');
        $tenant = $this->tenants->findById($user->tenantId);
        $shell = new AppShell(
            (string) ($tenant['name'] ?? ''),
            $user->name,
            $session instanceof Session ? Csrf::token($session) : '',
            Navigation::for($user, $this->policy, $request->rawPath())
        );
        return $next($request->withAttribute('shell', $shell));
    }
}
