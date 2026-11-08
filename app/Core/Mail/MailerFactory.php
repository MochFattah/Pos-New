<?php

declare(strict_types=1);

namespace App\Core\Mail;

use App\Core\Config;

final class MailerFactory
{
    public static function fromConfig(): MailerInterface
    {
        $cfg = Config::get('mail', []);
        return self::fromArray(is_array($cfg) ? $cfg : [], dirname(__DIR__, 3) . '/storage/logs/mail.log');
    }

    /** @param array<string, mixed> $cfg */
    public static function fromArray(array $cfg, string $logPath): MailerInterface
    {
        $driver = (string) ($cfg['driver'] ?? 'log');
        if ($driver === 'log') {
            return new LogMailer($logPath);
        }
        if ($driver === 'smtp') {
            return new SmtpMailer(
                (string) ($cfg['host'] ?? ''),
                (int) ($cfg['port'] ?? 587),
                (string) ($cfg['username'] ?? ''),
                (string) ($cfg['password'] ?? ''),
                (string) ($cfg['from_address'] ?? ''),
                (string) ($cfg['encryption'] ?? 'tls'),
                null,
                (string) ($cfg['ehlo_hostname'] ?? 'localhost'),
                (int) ($cfg['timeout'] ?? 10),
                (string) ($cfg['from_name'] ?? '')
            );
        }
        throw new MailException('Driver email tidak dikenal.');
    }
}
