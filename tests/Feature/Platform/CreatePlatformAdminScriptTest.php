<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Platform\Services\PlatformAdminCli;
use Tests\Support\PlatformHarness;

final class CreatePlatformAdminScriptTest extends PlatformHarness
{
    private string $script;

    protected function setUp(): void
    {
        parent::setUp();
        $this->script = dirname(__DIR__, 3) . '/scripts/create-platform-admin.php';
    }

    /** @return array{0: int, 1: string, 2: string} */
    private function runScript(array $args, string $stdin = '', ?string $forceSapi = null): array
    {
        $cmd = [PHP_BINARY];
        $prepend = null;
        if ($forceSapi !== null) {
            $prepend = tempnam(sys_get_temp_dir(), 'sapi');
            file_put_contents($prepend, "<?php define('POS_FORCE_SAPI', " . var_export($forceSapi, true) . ');');
            array_push($cmd, '-d', 'auto_prepend_file=' . $prepend);
        }
        $cmd[] = $this->script;
        array_push($cmd, ...$args);
        $p = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname($this->script, 2));
        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        $code = proc_close($p);
        if ($prepend !== null) {
            unlink($prepend);
        }
        return [$code, $out, $err];
    }

    public function testScriptRefusesNonCli(): void
    {
        foreach (['cgi-fcgi', 'apache2handler', 'fpm-fcgi'] as $sapi) {
            [$code, $out, $err] = $this->runScript(['Nama', 'a@example.com'], "sandi-aman-123\nsandi-aman-123\n", $sapi);
            $this->assertSame(1, $code, $sapi);
            $this->assertContains('hanya boleh dijalankan lewat CLI', $err);
            $this->assertSame('', $out);
        }
        $this->assertSame(1, PlatformAdminCli::run('cgi-fcgi', ['x', 'Nama', 'a@example.com'], fn (string $p): ?string => 'sandi-aman-123', $this->cliCreator(), $o, $e));
        $this->assertSame(0, $this->count('SELECT COUNT(*) AS n FROM platform_users'));
    }

    public function testScriptNeverTakesPasswordFromArgv(): void
    {
        [$code, , $err] = $this->runScript(['Nama', 'a@example.com', 'sandi-aman-123']);
        $this->assertSame(2, $code);
        $this->assertContains('Penggunaan', $err);
        [$code] = $this->runScript([]);
        $this->assertSame(2, $code);
    }

    /** @return callable(): \App\Modules\Platform\Services\PlatformAdminCreator */
    private function cliCreator(): callable
    {
        return fn () => $this->creator;
    }

    /** @param list<string> $answers */
    private function cli(array $args, array $answers, ?string &$out = null, ?string &$err = null): int
    {
        $queue = $answers;
        $prompt = static function (string $label) use (&$queue): ?string {
            return array_shift($queue);
        };
        return PlatformAdminCli::run('cli', array_merge(['create-platform-admin.php'], $args), $prompt, $this->cliCreator(), $out, $err);
    }

    public function testCliCreatesAdminWhenPasswordConfirmedTwice(): void
    {
        $code = $this->cli(['Admin Satu', 'Root@Example.com'], ['sandi-aman-123', 'sandi-aman-123'], $out, $err);
        $this->assertSame(0, $code, (string) $err);
        $this->assertSame(1, $this->count("SELECT COUNT(*) AS n FROM platform_users WHERE email = 'root@example.com'"));
        $this->assertFalse(str_contains((string) $out . (string) $err, 'sandi-aman-123'));
        $this->assertSame(302, $this->platformLogin('root@example.com', 'sandi-aman-123')->status());
    }

    public function testCliRejectsMismatchWeakAndEmptyPasswordWithoutCreating(): void
    {
        $this->assertSame(1, $this->cli(['Nama', 'a@example.com'], ['sandi-aman-123', 'sandi-aman-124'], $o, $e));
        $this->assertContains('tidak sama', (string) $e);
        $this->assertSame(1, $this->cli(['Nama', 'a@example.com'], ['pendek', 'pendek'], $o, $e));
        $this->assertSame(1, $this->cli(['Nama', 'a@example.com'], [], $o, $e));
        $this->assertSame(0, $this->count('SELECT COUNT(*) AS n FROM platform_users'));
    }

    public function testCliDuplicateEmailIsErrorAndRerunIsSafe(): void
    {
        $this->assertSame(0, $this->cli(['Satu', 'a@example.com'], ['sandi-aman-123', 'sandi-aman-123']));
        $this->assertSame(1, $this->cli(['Dua', 'A@example.com'], ['sandi-lain-9999', 'sandi-lain-9999'], $o, $e));
        $this->assertContains('sudah terdaftar', (string) $e);
        $this->assertSame(1, $this->count('SELECT COUNT(*) AS n FROM platform_users'));
    }

    public function testCliRequiresExactlyTwoArguments(): void
    {
        $this->assertSame(2, $this->cli(['Nama'], [], $o, $e));
        $this->assertSame(2, $this->cli(['Nama', 'a@example.com', 'sandi-aman-123'], [], $o, $e));
    }

    public function testPromptHiddenReadsLineFromNonTtyStream(): void
    {
        $h = fopen('php://memory', 'w+');
        fwrite($h, "rahasia-123\r\nlain\n");
        rewind($h);
        $this->assertSame('rahasia-123', PlatformAdminCli::promptHidden('', $h));
        $this->assertSame('lain', PlatformAdminCli::promptHidden('', $h));
        $this->assertSame(null, PlatformAdminCli::promptHidden('', $h));
    }
}
