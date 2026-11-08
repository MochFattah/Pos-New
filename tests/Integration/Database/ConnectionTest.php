<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Core\Database\Connection;
use RuntimeException;
use Tests\Support\Db;
use Tests\Support\TestCase;

final class ConnectionTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        $this->db = Db::memory();
        $this->db->execute('CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, v TEXT)');
    }

    public function testExecuteBindsParametersNotInterpolates(): void
    {
        $evil = "' OR 1=1 --";
        $this->assertSame(1, $this->db->execute('INSERT INTO t (v) VALUES (?)', [$evil]));
        $row = $this->db->fetchOne('SELECT v FROM t WHERE id = ?', [$this->db->lastInsertId()]);
        $this->assertSame($evil, $row['v']);
    }

    public function testQueryAndFetchOne(): void
    {
        $this->db->execute('INSERT INTO t (v) VALUES (?), (?)', ['a', 'b']);
        $this->assertSame([['v' => 'a'], ['v' => 'b']], $this->db->query('SELECT v FROM t ORDER BY id'));
        $this->assertSame(null, $this->db->fetchOne('SELECT v FROM t WHERE v = ?', ['zzz']));
    }

    public function testExecuteReturnsAffectedRows(): void
    {
        $this->db->execute('INSERT INTO t (v) VALUES (?), (?), (?)', ['x', 'x', 'y']);
        $this->assertSame(2, $this->db->execute('UPDATE t SET v = ? WHERE v = ?', ['z', 'x']));
    }

    public function testTransactionCommitsAndReturnsValue(): void
    {
        $r = $this->db->transaction(function (): string {
            $this->db->execute('INSERT INTO t (v) VALUES (?)', ['ok']);
            return 'done';
        });
        $this->assertSame('done', $r);
        $this->assertSame(1, count($this->db->query('SELECT * FROM t')));
    }

    public function testTransactionRollsBackOnException(): void
    {
        $this->assertThrows(RuntimeException::class, function (): void {
            $this->db->transaction(function (): void {
                $this->db->execute('INSERT INTO t (v) VALUES (?)', ['gone']);
                throw new RuntimeException('boom');
            });
        });
        $this->assertSame(0, count($this->db->query('SELECT * FROM t')));
    }

    public function testNestedTransactionUsesSavepoints(): void
    {
        $this->db->transaction(function (): void {
            $this->db->execute('INSERT INTO t (v) VALUES (?)', ['outer']);
            try {
                $this->db->transaction(function (): void {
                    $this->db->execute('INSERT INTO t (v) VALUES (?)', ['inner']);
                    throw new RuntimeException('inner fail');
                });
            } catch (RuntimeException) {
            }
        });
        $rows = $this->db->query('SELECT v FROM t');
        $this->assertSame([['v' => 'outer']], $rows);
    }

    public function testNestedFailurePropagatingRollsBackAll(): void
    {
        $this->assertThrows(RuntimeException::class, function (): void {
            $this->db->transaction(function (): void {
                $this->db->execute('INSERT INTO t (v) VALUES (?)', ['outer']);
                $this->db->transaction(function (): void {
                    throw new RuntimeException('x');
                });
            });
        });
        $this->assertSame(0, count($this->db->query('SELECT * FROM t')));
    }

    public function testMysqlDsn(): void
    {
        $dsn = Connection::mysqlDsn(['host' => 'db.local', 'port' => 3307, 'name' => 'pos', 'charset' => 'utf8mb4']);
        $this->assertSame('mysql:host=db.local;port=3307;dbname=pos;charset=utf8mb4', $dsn);
    }
}
