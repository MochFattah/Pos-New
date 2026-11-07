<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Tests\Support\Runner;
use Tests\Support\TestCase;

final class RunnerTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pos-runner-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/Bad', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/Bad/*') ?: [] as $f) {
            unlink($f);
        }
        @rmdir($this->dir . '/Bad');
        @rmdir($this->dir);
    }

    public function testMisnamedTestFileIsReportedAsFailure(): void
    {
        file_put_contents($this->dir . '/Bad/GhostTest.php', "<?php\ndeclare(strict_types=1);\nnamespace Tests\\Wrong;\nfinal class GhostTest {}\n");
        $result = Runner::run($this->dir);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(0, $result['passed']);
        $this->assertContains('GhostTest.php', $result['lines'][0]);
        $this->assertContains('tidak ditemukan', $result['lines'][0]);
    }

    public function testNonTestCaseClassIsReportedAsFailure(): void
    {
        file_put_contents($this->dir . '/Bad/PlainTest.php', "<?php\ndeclare(strict_types=1);\nnamespace Tests\\Bad;\nfinal class PlainTest {}\n");
        $result = Runner::run($this->dir);
        $this->assertSame(1, $result['failed']);
        $this->assertContains('tidak meng-extend', $result['lines'][0]);
    }

    public function testWarningDuringTestBecomesFailure(): void
    {
        file_put_contents($this->dir . '/Bad/NoisyTest.php', "<?php\ndeclare(strict_types=1);\nnamespace Tests\\Bad;\nuse Tests\\Support\\TestCase;\nfinal class NoisyTest extends TestCase\n{\n    public function testWarns(): void\n    {\n        \$a = [];\n        echo \$a['x'];\n    }\n}\n");
        $result = Runner::run($this->dir);
        $this->assertSame(1, $result['failed']);
        $this->assertContains('FAIL', $result['lines'][0]);
        $this->assertContains('ErrorException', $result['lines'][0]);
    }
}
