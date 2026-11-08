<?php

declare(strict_types=1);

namespace App\Core\Security;

use RuntimeException;

final class NativeSessionStorage implements SessionStorageInterface
{
    public function __construct(
        private readonly string $savePath,
        private readonly bool $secure = true,
        private readonly string $name = 'pos_session',
    ) {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (!is_dir($this->savePath) && !@mkdir($this->savePath, 0770, true) && !is_dir($this->savePath)) {
            throw new RuntimeException('Direktori sesi tidak dapat dibuat.');
        }
        session_name($this->name);
        session_save_path($this->savePath);
        $started = session_start([
            'use_strict_mode' => 1,
            'use_only_cookies' => 1,
            'cookie_httponly' => 1,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => $this->secure ? 1 : 0,
        ]);
        if (!$started) {
            throw new RuntimeException('Sesi tidak dapat dimulai.');
        }
    }

    public function id(): string
    {
        return session_id() ?: '';
    }

    public function all(): array
    {
        return $_SESSION;
    }

    public function replace(array $data): void
    {
        $_SESSION = $data;
    }

    public function regenerateId(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }
}
