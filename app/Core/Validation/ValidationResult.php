<?php

declare(strict_types=1);

namespace App\Core\Validation;

final class ValidationResult
{
    /**
     * @param array<string, string> $errors satu pesan per field
     * @param array<string, string> $validated field yang lolos, sudah di-trim/dinormalisasi
     */
    public function __construct(private array $errors, private array $validated)
    {
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Hanya field beraturan yang lolos. Bila ada kegagalan, field yang gagal tidak
     * disertakan; pemanggil harus memeriksa fails() sebelum memakai hasilnya.
     *
     * @return array<string, string>
     */
    public function validated(): array
    {
        return $this->validated;
    }
}
