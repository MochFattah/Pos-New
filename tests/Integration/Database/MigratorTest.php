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
        $names = array_column($this->db->query('SELECT name FROM schema_migrations'), 'name');
        $this->assertSame(['001_a.sql'], $names);
        // SQLite: DDL transactional, tabel c ikut di-rollback
        $this->assertSame(0, count($this->db->query("SELECT name FROM sqlite_master WHERE name = 'c'")));
    }
}
