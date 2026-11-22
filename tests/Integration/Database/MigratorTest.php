<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Core\Database\Connection;
use App\Core\Database\Migrator;
use RuntimeException;
use Tests\Support\Db;
use Tests\Support\TestCase;

final class MigratorTest extends TestCase
{
    private string $dir = '';
    private Connection $db;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pos-mig-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0777, true);
        $this->db = Db::memory();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            unlink($f);
        }
        @rmdir($this->dir);
    }

    private function write(string $name, string $sql): void
    {
        file_put_contents($this->dir . '/' . $name, $sql);
    }

    public function testMigratorAppliesOnceInOrder(): void
    {
        $this->write('002_b.sql', 'CREATE TABLE b (id INTEGER);');
        $this->write('001_a.sql', 'CREATE TABLE a (id INTEGER);');
        $m = new Migrator($this->db, $this->dir);
        $this->assertSame(['001_a.sql', '002_b.sql'], $m->migrate());
        $this->assertSame([], $m->migrate());
        $this->assertSame(2, count($this->db->query('SELECT name FROM schema_migrations')));
    }

    public function testNewFileAppliedLater(): void
    {
        $this->write('001_a.sql', 'CREATE TABLE a (id INTEGER);');
        $m = new Migrator($this->db, $this->dir);
        $m->migrate();
        $this->write('002_b.sql', 'CREATE TABLE b (id INTEGER);');
        $this->assertSame(['002_b.sql'], $m->migrate());
    }

    public function testSplitRespectsQuotesAndComments(): void
    {
        $sql = "-- komentar; dengan titik koma\n"
            . "CREATE TABLE n (v TEXT); # catatan; lagi\n"
            . "INSERT INTO n (v) VALUES ('a;b');\n"
            . "INSERT INTO n (v) VALUES ('it''s;ok')";
        $this->write('001_n.sql', $sql);
        (new Migrator($this->db, $this->dir))->migrate();
        $this->assertSame([['v' => 'a;b'], ['v' => "it's;ok"]], $this->db->query('SELECT v FROM n ORDER BY rowid'));
    }

    public function testFailureStopsAndIsNotRecorded(): void
    {
        $this->write('001_a.sql', 'CREATE TABLE a (id INTEGER);');
        $this->write('002_bad.sql', "CREATE TABLE c (id INTEGER);\nINSERT INTO nope VALUES (1);");
        $this->write('003_c.sql', 'CREATE TABLE d (id INTEGER);');
        $m = new Migrator($this->db, $this->dir);
        $message = '';
        try {
            $m->migrate();
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
        }
        $this->assertContains('002_bad.sql', $message);
        $this->assertSame(0, count($this->db->query("SELECT name FROM sqlite_master WHERE name = 'd'")));
        $names = array_column($this->db->query('SELECT name FROM schema_migrations'), 'name');
        $this->assertSame(['001_a.sql'], $names);
        // SQLite: DDL transactional, tabel c ikut di-rollback
        $this->assertSame(0, count($this->db->query("SELECT name FROM sqlite_master WHERE name = 'c'")));
    }

    public function testRetryAfterPartialNonTransactionalFailureSucceeds(): void
    {
        // Meniru MySQL (DDL auto-commit): pernyataan 1 sudah terlanjur dibuat, pernyataan 2 gagal.
        $sql = "CREATE TABLE IF NOT EXISTS p1 (id INTEGER);\nCREATE TABLE IF NOT EXISTS p2 (id INTEGER, x INTEGER);";
        $this->db->execute('CREATE TABLE p1 (id INTEGER)');
        $this->write('001_p.sql', $sql);
        $this->assertSame(['001_p.sql'], (new Migrator($this->db, $this->dir))->migrate());
        $this->assertSame(2, count($this->db->query("SELECT name FROM sqlite_master WHERE name IN ('p1','p2')")));
    }

    public function testFailureThenFixedRetrySucceeds(): void
    {
        $this->write('001_a.sql', "CREATE TABLE IF NOT EXISTS a (id INTEGER);\nINSERT INTO nope VALUES (1);");
        $m = new Migrator($this->db, $this->dir);
        $failed = false;
        try {
            $m->migrate();
        } catch (RuntimeException) {
            $failed = true;
        }
        $this->assertTrue($failed);
        $this->write('001_a.sql', 'CREATE TABLE IF NOT EXISTS a (id INTEGER);');
        $this->assertSame(['001_a.sql'], $m->migrate());
    }

    public function testFoundationMysqlMigrationIsIdempotentDdl(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 3) . '/database/migrations/0001_foundation_schema.sql');
        foreach (Migrator::split($sql, true) as $statement) {
            $this->assertTrue(
                preg_match('/^CREATE TABLE IF NOT EXISTS /', $statement) === 1,
                'tidak idempoten: ' . substr($statement, 0, 50)
            );
        }
    }

    public function testUnreadableFileIsNotRecorded(): void
    {
        $this->write('001_a.sql', 'CREATE TABLE a (id INTEGER);');
        $m = new class ($this->db, $this->dir) extends Migrator {
            protected function readFile(string $path): string|false
            {
                return false;
            }
        };
        $message = '';
        try {
            $m->migrate();
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
        }
        $this->assertContains('001_a.sql', $message);
        $this->assertSame(0, count($this->db->query('SELECT name FROM schema_migrations')));
        $this->assertSame([], $this->db->query("SELECT name FROM sqlite_master WHERE name = 'a'"));
    }

    public function testSplitBlockCommentWithSemicolon(): void
    {
        $parts = Migrator::split('SELECT 1 /* a ; b */ , 2; SELECT 3');
        $this->assertSame(2, count($parts));
        $this->assertSame('SELECT 3', $parts[1]);
        $this->assertSame(false, str_contains($parts[0], 'a ; b'));
        $this->assertSame(1, preg_match('/^SELECT 1\s+,\s*2$/', $parts[0]));
    }

    public function testSplitQuotedSemicolons(): void
    {
        $sql = <<<'SQL'
SELECT "a;b"; SELECT `c;d` FROM t; SELECT 'x -- y # z'; SELECT "p#q--r";
SQL;
        $this->assertSame(
            ['SELECT "a;b"', 'SELECT `c;d` FROM t', "SELECT 'x -- y # z'", 'SELECT "p#q--r"'],
            Migrator::split($sql)
        );
    }

    public function testSplitBackslashEscapeOnlyInMysqlMode(): void
    {
        // Mode MySQL tidak dijalankan di SQLite, jadi splitter diuji langsung dengan flag.
        $sql = <<<'SQL'
SELECT 'it\'s;ok'; SELECT 2
SQL;
        $this->assertSame([<<<'SQL'
SELECT 'it\'s;ok'
SQL, 'SELECT 2'], Migrator::split($sql, true));
        // Tanpa mode MySQL, \' menutup string lebih awal sehingga pemecahan berbeda.
        $this->assertSame(2, count(Migrator::split($sql, false)));
        $this->assertSame(false, Migrator::split($sql, false) === Migrator::split($sql, true));
    }

    public function testSplitCrlfAndEmptyStatements(): void
    {
        $this->assertSame(['SELECT 1', 'SELECT 2'], Migrator::split("SELECT 1;;\r\n;  ;\r\nSELECT 2;\r\n"));
        $this->assertSame(['SELECT 1'], Migrator::split("-- c;\r\nSELECT 1;\r\n# d;\r\n"));
    }
}
