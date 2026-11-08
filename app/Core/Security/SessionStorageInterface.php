<?php

declare(strict_types=1);

namespace App\Core\Security;

interface SessionStorageInterface
{
    public function start(): void;

    public function id(): string;

    /** @return array<string, mixed> */
    public function all(): array;

    /** @param array<string, mixed> $data */
    public function replace(array $data): void;

    public function regenerateId(): void;

    public function destroy(): void;
}
