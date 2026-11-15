<?php

declare(strict_types=1);

namespace App\Modules\Platform\Exceptions;

use RuntimeException;

/** Input pembuatan admin platform tidak valid; pesan aman ditampilkan ke operator. */
class PlatformAdminException extends RuntimeException
{
}
