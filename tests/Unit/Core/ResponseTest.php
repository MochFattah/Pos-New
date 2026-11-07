<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Response;
use Tests\Support\TestCase;

final class ResponseTest extends TestCase
{
    public function testRedirectSetsLocationHeader(): void
    {
        $r = Response::redirect('/login');
        $this->assertSame(302, $r->status());
        $this->assertSame('/login', $r->header('Location'));
        $this->assertSame('', $r->body());
    }

    public function testJsonSetsContentTypeAndBody(): void
    {
        $r = Response::json(['a' => 1], 201);
        $this->assertSame(201, $r->status());
        $this->assertSame('application/json; charset=utf-8', $r->header('content-type'));
        $this->assertSame('{"a":1}', $r->body());
    }

    public function testWithHeaderReturnsNewInstance(): void
    {
        $a = Response::html('x');
        $b = $a->withHeader('X-Test', '1');
        $this->assertSame(null, $a->header('X-Test'));
        $this->assertSame('1', $b->header('x-test'));
        $this->assertSame('text/html; charset=utf-8', $b->header('Content-Type'));
    }
}
