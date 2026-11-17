<?php

declare(strict_types=1);

namespace App\Core\Layout;

/**
 * Data kerangka aplikasi untuk layouts/app: nilai tak berubah yang dibangun AppShellMiddleware per
 * permintaan dan dibawa lewat atribut request (bukan state global), lalu dioper controller ke View.
 */
final class AppShell
{
    /** @param list<array{key: string, label: string, href: string, current: bool}> $nav */
    public function __construct(
        public readonly string $businessName,
        public readonly string $userName,
        public readonly string $csrf,
        public readonly array $nav = [],
    ) {
    }

    /** Cadangan bila controller dipanggil tanpa AppShellMiddleware: tanpa menu, tetap ada tombol keluar. */
    public static function minimal(string $userName, string $csrf): self
    {
        return new self('', $userName, $csrf);
    }
}
