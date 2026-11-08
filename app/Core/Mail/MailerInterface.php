<?php

declare(strict_types=1);

namespace App\Core\Mail;

interface MailerInterface
{
    /** @throws MailException bila pengiriman gagal */
    public function send(string $to, string $subject, string $textBody): void;
}
