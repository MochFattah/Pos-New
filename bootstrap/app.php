<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\Config;
use App\Core\Env;
use App\Core\Router;

$basePath = dirname(__DIR__);

Env::load($basePath . '/.env');
Config::load($basePath);
date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Jakarta'));

/**
 * Perakitan layanan manual. Fitur berikutnya menambah builder layanan miliknya
 * di sini dan mendaftarkannya pada resolver controller.
 */
$buildRouter = static function () use ($basePath): Router {
    // Resolver: ubah ke closure yang menyuntik dependensi saat controller butuh layanan.
    $router = new Router(static fn (string $class): object => new $class());
    (require $basePath . '/routes/web.php')($router);
    return $router;
};

$buildApplication = static function (Router $router) use ($basePath): Application {
    return new Application(
        $router,
        $basePath . '/storage/logs/app.log',
        (bool) Config::get('app.debug', false)
    );
};

return $buildApplication($buildRouter());
