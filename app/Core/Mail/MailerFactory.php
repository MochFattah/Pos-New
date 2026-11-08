<?php

declare(strict_types=1);

namespace App\Core\Mail;

use App\Core\Config;

final class MailerFactory
{
    public static function fromConfig(): MailerInterface
    {
        $cfg = Config::get('mail', []);
        return self::fromArray(is_array($cfg) ? $cfg : [], dirname(__DIR__, 3));
    }

    /**
     * @param array<string, mixed> $cfg
     * @param string $basePath dasar untuk path log relatif
     * @param callable|null $connector seam pengujian untuk SmtpMailer
     */
    public static function fromArray(array $cfg, string $basePath, ?callable $connector = null): MailerInterface
    {
        $driver = (string) ($cfg['driver'] ?? 'log');
        if ($driver === 'log') {
            $path = trim((string) ($cfg['log_path'] ?? ''));
            if ($path === '') {
                $path = 'storage/logs/mail.log';
            }
            $absolute = str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\/]#', $path) === 1;
            return new LogMailer($absolute ? $path : rtrim($basePath, '/') . '/' . $path);
        }
        if ($driver === 'smtp') {
            return new SmtpMailer(
                (string) ($cfg['host'] ?? ''),
                (int) ($cfg['port'] ?? 587),
                (string) ($cfg['username'] ?? ''),
                (string) ($cfg['password'] ?? ''),
                (string) ($cfg['from_address'] ?? ''),
                (string) ($cfg['encryption'] ?? 'tls'),
                $connector,
                (string) ($cfg['ehlo_hostname'] ?? 'localhost'),
                (float) ($cfg['timeout'] ?? 10),
                (string) ($cfg['from_name'] ?? '')
            );
        }
        throw new MailException('Driver email tidak dikenal.');
    }
}
