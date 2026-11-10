<?php

declare(strict_types=1);

namespace App\Modules\Tenants\Exceptions;

use RuntimeException;

final class DuplicateEmailException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Email sudah terdaftar.');
    }
}
