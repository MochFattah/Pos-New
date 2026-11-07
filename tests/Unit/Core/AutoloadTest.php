<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Tests\Support\TestCase;

final class AutoloadTest extends TestCase
{
    public function testResolvesAppClassByPsr4(): void
    {
        $this->assertTrue(class_exists(\App\Core\Config::class));
    }

    public function testResolvesTestsNamespace(): void
    {
        $this->assertTrue(class_exists(\Tests\Support\TestCase::class));
    }
}
