<?php

declare(strict_types=1);

namespace App\Core;

/** Penanda HTML tepercaya; satu-satunya nilai yang tidak di-escape oleh View::e(). */
final class RawHtml
{
    public function __construct(private readonly string $html)
    {
    }

    public function __toString(): string
    {
        return $this->html;
    }
}
