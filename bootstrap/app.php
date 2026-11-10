<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\Config;
use App\Core\Env;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Middleware\SecurityHeadersMiddleware;
use App\Core\Middleware\SessionMiddleware;
use App\Core\RequestContext;
use App\Core\Router;
use App\Core\Security\NativeSessionStorage;
use App\Core\Security\Session;
use App\Modules\AuditLogs\Repositories\AuditLogRepository;
use App\Modules\AuditLogs\Services\AuditLogger;
use App\Core\Database\Connection;
use App\Core\Mail\MailerFactory;
use App\Core\Security\PasswordHasher;
use App\Core\Security\RateLimiter;
use App\Core\Security\TokenService;
use App\Modules\Auth\Controllers\RegisterController;
use App\Modules\Auth\Repositories\EmailVerificationRepository;
use App\Modules\Auth\Services\VerificationEmailSender;
use App\Modules\Auth\Services\VerifyEmailService;
use App\Modules\Tenants\DemoService;
use App\Modules\Tenants\Repositories\RoleRepository;
use App\Modules\Tenants\Repositories\TenantRepository;
use App\Modules\Tenants\Repositories\UserRepository;
use App\Modules\Tenants\Services\RegisterTenantService;

$basePath = dirname(__DIR__);

Env::load($basePath . '/.env');
Config::load($basePath);
date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Jakarta'));

/**
 * Perakitan layanan manual. Fitur berikutnya menambah builder layanan miliknya
 * di sini dan mendaftarkannya pada resolver controller.
 */
$forceSecure = Config::get('security.session.secure', 'auto') === 'true';
$idleSeconds = (int) Config::get('security.session.idle_seconds', 28800);

$buildSession = static function () use ($basePath, $forceSecure, $idleSeconds): Session {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return new Session(new NativeSessionStorage($basePath . '/storage/sessions', $forceSecure, $idleSeconds, $https));
};

/** @return list<\App\Core\Middleware\MiddlewareInterface> urutan: header keamanan, sesi, CSRF */
$buildGlobalMiddleware = static fn (): array => [
    new SecurityHeadersMiddleware($forceSecure),
    new SessionMiddleware($buildSession, $idleSeconds),
    new CsrfMiddleware(),
];

$requestContext = new RequestContext();

/** Builder AuditLogger; layanan fitur memanggilnya saat dirakit. */
$buildAuditLogger = static fn (Connection $db): AuditLogger => new AuditLogger(
    new AuditLogRepository($db),
    static fn (): array => $requestContext->toArray()
);

/** Koneksi dibuat malas dan dipakai bersama; tidak dibuka untuk rute yang tak butuh DB. */
$sharedDb = static function (): Connection {
    static $db = null;
    return $db ??= Connection::fromConfig();
};

$buildRegisterController = static function () use ($buildAuditLogger, $sharedDb): RegisterController {
    $db = $sharedDb();
    $audit = $buildAuditLogger($db);
    $verifications = new EmailVerificationRepository($db);
    $users = new UserRepository($db);
    $tenants = new TenantRepository($db);
    $sender = new VerificationEmailSender(
        $verifications,
        new TokenService(),
        MailerFactory::fromConfig(),
        (string) Config::get('app.url', ''),
        (int) Config::get('security.verification_token.ttl_seconds', 86400)
    );
    $limiter = new RateLimiter(
        $db,
        (int) Config::get('security.login.max_attempts', 5),
        (int) Config::get('security.login.window_seconds', 900)
    );
    return new RegisterController(
        new RegisterTenantService($db, $tenants, $users, new RoleRepository($db), $sender, new PasswordHasher(), $audit),
        new VerifyEmailService($db, $verifications, $users, $tenants, new DemoService($db), $sender, $audit),
        $limiter
    );
};

$buildRouter = static function () use ($basePath, $buildGlobalMiddleware, $buildRegisterController): Router {
    // Resolver: controller yang butuh layanan dirakit lewat builder-nya; sisanya dibuat tanpa argumen.
    $router = new Router(static fn (string $class): object => match ($class) {
        RegisterController::class => $buildRegisterController(),
        default => new $class(),
    });
    foreach ($buildGlobalMiddleware() as $middleware) {
        $router->addGlobalMiddleware($middleware);
    }
    (require $basePath . '/routes/web.php')($router);
    return $router;
};

$buildApplication = static function (Router $router) use ($basePath, $forceSecure, $requestContext): Application {
    return new Application(
        $router,
        $basePath . '/storage/logs/app.log',
        (bool) Config::get('app.debug', false),
        $forceSecure,
        $requestContext
    );
};

return $buildApplication($buildRouter());
