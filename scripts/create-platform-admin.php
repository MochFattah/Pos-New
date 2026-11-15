<?php

declare(strict_types=1);

/**
 * Membuat admin platform (Super Admin): php scripts/create-platform-admin.php <nama> <email>
 *
 * Hanya CLI. Kata sandi diminta lewat prompt tanpa echo (stty -echo) dan dikonfirmasi dua kali;
 * tidak pernah dari argumen. Bila stdin bukan TTY (pipe) baris dibaca apa adanya; bila TTY tetapi
 * stty tidak ada, skrip menolak. Email yang sudah ada = galat, tidak ada perubahan (aman diulang).
 *
 * Seam uji: konstanta POS_FORCE_SAPI (mis. lewat auto_prepend_file) hanya bisa MEMPERKETAT -
 * nilai selain 'cli' membuat skrip menolak seolah dijalankan dari web; tidak bisa memalsukan CLI.
 */
$sapi = PHP_SAPI;
if (defined('POS_FORCE_SAPI') && is_string(POS_FORCE_SAPI) && POS_FORCE_SAPI !== 'cli') {
    $sapi = POS_FORCE_SAPI;
}
if ($sapi !== 'cli') {
    // STDERR tidak terdefinisi di SAPI web; pakai stream eksplisit.
    file_put_contents('php://stderr', "Skrip ini hanya boleh dijalankan lewat CLI.\n");
    exit(1);
}

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Core\Database\Connection;
use App\Core\Env;
use App\Core\Security\PasswordHasher;
use App\Modules\AuditLogs\Repositories\AuditLogRepository;
use App\Modules\AuditLogs\Services\AuditLogger;
use App\Modules\Platform\Repositories\PlatformUserRepository;
use App\Modules\Platform\Services\PlatformAdminCli;
use App\Modules\Platform\Services\PlatformAdminCreator;

$code = PlatformAdminCli::run(
    $sapi,
    $argv,
    static fn (string $label): ?string => PlatformAdminCli::promptHidden($label),
    static function () use ($basePath): PlatformAdminCreator {
        Env::load($basePath . '/.env');
        Config::load($basePath);
        $db = Connection::fromConfig();
        return new PlatformAdminCreator(
            new PlatformUserRepository($db),
            new PasswordHasher(),
            new AuditLogger(new AuditLogRepository($db), static fn (): array => ['ip' => null, 'request_id' => null])
        );
    },
    $out,
    $err
);
fwrite(STDOUT, (string) $out);
fwrite(STDERR, (string) $err);
exit($code);
