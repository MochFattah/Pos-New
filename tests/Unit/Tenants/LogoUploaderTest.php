<?php

declare(strict_types=1);

namespace Tests\Unit\Tenants;

use App\Core\Exceptions\ValidationException;
use App\Modules\Tenants\Services\LogoUploader;
use Tests\Support\ImageFixtures;
use Tests\Support\TestCase;

final class LogoUploaderTest extends TestCase
{
    private string $tmp = '';
    private string $base = '';

    protected function setUp(): void
    {
        $this->tmp = ImageFixtures::tempDir();
        $this->base = $this->tmp . '/uploads/logos';
    }

    protected function tearDown(): void
    {
        ImageFixtures::removeTree($this->tmp);
    }

    private function uploader(bool $uploaded = true): LogoUploader
    {
        // Seam uji: berkas biasa dianggap hasil unggahan HTTP dan dipindah dengan copy().
        return new LogoUploader($this->base, static fn (string $f): bool => $uploaded, static fn (string $from, string $to): bool => copy($from, $to));
    }

    private function rejected(array $file, string $needle = ''): string
    {
        try {
            $this->uploader()->store(7, $file);
        } catch (ValidationException $e) {
            $msg = $e->errors()['logo'] ?? '';
            $this->assertTrue($msg !== '', 'Pesan error logo kosong');
            if ($needle !== '') {
                $this->assertTrue(str_contains($msg, $needle), "Pesan '$msg' tidak memuat '$needle'");
            }
            $this->assertSame([], $this->stored(), 'Tidak boleh ada berkas tersimpan saat ditolak');
            return $msg;
        }
        $this->fail('Unggahan harus ditolak');
    }

    /** @return list<string> */
    private function stored(): array
    {
        return is_dir($this->base) ? array_map('basename', glob($this->base . '/*/*') ?: []) : [];
    }

    public function testRejectsPhpFileRenamedToPng(): void
    {
        $path = ImageFixtures::file($this->tmp, "<?php system(\$_GET['c']); ?>");
        $this->rejected(ImageFixtures::upload($path, 'logo.png', 'image/png'));
        $this->rejected(ImageFixtures::upload($path, 'logo.php.png', 'image/png'));
    }

    public function testRejectsOversizeFile(): void
    {
        $path = ImageFixtures::image($this->tmp);
        file_put_contents($path, str_repeat("\0", 1048576), FILE_APPEND);
        $this->rejected(ImageFixtures::upload($path), '1 MB');
    }

    public function testAcceptsFileExactlyAtLimitAndRejectsOneByteMore(): void
    {
        $path = ImageFixtures::image($this->tmp);
        // PNG sah tepat 1 MB: sisipkan chunk tEXt berisi padding sebelum IEND (berkas harus tetap berakhir di IEND).
        $png = (string) file_get_contents($path);
        $body = substr($png, 0, -12);
        $data = "k\0" . str_repeat('x', 1048576 - strlen($png) - 12 - 2);
        $chunk = pack('N', strlen($data)) . 'tEXt' . $data . pack('N', crc32('tEXt' . $data));
        file_put_contents($path, $body . $chunk . substr($png, -12));
        $this->assertSame(1048576, (int) filesize($path));
        $name = $this->uploader()->store(7, ImageFixtures::upload($path));
        $this->assertTrue(preg_match('/^[a-f0-9]{32}\.png\z/', $name) === 1);
        $this->uploader()->delete(7, $name);
        file_put_contents($path, "\0", FILE_APPEND);
        $this->rejected(ImageFixtures::upload($path), '1 MB');
    }

    public function testSizeIsMeasuredFromDiskNotFromClientClaim(): void
    {
        $path = ImageFixtures::image($this->tmp);
        file_put_contents($path, str_repeat("\0", 1048576), FILE_APPEND);
        $file = ImageFixtures::upload($path);
        $file['size'] = 100;
        $this->rejected($file, '1 MB');
    }

    public function testStoredNameIgnoresUserFilename(): void
    {
        $path = ImageFixtures::image($this->tmp);
        foreach (['../../x.png', '..\\..\\evil.php', "a\0.php", 'logo.php'] as $userName) {
            $name = $this->uploader()->store(7, ImageFixtures::upload($path, $userName));
            $this->assertTrue(preg_match('/^[a-f0-9]{32}\.png\z/', $name) === 1, $name);
            $this->assertTrue(is_file($this->base . '/7/' . $name));
        }
        $this->assertFalse(is_file($this->tmp . '/x.png'));
        $this->assertFalse(is_file($this->tmp . '/uploads/x.png'));
        $this->assertFalse(is_file($this->base . '/x.png'));
        $this->assertSame(4, count($this->stored()));
    }

    public function testNamesAreRandomAndUnique(): void
    {
        $path = ImageFixtures::image($this->tmp);
        $a = $this->uploader()->store(7, ImageFixtures::upload($path));
        $b = $this->uploader()->store(7, ImageFixtures::upload($path));
        $this->assertTrue($a !== $b);
    }

    public function testExtensionComesFromVerifiedMimeNotClientClaim(): void
    {
        $jpeg = ImageFixtures::image($this->tmp, 'jpeg');
        $name = $this->uploader()->store(7, ImageFixtures::upload($jpeg, 'logo.png', 'image/png'));
        $this->assertTrue(str_ends_with($name, '.jpg'), $name);
        $webp = ImageFixtures::image($this->tmp, 'webp');
        $name = $this->uploader()->store(7, ImageFixtures::upload($webp, 'logo.jpg', 'image/jpeg'));
        $this->assertTrue(str_ends_with($name, '.webp'), $name);
        $this->assertSame('image/webp', LogoUploader::mimeOf($name));
        $this->assertSame('image/jpeg', LogoUploader::mimeOf('0123456789abcdef0123456789abcdef.jpg'));
        $this->assertSame(null, LogoUploader::mimeOf('../x.png'));
        $this->assertSame(null, LogoUploader::mimeOf('0123456789abcdef0123456789abcdef.php'));
    }

    public function testRejectsGifAndSvg(): void
    {
        $this->rejected(ImageFixtures::upload(ImageFixtures::image($this->tmp, 'gif'), 'logo.png', 'image/png'));
        $svg = ImageFixtures::file($this->tmp, '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script></svg>');
        $this->rejected(ImageFixtures::upload($svg, 'logo.svg', 'image/svg+xml'));
        $this->rejected(ImageFixtures::upload($svg, 'logo.png', 'image/png'));
    }

    public function testRejectsPolyglotPngWithPhpPayload(): void
    {
        $png = (string) file_get_contents(ImageFixtures::image($this->tmp));
        foreach (['<?php system($_GET["c"]); ?>', '<?= `id` ?>', '<?PHP echo 1;'] as $payload) {
            $path = ImageFixtures::file($this->tmp, $png . $payload);
            $this->rejected(ImageFixtures::upload($path));
        }
    }

    public function testRejectsEmptyAndGarbageAndTruncated(): void
    {
        $this->rejected(ImageFixtures::upload(ImageFixtures::file($this->tmp, '')));
        $this->rejected(ImageFixtures::upload(ImageFixtures::file($this->tmp, 'bukan gambar')));
        $png = (string) file_get_contents(ImageFixtures::image($this->tmp));
        $this->rejected(ImageFixtures::upload(ImageFixtures::file($this->tmp, substr($png, 0, 12))));
        $this->rejected(ImageFixtures::upload(ImageFixtures::file($this->tmp, "\x89PNG\r\n\x1a\n")));
    }

    public function testUploadErrorCodesAreMapped(): void
    {
        $path = ImageFixtures::image($this->tmp);
        $this->rejected(ImageFixtures::upload($path, 'l.png', 'image/png', UPLOAD_ERR_INI_SIZE), '1 MB');
        $this->rejected(ImageFixtures::upload($path, 'l.png', 'image/png', UPLOAD_ERR_FORM_SIZE), '1 MB');
        $this->rejected(ImageFixtures::upload($path, 'l.png', 'image/png', UPLOAD_ERR_PARTIAL), 'sebagian');
        $this->rejected(ImageFixtures::upload($path, 'l.png', 'image/png', UPLOAD_ERR_NO_FILE), 'dipilih');
        foreach ([UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION, 99] as $code) {
            $this->rejected(ImageFixtures::upload($path, 'l.png', 'image/png', $code), 'server');
        }
    }

    public function testRejectsMalformedFileStructure(): void
    {
        $this->rejected([]);
        $this->rejected(['error' => UPLOAD_ERR_OK, 'tmp_name' => ['a'], 'name' => 'x']);
        $this->rejected(['error' => UPLOAD_ERR_OK, 'tmp_name' => '', 'name' => 'x']);
        $this->rejected(['error' => 'abc', 'tmp_name' => '/etc/passwd']);
        $this->rejected(ImageFixtures::upload($this->tmp . '/tidak-ada.png'));
    }

    public function testRejectsFileNotFromHttpUpload(): void
    {
        $path = ImageFixtures::image($this->tmp);
        $this->assertThrows(ValidationException::class, fn () => $this->uploader(false)->store(7, ImageFixtures::upload($path)));
        // Jalur produksi: berkas biasa (bukan hasil POST) ditolak is_uploaded_file().
        $this->assertThrows(ValidationException::class, fn () => (new LogoUploader($this->base))->store(7, ImageFixtures::upload($path)));
        $this->assertSame([], $this->stored());
    }

    public function testDirectoryIsCreatedPrivateAndFileIsNotWorldReadable(): void
    {
        $name = $this->uploader()->store(7, ImageFixtures::upload(ImageFixtures::image($this->tmp)));
        $this->assertSame(0, fileperms($this->base . '/7') & 0007);
        $this->assertSame(0, fileperms($this->base . '/7/' . $name) & 0007);
        $this->assertSame(0, fileperms($this->base . '/7/' . $name) & 0111, 'Berkas tidak boleh executable');
    }

    public function testDeleteIsConfinedToLogosDirectory(): void
    {
        $name = $this->uploader()->store(7, ImageFixtures::upload(ImageFixtures::image($this->tmp)));
        $victim = $this->tmp . '/uploads/victim.png';
        file_put_contents($victim, 'x');
        $outside = $this->tmp . '/outside.png';
        file_put_contents($outside, 'x');
        foreach (['../victim.png', '../../outside.png', '/etc/passwd', '..', '', "$name/../$name", 'x.php', strtoupper($name)] as $bad) {
            $this->uploader()->delete(7, $bad);
        }
        $this->assertTrue(is_file($victim));
        $this->assertTrue(is_file($outside));
        $this->assertTrue(is_file($this->base . '/7/' . $name), 'Nama tak valid tidak boleh menghapus apa pun');
        // Tenant lain tidak dapat menghapus berkas tenant ini lewat nama yang sama.
        $this->uploader()->delete(8, $name);
        $this->assertTrue(is_file($this->base . '/7/' . $name));
        $this->uploader()->delete(7, $name);
        $this->assertFalse(is_file($this->base . '/7/' . $name));
        $this->uploader()->delete(7, $name);
    }

    public function testPathOfIsTenantScopedAndValidated(): void
    {
        $name = $this->uploader()->store(7, ImageFixtures::upload(ImageFixtures::image($this->tmp)));
        $this->assertSame($this->base . '/7/' . $name, $this->uploader()->pathOf(7, $name));
        $this->assertSame(null, $this->uploader()->pathOf(8, $name));
        $this->assertSame(null, $this->uploader()->pathOf(7, '../7/' . $name));
        $this->assertSame(null, $this->uploader()->pathOf(7, '0123456789abcdef0123456789abcdef.png'));
    }
}
