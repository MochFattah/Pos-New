<?php

declare(strict_types=1);

use App\Core\Middleware\PlatformAuthMiddleware;
use App\Core\Router;
use App\Modules\Platform\Controllers\PlatformAuthController;
use App\Modules\Platform\Controllers\PlatformDashboardController;

/** Semua rute di bawah /platform. Tidak memakai AuthMiddleware/AccessGate tenant. */
return static function (Router $router): void {
    $router->get('/platform/masuk', [PlatformAuthController::class, 'showForm']);
    $router->post('/platform/masuk', [PlatformAuthController::class, 'submit']);
    $router->post('/platform/keluar', [PlatformAuthController::class, 'logout'], [PlatformAuthMiddleware::class]);
    $router->get('/platform', [PlatformDashboardController::class, 'index'], [PlatformAuthMiddleware::class]);
};
