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
    'session' => [
        'idle_seconds' => 28800,
        // true | false | auto. Bawaan: true di production, selain itu auto (ikuti HTTPS request itu sendiri).
        'secure' => (static function (): string {
            $default = Env::get('APP_ENV', 'production') === 'production' ? 'true' : 'auto';
            $value = strtolower(trim((string) Env::get('SESSION_SECURE', $default)));
            return in_array($value, ['true', 'false', 'auto'], true) ? $value : $default;
        })(),
    ],
    'demo' => ['default_ttl_seconds' => 86400],
    'install_token' => Env::get('INSTALL_TOKEN', ''),
];
