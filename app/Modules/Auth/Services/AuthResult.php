<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Core\Authorization\AuthUser;

final class AuthResult
{
    public const OK = 'ok';
    public const FAILED = 'failed';
    public const LOCKED = 'locked';
    public const UNVERIFIED = 'unverified';

    private function __construct(public readonly string $status, public readonly ?AuthUser $user = null)
    {
    }

    public static function ok(AuthUser $user): self
    {
        return new self(self::OK, $user);
    }

    public static function failed(): self
    {
        return new self(self::FAILED);
    }

    public static function locked(): self
    {
        return new self(self::LOCKED);
    }

    public static function unverified(): self
    {
        return new self(self::UNVERIFIED);
    }

    public function isOk(): bool
    {
        return $this->status === self::OK;
    }
}
