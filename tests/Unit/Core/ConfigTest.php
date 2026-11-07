<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Config;
use App\Core\Env;
use Tests\Support\TestCase;

final class ConfigTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pos-config-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/config', 0777, true);
        foreach (glob(__DIR__ . '/../../../config/*.php') as $file) {
            copy($file, $this->dir . '/config/' . basename($file));
        }
        Env::reset();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/config/*') ?: [] as $f) {
            unlink($f);
        }
        @rmdir($this->dir . '/config');
        @unlink($this->dir . '/.env');
        @rmdir($this->dir);
        Env::reset();
    }

    public function testReadsNestedKeyWithDefault(): void
    {
        Config::load($this->dir);
        $this->assertSame(5, Config::get('security.login.max_attempts'));
        $this->assertSame(900, Config::get('security.login.window_seconds'));
        $this->assertSame('Asia/Jakarta', Config::get('app.timezone'));
        $this->assertSame('d', Config::get('x.y', 'd'));
    }

    public function testEnvOverridesValue(): void
    {
        file_put_contents($this->dir . '/.env', "APP_TIMEZONE=Asia/Makassar\n# komentar\n");
        Env::load($this->dir . '/.env');
        Config::load($this->dir);
        $this->assertSame('Asia/Makassar', Config::get('app.timezone'));
    }

    public function testEnvParsesQuotesAndMissingFile(): void
    {
        file_put_contents($this->dir . '/.env', "APP_TIMEZONE=\"Asia/Jayapura\"\n");
        Env::load($this->dir . '/.env');
        $this->assertSame('Asia/Jayapura', Env::get('APP_TIMEZONE'));
        Env::load($this->dir . '/tidak-ada.env');
        $this->assertSame('z', Env::get('TIDAK_ADA_KEY', 'z'));
    }
}
