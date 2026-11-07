<?php

declare(strict_types=1);

namespace Tests\Support\Fixtures;

use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

final class DummyController
{
    public function __construct(private string $dep)
    {
    }

    public function show(Request $r): Response
    {
        return Response::html($this->dep . '-' . $r->attribute('n'));
    }
}
