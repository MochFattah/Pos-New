<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/** Dilempar TestCase::skip() agar Runner mencatat SKIP, bukan gagal. */
final class SkippedException extends RuntimeException
{
}
