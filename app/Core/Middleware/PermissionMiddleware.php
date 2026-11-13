<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Authorization\AuthUser;
use App\Core\Authorization\PolicyService;
use App\Core\Request;
use App\Core\Response;
use LogicException;

/**
 * Mewajibkan satu permission pada rute. Dipasang setelah AuthMiddleware (butuh attribute 'user').
 * Rute dideklarasikan sebelum layanan ada, maka PolicyService diambil malas dari penyedia
 * yang didaftarkan perakit aplikasi lewat provide().
 */
final class PermissionMiddleware implements MiddlewareInterface
{
    /** @var (callable(): PolicyService)|null */
    private static $provider = null;

    private function __construct(private readonly string $permission)
    {
    }

    public static function requires(string $permission): MiddlewareInterface
    {
        return new self($permission);
    }

    /** @param (callable(): PolicyService)|null $provider null melepas penyedia (dipakai tes) */
    public static function provide(?callable $provider): void
    {
        self::$provider = $provider;
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $request->attribute('user');
        if (!$user instanceof AuthUser) {
            return Response::redirect('/masuk');
        }
        if (self::$provider === null) {
            throw new LogicException('PolicyService belum didaftarkan untuk PermissionMiddleware.');
        }
        $policy = (self::$provider)();
        $policy->authorize($user, $this->permission);
        return $next($request);
    }
}
