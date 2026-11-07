<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => 'POS Retail',
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::get('APP_DEBUG', 'false') === 'true',
    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Jakarta'),
    'currency' => 'IDR',
];
