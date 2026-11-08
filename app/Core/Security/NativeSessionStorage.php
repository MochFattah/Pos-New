<?php

declare(strict_types=1);

namespace App\Core\Security;

use RuntimeException;

class NativeSessionStorage implements SessionStorageInterface
{
    private const GC_MARGIN = 3600;

    /**
     * @param bool $forceSecure cookie selalu Secure (config security.session.secure = true)
     * @param bool $requestHttps request ini sendiri HTTPS ($_SERVER['HTTPS']); X-Forwarded-Proto tidak dipercaya
     */
    public function __construct(
        private readonly string $savePath,
        private readonly bool $forceSecure,
        private readonly int $idleSeconds,
        private readonly bool $requestHttps,
        private readonly string $name = 'pos_session',
    ) {
    }

    /** @return array<string, int|string> */
    public function options(): array
    {
        return [
            'use_strict_mode' => 1,
            'use_only_cookies' => 1,
            'cookie_httponly' => 1,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => $this->isSecure() ? 1 : 0,
            'gc_maxlifetime' => $this->idleSeconds + self::GC_MARGIN,
        ];
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
        if (!session_start($this->options())) {
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
        if (!$this->nativeRegenerate()) {
            throw new RuntimeException('ID sesi gagal diperbarui.');
        }
    }

    public function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        $this->expireCookie();
    }

    protected function nativeRegenerate(): bool
    {
        return session_regenerate_id(true);
    }

    protected function expireCookie(): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie($this->name, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $this->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function isSecure(): bool
    {
        return $this->forceSecure || $this->requestHttps;
    }
}
