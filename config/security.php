<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'login' => [
        'max_attempts' => 5,
        'window_seconds' => 900,
        // Agregat per IP (semua email) untuk menahan password spraying.
        'ip_max_attempts' => (int) Env::get('LOGIN_IP_MAX_ATTEMPTS', '50'),
    ],
    // Login Super Admin: per (email, IP) dan agregat per IP; namespace identifier terpisah dari tenant.
    'platform_login' => [
        'max_attempts' => (int) Env::get('PLATFORM_LOGIN_MAX_ATTEMPTS', '5'),
        'window_seconds' => (int) Env::get('PLATFORM_LOGIN_WINDOW_SECONDS', '900'),
        'ip_max_attempts' => (int) Env::get('PLATFORM_LOGIN_IP_MAX_ATTEMPTS', '20'),
    ],
    // Permintaan tautan reset: per (email, IP) dan per IP.
    'reset_request' => [
        'max_attempts' => (int) Env::get('RESET_REQUEST_MAX_ATTEMPTS', '5'),
        'window_seconds' => (int) Env::get('RESET_REQUEST_WINDOW_SECONDS', '3600'),
    ],
    // Registrasi: lebih longgar dari login; hanya percobaan yang lolos validasi dihitung.
    'registration' => [
        'max_attempts' => (int) Env::get('REGISTRATION_MAX_ATTEMPTS', '20'),
        'window_seconds' => (int) Env::get('REGISTRATION_WINDOW_SECONDS', '3600'),
    ],
    // Kirim ulang undangan pengguna: per pengguna yang diundang.
    'invitation_resend' => [
        'max_attempts' => (int) Env::get('INVITATION_RESEND_MAX_ATTEMPTS', '3'),
        'window_seconds' => (int) Env::get('INVITATION_RESEND_WINDOW_SECONDS', '3600'),
    ],
    // Undangan pengguna baru: kuota per tenant (jendela geser) dan batas undangan yang belum diterima.
    // Menahan penyalahgunaan (pemakaian email sebagai relay, penguncian email orang lain).
    'invite' => [
        'max_attempts' => (int) Env::get('INVITE_MAX_ATTEMPTS', '20'),
        'window_seconds' => (int) Env::get('INVITE_WINDOW_SECONDS', '3600'),
        'max_pending' => (int) Env::get('INVITE_MAX_PENDING', '50'),
        // Baris pengguna pending non-Owner yang lebih tua dari ini (dan tanpa token valid) dianggap basi: emailnya dibebaskan.
        'stale_seconds' => (int) Env::get('INVITE_STALE_SECONDS', '86400'),
    ],
    'invitation_token' => ['ttl_seconds' => 86400],
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
    // Proxy terbalik tepercaya (IP/CIDR dipisah koma). Kosong = X-Forwarded-For diabaikan, IP klien = REMOTE_ADDR.
    'trusted_proxies' => array_values(array_filter(
        array_map('trim', explode(',', (string) Env::get('TRUSTED_PROXIES', ''))),
        static fn (string $v): bool => $v !== ''
    )),
    'demo' => ['default_ttl_seconds' => 86400],
    'install_token' => Env::get('INSTALL_TOKEN', ''),
];
