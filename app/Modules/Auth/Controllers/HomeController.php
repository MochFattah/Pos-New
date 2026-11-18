<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\Authorization\AuthUser;
use App\Core\Layout\AppShell;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security\Csrf;
use App\Core\Security\Session;
use App\Core\View;
use App\Modules\Auth\Services\AuthService;
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
        $name = $user instanceof AuthUser ? $user->name : '';
        $remaining = $user instanceof AuthUser ? $this->demo->remainingSeconds($user->tenantId) : null;
        $shell = $request->attribute('shell');
        return Response::html(View::render('home/beranda', [
            'title' => 'Beranda',
            'name' => $name,
            'demoRemaining' => $remaining !== null ? self::describeRemaining($remaining) : null,
            'shell' => $shell instanceof AppShell ? $shell : AppShell::minimal($name, $this->csrf($request)),
        ], 'app'));
    }

    /** Halaman awal publik; pengguna yang sudah masuk langsung ke beranda. */
    public function landing(Request $request): Response
    {
        $session = $request->attribute('session');
        if ($session instanceof Session && is_int($session->get(AuthService::SESSION_USER))) {
            return Response::redirect('/beranda');
        }
        return Response::html(View::render('home/landing', ['title' => 'Aplikasi kasir untuk usaha ritel', 'cardClass' => 'auth-card--wide']));
    }

    /** Sisa waktu dalam bahasa sederhana: "3 hari 4 jam", "5 jam 10 menit", atau "kurang dari 1 menit". */
    public static function describeRemaining(int $seconds): string
    {
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        if ($days > 0) {
            return $days . ' hari' . ($hours > 0 ? ' ' . $hours . ' jam' : '');
        }
        if ($hours > 0) {
            return $hours . ' jam' . ($minutes > 0 ? ' ' . $minutes . ' menit' : '');
        }
        return $minutes > 0 ? $minutes . ' menit' : 'kurang dari 1 menit';
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
