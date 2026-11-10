<?php

declare(strict_types=1);

use App\Core\Middleware\AccessGateMiddleware;
use App\Core\Middleware\AuthMiddleware;
use App\Core\Router;
use App\Modules\Auth\Controllers\HomeController;
use App\Modules\Auth\Controllers\LoginController;
use App\Modules\Auth\Controllers\PasswordResetController;
use App\Modules\Auth\Controllers\RegisterController;

return static function (Router $router): void {
    $router->get('/daftar', [RegisterController::class, 'showForm']);
    $router->post('/daftar', [RegisterController::class, 'submit']);
    $router->get('/daftar/cek-email', [RegisterController::class, 'sent']);
    $router->get('/verifikasi-email', [RegisterController::class, 'resendForm']);
    $router->post('/verifikasi-email/kirim-ulang', [RegisterController::class, 'resend']);
    $router->get('/verifikasi-email/{token}', [RegisterController::class, 'verifyLink']);

    $router->get('/masuk', [LoginController::class, 'showForm']);
    $router->post('/masuk', [LoginController::class, 'submit']);
    $router->post('/keluar', [LoginController::class, 'logout'], [AuthMiddleware::class]);
    $router->get('/lupa-password', [PasswordResetController::class, 'showRequestForm']);
    $router->post('/lupa-password', [PasswordResetController::class, 'submitRequest']);
    $router->get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm']);
    $router->post('/reset-password/{token}', [PasswordResetController::class, 'submitReset']);

    $router->get('/beranda', [HomeController::class, 'home'], [AuthMiddleware::class, AccessGateMiddleware::class]);
    $router->get('/demo-berakhir', [HomeController::class, 'demoEnded'], [AuthMiddleware::class]);
    $router->get('/bantuan', [HomeController::class, 'support']);
};
