<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap/autoload.php';

use Tests\Support\TestCase;

$filter = $argv[1] ?? '';
$passed = 0;
$failed = 0;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS));
$files = [];
foreach ($iterator as $file) {
    if (str_ends_with($file->getFilename(), 'Test.php')) {
        $files[] = $file->getPathname();
    }
}
sort($files);

foreach ($files as $path) {
    $relative = substr($path, strlen(__DIR__) + 1, -4);
    $class = 'Tests\\' . str_replace('/', '\\', $relative);
    if (!class_exists($class) || !is_subclass_of($class, TestCase::class)) {
        continue;
    }
    foreach (get_class_methods($class) as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }
        $name = $class . '::' . $method;
        if ($filter !== '' && !str_contains($name, $filter)) {
            continue;
        }
        $case = new $class();
        try {
            $case->runSetUp();
            $case->$method();
            $passed++;
            echo "PASS  $name\n";
        } catch (Throwable $e) {
            $failed++;
            echo "FAIL  $name\n      " . $e::class . ': ' . $e->getMessage() . "\n";
        } finally {
            try {
                $case->runTearDown();
            } catch (Throwable) {
            }
        }
    }
}

echo "\n" . ($passed + $failed) . " tes, $passed lulus, $failed gagal\n";
exit($failed > 0 ? 1 : 0);
