<?php

declare(strict_types=1);

namespace App\Modules\Tenants\Services;

use App\Core\Exceptions\ValidationException;

/**
 * Menyimpan logo tenant di luar document root: {base}/{tenantId}/{32 hex}.{png|jpg|webp}.
 *
 * Aturan keamanan:
 * - Nama berkas dibuat server (bin2hex(random_bytes(16))); nama dari klien tidak pernah dipakai.
 * - Ekstensi diturunkan dari MIME hasil finfo, bukan dari klaim klien; hanya png/jpeg/webp (SVG, GIF ditolak).
 * - getimagesize harus sepakat dengan finfo, dimensi dibatasi (bom dekompresi).
 * - Struktur: PNG harus memuat chunk IEND, JPEG penanda FFD9 (EOI), dengan trailer maksimal 4 KB sesudahnya
 *   (EXIF/padding/tanda tangan editor); WebP harus sesuai ukuran RIFF. Pemindaian "<?php" sengaja tidak ada:
 *   berkas tidak pernah di-include/dieksekusi dan menghasilkan false positive pada gambar sah. Berkas
 *   tersimpan di luar docroot tanpa bit eksekusi dan hanya disajikan lewat rute terkontrol dengan
 *   Content-Type gambar + nosniff + CSP sandbox.
 * - Hapus/baca hanya untuk nama yang cocok pola ketat di dalam direktori tenant yang bersangkutan.
 */
class LogoUploader
{
    public const MAX_BYTES = 1048576;
    private const MAX_TRAILER = 4096;
    private const MAX_PIXELS = 25_000_000;
    private const NAME_PATTERN = '/^[a-f0-9]{32}\.(png|jpg|webp)\z/';

    private const TYPES = [
        'image/png' => ['png', IMAGETYPE_PNG],
        'image/jpeg' => ['jpg', IMAGETYPE_JPEG],
        'image/webp' => ['webp', IMAGETYPE_WEBP],
    ];

    /** @var callable(string): bool */
    private $isUploaded;

    /** @var callable(string, string): bool */
    private $mover;

    /**
     * @param (callable(string): bool)|null $isUploaded seam uji; produksi memakai is_uploaded_file
     * @param (callable(string, string): bool)|null $mover seam uji; produksi memakai move_uploaded_file
     */
    public function __construct(private readonly string $baseDir, ?callable $isUploaded = null, ?callable $mover = null)
    {
        $this->isUploaded = $isUploaded ?? static fn (string $f): bool => is_uploaded_file($f);
        $this->mover = $mover ?? static fn (string $from, string $to): bool => move_uploaded_file($from, $to);
    }

    /**
     * @param array<string, mixed> $file entri $_FILES
     * @return string nama berkas tersimpan (bukan path)
     * @throws ValidationException field 'logo'
     */
    public function store(int $tenantId, array $file): string
    {
        if ($tenantId < 1) {
            throw new \InvalidArgumentException('tenantId tidak valid.');
        }
        $tmp = $this->checkUpload($file);

        clearstatcache(true, $tmp);
        $size = is_file($tmp) ? filesize($tmp) : false;
        if ($size === false || $size < 1) {
            $this->fail('Berkas logo kosong atau tidak terbaca.');
        }
        if ($size > self::MAX_BYTES) {
            $this->fail('Ukuran logo maksimal 1 MB.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!is_string($mime) || !isset(self::TYPES[$mime])) {
            $this->fail('Logo harus berupa gambar PNG, JPEG, atau WebP.');
        }
        [$ext, $imageType] = self::TYPES[$mime];

        $info = @getimagesize($tmp);
        if ($info === false || $info[2] !== $imageType || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_PIXELS) {
            $this->fail('Berkas logo bukan gambar yang valid.');
        }
        $bytes = file_get_contents($tmp);
        if ($bytes === false || !self::cleanStructure($mime, $bytes)) {
            $this->fail('Berkas logo bukan gambar yang valid.');
        }

        $dir = $this->tenantDir($tenantId);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            $this->fail('Logo tidak dapat disimpan di server. Hubungi dukungan.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $target = $dir . '/' . $name;
        if (!($this->mover)($tmp, $target)) {
            $this->fail('Logo tidak dapat disimpan di server. Hubungi dukungan.');
        }
        @chmod($target, 0640);
        return $name;
    }

    /** Menghapus logo bila nama valid dan berkas ada di direktori tenant ini; selain itu tidak melakukan apa pun. */
    public function delete(int $tenantId, string $name): void
    {
        $path = $this->pathOf($tenantId, $name);
        if ($path !== null) {
            @unlink($path);
        }
    }

    /** @return string|null path berkas bila nama valid, bukan symlink, dan ada di direktori tenant ini */
    public function pathOf(int $tenantId, string $name): ?string
    {
        if ($tenantId < 1 || preg_match(self::NAME_PATTERN, $name) !== 1) {
            return null;
        }
        $path = $this->tenantDir($tenantId) . '/' . $name;
        return is_file($path) && !is_link($path) ? $path : null;
    }

    /** @return string|null MIME dari ekstensi nama buatan server; null bila nama tidak sah */
    public static function mimeOf(string $name): ?string
    {
        if (preg_match(self::NAME_PATTERN, $name, $m) !== 1) {
            return null;
        }
        foreach (self::TYPES as $mime => [$ext]) {
            if ($ext === $m[1]) {
                return $mime;
            }
        }
        return null;
    }

    private function tenantDir(int $tenantId): string
    {
        return rtrim($this->baseDir, '/') . '/' . $tenantId;
    }

    /** @param array<string, mixed> $file @return string path berkas sementara */
    private function checkUpload(array $file): string
    {
        $error = $file['error'] ?? null;
        if (!is_int($error)) {
            $this->fail('Berkas logo tidak valid.');
        }
        match ($error) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $this->fail('Ukuran logo maksimal 1 MB.'),
            UPLOAD_ERR_PARTIAL => $this->fail('Logo hanya terunggah sebagian. Coba lagi.'),
            UPLOAD_ERR_NO_FILE => $this->fail('Tidak ada berkas logo yang dipilih.'),
            default => $this->fail('Logo tidak dapat diproses karena kesalahan di server. Hubungi dukungan.'),
        };
        $tmp = $file['tmp_name'] ?? null;
        if (!is_string($tmp) || $tmp === '' || str_contains($tmp, "\0") || !($this->isUploaded)($tmp)) {
            $this->fail('Berkas logo tidak valid.');
        }
        return $tmp;
    }

    private static function cleanStructure(string $mime, string $bytes): bool
    {
        $len = strlen($bytes);
        return match ($mime) {
            'image/png' => self::endsWithinTrailer($bytes, "\0\0\0\0IEND\xAE\x42\x60\x82"),
            'image/jpeg' => self::endsWithinTrailer($bytes, "\xFF\xD9"),
            'image/webp' => $len >= 12 && (int) unpack('V', substr($bytes, 4, 4))[1] + 8 === $len,
            default => false,
        };
    }

    /** Penanda akhir harus ada dan sisa byte sesudahnya tidak lebih dari MAX_TRAILER. */
    private static function endsWithinTrailer(string $bytes, string $marker): bool
    {
        $pos = strrpos($bytes, $marker);
        return $pos !== false && strlen($bytes) - ($pos + strlen($marker)) <= self::MAX_TRAILER;
    }

    /** @return never */
    private function fail(string $message): void
    {
        throw new ValidationException(['logo' => $message]);
    }
}
