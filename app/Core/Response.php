<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /** @var array<string, string> nama header huruf kecil => nilai */
    private array $headers = [];

    /** @var array<string, string> nama header huruf kecil => nama asli */
    private array $names = [];

    public function __construct(private string $body = '', private int $status = 200)
    {
    }

    public static function html(string $body, int $status = 200): self
    {
        return (new self($body, $status))->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * @param array<mixed> $data
     */
    public static function json(array $data, int $status = 200): self
    {
        return (new self(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $status))
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return (new self('', $status))->withHeader('Location', $url);
    }

    public function withHeader(string $name, string $value): static
    {
        $copy = clone $this;
        $key = strtolower($name);
        $copy->headers[$key] = $value;
        $copy->names[$key] = $name;
        return $copy;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $key => $value) {
            header($this->names[$key] . ': ' . $value);
        }
        echo $this->body;
    }
}
