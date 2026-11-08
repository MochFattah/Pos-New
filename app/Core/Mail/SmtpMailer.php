<?php

declare(strict_types=1);

namespace App\Core\Mail;

/**
 * Klien SMTP minimal. Belum diverifikasi terhadap server SMTP nyata.
 * Enkripsi: none | starttls | tls (TLS implisit via tls://).
 */
class SmtpMailer implements MailerInterface
{
    /** @var callable|null */
    private $connector;

    public function __construct(
        private string $host,
        private int $port,
        private string $user,
        private string $pass,
        private string $from,
        private string $encryption,
        ?callable $connector = null,
        private string $ehloHostname = 'localhost',
        private int $timeout = 10,
        private string $fromName = ''
    ) {
        if (!in_array($encryption, ['none', 'starttls', 'tls'], true)) {
            throw new MailException('Mode enkripsi SMTP tidak dikenal.');
        }
        $this->connector = $connector;
    }

    public function send(string $to, string $subject, string $textBody): void
    {
        foreach ([$to, $subject, $this->from, $this->fromName, $this->ehloHostname] as $v) {
            if (preg_match('/[\r\n\0]/', $v) === 1) {
                throw new MailException('Karakter baris baru tidak diizinkan pada header email.');
            }
        }
        foreach ([$to, $this->from] as $addr) {
            if (filter_var($addr, FILTER_VALIDATE_EMAIL) === false) {
                throw new MailException('Alamat email tidak valid.');
            }
        }

        $stream = $this->connect();
        try {
            $this->conversation($stream, $to, $subject, $textBody);
        } catch (MailException $e) {
            @fwrite($stream, "QUIT\r\n");
            throw $e;
        } finally {
            @fclose($stream);
        }
    }

    /** @param resource $stream */
    protected function enableCrypto($stream): void
    {
        if (@stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
            throw new MailException('Negosiasi TLS gagal.');
        }
    }

    /** @return resource */
    private function connect()
    {
        if ($this->connector !== null) {
            $stream = ($this->connector)();
        } else {
            $scheme = $this->encryption === 'tls' ? 'tls' : 'tcp';
            $stream = @stream_socket_client(
                sprintf('%s://%s:%d', $scheme, $this->host, $this->port),
                $errno,
                $errstr,
                (float) $this->timeout
            );
        }
        if (!is_resource($stream)) {
            throw new MailException('Tidak dapat terhubung ke server SMTP.');
        }
        stream_set_timeout($stream, $this->timeout);
        return $stream;
    }

    /** @param resource $s */
    private function conversation($s, string $to, string $subject, string $body): void
    {
        $this->expect($s, [220], 'salam server');
        $this->command($s, 'EHLO ' . $this->ehloHostname, [250], 'EHLO');

        if ($this->encryption === 'starttls') {
            $this->command($s, 'STARTTLS', [220], 'STARTTLS');
            $this->enableCrypto($s);
            $this->command($s, 'EHLO ' . $this->ehloHostname, [250], 'EHLO setelah TLS');
        }

        if ($this->user !== '') {
            $this->command($s, 'AUTH LOGIN', [334], 'AUTH');
            $this->command($s, base64_encode($this->user), [334], 'otentikasi');
            $this->command($s, base64_encode($this->pass), [235], 'otentikasi');
        }

        $this->command($s, 'MAIL FROM:<' . $this->from . '>', [250], 'MAIL FROM');
        $this->command($s, 'RCPT TO:<' . $to . '>', [250, 251], 'RCPT TO');
        $this->command($s, 'DATA', [354], 'DATA');

        $this->write($s, $this->buildMessage($to, $subject, $body) . "\r\n.\r\n");
        $this->expect($s, [250], 'pengiriman pesan');

        $this->write($s, "QUIT\r\n");
        // Balasan QUIT tidak menentukan keberhasilan pengiriman.
        try {
            $this->readReply($s);
        } catch (MailException) {
        }
    }

    private function buildMessage(string $to, string $subject, string $body): string
    {
        $domain = substr(strrchr($this->from, '@') ?: '@localhost', 1);
        $fromHeader = $this->fromName === ''
            ? '<' . $this->from . '>'
            : $this->encodeHeader(str_replace(['"', '\\'], '', $this->fromName), true) . ' <' . $this->from . '>';

        $headers = [
            'Date: ' . date('r'),
            'From: ' . $fromHeader,
            'To: <' . $to . '>',
            'Subject: ' . $this->encodeHeader($subject, false),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        $body = (string) preg_replace('/\r\n|\r|\n/', "\r\n", $body);
        $body = (string) preg_replace('/^\./m', '..', $body);
        if (!str_ends_with($body, "\r\n")) {
            $body .= "\r\n";
        }
        // Akhiran CRLF dari badan dipakai bersama pembatas "\r\n.\r\n" oleh pemanggil.
        return implode("\r\n", $headers) . "\r\n\r\n" . substr($body, 0, -2);
    }

    /** RFC 2047 (encoded-word B) bila ada karakter non-ASCII. */
    private function encodeHeader(string $value, bool $quoteAscii): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value) !== 1) {
            return $quoteAscii ? '"' . $value . '"' : $value;
        }
        $words = [];
        for ($offset = 0, $len = strlen($value); $offset < $len;) {
            $chunk = mb_strcut($value, $offset, 45, 'UTF-8');
            if ($chunk === '') {
                break;
            }
            $offset += strlen($chunk);
            $words[] = '=?UTF-8?B?' . base64_encode($chunk) . '?=';
        }
        return implode("\r\n ", $words);
    }

    /**
     * @param resource $s
     * @param list<int> $ok
     */
    private function command($s, string $line, array $ok, string $stage): void
    {
        $this->write($s, $line . "\r\n");
        $this->expect($s, $ok, $stage);
    }

    /** @param resource $s */
    private function write($s, string $data): void
    {
        $len = strlen($data);
        for ($sent = 0; $sent < $len;) {
            $n = @fwrite($s, substr($data, $sent));
            if ($n === false || $n === 0) {
                throw new MailException('Gagal menulis ke server SMTP.');
            }
            $sent += $n;
        }
    }

    /**
     * @param resource $s
     * @param list<int> $ok
     */
    private function expect($s, array $ok, string $stage): void
    {
        [$code, $text] = $this->readReply($s);
        if (!in_array($code, $ok, true)) {
            $text = substr((string) preg_replace('/[^\x20-\x7E]/', '', $text), 0, 200);
            throw new MailException(sprintf('Server SMTP menolak pada tahap %s: %d %s', $stage, $code, $text));
        }
    }

    /**
     * Membaca balasan multibaris ("250-..." sampai "250 ...").
     *
     * @param resource $s
     * @return array{0: int, 1: string}
     */
    private function readReply($s): array
    {
        $text = '';
        while (true) {
            $line = fgets($s, 1024);
            if ($line === false) {
                $meta = stream_get_meta_data($s);
                throw new MailException(!empty($meta['timed_out'])
                    ? 'Waktu tunggu server SMTP habis.'
                    : 'Koneksi SMTP terputus.');
            }
            if (preg_match('/^(\d{3})([ -])(.*)$/s', rtrim($line, "\r\n"), $m) !== 1) {
                throw new MailException('Balasan SMTP tidak dikenali.');
            }
            $text = $m[3];
            if ($m[2] === ' ') {
                return [(int) $m[1], $text];
            }
        }
    }
}
