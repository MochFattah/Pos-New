<?php

declare(strict_types=1);

use App\Core\Middleware\AccessGateMiddleware;
use App\Core\Middleware\AuthMiddleware;
use App\Core\Middleware\PermissionMiddleware;
use App\Core\Router;
use App\Modules\Auth\Controllers\HomeController;
use App\Modules\Auth\Controllers\LoginController;
use App\Modules\Auth\Controllers\PasswordResetController;
use App\Modules\Auth\Controllers\RegisterController;
use App\Modules\Branches\Controllers\BranchController;

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

    $guard = static fn (string $permission): array => [AuthMiddleware::class, AccessGateMiddleware::class, PermissionMiddleware::requires($permission)];
    $router->get('/cabang', [BranchController::class, 'index'], $guard('branches.view'));
    $router->get('/cabang/baru', [BranchController::class, 'createForm'], $guard('branches.manage'));
    $router->post('/cabang', [BranchController::class, 'store'], $guard('branches.manage'));
    $router->get('/cabang/{id}/ubah', [BranchController::class, 'editForm'], $guard('branches.manage'));
    $router->post('/cabang/{id}', [BranchController::class, 'update'], $guard('branches.manage'));
    $router->post('/cabang/{id}/nonaktifkan', [BranchController::class, 'deactivate'], $guard('branches.manage'));
    $router->post('/cabang/{id}/aktifkan', [BranchController::class, 'activate'], $guard('branches.manage'));

    $router->get('/bantuan', [HomeController::class, 'support']);
};
