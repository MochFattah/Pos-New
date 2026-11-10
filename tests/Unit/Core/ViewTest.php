<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\View;
use InvalidArgumentException;
use Tests\Support\TestCase;

final class ViewTest extends TestCase
{
    public function testViewEscapesUserInput(): void
    {
        $html = View::render('auth/notice', [
            'title' => '<b>x</b>',
            'heading' => '<script>alert(1)</script>',
            'message' => "a \"b\" 'c' & d",
        ]);
        $this->assertContains('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertFalse(str_contains($html, '<script>'));
        $this->assertContains('&quot;b&quot; &#039;c&#039; &amp; d', $html);
        $this->assertContains('<title>&lt;b&gt;x&lt;/b&gt; - POS Retail</title>', $html);
    }

    public function testRawIsOnlyWayToSkipEscaping(): void
    {
        $this->assertSame('&lt;i&gt;', View::e('<i>'));
        $this->assertSame('<i>', View::e(View::raw('<i>')));
        $this->assertSame('', View::e(['x']));
    }

    public function testPathTraversalIsRejected(): void
    {
        foreach (['../config/app', 'auth/../../config/app', '/etc/passwd', 'auth/notice.php', "auth/notice\0", "auth/notice\n"] as $name) {
            $this->assertThrows(InvalidArgumentException::class, fn () => View::render($name, ['title' => 't']));
        }
        $this->assertThrows(InvalidArgumentException::class, fn () => View::render('auth/notice', ['title' => 't', 'heading' => 'h', 'message' => 'm'], '../x'));
        $this->assertThrows(InvalidArgumentException::class, fn () => View::render('auth/tidak-ada'));
    }
}
