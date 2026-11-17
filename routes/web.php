<?php

declare(strict_types=1);

use App\Core\Middleware\AccessGateMiddleware;
use App\Core\Middleware\AppShellMiddleware;
use App\Core\Middleware\AuthMiddleware;
use App\Core\Middleware\PermissionMiddleware;
use App\Core\Router;
use App\Modules\Auth\Controllers\HomeController;
use App\Modules\Auth\Controllers\LoginController;
use App\Modules\Auth\Controllers\PasswordResetController;
use App\Modules\Auth\Controllers\RegisterController;
use App\Modules\Branches\Controllers\BranchController;
use App\Modules\Roles\Controllers\RoleController;
use App\Modules\Tenants\Controllers\SettingsController;
use App\Modules\Users\Controllers\InvitationController;
use App\Modules\Users\Controllers\UserController;

return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'landing']);
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

    $router->get('/beranda', [HomeController::class, 'home'], [AuthMiddleware::class, AccessGateMiddleware::class, AppShellMiddleware::class]);
    $router->get('/demo-berakhir', [HomeController::class, 'demoEnded'], [AuthMiddleware::class]);

    $guard = static fn (string $permission): array => [AuthMiddleware::class, AccessGateMiddleware::class, PermissionMiddleware::requires($permission), AppShellMiddleware::class];
    $router->get('/cabang', [BranchController::class, 'index'], $guard('branches.view'));
    $router->get('/cabang/baru', [BranchController::class, 'createForm'], $guard('branches.manage'));
    $router->post('/cabang', [BranchController::class, 'store'], $guard('branches.manage'));
    $router->get('/cabang/{id}/ubah', [BranchController::class, 'editForm'], $guard('branches.manage'));
    $router->post('/cabang/{id}', [BranchController::class, 'update'], $guard('branches.manage'));
    $router->post('/cabang/{id}/nonaktifkan', [BranchController::class, 'deactivate'], $guard('branches.manage'));
    $router->post('/cabang/{id}/aktifkan', [BranchController::class, 'activate'], $guard('branches.manage'));

    $router->get('/undangan/{token}', [InvitationController::class, 'show']);
    $router->post('/undangan/{token}', [InvitationController::class, 'accept']);

    // Rute literal (/undang, /baru) harus lebih dulu dari rute {id}.
    $router->get('/pengguna', [UserController::class, 'index'], $guard('users.view'));
    $router->get('/pengguna/undang', [UserController::class, 'inviteForm'], $guard('users.manage'));
    $router->post('/pengguna/undang', [UserController::class, 'invite'], $guard('users.manage'));
    $router->get('/pengguna/{id}/ubah', [UserController::class, 'editForm'], $guard('users.manage'));
    $router->post('/pengguna/{id}', [UserController::class, 'update'], $guard('users.manage'));
    $router->post('/pengguna/{id}/nonaktifkan', [UserController::class, 'deactivate'], $guard('users.manage'));
    $router->post('/pengguna/{id}/aktifkan', [UserController::class, 'activate'], $guard('users.manage'));
    $router->post('/pengguna/{id}/role', [UserController::class, 'assignRoles'], $guard('users.manage'));
    $router->post('/pengguna/{id}/cabang', [UserController::class, 'assignBranches'], $guard('users.manage'));
    $router->post('/pengguna/{id}/kirim-ulang-undangan', [UserController::class, 'resendInvite'], $guard('users.manage'));

    $router->get('/role', [RoleController::class, 'index'], $guard('roles.view'));
    $router->get('/role/baru', [RoleController::class, 'createForm'], $guard('roles.manage'));
    $router->post('/role', [RoleController::class, 'store'], $guard('roles.manage'));
    $router->get('/role/{id}', [RoleController::class, 'show'], $guard('roles.view'));
    $router->post('/role/{id}/izin', [RoleController::class, 'syncPermissions'], $guard('roles.manage'));
    $router->post('/role/{id}/nama', [RoleController::class, 'rename'], $guard('roles.manage'));
    $router->post('/role/{id}/hapus', [RoleController::class, 'delete'], $guard('roles.manage'));

    $router->get('/pengaturan', [SettingsController::class, 'show'], $guard('tenant_settings.view'));
    $router->post('/pengaturan', [SettingsController::class, 'update'], $guard('tenant_settings.manage'));
    // Logo hanya milik tenant dari sesi; tanpa parameter pemilih tenant/berkas.
    $router->get('/pengaturan/logo', [SettingsController::class, 'logo'], $guard('tenant_settings.view'));
    $router->post('/pengaturan/logo', [SettingsController::class, 'uploadLogo'], $guard('tenant_settings.manage'));
    $router->post('/pengaturan/logo/hapus', [SettingsController::class, 'removeLogo'], $guard('tenant_settings.manage'));

    $router->get('/bantuan', [HomeController::class, 'support']);
};
