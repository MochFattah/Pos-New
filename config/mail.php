<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'driver' => Env::get('MAIL_DRIVER', 'log'),
    'host' => Env::get('MAIL_HOST', ''),
    'port' => (int) Env::get('MAIL_PORT', '587'),
    'username' => Env::get('MAIL_USERNAME', ''),
    'password' => Env::get('MAIL_PASSWORD', ''),
    'encryption' => Env::get('MAIL_ENCRYPTION', 'tls'),
    'from_address' => Env::get('MAIL_FROM_ADDRESS', ''),
    'from_name' => Env::get('MAIL_FROM_NAME', 'POS Retail'),
    'timeout' => (int) Env::get('MAIL_TIMEOUT', '10'),
    'ehlo_hostname' => Env::get('MAIL_EHLO_HOSTNAME', 'localhost'),
];
