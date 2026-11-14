<?php

declare(strict_types=1);

namespace Tests\Feature\Tenants;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\ValidationException;
use Tests\Support\ImageFixtures;
use Tests\Support\SettingsHarness;

final class SettingsTest extends SettingsHarness
{
    private function owner(string $email = 'budi@example.com'): array
    {
        $r = $this->activeOwner($email);
        $this->assertSame(302, $this->login($email)->status());
        return $r;
    }

    private function valid(array $over = []): array
    {
        return $over + [
            'business_type' => 'Toko kelontong', 'address' => 'Jl. Merdeka 1', 'phone' => '081234567890',
            'timezone' => 'Asia/Makassar', 'currency_code' => 'IDR', 'number_format' => 'id-ID',
        ];
    }

    private function logoFor(int $tenantId): ?string
    {
        $row = $this->db->fetchOne('SELECT logo_path FROM tenant_profiles WHERE tenant_id = ?', [$tenantId]);
        return $row['logo_path'] ?? null;
    }

    private function png(int $red = 200): array
    {
        return ImageFixtures::upload(ImageFixtures::image($this->tmp, 'png', 8, 8, $red));
    }

    public function testShowsSettingsPageToOwner(): void
    {
        $this->owner();
        $r = $this->send('GET', '/pengaturan');
        $this->assertSame(200, $r->status());
        $this->assertTrue(str_contains($r->body(), 'Asia/Jakarta'));
        $this->assertTrue(str_contains($r->body(), 'name="_csrf"'));
        $this->assertTrue(str_contains($r->body(), 'enctype="multipart/form-data"'));
        $this->assertSame('no-store', $r->header('Cache-Control'));
    }

    public function testUpdateSavesProfileAndSettings(): void
    {
        $a = $this->owner();
        $r = $this->send('POST', '/pengaturan', $this->valid());
        $this->assertSame(302, $r->status());
        $this->assertSame('/pengaturan', $r->header('Location'));
        $p = $this->db->fetchOne('SELECT * FROM tenant_profiles WHERE tenant_id = ?', [$a['tenantId']]);
        $s = $this->db->fetchOne('SELECT * FROM tenant_settings WHERE tenant_id = ?', [$a['tenantId']]);
        $this->assertSame('Toko kelontong', $p['business_type']);
        $this->assertSame('Jl. Merdeka 1', $p['address']);
        $this->assertSame('081234567890', $p['phone']);
        $this->assertSame('Asia/Makassar', $s['timezone']);
        $this->assertSame('IDR', $s['currency_code']);
    }

    public function testInvalidTimezoneRejected(): void
    {
        $a = $this->owner();
        foreach (['Mars/Olympus', 'asia/jakarta', 'WIB', '+07:00', 'Asia/Jakarta ; DROP', '../../etc/passwd'] as $tz) {
            $r = $this->send('POST', '/pengaturan', $this->valid(['timezone' => $tz]));
            $this->assertSame(422, $r->status(), $tz);
            $this->assertTrue(str_contains($r->body(), 'Zona waktu'), $tz);
        }
        $this->assertSame('Asia/Jakarta', $this->db->fetchOne('SELECT timezone FROM tenant_settings WHERE tenant_id = ?', [$a['tenantId']])['timezone']);
        $this->assertSame(0, $this->count("SELECT COUNT(*) AS n FROM audit_logs WHERE action = 'tenant.settings_updated'"));
    }

    public function testValidationOfOtherFields(): void
    {
        $a = $this->owner();
        $owner = $this->authUserFor($a['tenantId'], $a['userId']);
        $bad = [
            ['currency_code' => 'RP'], ['currency_code' => 'IDRX'], ['currency_code' => 'I1R'], ['currency_code' => ''],
            ['number_format' => 'fr-FR'], ['number_format' => ''],
            ['business_type' => str_repeat('a', 101)], ['address' => str_repeat('a', 501)], ['phone' => 'abc'], ['phone' => str_repeat('1', 40)],
            ['address' => "a\0b"], ['business_type' => ['x']],
        ];
        foreach ($bad as $over) {
            $this->assertThrows(ValidationException::class, fn () => $this->settings->update($owner, $this->valid($over)), json_encode($over));
        }
        // Mata uang huruf kecil dinormalkan ke huruf besar.
        $this->settings->update($owner, $this->valid(['currency_code' => 'usd', 'number_format' => 'en-US']));
        $s = $this->db->fetchOne('SELECT * FROM tenant_settings WHERE tenant_id = ?', [$a['tenantId']]);
        $this->assertSame('USD', $s['currency_code']);
        $this->assertSame('en-US', $s['number_format']);
    }

    public function testEmptyOptionalProfileFieldsStoredAsNull(): void
    {
        $a = $this->owner();
        $this->send('POST', '/pengaturan', $this->valid(['business_type' => '', 'address' => '', 'phone' => '']));
        $p = $this->db->fetchOne('SELECT * FROM tenant_profiles WHERE tenant_id = ?', [$a['tenantId']]);
        $this->assertSame(null, $p['business_type']);
        $this->assertSame(null, $p['address']);
        $this->assertSame(null, $p['phone']);
    }

    public function testMassAssignmentIgnored(): void
    {
        $a = $this->owner();
        $b = $this->activeOwner('lain@example.com');
        $this->send('POST', '/pengaturan', $this->valid(['tenant_id' => $b['tenantId'], 'logo_path' => 'x.png', 'id' => 99]));
        $this->assertSame('Asia/Makassar', $this->db->fetchOne('SELECT timezone FROM tenant_settings WHERE tenant_id = ?', [$a['tenantId']])['timezone']);
        $this->assertSame('Asia/Jakarta', $this->db->fetchOne('SELECT timezone FROM tenant_settings WHERE tenant_id = ?', [$b['tenantId']])['timezone']);
        $this->assertSame(null, $this->logoFor($a['tenantId']));
    }

    public function testSettingsChangeIsAudited(): void
    {
        $a = $this->owner();
        $this->send('POST', '/pengaturan', $this->valid(['address' => 'Jl. Rahasia 99 sangat privat', 'phone' => '081111111111']));
        $row = $this->db->fetchOne("SELECT * FROM audit_logs WHERE action = 'tenant.settings_updated'");
        $this->assertTrue($row !== null);
        $this->assertSame($a['tenantId'], (int) $row['tenant_id']);
        $this->assertSame($a['userId'], (int) $row['actor_id']);
        $meta = json_decode((string) $row['metadata'], true);
        $this->assertSame(['address', 'business_type', 'phone', 'timezone'], $meta['changed_fields']);
        $this->assertFalse(str_contains((string) $row['metadata'], 'Rahasia'), 'Nilai tidak boleh masuk audit');
        // Tanpa perubahan: tidak ada baris audit baru.
        $this->send('POST', '/pengaturan', $this->valid(['address' => 'Jl. Rahasia 99 sangat privat', 'phone' => '081111111111']));
        $this->assertSame(1, $this->count("SELECT COUNT(*) AS n FROM audit_logs WHERE action = 'tenant.settings_updated'"));
    }

    public function testPermissionsAreEnforced(): void
    {
        $a = $this->owner();
        $viewer = $this->makeUser($a['tenantId'], 'viewer@example.com', ['tenant_settings.view']);
        $none = $this->makeUser($a['tenantId'], 'none@example.com', ['branches.view']);
        $this->send('POST', '/keluar');

        $this->assertSame(302, $this->login('none@example.com')->status());
        foreach ([['GET', '/pengaturan'], ['GET', '/pengaturan/logo'], ['POST', '/pengaturan'], ['POST', '/pengaturan/logo'], ['POST', '/pengaturan/logo/hapus']] as [$m, $u]) {
            $this->assertSame(403, $this->status($m, $u, $this->valid()), "$m $u tanpa izin");
        }
        $this->send('POST', '/keluar');

        $this->assertSame(302, $this->login('viewer@example.com')->status());
        $this->assertSame(200, $this->status('GET', '/pengaturan'));
        $this->assertSame(404, $this->status('GET', '/pengaturan/logo'));
        foreach ([['POST', '/pengaturan'], ['POST', '/pengaturan/logo'], ['POST', '/pengaturan/logo/hapus']] as [$m, $u]) {
            $this->assertSame(403, $this->status($m, $u, $this->valid()), "$m $u hanya lihat");
        }
        $this->assertSame('Asia/Jakarta', $this->db->fetchOne('SELECT timezone FROM tenant_settings WHERE tenant_id = ?', [$a['tenantId']])['timezone']);
        // Penolakan pada service juga (bukan hanya rute).
        $this->assertThrows(ForbiddenException::class, fn () => $this->settings->update($this->authUserFor($a['tenantId'], $viewer), $this->valid()));
        $this->assertThrows(ForbiddenException::class, fn () => $this->settings->uploadLogo($this->authUserFor($a['tenantId'], $viewer), $this->png()));
        $this->assertThrows(ForbiddenException::class, fn () => $this->settings->get($this->authUserFor($a['tenantId'], $none)));
    }

    public function testUnauthenticatedRedirectedToLogin(): void
    {
        $this->owner();
        $this->send('POST', '/keluar');
        foreach ([['GET', '/pengaturan'], ['GET', '/pengaturan/logo']] as [$m, $u]) {
            $r = $this->send($m, $u);
            $this->assertSame(302, $r->status(), $u);
        }
    }

    public function testPostWithoutCsrfRejected(): void
    {
        $a = $this->owner();
        $this->assertSame(419, $this->send('POST', '/pengaturan', $this->valid(), false)->status());
        $this->assertSame(419, $this->sendWithFiles('/pengaturan/logo', [], ['logo' => $this->png()], false)->status());
        $this->assertSame(419, $this->send('POST', '/pengaturan/logo/hapus', [], false)->status());
        $this->assertSame('Asia/Jakarta', $this->db->fetchOne('SELECT timezone FROM tenant_settings WHERE tenant_id = ?', [$a['tenantId']])['timezone']);
        $this->assertSame(null, $this->logoFor($a['tenantId']));
    }

    public function testLogoUploadServeReplaceAndRemove(): void
    {
        $a = $this->owner();
        $this->assertSame(404, $this->status('GET', '/pengaturan/logo'), 'Tanpa logo → 404');

        $r = $this->sendWithFiles('/pengaturan/logo', [], ['logo' => $this->png()]);
        $this->assertSame(302, $r->status());
        $first = $this->logoFor($a['tenantId']);
        $this->assertTrue(preg_match('/^[a-f0-9]{32}\.png\z/', (string) $first) === 1, (string) $first);
        $this->assertTrue(is_file($this->logosDir . '/' . $a['tenantId'] . '/' . $first));

        $img = $this->send('GET', '/pengaturan/logo');
        $this->assertSame(200, $img->status());
        $this->assertSame('image/png', $img->header('Content-Type'));
        $this->assertSame('nosniff', $img->header('X-Content-Type-Options'));
        $this->assertTrue(str_contains((string) $img->header('Cache-Control'), 'private'));
        $this->assertSame((string) file_get_contents($this->logosDir . '/' . $a['tenantId'] . '/' . $first), $img->body());
        $this->assertTrue(str_contains($this->send('GET', '/pengaturan')->body(), 'src="/pengaturan/logo'));

        // Mengganti logo menghapus berkas lama dan hanya meninggalkan satu.
        $this->sendWithFiles('/pengaturan/logo', [], ['logo' => ImageFixtures::upload(ImageFixtures::image($this->tmp, 'jpeg'), 'x.jpg', 'image/jpeg')]);
        $second = $this->logoFor($a['tenantId']);
        $this->assertTrue($second !== $first && str_ends_with((string) $second, '.jpg'));
        $this->assertFalse(is_file($this->logosDir . '/' . $a['tenantId'] . '/' . $first));
        $this->assertSame('image/jpeg', $this->send('GET', '/pengaturan/logo')->header('Content-Type'));
        $this->assertSame(2, $this->count("SELECT COUNT(*) AS n FROM audit_logs WHERE action = 'tenant.logo_updated'"));

        $this->assertSame(302, $this->send('POST', '/pengaturan/logo/hapus')->status());
        $this->assertSame(null, $this->logoFor($a['tenantId']));
        $this->assertSame([], glob($this->logosDir . '/' . $a['tenantId'] . '/*'));
        $this->assertSame(404, $this->status('GET', '/pengaturan/logo'));
        $this->assertSame(3, $this->count("SELECT COUNT(*) AS n FROM audit_logs WHERE action = 'tenant.logo_updated'"));
    }

    public function testLogoAuditContainsNoFilename(): void
    {
        $a = $this->owner();
        $this->sendWithFiles('/pengaturan/logo', [], ['logo' => ImageFixtures::upload(ImageFixtures::image($this->tmp), 'rahasia-saya.png')]);
        $row = $this->db->fetchOne("SELECT * FROM audit_logs WHERE action = 'tenant.logo_updated'");
        $this->assertSame($a['tenantId'], (int) $row['tenant_id']);
        $this->assertFalse(str_contains((string) $row['metadata'], 'rahasia-saya'));
    }

    public function testRejectedLogoLeavesExistingLogoAndShowsError(): void
    {
        $a = $this->owner();
        $this->sendWithFiles('/pengaturan/logo', [], ['logo' => $this->png()]);
        $before = $this->logoFor($a['tenantId']);
        $php = ImageFixtures::file($this->tmp, '<?php echo 1;');
        $r = $this->sendWithFiles('/pengaturan/logo', [], ['logo' => ImageFixtures::upload($php)]);
        $this->assertSame(422, $r->status());
        $this->assertTrue(str_contains($r->body(), 'role="alert"'));
        $this->assertSame($before, $this->logoFor($a['tenantId']));
        $this->assertTrue(is_file($this->logosDir . '/' . $a['tenantId'] . '/' . $before));
        $r = $this->sendWithFiles('/pengaturan/logo', [], []);
        $this->assertSame(422, $r->status(), 'Tanpa berkas');
        $this->assertSame(1, $this->count("SELECT COUNT(*) AS n FROM audit_logs WHERE action = 'tenant.logo_updated'"));
    }

    public function testTenantCannotReadOtherTenantLogo(): void
    {
        $a = $this->owner('a@example.com');
        $this->sendWithFiles('/pengaturan/logo', [], ['logo' => $this->png(10)]);
        $logoA = $this->logoFor($a['tenantId']);
        $bytesA = (string) file_get_contents($this->logosDir . '/' . $a['tenantId'] . '/' . $logoA);
        $this->send('POST', '/keluar');

        $b = $this->owner('b@example.com');
        // B belum punya logo: tidak ada cara memilih tenant lewat parameter, jadi 404 (bukan logo A).
        foreach (['/pengaturan/logo', '/pengaturan/logo?tenant_id=' . $a['tenantId'], '/pengaturan/logo?file=' . $logoA, '/pengaturan/logo?tenant=' . $a['tenantId'] . '&name=' . $logoA] as $uri) {
            $this->assertSame(404, $this->status('GET', $uri), $uri);
        }
        $this->sendWithFiles('/pengaturan/logo', ['tenant_id' => $a['tenantId'], 'logo_path' => $logoA], ['logo' => $this->png(240)]);
        $logoB = $this->logoFor($b['tenantId']);
        $this->assertTrue($logoB !== null && $logoB !== $logoA);
        $bytesB = (string) file_get_contents($this->logosDir . '/' . $b['tenantId'] . '/' . $logoB);
        $this->assertTrue($bytesA !== $bytesB);
        $this->assertSame($bytesB, $this->send('GET', '/pengaturan/logo?tenant_id=' . $a['tenantId'])->body());
        $this->assertSame($logoA, $this->logoFor($a['tenantId']), 'Logo A tak berubah oleh B');
        $this->assertTrue(is_file($this->logosDir . '/' . $a['tenantId'] . '/' . $logoA));

        // B menghapus logonya: logo A tetap ada.
        $this->send('POST', '/pengaturan/logo/hapus', ['tenant_id' => $a['tenantId']]);
        $this->assertTrue(is_file($this->logosDir . '/' . $a['tenantId'] . '/' . $logoA));
        $this->send('POST', '/keluar');
        $this->assertSame(302, $this->login('a@example.com')->status());
        $this->assertSame($bytesA, $this->send('GET', '/pengaturan/logo')->body());
    }

    public function testDbValueWithPathTraversalIsNeverServedOrDeleted(): void
    {
        $a = $this->owner();
        @mkdir($this->tmp . '/uploads', 0700, true);
        $victim = $this->tmp . '/uploads/victim.png';
        file_put_contents($victim, 'x');
        foreach (['../../victim.png', '/etc/passwd', '../1/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png'] as $evil) {
            $this->db->execute('UPDATE tenant_profiles SET logo_path = ? WHERE tenant_id = ?', [$evil, $a['tenantId']]);
            $this->assertSame(404, $this->status('GET', '/pengaturan/logo'), $evil);
            $this->sendWithFiles('/pengaturan/logo', [], ['logo' => $this->png()]);
            $this->assertTrue(is_file($victim));
        }
    }

    public function testPolyglotStoredUnderSafeExtensionIsRejected(): void
    {
        $a = $this->owner();
        $png = (string) file_get_contents(ImageFixtures::image($this->tmp));
        $path = ImageFixtures::file($this->tmp, $png . '<?php system($_GET["c"]); ?>');
        $r = $this->sendWithFiles('/pengaturan/logo', [], ['logo' => ImageFixtures::upload($path, 'a.php.png')]);
        $this->assertSame(422, $r->status());
        $this->assertSame(null, $this->logoFor($a['tenantId']));
        $this->assertSame([], glob($this->logosDir . '/*/*') ?: []);
    }

    public function testUploadsDirectoryIsOutsidePublicAndGuarded(): void
    {
        $root = dirname(__DIR__, 3);
        $cfg = require $root . '/config/app.php';
        $default = (string) ($cfg['uploads_path'] ?? '');
        $this->assertTrue($default !== '' && !str_contains($default, '/public'), 'uploads_path harus di luar public/');
        $this->assertTrue(is_file($root . '/storage/.htaccess'));
        $this->assertTrue(str_contains((string) file_get_contents($root . '/storage/.htaccess'), 'Require all denied'));
        $this->assertTrue(is_file($root . '/storage/index.html') || is_file($root . '/storage/index.php'));
        $this->assertFalse(is_dir($root . '/public/uploads'));
    }
}
