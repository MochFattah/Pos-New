<?php

declare(strict_types=1);

namespace App\Core\Mail;

/** Membangun mailer sungguhan saat kirim pertama, sehingga konfigurasi yang ditolak tidak merusak halaman yang tidak mengirim email. */
final class LazyMailer implements MailerInterface
{
    private ?MailerInterface $inner = null;

    /** @param callable(): MailerInterface $factory */
    public function __construct(private $factory)
    {
    }

    public function send(string $to, string $subject, string $textBody): void
    {
        $this->inner ??= ($this->factory)();
        $this->inner->send($to, $subject, $textBody);
    }
}
