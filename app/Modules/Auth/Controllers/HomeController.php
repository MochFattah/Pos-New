<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\Authorization\AuthUser;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security\Csrf;
use App\Core\Security\Session;
use App\Core\View;
use App\Modules\Tenants\DemoService;

/** Halaman minimal pasca-login: beranda, demo berakhir, dan bantuan. */
final class HomeController
{
    public function __construct(private readonly DemoService $demo, private readonly string $supportEmail = '')
    {
    }

    public function home(Request $request): Response
    {
        $user = $request->attribute('user');
        return Response::html(View::render('auth/beranda', [
            'title' => 'Beranda',
            'name' => $user instanceof AuthUser ? $user->name : '',
            'csrf' => $this->csrf($request),
        ]));
    }

    public function demoEnded(Request $request): Response
    {
        $user = $request->attribute('user');
        if ($user instanceof AuthUser && $this->demo->isActive($user->tenantId)) {
            return Response::redirect('/beranda');
        }
        return Response::html(View::render('auth/demo-berakhir', [
            'title' => 'Masa demo berakhir',
            'csrf' => $this->csrf($request),
        ]), 403);
    }

    public function support(Request $request): Response
    {
        return Response::html(View::render('auth/bantuan', [
            'title' => 'Bantuan',
            'supportEmail' => $this->supportEmail,
        ]));
    }

    private function csrf(Request $request): string
    {
        $session = $request->attribute('session');
        return $session instanceof Session ? Csrf::token($session) : '';
    }
}
