<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

final class ValidationException extends HttpException
{
    /**
     * @param array<string, string> $errors pesan per field
     */
    public function __construct(private array $errors = [], string $message = 'Data yang dikirim tidak valid.')
    {
        parent::__construct(422, $message);
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
