<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Skrip ini hanya boleh dijalankan lewat CLI.\n");
    exit(1);
}

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Core\Database\Connection;
use App\Core\Database\Migrator;
use App\Core\Env;

try {
    Env::load($basePath . '/.env');
    Config::load($basePath);
    $applied = (new Migrator(Connection::fromConfig(), $basePath . '/database/migrations'))->migrate();
} catch (Throwable $e) {
    // Hanya pesan; tanpa stack trace agar DSN/kredensial tidak bocor.
    fwrite(STDERR, 'Migrasi gagal: ' . $e->getMessage() . "\n");
    exit(1);
}

if ($applied === []) {
    echo "Tidak ada migrasi baru.\n";
    exit(0);
}
foreach ($applied as $name) {
    echo "Diterapkan: $name\n";
}
