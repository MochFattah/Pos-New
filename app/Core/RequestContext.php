<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Konteks permintaan yang dibagi satu proses: IP klien dan ID permintaan yang
 * dibuat sekali, dipakai halaman 500 dan audit log agar bisa dicocokkan.
 */
final class RequestContext
{
    private ?Request $request = null;
    private ?string $requestId = null;

    public function bind(Request $request): void
    {
        $this->request = $request;
    }

    public function requestId(): string
    {
        return $this->requestId ??= bin2hex(random_bytes(6));
    }

    /** @return array{ip: ?string, request_id: string} */
    public function toArray(): array
    {
        return ['ip' => $this->request?->ip(), 'request_id' => $this->requestId()];
    }
}
