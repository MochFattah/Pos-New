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

    public static function fromGlobals(): self
    {
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
            is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0',
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public static function create(string $method, string $uri, array $data = [], array $headers = []): self
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
            [],
            '127.0.0.1',
        );
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
