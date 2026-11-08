<?php

declare(strict_types=1);

namespace App\Core\Security;

use Closure;

final class Session
{
    private const FLASH = '_flash';
    private const ACTIVITY = '_last_activity';

    private Closure $clock;

    /** @param (callable(): int)|null $clock */
    public function __construct(private readonly SessionStorageInterface $storage, ?callable $clock = null)
    {
        $this->clock = $clock !== null ? Closure::fromCallable($clock) : static fn (): int => time();
    }

    public function start(): void
    {
        $this->storage->start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->storage->all()[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $data = $this->storage->all();
        $data[$key] = $value;
        $this->storage->replace($data);
    }

    public function forget(string $key): void
    {
        $data = $this->storage->all();
        unset($data[$key]);
        $this->storage->replace($data);
    }

    public function flash(string $key, mixed $value): void
    {
        $flash = (array) $this->get(self::FLASH, []);
        $flash[$key] = $value;
        $this->set(self::FLASH, $flash);
    }

    public function pullFlash(string $key): mixed
    {
        $flash = (array) $this->get(self::FLASH, []);
        $value = $flash[$key] ?? null;
        unset($flash[$key]);
        $this->set(self::FLASH, $flash);
        return $value;
    }

    public function regenerate(): void
    {
        $this->storage->regenerateId();
    }

    public function destroy(): void
    {
        $this->storage->destroy();
    }

    public function isIdleExpired(int $maxIdleSeconds): bool
    {
        $last = $this->get(self::ACTIVITY);
        return is_int($last) && ($this->clock)() - $last > $maxIdleSeconds;
    }
}
