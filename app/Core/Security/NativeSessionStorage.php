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
            // Folder sesi milik aplikasi: cron GC bawaan distro hanya membersihkan save_path bawaan.
            'gc_probability' => 1,
            'gc_divisor' => 100,
        ];
    }

    /**
     * Malas: sesi hanya dibuka bila permintaan membawa cookie ke berkas sesi yang ada.
     * Permintaan tanpa cookie (bot, 404, halaman publik) tidak membuat berkas; sesi baru
     * dibuat pada penulisan data pertama (replace/regenerateId).
     */
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if ($this->hasLiveSessionFile()) {
            $this->begin();
        }
    }

    public function id(): string
    {
        return session_status() === PHP_SESSION_ACTIVE ? (session_id() ?: '') : '';
    }

    public function all(): array
    {
        return session_status() === PHP_SESSION_ACTIVE ? $_SESSION : [];
    }

    public function replace(array $data): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if ($data === []) {
                return;
            }
            $this->begin();
        }
        $_SESSION = $data;
    }

    public function regenerateId(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->begin();
        }
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

    private function begin(): void
    {
        if (!is_dir($this->savePath) && !@mkdir($this->savePath, 0770, true) && !is_dir($this->savePath)) {
            throw new RuntimeException('Direktori sesi tidak dapat dibuat.');
        }
        session_name($this->name);
        session_save_path($this->savePath);
        if (!session_start($this->options())) {
            throw new RuntimeException('Sesi tidak dapat dimulai.');
        }
    }

    /** Cookie ada, bentuknya valid, dan berkas sesinya ada (ID acak/palsu tidak membuat berkas baru). */
    private function hasLiveSessionFile(): bool
    {
        $id = $_COOKIE[$this->name] ?? null;
        return is_string($id)
            && preg_match('/^[A-Za-z0-9,-]{16,128}$/', $id) === 1
            && is_file(rtrim($this->savePath, '/') . '/sess_' . $id);
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
