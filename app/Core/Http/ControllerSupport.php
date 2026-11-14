<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Authorization\AuthUser;
use App\Core\Exceptions\NotFoundException;
use App\Core\Request;
use App\Core\Security\Csrf;
use App\Core\Security\Session;

/** Pembantu bersama controller yang berada di belakang AuthMiddleware. */
trait ControllerSupport
{
    private function user(Request $request): AuthUser
    {
        $user = $request->attribute('user');
        if (!$user instanceof AuthUser) {
            throw new \LogicException(static::class . ' membutuhkan AuthMiddleware.');
        }
        return $user;
    }

    private function id(Request $request, string $notFoundMessage): int
    {
        $raw = $request->attribute('id');
        if (!is_string($raw) || preg_match('/^[1-9][0-9]{0,17}\z/', $raw) !== 1) {
            throw new NotFoundException($notFoundMessage);
        }
        return (int) $raw;
    }

    private function session(Request $request): ?Session
    {
        $session = $request->attribute('session');
        return $session instanceof Session ? $session : null;
    }

    private function csrf(Request $request): string
    {
        $session = $this->session($request);
        return $session !== null ? Csrf::token($session) : '';
    }

    private function flash(Request $request, string $key, string $message): void
    {
        $this->session($request)?->flash($key, $message);
    }

    /** @return string|null pesan flash sekali pakai */
    private function pull(Request $request, string $key): ?string
    {
        $value = $this->session($request)?->pullFlash($key);
        return is_string($value) ? $value : null;
    }

    /** @return list<mixed> daftar dari input (mis. checkbox); bukan array dianggap kosong */
    private function listInput(Request $request, string $key): array
    {
        $value = $request->input($key);
        return is_array($value) ? array_values($value) : [];
    }

    /** @param array<string, string> $errors */
    private function firstError(array $errors): string
    {
        return $errors === [] ? 'Permintaan tidak dapat diproses.' : (string) reset($errors);
    }
}
