<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Request;
use Tests\Support\TestCase;

final class RequestTest extends TestCase
{
    public function testQueryStringIsStrippedFromPath(): void
    {
        $r = Request::create('GET', '/a/b?x=1&y=2');
        $this->assertSame('/a/b', $r->path());
        $this->assertSame('/a/b', $r->rawPath());
        $this->assertSame('1', $r->input('x'));
    }

    public function testRawPathStaysEncodedWhilePathIsDecodedOnce(): void
    {
        $r = Request::create('GET', '/x/%2541');
        $this->assertSame('/x/%2541', $r->rawPath());
        $this->assertSame('/x/%41', $r->path());
    }

    public function testForwardedForIgnoredWhenNoTrustedProxies(): void
    {
        $this->assertSame('9.9.9.9', Request::resolveClientIp('9.9.9.9', '1.2.3.4', []));
    }

    public function testForwardedForIgnoredWhenRemoteIsNotTrusted(): void
    {
        $this->assertSame('8.8.8.8', Request::resolveClientIp('8.8.8.8', '1.2.3.4', ['10.0.0.0/8']));
    }

    public function testRightMostUntrustedEntryWinsWhenRemoteTrusted(): void
    {
        $trusted = ['10.0.0.0/8', '192.168.1.5'];
        // klien memalsukan 6.6.6.6 di paling kiri; proxy menambahkan IP asli di kanan
        $this->assertSame('203.0.113.9', Request::resolveClientIp('10.0.0.2', '6.6.6.6, 203.0.113.9', $trusted));
        $this->assertSame('203.0.113.9', Request::resolveClientIp('10.0.0.2', '6.6.6.6, 203.0.113.9, 10.1.1.1, 192.168.1.5', $trusted));
        $this->assertSame('10.0.0.2', Request::resolveClientIp('10.0.0.2', '10.9.9.9, 192.168.1.5', $trusted));
        $this->assertSame('10.0.0.2', Request::resolveClientIp('10.0.0.2', null, $trusted));
    }

    public function testIpv6AndMappedAddresses(): void
    {
        $trusted = ['2001:db8::/32', '::1'];
        $this->assertSame('2001:4860::1', Request::resolveClientIp('2001:db8::5', '2001:4860:0:0:0:0:0:1', $trusted));
        $this->assertSame('::1', Request::resolveClientIp('::1', '2001:db8:1::1', $trusted));
        $this->assertSame('1.2.3.4', Request::resolveClientIp('::1', '1.2.3.4', $trusted));
        $this->assertSame('5.6.7.8', Request::resolveClientIp('::ffff:10.0.0.1', '5.6.7.8', ['10.0.0.0/8']));
        // IPv4 tidak pernah cocok dengan CIDR IPv6 dan sebaliknya
        $this->assertSame('10.0.0.1', Request::resolveClientIp('10.0.0.1', '5.6.7.8', ['::/0']));
    }

    public function testMalformedEntriesFailClosedToRemoteAddr(): void
    {
        $trusted = ['10.0.0.0/8'];
        foreach (['not-an-ip', '1.2.3.4, garbage', '1.2.3.4,', '', '1.2.3.4:8080', '999.1.1.1', "1.2.3.4\n6.6.6.6"] as $xff) {
            $this->assertSame('10.0.0.3', Request::resolveClientIp('10.0.0.3', $xff, $trusted), $xff);
        }
        // entri konfigurasi rusak diabaikan, bukan mempercayai semuanya
        $this->assertSame('10.0.0.3', Request::resolveClientIp('10.0.0.3', '1.2.3.4', ['bogus', '10.0.0.0/99', '10.0.0.0/x']));
    }

    public function testCidrBoundaries(): void
    {
        $trusted = ['172.16.0.0/12'];
        $this->assertSame('7.7.7.7', Request::resolveClientIp('172.31.255.255', '7.7.7.7', $trusted));
        $this->assertSame('172.32.0.1', Request::resolveClientIp('172.32.0.1', '7.7.7.7', $trusted));
    }

    public function testFromGlobalsUsesTrustedProxyList(): void
    {
        $saved = $_SERVER;
        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/';
            $_SERVER['REMOTE_ADDR'] = '10.0.0.2';
            $_SERVER['HTTP_X_FORWARDED_FOR'] = '6.6.6.6, 203.0.113.9';
            $this->assertSame('10.0.0.2', Request::fromGlobals([])->ip());
            $this->assertSame('203.0.113.9', Request::fromGlobals(['10.0.0.0/8'])->ip());
            $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
            $this->assertSame('8.8.8.8', Request::fromGlobals(['10.0.0.0/8'])->ip());
        } finally {
            $_SERVER = $saved;
        }
    }
}
