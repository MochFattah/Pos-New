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

        $lines = explode("\r\n", $w);
        $dataAt = array_search('DATA', $lines, true);
        $this->assertTrue($dataAt !== false);
        $this->assertSame([
            'EHLO pos.local',
            'AUTH LOGIN',
            base64_encode('user'),
            base64_encode('S3cretPass'),
            'MAIL FROM:<no-reply@toko.test>',
            'RCPT TO:<budi@example.com>',
            'DATA',
        ], array_slice($lines, 0, $dataAt + 1));
        $this->assertSame(['.', 'QUIT', ''], array_slice($lines, -3));
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
        $m2 = new SmtpMailer('h', 25, '', '', 'a@toko.test', 'none', $connector, 'localhost', 0.05);
        $this->assertThrows(MailException::class, fn () => $m2->send('b@x.com', 's', 'x'));
    }

    public function testSmtpMailerTimeoutIsSubSecondAndReportsIndonesianMessage(): void
    {
        [$connector] = $this->pair([]);
        $m = new SmtpMailer('h', 25, 'user', 'S3cretPass', 'a@toko.test', 'none', $connector, 'localhost', 0.05);
        $t = microtime(true);
        try {
            $m->send('b@x.com', 's', 'x');
            $this->fail('harus melempar');
        } catch (MailException $e) {
            $this->assertContains('Waktu tunggu server SMTP habis', $e->getMessage());
            $this->assertFalse(str_contains($e->getMessage(), 'S3cretPass'));
            $this->assertFalse(str_contains($e->getMessage(), 'user'));
        }
        $this->assertTrue(microtime(true) - $t < 0.9, 'waktu tunggu harus di bawah satu detik');
    }

    public function testSmtpMailerNormalisesBareCrAndLfToCrlf(): void
    {
        [$connector, $server] = $this->pair(['220 hi', '250 ok', '250 f', '250 r', '354 go', '250 q', '221 b']);
        (new SmtpMailer('h', 25, '', '', 'a@toko.test', 'none', $connector, 'localhost', 2))->send('b@x.com', 's', "a\rb\nc\r\nd");
        $this->assertContains("\r\n\r\na\r\nb\r\nc\r\nd\r\n.\r\n", $this->drain($server));
    }

    public function testSmtpMailerStuffsDotOnFirstBodyLine(): void
    {
        [$connector, $server] = $this->pair(['220 hi', '250 ok', '250 f', '250 r', '354 go', '250 q', '221 b']);
        (new SmtpMailer('h', 25, '', '', 'a@toko.test', 'none', $connector, 'localhost', 2))->send('b@x.com', 's', ".awal\nlain");
        $this->assertContains("\r\n\r\n..awal\r\nlain\r\n.\r\n", $this->drain($server));
    }

    public function testSmtpMailerAssemblesMultilineReplyAcrossPartialReads(): void
    {
        ChunkedStream::$script = implode("\r\n", ['220 hi', '250-satu', '250-dua', '250 tiga', '250 f', '250 r', '354 go', '250 q', '221 b']) . "\r\n";
        ChunkedStream::$written = '';
        ChunkedStream::register();
        $connector = static fn () => fopen('chunked://x', 'r+');
        (new SmtpMailer('h', 25, '', '', 'a@toko.test', 'none', $connector, 'localhost', 2))->send('b@x.com', 's', 'x');
        $this->assertTrue(str_starts_with(ChunkedStream::$written, "EHLO localhost\r\nMAIL FROM:<a@toko.test>\r\nRCPT TO:<b@x.com>\r\nDATA\r\n"));
        $this->assertTrue(str_ends_with(ChunkedStream::$written, "\r\n.\r\nQUIT\r\n"));
    }

    public function testSmtpMailerEncodesNonAsciiFromName(): void
    {
        [$connector, $server] = $this->pair(['220 hi', '250 ok', '250 f', '250 r', '354 go', '250 q', '221 b']);
        (new SmtpMailer('h', 25, '', '', 'a@toko.test', 'none', $connector, 'localhost', 2, 'Toko Sëhat'))->send('b@x.com', 's', 'x');
        $w = $this->drain($server);
        $this->assertTrue(preg_match('/^From: (.*)$/m', $w, $m) === 1);
        $this->assertContains('=?UTF-8?B?', $m[1]);
        $this->assertFalse(str_contains($m[1], 'Sëhat'));
        $this->assertSame('Toko Sëhat <a@toko.test>', iconv_mime_decode(rtrim($m[0]), 0, 'UTF-8') === false ? '' : substr(iconv_mime_decode(rtrim($m[0]), 0, 'UTF-8'), 6));
    }

    public function testSmtpMailerSplitsLongMultibyteSubjectIntoValidEncodedWords(): void
    {
        $subject = str_repeat('Pembayaran Ñandú Ünggul ', 6);
        $this->assertTrue(strlen($subject) > 90);
        [$connector, $server] = $this->pair(['220 hi', '250 ok', '250 f', '250 r', '354 go', '250 q', '221 b']);
        (new SmtpMailer('h', 25, '', '', 'a@toko.test', 'none', $connector, 'localhost', 2))->send('b@x.com', $subject, 'x');
        $w = $this->drain($server);
        $this->assertTrue(preg_match('/^Subject: [^\r\n]*(?:\r\n [^\r\n]*)*/m', $w, $m) === 1);
        $header = $m[0];
        $this->assertTrue(preg_match_all('/=\?UTF-8\?B\?[A-Za-z0-9+\/=]+\?=/', $header, $words) >= 2);
        foreach ($words[0] as $word) {
            $this->assertTrue(strlen($word) <= 75, 'encoded-word > 75: ' . strlen($word));
        }
        $this->assertSame($subject, substr((string) iconv_mime_decode($header, 0, 'UTF-8'), 9));
    }

    public function testSmtpMailerRejectsUnknownEncryption(): void
    {
        $this->assertThrows(MailException::class, fn () => new SmtpMailer('h', 25, '', '', 'a@b.com', 'ssl'));
    }

    public function testFactoryDefaultsToLogAndBuildsSmtp(): void
    {
        $this->assertTrue(MailerFactory::fromArray(['env' => 'local'], '/x') instanceof LogMailer);
        $this->assertTrue(MailerFactory::fromArray(['driver' => 'log', 'env' => 'local'], '/x') instanceof LogMailer);
        $smtp = MailerFactory::fromArray(['driver' => 'smtp', 'host' => 'h', 'port' => 25, 'from_address' => 'a@b.com', 'encryption' => 'none'], '/x');
        $this->assertTrue($smtp instanceof SmtpMailer);
        $this->assertThrows(MailException::class, fn () => MailerFactory::fromArray(['driver' => 'pigeon'], '/x'));
    }

    public function testFactoryRefusesLogDriverInProductionUnlessAllowed(): void
    {
        foreach ([['driver' => 'log'], ['driver' => 'log', 'env' => 'production'], ['driver' => 'log', 'env' => 'production', 'allow_log' => false], ['env' => 'production']] as $cfg) {
            $this->assertThrows(MailException::class, fn () => MailerFactory::fromArray($cfg, '/x'));
        }
        $this->assertTrue(MailerFactory::fromArray(['driver' => 'log', 'env' => 'production', 'allow_log' => true], '/x') instanceof LogMailer);
        $this->assertTrue(MailerFactory::fromArray(['driver' => 'log', 'env' => 'local'], '/x') instanceof LogMailer);
    }

    public function testLazyMailerDefersProductionRefusalToSend(): void
    {
        $lazy = new \App\Core\Mail\LazyMailer(static fn () => MailerFactory::fromArray(['driver' => 'log', 'env' => 'production'], '/x'));
        $this->assertTrue($lazy instanceof \App\Core\Mail\MailerInterface, 'dibangun tanpa galat');
        $this->assertThrows(MailException::class, fn () => $lazy->send('a@b.com', 'S', 'isi'));
        $this->assertThrows(MailException::class, fn () => $lazy->send('a@b.com', 'S', 'isi'));
    }

    public function testLazyFromConfigDoesNotThrowOnConstruction(): void
    {
        $this->assertTrue(MailerFactory::lazyFromConfig() instanceof \App\Core\Mail\LazyMailer);
    }

    public function testFactoryLogMailerWritesToConfiguredPath(): void
    {
        $abs = $this->dir . '/abs/custom.log';
        MailerFactory::fromArray(['driver' => 'log', 'env' => 'local', 'log_path' => $abs], $this->dir . '/base')->send('a@b.com', 'S', 'isi');
        $this->assertContains('To: a@b.com', (string) file_get_contents($abs));

        MailerFactory::fromArray(['driver' => 'log', 'env' => 'local', 'log_path' => 'var/rel.log'], $this->dir . '/base')->send('c@b.com', 'S', 'isi');
        $this->assertContains('To: c@b.com', (string) file_get_contents($this->dir . '/base/var/rel.log'));

        MailerFactory::fromArray(['driver' => 'log', 'env' => 'local'], $this->dir . '/base')->send('d@b.com', 'S', 'isi');
        $this->assertContains('To: d@b.com', (string) file_get_contents($this->dir . '/base/storage/logs/mail.log'));
    }

    public function testFactoryFailsEarlyOnIncompleteSmtpConfig(): void
    {
        $ok = ['driver' => 'smtp', 'host' => 'h', 'port' => 25, 'from_address' => 'a@b.com', 'encryption' => 'none'];
        $this->assertThrows(MailException::class, fn () => MailerFactory::fromArray(['host' => ''] + $ok, '/x'));
        $this->assertThrows(MailException::class, fn () => MailerFactory::fromArray(['from_address' => ''] + $ok, '/x'));
    }

    public function testFactoryBuildsWorkingSmtpMailerFromConfig(): void
    {
        [$connector, $server] = $this->pair(['220 hi', '250 AUTH LOGIN', '334 a', '334 b', '235 ok', '250 f', '250 r', '354 go', '250 q', '221 b']);
        $mailer = MailerFactory::fromArray([
            'driver' => 'smtp', 'host' => 'smtp.test', 'port' => 25, 'username' => 'u', 'password' => 'p',
            'from_address' => 'no-reply@toko.test', 'from_name' => 'Toko', 'encryption' => 'none',
            'timeout' => 2.5, 'ehlo_hostname' => 'pos.local',
        ], '/x', $connector);
        $this->assertTrue($mailer instanceof SmtpMailer);
        $mailer->send('b@x.com', 'Halo', 'isi');
        $w = $this->drain($server);
        $this->assertTrue(str_starts_with($w, "EHLO pos.local\r\nAUTH LOGIN\r\n"));
        $this->assertContains('From: "Toko" <no-reply@toko.test>', $w);
    }
}

/** Aliran palsu yang menyajikan skrip balasan paling banyak 3 byte per pembacaan. */
final class ChunkedStream
{
    public static string $script = '';
    public static string $written = '';
    private int $pos = 0;
    /** @var resource|null */
    public $context;

    public static function register(): void
    {
        if (!in_array('chunked', stream_get_wrappers(), true)) {
            stream_wrapper_register('chunked', self::class);
        }
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened): bool
    {
        $this->pos = 0;
        return true;
    }

    public function stream_read(int $count): string|false
    {
        // Potongan tidak melewati akhir baris agar tidak ada data tersisa di buffer baca PHP.
        $chunk = substr(self::$script, $this->pos, 3);
        $nl = strpos($chunk, "\n");
        if ($nl !== false) {
            $chunk = substr($chunk, 0, $nl + 1);
        }
        $this->pos += strlen($chunk);
        return $chunk;
    }

    public function stream_write(string $data): int
    {
        self::$written .= $data;
        return strlen($data);
    }

    public function stream_eof(): bool
    {
        return $this->pos >= strlen(self::$script);
    }

    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
    {
        return false;
    }

    /** @return array<string, int> */
    public function stream_stat(): array
    {
        return [];
    }
}
