<?php

declare(strict_types=1);

use App\Core\Router;
use App\Modules\Auth\Controllers\RegisterController;

return static function (Router $router): void {
    $router->get('/daftar', [RegisterController::class, 'showForm']);
    $router->post('/daftar', [RegisterController::class, 'submit']);
    $router->get('/daftar/cek-email', [RegisterController::class, 'sent']);
    $router->get('/verifikasi-email', [RegisterController::class, 'resendForm']);
    $router->post('/verifikasi-email/kirim-ulang', [RegisterController::class, 'resend']);
    $router->get('/verifikasi-email/{token}', [RegisterController::class, 'verifyLink']);
};
