<?php

declare(strict_types=1);

namespace Tests\Unit\AuditLogs;

use App\Core\Database\Connection;
use App\Modules\AuditLogs\Repositories\AuditLogRepository;
use App\Modules\AuditLogs\Services\AuditLogger;
use InvalidArgumentException;
use JsonSerializable;
use stdClass;
use Tests\Support\Db;
use Tests\Support\TestCase;

final class AuditLoggerTest extends TestCase
{
    private Connection $db;
    private AuditLogRepository $repo;
    private AuditLogger $logger;

    protected function setUp(): void
    {
        $this->db = Db::fresh();
        foreach (['T1', 'T2'] as $name) {
            $this->db->execute(
                "INSERT INTO tenants (uuid, name, status, created_at, updated_at) VALUES (?, ?, 'active', '2026-01-01 00:00:00', '2026-01-01 00:00:00')",
                [bin2hex(random_bytes(8)) . $name, $name]
            );
        }
        $this->repo = new AuditLogRepository($this->db);
        $this->logger = new AuditLogger(
            $this->repo,
            static fn (): array => ['ip' => '203.0.113.9', 'request_id' => 'req-abc'],
            static fn (): int => 1_800_000_000
        );
    }

    private function lastRow(): array
    {
        return $this->db->fetchOne('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 1') ?? [];
    }

    public function testSensitiveMetadataKeysAreStripped(): void
    {
        $this->logger->record('user.updated', 1, AuditLogger::ACTOR_TENANT_USER, 5, 'user', 5, [
            'name' => 'Budi',
            'Password' => 'x',
            'nested' => ['API_KEY' => 'k', 'ok' => 1, 'deep' => ['Reset_Token' => 't', 'keep' => true]],
            'list' => [['csrf_token' => 'c', 'a' => 1], ['Authorization' => 'Bearer z', 'b' => 2]],
            'card_number' => '4111', 'cookie' => 'sid', 'otp_code' => '1', 'client_secret' => 's',
        ]);
        $meta = json_decode((string) $this->lastRow()['metadata'], true);
        $this->assertSame([
            'name' => 'Budi',
            'nested' => ['ok' => 1, 'deep' => ['keep' => true]],
            'list' => [['a' => 1], ['b' => 2]],
        ], $meta);
    }

    public function testListIsScopedToTenant(): void
    {
        $this->logger->record('a.one', 1, AuditLogger::ACTOR_TENANT_USER, 1);
        $this->logger->record('a.two', 2, AuditLogger::ACTOR_TENANT_USER, 2);
        $this->logger->record('platform.event', null, AuditLogger::ACTOR_PLATFORM_USER, 1);
        $page = $this->repo->paginate(1, 1, 20);
        $this->assertSame(1, $page['total']);
        $this->assertSame(1, count($page['rows']));
        $this->assertSame('a.one', $page['rows'][0]['action']);
    }

    public function testPaginationLimitsRows(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->logger->record('item.created', 1, AuditLogger::ACTOR_SYSTEM, null, 'item', $i);
        }
        $p1 = $this->repo->paginate(1, 1, 2);
        $p3 = $this->repo->paginate(1, 3, 2);
        $this->assertSame(5, $p1['total']);
        $this->assertSame(2, count($p1['rows']));
        $this->assertSame(5, (int) $p1['rows'][0]['entity_id']);
        $this->assertSame(1, count($p3['rows']));
        $this->assertSame(1, (int) $p3['rows'][0]['entity_id']);
    }

    public function testPerPageIsClamped(): void
    {
        $this->logger->record('item.created', 1, AuditLogger::ACTOR_SYSTEM, null);
        $this->assertSame(100, $this->repo->paginate(1, 1, 5000)['per_page']);
        $this->assertSame(1, $this->repo->paginate(1, 1, 0)['per_page']);
        $this->assertSame(1, $this->repo->paginate(1, -4, 10)['page']);
    }

    public function testInvalidActorTypeRejected(): void
    {
        $this->assertThrows(InvalidArgumentException::class, fn () => $this->logger->record('a.b', 1, 'hacker', 1));
        $this->assertSame(0, count($this->db->query('SELECT id FROM audit_logs')));
    }

    public function testMalformedActionRejected(): void
    {
        foreach (['', 'A.b', '1abc', 'x', 'has space', 'semi;colon', str_repeat('a', 101)] as $bad) {
            $this->assertThrows(InvalidArgumentException::class, fn () => $this->logger->record($bad, 1, 'system', null));
        }
    }

    public function testOversizedMetadataIsTruncatedMarker(): void
    {
        $this->logger->record('big.event', 1, 'system', null, null, null, ['blob' => str_repeat('x', 9000)]);
        $this->assertSame('{"_truncated":true}', $this->lastRow()['metadata']);
    }

    public function testUnserializableMetadataDoesNotThrow(): void
    {
        $this->logger->record('bad.event', 1, 'system', null, null, null, ['v' => "\xB1\x31"]);
        $this->assertSame('{"_unserializable":true}', $this->lastRow()['metadata']);
        $r = fopen('php://memory', 'r');
        $this->logger->record('bad.event', 1, 'system', null, null, null, ['r' => $r]);
        fclose($r);
        $this->assertSame('{"_unserializable":true}', $this->lastRow()['metadata']);
    }

    public function testRecordStoresIpRequestIdAndUtcTimestamp(): void
    {
        $this->logger->record('auth.login_succeeded', 1, 'tenant_user', 7, 'user', 7);
        $row = $this->lastRow();
        $this->assertSame('203.0.113.9', $row['ip']);
        $this->assertSame('req-abc', $row['request_id']);
        $this->assertSame(gmdate('Y-m-d H:i:s', 1_800_000_000), $row['created_at']);
        $this->assertSame(null, $row['metadata']);
    }

    public function testRecordedRowNeverContainsPlainPassword(): void
    {
        $this->logger->record('tenant.registered', 1, 'system', null, null, null, ['password' => 'S3NT1NEL-xyz', 'email' => 'a@b.c']);
        $row = $this->lastRow();
        $this->assertFalse(str_contains(json_encode($row, JSON_THROW_ON_ERROR), 'S3NT1NEL-xyz'));
        $this->assertTrue(str_contains((string) $row['metadata'], 'a@b.c'));
    }

    private function stored(): array
    {
        return json_decode((string) $this->lastRow()['metadata'], true, 512, JSON_THROW_ON_ERROR);
    }

    private function rec(array $meta): void
    {
        $this->logger->record('obj.event', 1, 'system', null, null, null, $meta);
    }

    public function testObjectsAreSanitisedToo(): void
    {
        $std = new stdClass();
        $std->password = 'x';
        $std->name = 'n';
        $js = new class implements JsonSerializable {
            public function jsonSerialize(): mixed
            {
                return ['Api_Key' => 'k', 'v' => 1];
            }
        };
        $this->rec(['user' => $std, 'list' => [$std, $js], 5 => 'num', 'MiXeD' => ['Secret' => 1, 'a' => 2]]);
        $this->assertSame([
            'user' => ['name' => 'n'],
            'list' => [['name' => 'n'], ['v' => 1]],
            5 => 'num',
            'MiXeD' => ['a' => 2],
        ], $this->stored());
        $this->assertFalse(str_contains((string) $this->lastRow()['metadata'], '"x"'));
    }

    public function testClosureMetadataIsUnserializable(): void
    {
        $this->rec(['f' => static fn () => 1]);
        $this->assertSame('{"_unserializable":true}', $this->lastRow()['metadata']);
    }

    public function testKeyMatchingAvoidsFalsePositives(): void
    {
        $kept = ['discard_reason', 'cardinality', 'footprint', 'spot_price', 'pinned', 'sessions'];
        $dropped = ['card_number', 'cardNumber', 'otp', 'user_otp', 'pin', 'session_id', 'jwt', 'access_token',
            'PASSWORD', 'new_passwd', 'x-api-key', 'apikey', 'user.pwd'];
        $meta = [];
        foreach (array_merge($kept, $dropped) as $k) {
            $meta[$k] = 1;
        }
        $this->rec($meta);
        $this->assertSame($kept, array_keys($this->stored()));
    }

    public function testSelfReferencingArrayDoesNotHang(): void
    {
        $a = ['x' => 1];
        $a['self'] = &$a;
        $t = microtime(true);
        $this->rec($a);
        $this->assertTrue(microtime(true) - $t < 2.0);
        json_decode((string) $this->lastRow()['metadata'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue(true);
    }

    public function testDeepNestingIsBounded(): void
    {
        $v = 'leaf';
        for ($i = 0; $i < 1000; $i++) {
            $v = ['n' => $v];
        }
        $this->rec(['deep' => $v]);
        $this->assertTrue(str_contains((string) $this->lastRow()['metadata'], '[dalam]'));
        $this->assertFalse(str_contains((string) $this->lastRow()['metadata'], 'leaf'));
    }

    public function testHugeArrayIsNodeLimited(): void
    {
        $t = microtime(true);
        $this->rec(['big' => range(1, 100000)]);
        $this->assertTrue(microtime(true) - $t < 2.0);
        $m = $this->stored();
        $this->assertTrue(($m['_truncated'] ?? false) === true);
        $this->assertTrue(count($m['big']) <= 500);
    }

    public function testListIsolatedAcrossThreeTenantsAndNull(): void
    {
        $this->db->execute("INSERT INTO tenants (uuid, name, status, created_at, updated_at) VALUES ('u3', 'T3', 'active', '2026-01-01 00:00:00', '2026-01-01 00:00:00')");
        foreach ([1, 2, 3, null] as $t) {
            $this->logger->record('iso.event', $t, 'system', null);
            $this->logger->record('iso.event', $t, 'system', null);
        }
        foreach ([1, 2, 3] as $t) {
            $p = $this->repo->paginate($t, 1, 50);
            $this->assertSame(2, $p['total']);
            foreach ($p['rows'] as $r) {
                $this->assertSame($t, (int) $r['tenant_id']);
            }
        }
    }

    public function testPaginateSurvivesExtremePages(): void
    {
        $this->logger->record('item.created', 1, 'system', null);
        foreach ([PHP_INT_MAX, 0, -5, PHP_INT_MIN] as $pg) {
            $p = $this->repo->paginate(1, $pg, 100);
            $this->assertTrue($p['page'] >= 1 && $p['page'] <= 1000000);
        }
        $this->assertSame(1, $this->repo->paginate(1, 0, 10)['page']);
        $this->assertSame(1, count($this->repo->paginate(1, 1, 10)['rows']));
    }

    public function testMetadataBoundaryAt8192Bytes(): void
    {
        $overhead = strlen('{"b":""}');
        $this->rec(['b' => str_repeat('x', 8192 - $overhead)]);
        $this->assertSame(8192, strlen((string) $this->lastRow()['metadata']));
        $this->rec(['b' => str_repeat('x', 8193 - $overhead)]);
        $this->assertSame('{"_truncated":true}', $this->lastRow()['metadata']);
    }

    public function testEntityTypeTooLongRejectedBeforeInsert(): void
    {
        $this->assertThrows(InvalidArgumentException::class, fn () => $this->logger->record('a.b', 1, 'system', null, str_repeat('e', 101)));
        $this->assertSame(0, count($this->db->query('SELECT id FROM audit_logs')));
    }

    public function testIpAndRequestIdAreTruncated(): void
    {
        $l = new AuditLogger($this->repo, static fn (): array => ['ip' => str_repeat('1', 80), 'request_id' => str_repeat('r', 90)]);
        $l->record('a.b', 1, 'system', null);
        $this->assertSame(45, strlen($this->lastRow()['ip']));
        $this->assertSame(64, strlen($this->lastRow()['request_id']));
    }
}
