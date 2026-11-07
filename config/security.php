<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'login' => [
        'max_attempts' => 5,
        'window_seconds' => 900,
    ],
    'reset_token' => ['ttl_seconds' => 3600],
    'verification_token' => ['ttl_seconds' => 86400],
    'session' => ['idle_seconds' => 28800],
    'demo' => ['default_ttl_seconds' => 86400],
    'install_token' => Env::get('INSTALL_TOKEN', ''),
];
