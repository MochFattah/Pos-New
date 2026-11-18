<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Security\RateLimiter;
use App\Core\View;
use App\Modules\Auth\Controllers\RegisterController;
use App\Modules\Tenants\Controllers\SettingsController;
use App\Modules\Tenants\Repositories\TenantRepository;
use App\Modules\Tenants\Services\LogoUploader;
use App\Modules\Tenants\Services\TenantSettingsService;

/** Harness lengkap (semua modul fondasi) untuk merender setiap jenis halaman; dipakai ViewTest dan scripts/dump-pages.php. */
abstract class PageFixtures extends UserHarness
{
    protected const ROOT = __DIR__ . '/../..';

    protected function extraServices(): array
    {
        $parent = parent::extraServices();
        $clock = fn (): int => $this->now;
        $uploader = new LogoUploader(sys_get_temp_dir() . '/view-logos-' . bin2hex(random_bytes(4)), static fn (string $f): bool => true, static fn (string $a, string $b): bool => false);
        $settings = new TenantSettingsService($this->db, new TenantRepository($this->db), $uploader, $this->makeAudit(), $this->policy, $clock);
        return $parent + [
            SettingsController::class => new SettingsController($settings, $this->policy),
            RegisterController::class => new RegisterController($this->register, $this->verify, new RateLimiter($this->db, 5, 3600, $clock), new RateLimiter($this->db, 50, 3600, $clock)),
        ];
    }

    protected function get(string $uri): string
    {
        $r = $this->send('GET', $uri);
        $this->assertTrue($r->status() === 200 || $r->status() === 404, "GET $uri status " . $r->status());
        return $r->body();
    }

    /** @return array{tenantId: int, branchId: int, roleId: int, userId: int} */
    protected function loggedInOwner(): array
    {
        $t = $this->activeOwner();
        $this->assertSame(302, $this->login()->status());
        $branch = (int) $this->db->fetchOne('SELECT id FROM branches WHERE tenant_id = ? LIMIT 1', [$t['tenantId']])['id'];
        $role = $this->roleId($t['tenantId'], 'kasir');
        return ['tenantId' => $t['tenantId'], 'userId' => $t['userId'], 'branchId' => $branch, 'roleId' => $role];
    }

    /** Seluruh halaman yang dapat dirender: [nama => HTML]. @return array<string, string> */
    protected function renderAllPages(): array
    {
        $pages = [];
        foreach (['/', '/masuk', '/daftar', '/lupa-password', '/bantuan', '/daftar/cek-email', '/verifikasi-email', '/undangan/tidak-ada', '/reset-password/tidak-ada', '/verifikasi-email/tidak-ada'] as $uri) {
            $pages[$uri] = $this->get($uri);
        }
        $pages['POST /masuk (gagal)'] = $this->send('POST', '/masuk', ['email' => '', 'password' => ''])->body();
        $pages['POST /daftar (gagal)'] = $this->send('POST', '/daftar', [])->body();

        $o = $this->loggedInOwner();
        $this->makeUser($o['tenantId'], 'staf@example.com', ['branches.view']);
        $staff = (int) $this->db->fetchOne("SELECT id FROM users WHERE email = 'staf@example.com'")['id'];
        $this->userService->invite($this->authUserFor($o['tenantId'], $o['userId']), ['name' => 'Undangan', 'email' => 'undangan@example.com', 'phone' => '']);
        $token = $this->mailer->lastInvitationToken();
        $pages['/undangan/{token}'] = $this->get('/undangan/' . $token);

        foreach (['/beranda', '/cabang', '/cabang/baru', '/cabang/' . $o['branchId'] . '/ubah', '/pengguna', '/pengguna/undang', '/pengguna/' . $staff . '/ubah',
            '/role', '/role/baru', '/role/' . $o['roleId'], '/pengaturan'] as $uri) {
            $pages[$uri] = $this->get($uri);
        }
        $pages['POST /cabang (gagal)'] = $this->send('POST', '/cabang', ['code' => '', 'name' => ''])->body();
        $pages['POST /role (gagal)'] = $this->send('POST', '/role', ['name' => ''])->body();
        $pages['POST /pengaturan (gagal)'] = $this->send('POST', '/pengaturan', ['business_name' => '', 'name' => ''])->body();
        $pages['POST /pengguna/{id} (gagal)'] = $this->send('POST', '/pengguna/' . $staff, ['name' => '', 'phone' => 'x'])->body();
        $pages['POST /pengguna/undang (gagal)'] = $this->send('POST', '/pengguna/undang', ['name' => '', 'email' => ''])->body();

        foreach ([403, 404, 419, 422, 429, 500, 418] as $status) {
            $pages['error ' . $status] = View::render('errors/' . (is_file(self::ROOT . "/resources/views/errors/$status.php") ? $status : 'generic'), [
                'title' => 'Kesalahan', 'status' => $status, 'message' => 'Pesan uji.', 'requestId' => $status === 500 ? 'abc123' : null, 'detail' => null,
            ]);
        }
        $pages['platform/masuk'] = View::render('platform/masuk', ['title' => 'Masuk platform', 'csrf' => 'x', 'errors' => ['email' => 'Salah'], 'email' => 'a@b.c', 'error' => 'Gagal']);
        $pages['platform/beranda'] = View::render('platform/beranda', ['title' => 'Beranda platform', 'name' => 'Admin', 'csrf' => 'x'], 'platform');
        return $pages;
    }
}
