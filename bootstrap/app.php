<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\Config;
use App\Core\Env;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Middleware\SecurityHeadersMiddleware;
use App\Core\Middleware\SessionMiddleware;
use App\Core\Router;
use App\Core\Security\NativeSessionStorage;
use App\Core\Security\Session;

$basePath = dirname(__DIR__);

Env::load($basePath . '/.env');
Config::load($basePath);
date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Jakarta'));

/**
 * Perakitan layanan manual. Fitur berikutnya menambah builder layanan miliknya
 * di sini dan mendaftarkannya pada resolver controller.
 */
$buildSession = static function () use ($basePath): Session {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return new Session(new NativeSessionStorage($basePath . '/storage/sessions', $https));
};

/** @return list<\App\Core\Middleware\MiddlewareInterface> urutan: header keamanan, sesi, CSRF */
$buildGlobalMiddleware = static fn (): array => [
    new SecurityHeadersMiddleware(),
    new SessionMiddleware($buildSession, (int) Config::get('security.session.idle_seconds', 28800)),
    new CsrfMiddleware(),
];

$buildRouter = static function () use ($basePath, $buildGlobalMiddleware): Router {
    // Resolver: ubah ke closure yang menyuntik dependensi saat controller butuh layanan.
    $router = new Router(static fn (string $class): object => new $class());
    foreach ($buildGlobalMiddleware() as $middleware) {
        $router->addGlobalMiddleware($middleware);
    }
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
