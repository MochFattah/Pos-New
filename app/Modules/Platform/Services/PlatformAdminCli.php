<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Exceptions\PlatformAdminException;
use Throwable;

/**
 * Logika skrip scripts/create-platform-admin.php agar dapat diuji. Password tidak pernah dari argv:
 * hanya lewat prompt (tanpa echo di TTY; dari pipe stdin bila bukan TTY).
 */
final class PlatformAdminCli
{
    /**
     * @param list<string> $argv termasuk nama skrip di indeks 0
     * @param callable(string): ?string $prompt label => jawaban, null bila EOF/gagal
     * @param callable(): PlatformAdminCreator $creator dibuat malas, setelah argumen dan password valid
     * @return int kode keluar: 0 sukses, 1 gagal, 2 salah pakai
     */
    public static function run(string $sapi, array $argv, callable $prompt, callable $creator, ?string &$out = null, ?string &$err = null): int
    {
        $out = '';
        $err = '';
        if ($sapi !== 'cli') {
            $err = "Skrip ini hanya boleh dijalankan lewat CLI.\n";
            return 1;
        }
        if (count($argv) !== 3) {
            $err = "Penggunaan: php scripts/create-platform-admin.php <nama> <email>\nKata sandi diminta lewat prompt, tidak diterima sebagai argumen.\n";
            return 2;
        }
        [, $name, $email] = $argv;

        $password = $prompt('Kata sandi: ');
        if ($password === null || $password === '') {
            $err = "Kata sandi tidak dibaca.\n";
            return 1;
        }
        $confirm = $prompt('Ulangi kata sandi: ');
        if ($confirm === null || !hash_equals($password, $confirm)) {
            $err = "Kedua kata sandi tidak sama.\n";
            return 1;
        }

        try {
            $id = $creator()->create($name, $email, $password);
        } catch (PlatformAdminException $e) {
            $err = $e->getMessage() . "\n";
            return 1;
        } catch (Throwable $e) {
            // Hanya pesan, tanpa stack trace, agar kredensial/DSN tidak bocor.
            $err = 'Gagal membuat admin platform: ' . $e->getMessage() . "\n";
            return 1;
        }
        $out = "Admin platform dibuat (id $id).\n";
        return 0;
    }

    /**
     * Membaca satu baris. Di TTY: matikan echo lewat stty dan pulihkan selalu; bila TTY tetapi stty
     * tidak tersedia, tolak (null) daripada menampilkan kata sandi. Bukan TTY (pipe): baca apa adanya.
     *
     * @param resource $in
     */
    public static function promptHidden(string $label, $in = null): ?string
    {
        $in ??= STDIN;
        fwrite(STDERR, $label);
        $tty = function_exists('stream_isatty') && stream_isatty($in);
        $saved = null;
        if ($tty) {
            $saved = self::stty('-g');
            if ($saved === null || self::stty('-echo') === null) {
                fwrite(STDERR, "\nTidak dapat menonaktifkan echo terminal (stty); kata sandi tidak dibaca.\n");
                return null;
            }
        }
        try {
            $line = fgets($in);
        } finally {
            if ($saved !== null) {
                self::stty($saved);
                fwrite(STDERR, "\n");
            }
        }
        return $line === false ? null : rtrim($line, "\r\n");
    }

    private static function stty(string $arg): ?string
    {
        if (!function_exists('exec')) {
            return null;
        }
        $lines = [];
        $code = 1;
        @exec('stty ' . escapeshellarg($arg) . ' 2>/dev/null', $lines, $code);
        return $code === 0 ? implode("\n", $lines) : null;
    }
}
