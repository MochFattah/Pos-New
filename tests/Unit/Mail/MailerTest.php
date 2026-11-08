<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Core\Mail\LogMailer;
use App\Core\Mail\MailException;
use App\Core\Mail\MailerFactory;
use App\Core\Mail\SmtpMailer;
use Tests\Support\TestCase;

final class MailerTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pos-mail-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if ($this->dir === '' || !is_dir($this->dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->dir);
    }

    public function testLogMailerWritesRecipientAndBody(): void
    {
        $path = $this->dir . '/nested/mail.log';
        $m = new LogMailer($path);
        $m->send('budi@example.com', 'Verifikasi', "Halo\nKlik http://x/verify?t=abc");
        $m->send('ani@example.com', 'Kedua', 'Isi kedua');
        $log = (string) file_get_contents($path);
        $this->assertContains('To: budi@example.com', $log);
        $this->assertContains('Subject: Verifikasi', $log);
        $this->assertContains('http://x/verify?t=abc', $log);
        $this->assertContains('To: ani@example.com', $log);
        $this->assertSame(2, substr_count($log, 'To: '));
    }

    public function testLogMailerFailureThrowsMailException(): void
    {
        $blocker = $this->dir . '/file';
        mkdir($this->dir, 0775, true);
        file_put_contents($blocker, 'x');
        $this->assertThrows(MailException::class, fn () => (new LogMailer($blocker . '/sub/mail.log'))->send('a@b.com', 's', 'b'));
    }

    /**
     * @param list<string> $script balasan server, sudah ditulis di muka
     * @return array{0: callable, 1: resource} connector dan sisi server
     */
    private function pair(array $script): array
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $this->assertTrue($pair !== false);
        fwrite($pair[1], implode('', array_map(static fn (string $l): string => $l . "\r\n", $script)));
        return [static fn () => $pair[0], $pair[1]];
    }

    private function drain($server): string
    {
        stream_set_blocking($server, false);
        $out = '';
        while (($c = fread($server, 8192)) !== false && $c !== '') {
            $out .= $c;
        }
        return $out;
    }

    public function testSmtpMailerSpeaksExpectedCommandSequence(): void
    {
        [$connector, $server] = $this->pair([
            '220 fake ESMTP',
            '250-fake', '250-SIZE 100', '250 AUTH LOGIN',
            '334 VXNlcm5hbWU6',
            '334 UGFzc3dvcmQ6',
            '235 ok',
            '250 sender ok',
            '250 rcpt ok',
            '354 go',
            '250 queued',
            '221 bye',
        ]);
        $m = new SmtpMailer('smtp.test', 25, 'user', 'S3cretPass', 'no-reply@toko.test', 'none', $connector, 'pos.local', 2);
        $m->send('budi@example.com', 'Verifikasi Email', "Baris satu\n.titik awal\nBaris tiga");
        $w = $this->drain($server);

        $expected = [
            "EHLO pos.local\r\n",
            "AUTH LOGIN\r\n",
            base64_encode('user') . "\r\n",
            base64_encode('S3cretPass') . "\r\n",
            "MAIL FROM:<no-reply@toko.test>\r\n",
            "RCPT TO:<budi@example.com>\r\n",
            "DATA\r\n",
        ];
        $pos = 0;
        foreach ($expected as $line) {
            $i = strpos($w, $line, $pos);
            $this->assertTrue($i !== false, 'urutan perintah salah di: ' . trim($line));
            $pos = $i + strlen($line);
        }
        $this->assertContains("Subject: Verifikasi Email\r\n", $w);
        $this->assertContains("To: <budi@example.com>\r\n", $w);
        $this->assertContains("MIME-Version: 1.0\r\n", $w);
        $this->assertContains("Content-Type: text/plain; charset=UTF-8\r\n", $w);
        $this->assertContains("Content-Transfer-Encoding: 8bit\r\n", $w);
        $this->assertContains("Date: ", $w);
        $this->assertContains("Message-ID: <", $w);
        $this->assertContains("\r\n\r\nBaris satu\r\n..titik awal\r\nBaris tiga\r\n.\r\n", $w);
        $this->assertTrue(str_ends_with($w, "QUIT\r\n"));
    }

    public function testSmtpMailerSkipsAuthWithoutUserAndEncodesSubject(): void
    {
        [$connector, $server] = $this->pair(['220 hi', '250 ok', '250 f', '250 r', '354 go', '250 q', '221 b']);
        (new SmtpMailer('h', 25, '', '', 'a@toko.test', 'none', $connector, 'localhost', 2))->send('b@x.com', 'Halo Dünya', 'x');
        $w = $this->drain($server);
        $this->assertFalse(str_contains($w, 'AUTH'));
        $this->assertContains('Subject: =?UTF-8?B?' . base64_encode('Halo Dünya') . '?=', $w);
    }

    public function testSmtpMailerStarttlsSequenceUpToTlsHandshake(): void
    {
        // Jabat tangan TLS tidak bisa dipalsukan di socket pair; hanya urutan sampai "220 Ready" yang diuji.
        [$connector, $server] = $this->pair(['220 hi', '250-x', '250 STARTTLS', '220 Ready']);
        $m = new class ('h', 587, '', '', 'a@toko.test', 'starttls', $connector, 'localhost', 2) extends SmtpMailer {
            protected function enableCrypto($stream): void
            {
                throw new MailException('crypto diaktifkan');
            }
        };
        try {
            $m->send('b@x.com', 's', 'x');
            $this->fail('harus melempar');
        } catch (MailException $e) {
            $this->assertSame('crypto diaktifkan', $e->getMessage());
        }
        $this->assertSame("EHLO localhost\r\nSTARTTLS\r\nQUIT\r\n", $this->drain($server));
    }

    public function testSmtpMailerReissuesEhloAfterStarttls(): void
    {
        [$connector, $server] = $this->pair(['220 hi', '250 STARTTLS', '220 Ready', '250 ok', '250 f', '250 r', '354 go', '250 q', '221 b']);
        $m = new class ('h', 587, '', '', 'a@toko.test', 'starttls', $connector, 'localhost', 2) extends SmtpMailer {
            protected function enableCrypto($stream): void
            {
            }
        };
        $m->send('b@x.com', 's', 'x');
        $w = $this->drain($server);
        $this->assertSame(2, substr_count($w, 'EHLO localhost'));
    }

    public function testSmtpMailerRejectsCrlfInSubject(): void
    {
        $opened = false;
        $connector = function () use (&$opened) {
            $opened = true;
            return false;
        };
        $m = new SmtpMailer('h', 25, '', '', 'a@toko.test', 'none', $connector);
        $this->assertThrows(MailException::class, fn () => $m->send('b@x.com', "Hai\r\nBcc: z@x.com", 'x'));
        $this->assertThrows(MailException::class, fn () => $m->send("b@x.com\nBcc: z@x.com", 's', 'x'));
        $this->assertThrows(MailException::class, fn () => (new SmtpMailer('h', 25, '', '', "a@toko.test\r\nX: y", 'none', $connector))->send('b@x.com', 's', 'x'));
        $this->assertFalse($opened);
    }

    public function testSmtpMailerErrorReplyThrowsWithoutLeakingSecrets(): void
    {
        [$connector] = $this->pair(['220 hi', '250 AUTH LOGIN', '334 VXNlcm5hbWU6', '334 UGFzc3dvcmQ6', '535 auth failed']);
        $m = new SmtpMailer('h', 25, 'user', 'S3cretPass', 'a@toko.test', 'none', $connector, 'localhost', 2);
        try {
            $m->send('b@x.com', 's', 'x');
            $this->fail('harus melempar');
        } catch (MailException $e) {
            $this->assertContains('535', $e->getMessage());
            $this->assertFalse(str_contains($e->getMessage(), 'S3cretPass'));
            $this->assertFalse(str_contains($e->getMessage(), base64_encode('S3cretPass')));
        }
    }

    public function testSmtpMailerThrowsWhenConnectFailsOrServerCloses(): void
    {
        $m = new SmtpMailer('h', 25, '', '', 'a@toko.test', 'none', static fn () => false);
        $this->assertThrows(MailException::class, fn () => $m->send('b@x.com', 's', 'x'));
        [$connector] = $this->pair(['220 hi']);
        $m2 = new SmtpMailer('h', 25, '', '', 'a@toko.test', 'none', $connector, 'localhost', 1);
        $this->assertThrows(MailException::class, fn () => $m2->send('b@x.com', 's', 'x'));
    }

    public function testSmtpMailerRejectsUnknownEncryption(): void
    {
        $this->assertThrows(MailException::class, fn () => new SmtpMailer('h', 25, '', '', 'a@b.com', 'ssl'));
    }

    public function testFactoryDefaultsToLogAndBuildsSmtp(): void
    {
        $this->assertTrue(MailerFactory::fromArray([], '/x/mail.log') instanceof LogMailer);
        $this->assertTrue(MailerFactory::fromArray(['driver' => 'log'], '/x/mail.log') instanceof LogMailer);
        $smtp = MailerFactory::fromArray(['driver' => 'smtp', 'host' => 'h', 'port' => 25, 'from_address' => 'a@b.com', 'encryption' => 'none'], '/x/mail.log');
        $this->assertTrue($smtp instanceof SmtpMailer);
        $this->assertThrows(MailException::class, fn () => MailerFactory::fromArray(['driver' => 'pigeon'], '/x/mail.log'));
    }
}
