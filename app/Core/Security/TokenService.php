<?php

declare(strict_types=1);

namespace App\Core\Security;

final class TokenService
{
    /** @return array{plain: string, hash: string} */
    public function generate(): array
    {
        $plain = bin2hex(random_bytes(32));
        return ['plain' => $plain, 'hash' => $this->hash($plain)];
    }

    public function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
