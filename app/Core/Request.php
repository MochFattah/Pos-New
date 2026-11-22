<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $data
     * @param array<string, string> $headers nama header dalam huruf kecil
     * @param array<string, array<string, mixed>> $files
     * @param array<string, mixed> $attributes
     */
    private function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $data,
        private array $headers,
        private array $files,
        private string $ip,
        private array $attributes = [],
    ) {
    }

    /** @param list<string>|null $trustedProxies IP/CIDR proxy tepercaya; null = config security.trusted_proxies */
    public static function fromGlobals(?array $trustedProxies = null): self
    {
        $trustedProxies ??= self::configuredProxies();
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_') && is_string($value)) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }
        foreach (['CONTENT_TYPE' => 'content-type', 'CONTENT_LENGTH' => 'content-length'] as $key => $name) {
            if (isset($_SERVER[$key]) && is_string($_SERVER[$key]) && $_SERVER[$key] !== '') {
                $headers[$name] = $_SERVER[$key];
            }
        }

        $uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';

        return new self(
            strtoupper(is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET'),
            self::pathOf($uri),
            $_GET,
            $_POST,
            $headers,
            $_FILES,
            self::resolveClientIp(
                is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0',
                is_string($_SERVER['HTTP_X_FORWARDED_FOR'] ?? null) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : null,
                $trustedProxies
            ),
            ['https' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'],
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @param array<string, array<string, mixed>> $files struktur seperti $_FILES
     */
    public static function create(string $method, string $uri, array $data = [], array $headers = [], array $files = []): self
    {
        $query = [];
        $queryString = parse_url($uri, PHP_URL_QUERY);
        if (is_string($queryString)) {
            parse_str($queryString, $query);
        }

        return new self(
            strtoupper($method),
            self::pathOf($uri),
            $query,
            $data,
            array_change_key_case($headers, CASE_LOWER),
            $files,
            '127.0.0.1',
        );
    }

    /** @return list<string> */
    private static function configuredProxies(): array
    {
        $list = Config::get('security.trusted_proxies', []);
        return is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
    }

    /**
     * IP klien. X-Forwarded-For hanya dibaca bila REMOTE_ADDR adalah proxy tepercaya; lalu
     * dipilih entri paling kanan yang bukan proxy tepercaya (entri kiri bisa dipalsukan klien).
     * Entri tak valid menghentikan penelusuran dan mengembalikan REMOTE_ADDR (gagal tertutup).
     *
     * @param list<string> $trusted IP atau CIDR (IPv4/IPv6)
     */
    public static function resolveClientIp(string $remote, ?string $forwardedFor, array $trusted): string
    {
        if ($trusted === [] || $forwardedFor === null || !self::ipMatches($remote, $trusted)) {
            return $remote;
        }
        foreach (array_reverse(explode(',', $forwardedFor)) as $entry) {
            $entry = trim($entry);
            $packed = filter_var($entry, FILTER_VALIDATE_IP) !== false ? @inet_pton($entry) : false;
            if ($packed === false) {
                return $remote;
            }
            if (!self::ipMatches($entry, $trusted)) {
                return (string) inet_ntop($packed);
            }
        }
        return $remote;
    }

    /** @param list<string> $list IP atau CIDR; entri rusak diabaikan */
    private static function ipMatches(string $ip, array $list): bool
    {
        $addr = self::pack($ip);
        if ($addr === null) {
            return false;
        }
        foreach ($list as $item) {
            [$net, $bits] = array_pad(explode('/', trim($item), 2), 2, null);
            $base = self::pack((string) $net);
            if ($base === null || strlen($base) !== strlen($addr)) {
                continue;
            }
            $max = strlen($base) * 8;
            if ($bits === null) {
                $len = $max;
            } elseif (preg_match('/^\d{1,3}$/', $bits) === 1 && (int) $bits <= $max) {
                $len = (int) $bits;
            } else {
                continue;
            }
            $bytes = intdiv($len, 8);
            if ($bytes > 0 && substr($addr, 0, $bytes) !== substr($base, 0, $bytes)) {
                continue;
            }
            $rest = $len % 8;
            if ($rest === 0) {
                return true;
            }
            $mask = (0xFF << (8 - $rest)) & 0xFF;
            if ((ord($addr[$bytes]) & $mask) === (ord($base[$bytes]) & $mask)) {
                return true;
            }
        }
        return false;
    }

    /** Biner 4/16 byte; ::ffff:a.b.c.d dinormalkan ke IPv4. */
    private static function pack(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return null;
        }
        if (strlen($packed) === 16 && str_starts_with($packed, "\0\0\0\0\0\0\0\0\0\0\xFF\xFF")) {
            return substr($packed, 12);
        }
        return $packed;
    }

    private static function pathOf(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);
        return is_string($path) && $path !== '' ? $path : '/';
    }

    public function method(): string
    {
        return $this->method;
    }

    /**
     * Path mentah (masih ter-encode, tanpa query string) untuk pencocokan rute.
     */
    public function rawPath(): string
    {
        return $this->path;
    }

    /**
     * Path yang sudah di-decode, hanya untuk kemudahan baca/log; jangan dipakai mencocokkan rute.
     */
    public function path(): string
    {
        return rawurldecode($this->path);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $this->query[$key] ?? $default;
    }

    /** Hanya dari badan permintaan (POST), tidak pernah dari query string; untuk rahasia yang tak boleh ada di URL. */
    public function form(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($this->query, $this->data);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function ip(): string
    {
        return $this->ip;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function withAttribute(string $key, mixed $value): static
    {
        $copy = clone $this;
        $copy->attributes[$key] = $value;
        return $copy;
    }
}
