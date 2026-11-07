<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Request;
use Tests\Support\TestCase;

final class RequestTest extends TestCase
{
    public function testQueryStringIsStrippedFromPath(): void
    {
        $r = Request::create('GET', '/a/b?x=1&y=2');
        $this->assertSame('/a/b', $r->path());
        $this->assertSame('/a/b', $r->rawPath());
        $this->assertSame('1', $r->input('x'));
    }

    public function testRawPathStaysEncodedWhilePathIsDecodedOnce(): void
    {
        $r = Request::create('GET', '/x/%2541');
        $this->assertSame('/x/%2541', $r->rawPath());
        $this->assertSame('/x/%41', $r->path());
    }
}
