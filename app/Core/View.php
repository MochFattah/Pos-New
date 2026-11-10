<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

/**
 * Perender templat PHP sederhana. Di dalam templat gunakan $this->e($nilai) untuk
 * setiap keluaran; HTML mentah hanya lewat $this->raw() secara eksplisit.
 */
final class View
{
    private const NAME_PATTERN = '#^[a-z0-9_-]+(/[a-z0-9_-]+)*\z#D';

    private function __construct()
    {
    }

    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = [], string $layout = 'public'): string
    {
        $self = new self();
        $content = $self->include(self::path($template), $data);
        return $self->include(self::path('layouts/' . $layout), array_merge($data, ['content' => new RawHtml($content)]) + ['title' => ''], true);
    }

    public static function e(mixed $value): string
    {
        if ($value instanceof RawHtml) {
            return (string) $value;
        }
        return htmlspecialchars(is_scalar($value) || $value === null || $value instanceof \Stringable ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function raw(string $html): RawHtml
    {
        return new RawHtml($html);
    }

    private static function path(string $name): string
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException('Nama templat tidak valid.');
        }
        $file = dirname(__DIR__, 2) . '/resources/views/' . $name . '.php';
        if (!is_file($file)) {
            throw new InvalidArgumentException('Templat tidak ditemukan: ' . $name);
        }
        return $file;
    }

    /** @param array<string, mixed> $viewData */
    private function include(string $viewFile, array $viewData, bool $isLayout = false): string
    {
        unset($viewData['this'], $viewData['viewFile'], $viewData['viewData'], $viewData['isLayout']);
        extract($viewData, EXTR_SKIP);
        ob_start();
        try {
            require $viewFile;
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
