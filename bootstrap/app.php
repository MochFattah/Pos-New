<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\Config;
use App\Core\Env;
use App\Core\Middleware\AccessGateMiddleware;
use App\Core\Middleware\AuthMiddleware;
use App\Core\Authorization\AccessDeniedRecorder;
use App\Core\Authorization\PolicyService;
use App\Core\Middleware\CsrfMiddleware;
use App\Core\Middleware\PermissionMiddleware;
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
use App\Modules\Auth\Controllers\HomeController;
use App\Modules\Branches\Controllers\BranchController;
use App\Modules\Branches\Repositories\BranchRepository;
use App\Modules\Branches\Services\BranchService;
use App\Modules\Auth\Services\InvitationEmailSender;
use App\Modules\Roles\Controllers\RoleController;
use App\Modules\Roles\Repositories\PermissionRepository;
use App\Modules\Roles\Services\RoleService;
use App\Modules\Users\Controllers\InvitationController;
use App\Modules\Users\Controllers\UserController;
use App\Modules\Users\Services\UserService;
use App\Modules\Auth\Controllers\LoginController;
use App\Modules\Auth\Controllers\PasswordResetController;
use App\Modules\Auth\Controllers\RegisterController;
use App\Modules\Auth\Repositories\PasswordResetRepository;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\PasswordResetService;
use App\Modules\Auth\Repositories\EmailVerificationRepository;
use App\Modules\Auth\Services\VerificationEmailSender;
use App\Modules\Auth\Services\VerifyEmailService;
use App\Modules\Tenants\DemoService;
use App\Modules\Tenants\Repositories\RoleRepository;
use App\Modules\Tenants\Repositories\TenantRepository;
use App\Modules\Tenants\Repositories\UserRepository;
use App\Modules\Tenants\Controllers\SettingsController;
use App\Modules\Tenants\Services\LogoUploader;
use App\Modules\Tenants\Services\RegisterTenantService;
use App\Modules\Tenants\Services\TenantSettingsService;

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

/** Retensi login_attempts = jendela terbesar di antara semua limiter yang berbagi tabel itu. */
$limiterRetention = static fn (): int => max(
    (int) Config::get('security.login.window_seconds', 900),
    (int) Config::get('security.reset_request.window_seconds', 3600),
    (int) Config::get('security.registration.window_seconds', 3600),
    (int) Config::get('security.invitation_resend.window_seconds', 3600)
);

$buildRegisterController = static function () use ($buildAuditLogger, $sharedDb, $limiterRetention): RegisterController {
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
        (int) Config::get('security.verification_token.ttl_seconds', 86400),
        null,
        Config::get('app.env', 'production') === 'production'
    );
    $limiter = new RateLimiter(
        $db,
        (int) Config::get('security.login.max_attempts', 5),
        (int) Config::get('security.login.window_seconds', 900),
        null,
        $limiterRetention()
    );
    $registrationLimiter = new RateLimiter(
        $db,
        (int) Config::get('security.registration.max_attempts', 20),
        (int) Config::get('security.registration.window_seconds', 3600),
        null,
        $limiterRetention()
    );
    return new RegisterController(
        new RegisterTenantService($db, $tenants, $users, new RoleRepository($db), $sender, new PasswordHasher(), $audit),
        new VerifyEmailService($db, $verifications, $users, $tenants, new DemoService($db), $sender, $audit),
        $limiter,
        $registrationLimiter
    );
};

$buildAuthService = static function () use ($buildAuditLogger, $sharedDb, $limiterRetention): AuthService {
    $db = $sharedDb();
    $window = (int) Config::get('security.login.window_seconds', 900);
    return new AuthService(
        $db,
        new UserRepository($db),
        new RateLimiter($db, (int) Config::get('security.login.max_attempts', 5), $window, null, $limiterRetention()),
        new RateLimiter($db, (int) Config::get('security.login.ip_max_attempts', 50), $window, null, $limiterRetention()),
        new PasswordHasher(),
        $buildAuditLogger($db)
    );
};

$buildPasswordResetController = static function () use ($buildAuditLogger, $sharedDb, $limiterRetention): PasswordResetController {
    $db = $sharedDb();
    $ttl = (int) Config::get('security.reset_token.ttl_seconds', 3600);
    return new PasswordResetController(
        new PasswordResetService(
            $db,
            new PasswordResetRepository($db),
            new UserRepository($db),
            new TokenService(),
            new PasswordHasher(),
            MailerFactory::fromConfig(),
            $buildAuditLogger($db),
            (string) Config::get('app.url', ''),
            $ttl,
            null,
            Config::get('app.env', 'production') === 'production'
        ),
        new RateLimiter(
            $db,
            (int) Config::get('security.reset_request.max_attempts', 5),
            (int) Config::get('security.reset_request.window_seconds', 3600),
            null,
            $limiterRetention()
        )
    );
};

// Satu PolicyService (dan cache katalog permission-nya) per request.
$sharedPolicy = static function () use ($buildAuditLogger, $sharedDb): PolicyService {
    static $policy = null;
    return $policy ??= new PolicyService($sharedDb(), new AccessDeniedRecorder($buildAuditLogger($sharedDb())));
};

$buildBranchController = static function () use ($buildAuditLogger, $sharedDb, $sharedPolicy): BranchController {
    $db = $sharedDb();
    $policy = $sharedPolicy();
    return new BranchController(new BranchService($db, new BranchRepository($db), $policy, $buildAuditLogger($db), new AccessDeniedRecorder($buildAuditLogger($db))), $policy);
};

$buildUserController = static function () use ($buildAuditLogger, $sharedDb, $sharedPolicy, $limiterRetention): UserController {
    $db = $sharedDb();
    $policy = $sharedPolicy();
    $sender = new InvitationEmailSender(
        new EmailVerificationRepository($db),
        new TokenService(),
        MailerFactory::fromConfig(),
        (string) Config::get('app.url', ''),
        (int) Config::get('security.invitation_token.ttl_seconds', 86400),
        null,
        Config::get('app.env', 'production') === 'production'
    );
    $resendLimiter = new RateLimiter(
        $db,
        (int) Config::get('security.invitation_resend.max_attempts', 3),
        (int) Config::get('security.invitation_resend.window_seconds', 3600),
        null,
        $limiterRetention()
    );
    $service = new UserService(
        $db,
        new UserRepository($db),
        new RoleRepository($db),
        new BranchRepository($db),
        $policy,
        $buildAuditLogger($db),
        new AccessDeniedRecorder($buildAuditLogger($db)),
        $sender,
        new PasswordHasher(),
        $resendLimiter
    );
    return new UserController($service, $policy);
};

$buildInvitationController = static function () use ($buildAuditLogger, $sharedDb): InvitationController {
    $db = $sharedDb();
    $users = new UserRepository($db);
    $verifications = new EmailVerificationRepository($db);
    // Pengirim tak dipakai jalur ini (penerimaan undangan); VerifyEmailService memintanya untuk kirim ulang Owner.
    $sender = new VerificationEmailSender($verifications, new TokenService(), MailerFactory::fromConfig(), (string) Config::get('app.url', ''), 86400);
    return new InvitationController(new VerifyEmailService($db, $verifications, $users, new TenantRepository($db), new DemoService($db), $sender, $buildAuditLogger($db)));
};

$buildRoleController = static function () use ($buildAuditLogger, $sharedDb, $sharedPolicy): RoleController {
    $db = $sharedDb();
    $policy = $sharedPolicy();
    return new RoleController(
        new RoleService($db, new RoleRepository($db), new PermissionRepository($db), $policy, $buildAuditLogger($db), new AccessDeniedRecorder($buildAuditLogger($db))),
        $policy
    );
};

$buildSettingsController = static function () use ($buildAuditLogger, $sharedDb, $sharedPolicy, $basePath): SettingsController {
    $db = $sharedDb();
    $policy = $sharedPolicy();
    $uploads = (string) Config::get('app.uploads_path', $basePath . '/storage/uploads/logos');
    return new SettingsController(
        new TenantSettingsService($db, new TenantRepository($db), new LogoUploader($uploads), $buildAuditLogger($db), $policy),
        $policy
    );
};

$buildRouter = static function () use ($basePath, $buildSettingsController, $buildGlobalMiddleware, $buildRegisterController, $buildAuthService, $buildPasswordResetController, $buildBranchController, $buildUserController, $buildInvitationController, $buildRoleController, $sharedDb, $sharedPolicy): Router {
    PermissionMiddleware::provide($sharedPolicy);
    // Resolver: controller yang butuh layanan dirakit lewat builder-nya; sisanya dibuat tanpa argumen.
    $router = new Router(static fn (string $class): object => match ($class) {
        RegisterController::class => $buildRegisterController(),
        LoginController::class => new LoginController($buildAuthService()),
        PasswordResetController::class => $buildPasswordResetController(),
        BranchController::class => $buildBranchController(),
        UserController::class => $buildUserController(),
        InvitationController::class => $buildInvitationController(),
        RoleController::class => $buildRoleController(),
        SettingsController::class => $buildSettingsController(),
        HomeController::class => new HomeController(new DemoService($sharedDb()), (string) Config::get('app.support_email', '')),
        AuthMiddleware::class => new AuthMiddleware($buildAuthService()),
        AccessGateMiddleware::class => new AccessGateMiddleware(new DemoService($sharedDb())),
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
