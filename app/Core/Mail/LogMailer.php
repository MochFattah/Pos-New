<?php

declare(strict_types=1);

namespace App\Core\Mail;

/** Pengembangan saja: menulis email ke berkas log terpisah (bukan log aplikasi). */
final class LogMailer implements MailerInterface
{
    public function __construct(private string $path)
    {
    }

    public function send(string $to, string $subject, string $textBody): void
    {
        $dir = dirname($this->path);
        try {
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new MailException('Direktori log email tidak dapat dibuat.');
            }
        } catch (\ErrorException) {
            throw new MailException('Direktori log email tidak dapat dibuat.');
        }
        $clean = static fn (string $v): string => (string) preg_replace('/[\r\n]+/', ' ', $v);
        $block = sprintf(
            "=== %s ===\nTo: %s\nSubject: %s\n\n%s\n\n",
            date('Y-m-d H:i:s'),
            $clean($to),
            $clean($subject),
            $textBody
        );
        try {
            $written = @file_put_contents($this->path, $block, FILE_APPEND | LOCK_EX);
        } catch (\ErrorException) {
            $written = false;
        }
        if ($written === false) {
            throw new MailException('Log email tidak dapat ditulis.');
        }
    }
}
