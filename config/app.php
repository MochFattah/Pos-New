<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => 'POS Retail',
    // Basis URL publik untuk tautan di email (tanpa garis miring akhir).
    'url' => rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::get('APP_DEBUG', 'false') === 'true',
    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Jakarta'),
    'currency' => 'IDR',
];
