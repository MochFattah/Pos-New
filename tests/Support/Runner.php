<?php

declare(strict_types=1);

namespace Tests\Support;

use ErrorException;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

final class Runner
{
    /**
     * Menjalankan semua tes di bawah $dir (namespace Tests\ dipetakan ke $dir).
     *
     * @return array{passed:int, failed:int, lines:list<string>}
     */
    public static function run(string $dir, string $filter = ''): array
    {
        $dir = rtrim($dir, '/');
        $result = ['passed' => 0, 'failed' => 0, 'lines' => []];

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (str_ends_with($file->getFilename(), 'Test.php')) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        foreach ($files as $path) {
            $relative = substr($path, strlen($dir) + 1, -4);
            $class = 'Tests\\' . str_replace('/', '\\', $relative);

            if (!class_exists($class, true) && is_file($path)) {
                require_once $path;
            }
            if (!class_exists($class, false)) {
                self::fail($result, $path, "class $class tidak ditemukan (nama file/namespace tidak sesuai)");
                continue;
            }
            if (!is_subclass_of($class, TestCase::class)) {
                self::fail($result, $path, "class $class tidak meng-extend " . TestCase::class);
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
                self::runOne($result, $class, $method, $name);
            }
        }

        return $result;
    }

    /** @param array{passed:int, failed:int, lines:list<string>} $result */
    private static function runOne(array &$result, string $class, string $method, string $name): void
    {
        $case = new $class();
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            $case->runSetUp();
            $case->$method();
            $result['passed']++;
            $result['lines'][] = "PASS  $name";
        } catch (SkippedException $e) {
            $result['passed']++;
            $result['lines'][] = "SKIP  $name\n      " . $e->getMessage();
        } catch (Throwable $e) {
            $result['failed']++;
            $result['lines'][] = "FAIL  $name\n      " . $e::class . ': ' . $e->getMessage();
        } finally {
            try {
                $case->runTearDown();
            } catch (Throwable) {
            }
            restore_error_handler();
        }
    }

    /** @param array{passed:int, failed:int, lines:list<string>} $result */
    private static function fail(array &$result, string $path, string $reason): void
    {
        $result['failed']++;
        $result['lines'][] = "FAIL  $path\n      $reason";
    }
}
