<?php

declare(strict_types=1);

use App\Core\Router;
use App\Modules\Install\InstallController;

/**
 * Hanya didaftarkan oleh bootstrap selama penginstal aktif (belum terpasang dan INSTALL_TOKEN terisi).
 * Setelah itu rute ini tidak ada sama sekali, sehingga /install* sama dengan rute tak dikenal (404).
 */
return static function (Router $router): void {
    $router->get('/install', [InstallController::class, 'showForm']);
    $router->post('/install', [InstallController::class, 'submit']);
};
