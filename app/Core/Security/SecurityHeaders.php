<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Core\Response;

/** Satu-satunya sumber daftar header keamanan. */
final class SecurityHeaders
{
    public static function apply(Response $response, bool $hsts): Response
    {
        $response = $response
            ->withHeader('Content-Security-Policy', "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'")
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        return $hsts
            ? $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            : $response;
    }
}
