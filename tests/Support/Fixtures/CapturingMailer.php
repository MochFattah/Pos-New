<?php

declare(strict_types=1);

namespace Tests\Support\Fixtures;

use App\Core\Mail\MailerInterface;
use App\Core\Mail\MailException;

final class CapturingMailer implements MailerInterface
{
    /** @var list<array{to: string, subject: string, body: string}> */
    public array $sent = [];

    public bool $fail = false;

    public function send(string $to, string $subject, string $textBody): void
    {
        if ($this->fail) {
            throw new MailException('SMTP down');
        }
        $this->sent[] = ['to' => $to, 'subject' => $subject, 'body' => $textBody];
    }

    /** Token polos dari tautan email terakhir. */
    public function lastToken(): string
    {
        $last = $this->sent[count($this->sent) - 1] ?? null;
        if ($last === null || preg_match('#/verifikasi-email/([a-f0-9]{64})#', $last['body'], $m) !== 1) {
            throw new \RuntimeException('Tidak ada tautan verifikasi.');
        }
        return $m[1];
    }
}
