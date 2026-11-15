<?php

declare(strict_types=1);

namespace App\Modules\Platform\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Security\Csrf;
use App\Core\Security\Session;
use App\Core\View;

/** Beranda Super Admin minimal: tanpa data tenant, paket, atau Midtrans (di luar fondasi). */
final class PlatformDashboardController
{
    public function index(Request $request): Response
    {
        $user = $request->attribute('platform_user');
        if (!is_array($user)) {
            throw new \LogicException(static::class . ' membutuhkan PlatformAuthMiddleware.');
        }
        $session = $request->attribute('session');
        return Response::html(View::render('platform/beranda', [
            'title' => 'Beranda platform',
            'name' => (string) ($user['name'] ?? ''),
            'csrf' => $session instanceof Session ? Csrf::token($session) : '',
        ]))->sensitive();
    }
}
