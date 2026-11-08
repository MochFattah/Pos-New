<?php

declare(strict_types=1);

namespace App\Core\Security;

final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(Session $session): string
    {
        $token = $session->get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $session->set(self::KEY, $token);
        }
        return $token;
    }

    public static function isValid(Session $session, ?string $token): bool
    {
        $expected = $session->get(self::KEY);
        return is_string($expected) && $expected !== '' && $token !== null && $token !== ''
            && hash_equals($expected, $token);
    }
}
