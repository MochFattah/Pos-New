<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $map = [
        'App\\' => dirname(__DIR__) . '/app/',
        'Tests\\' => dirname(__DIR__) . '/tests/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});
