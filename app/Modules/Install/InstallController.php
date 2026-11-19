<?php

declare(strict_types=1);

namespace App\Modules\Install;

use App\Core\Exceptions\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security\Csrf;
use App\Core\Security\Session;
use App\Core\View;

final class InstallController
{
    private const GENERIC_FAILURE = 'Pemasangan gagal. Periksa pengaturan database pada berkas .env dan hak akses database, lalu coba lagi. Rincian teknis dicatat di storage/logs/install.log.';

    public function __construct(
        private readonly InstallService $install,
        private readonly InstallAttemptLimiter $limiter,
    ) {
    }

    public function showForm(Request $request): Response
    {
        $this->guard();
        return $this->form($request, null, 200);
    }

    public function submit(Request $request): Response
    {
        $this->guard();
        $ip = $request->ip();
        if ($this->limiter->tooManyAttempts($ip)) {
            return $this->form($request, 'Terlalu banyak percobaan token. Coba lagi dalam beberapa menit.', 429);
        }
        $given = $request->form('token');
        if (!is_string($given) || !$this->install->tokenMatches($given)) {
            $this->limiter->recordFailure($ip);
            return $this->form($request, 'Token pemasangan tidak valid.', 422);
        }

        $result = $this->install->install();
        return match ($result['status']) {
            InstallService::OK => Response::html(View::render('install/done', [
                'title' => 'Pemasangan selesai',
                'applied' => $result['applied'],
                'cardClass' => 'auth-card--wide',
            ]), 200)->sensitive(),
            InstallService::ALREADY => throw new NotFoundException(),
            InstallService::BUSY => $this->form($request, 'Pemasangan sedang berjalan di proses lain. Tunggu sebentar lalu muat ulang halaman ini.', 409),
            InstallService::PREFLIGHT => $this->form($request, 'Pemasangan belum dapat dijalankan karena ada pemeriksaan yang gagal. Perbaiki lalu coba lagi.', 422),
            default => $this->form($request, self::GENERIC_FAILURE, 500),
        };
    }

    /** Setelah terpasang (atau tanpa INSTALL_TOKEN) halaman ini sama dengan rute yang tidak ada. */
    private function guard(): void
    {
        if (!$this->install->isActive()) {
            throw new NotFoundException();
        }
    }

    private function form(Request $request, ?string $error, int $status): Response
    {
        $session = $request->attribute('session');
        return Response::html(View::render('install/index', [
            'title' => 'Pemasangan',
            'csrf' => $session instanceof Session ? Csrf::token($session) : '',
            'checks' => $this->install->preflight(),
            'error' => $error,
            'cardClass' => 'auth-card--wide',
        ]), $status)->sensitive()->withHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
