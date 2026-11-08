<?php

declare(strict_types=1);

namespace App\Core\Security;

/** Penyimpanan sesi di memori, khusus tes. */
final class ArraySessionStorage implements SessionStorageInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    private int $counter = 0;

    private string $id = '';

    public function start(): void
    {
        if ($this->id === '') {
            $this->id = 'array-' . ++$this->counter;
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function replace(array $data): void
    {
        $this->data = $data;
    }

    public function regenerateId(): void
    {
        $this->id = 'array-' . ++$this->counter;
    }

    public function destroy(): void
    {
        $this->data = [];
        $this->id = '';
    }
}
