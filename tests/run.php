<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap/autoload.php';

use Tests\Support\Runner;

$result = Runner::run(__DIR__, $argv[1] ?? '');

foreach ($result['lines'] as $line) {
    echo $line . "\n";
}
echo "\n" . ($result['passed'] + $result['failed'] + $result['skipped']) . " tes, {$result['passed']} lulus, {$result['skipped']} dilewati, {$result['failed']} gagal\n";
exit($result['failed'] > 0 ? 1 : 0);
