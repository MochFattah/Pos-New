<?php

declare(strict_types=1);

namespace App\Modules\Install;

use Closure;

/**
 * Pemeriksaan prasyarat penginstal. Hanya membaca keadaan server; tidak membuat berkas apa pun
 * (termasuk .env) dan tidak membuka koneksi database.
 */
final class PreflightChecker
{
    public const MIN_PHP_ID = 80300;

    /** Ekstensi yang benar-benar dipakai kode: PDO MySQL, mbstring, fileinfo (logo), openssl (SMTP TLS). */
    public const EXTENSIONS = [
        'pdo_mysql' => 'koneksi MySQL',
        'mbstring' => 'pemrosesan teks UTF-8',
        'fileinfo' => 'pemeriksaan jenis berkas logo',
        'openssl' => 'SMTP terenkripsi dan token acak',
    ];

    private Closure $extensionLoaded;

    /** @param (callable(string): bool)|null $extensionLoaded seam uji; bawaan extension_loaded() */
    public function __construct(
        private readonly string $basePath,
        private readonly string $migrationsDir,
        private readonly int $phpVersionId = PHP_VERSION_ID,
        ?callable $extensionLoaded = null,
    ) {
        $this->extensionLoaded = $extensionLoaded !== null
            ? Closure::fromCallable($extensionLoaded)
            : static fn (string $name): bool => extension_loaded($name);
    }

    /** @return list<array{label: string, ok: bool, message: string}> */
    public function run(): array
    {
        $checks = [];
        $phpOk = $this->phpVersionId >= self::MIN_PHP_ID;
        $checks[] = [
            'label' => 'PHP 8.3 atau lebih baru',
            'ok' => $phpOk,
            'message' => $phpOk ? 'Lulus.' : 'Versi PHP terlalu lama. Pilih PHP 8.3 atau lebih baru di MultiPHP Manager cPanel.',
        ];
        foreach (self::EXTENSIONS as $ext => $purpose) {
            $ok = ($this->extensionLoaded)($ext);
            $checks[] = [
                'label' => 'Ekstensi ' . $ext,
                'ok' => $ok,
                'message' => $ok ? 'Lulus.' : "Ekstensi $ext belum aktif (dipakai untuk $purpose). Aktifkan di Select PHP Version / MultiPHP INI Editor cPanel atau minta penyedia hosting.",
            ];
        }

        $storage = $this->basePath . '/storage';
        $storageOk = is_dir($storage) && is_writable($storage);
        $checks[] = [
            'label' => 'Folder storage/ dapat ditulis',
            'ok' => $storageOk,
            'message' => $storageOk ? 'Lulus.' : 'Folder storage/ tidak ada atau tidak dapat ditulis oleh PHP. Atur izin folder menjadi 750 atau 755 sesuai pemilik akun.',
        ];

        $envOk = is_file($this->basePath . '/.env') && is_readable($this->basePath . '/.env');
        $checks[] = [
            'label' => 'Berkas .env tersedia',
            'ok' => $envOk,
            'message' => $envOk ? 'Lulus.' : 'Berkas .env belum ada. Salin .env.example menjadi .env lalu isi pengaturan database dan INSTALL_TOKEN. Penginstal tidak membuatnya otomatis.',
        ];

        $sql = is_dir($this->migrationsDir) ? (glob(rtrim($this->migrationsDir, '/') . '/*.sql') ?: []) : [];
        $migrationsOk = $sql !== [] && array_reduce($sql, static fn (bool $c, string $f): bool => $c && is_readable($f), true);
        $checks[] = [
            'label' => 'Berkas migrasi database terbaca',
            'ok' => $migrationsOk,
            'message' => $migrationsOk ? 'Lulus.' : 'Folder database/migrations tidak berisi berkas .sql yang dapat dibaca. Unggah ulang seluruh berkas aplikasi.',
        ];

        return $checks;
    }

    /** @param list<array{label: string, ok: bool, message: string}> $checks */
    public static function allPassed(array $checks): bool
    {
        foreach ($checks as $check) {
            if (!$check['ok']) {
                return false;
            }
        }
        return true;
    }
}
