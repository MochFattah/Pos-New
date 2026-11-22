<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Security\SecurityHeaders;
use Throwable;

final class Application
{
    public function __construct(
        private Router $router,
        private string $logFile,
        private bool $debug = false,
        private bool $forceHttps = false,
        private ?RequestContext $context = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        $this->context?->bind($request);
        return SecurityHeaders::apply(
            $this->dispatch($request),
            $this->forceHttps || $request->attribute('https') === true
        );
    }

    private function dispatch(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (NotFoundException | ForbiddenException | ValidationException $e) {
            return self::page($e->status(), $e->getMessage());
        } catch (HttpException $e) {
            return self::page($e->status(), $e->getMessage() !== '' ? $e->getMessage() : 'Permintaan tidak dapat diproses.');
        } catch (Throwable $e) {
            $id = $this->context?->requestId() ?? bin2hex(random_bytes(6));
            $this->log($id, $request, $e);
            $detail = $this->debug ? $e::class . ': ' . $e->getMessage() : null;
            return self::page(500, 'Terjadi kesalahan pada server.', $id, $detail);
        }
    }

    /** Halaman kesalahan lewat layouts/public; bila lapisan view sendiri gagal, jatuh ke HTML polos agar tetap aman. */
    private static function page(int $status, string $message, ?string $requestId = null, ?string $detail = null): Response
    {
        try {
            $template = is_file(dirname(__DIR__, 2) . '/resources/views/errors/' . $status . '.php') ? (string) $status : 'generic';
            $titles = [403 => 'Akses ditolak', 404 => 'Halaman tidak ditemukan', 419 => 'Sesi kedaluwarsa', 422 => 'Data tidak dapat diproses', 429 => 'Terlalu banyak permintaan', 500 => 'Terjadi kesalahan'];
            return Response::html(View::render('errors/' . $template, [
                'title' => $titles[$status] ?? 'Kesalahan ' . $status,
                'status' => $status,
                'message' => $message,
                'requestId' => $requestId,
                'detail' => $detail,
            ]), $status);
        } catch (Throwable) {
            return self::plainPage($status, $message, $requestId, $detail);
        }
    }

    private static function plainPage(int $status, string $message, ?string $requestId, ?string $detail): Response
    {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<!doctype html><html lang="id"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Kesalahan ' . $status . '</title></head><body>'
            . '<h1>' . $status . '</h1><p>' . $e($message) . '</p>';
        if ($requestId !== null) {
            $html .= '<p>ID permintaan: ' . $e($requestId) . '</p>';
        }
        if ($detail !== null) {
            $html .= '<pre>' . $e($detail) . '</pre>';
        }
        return Response::html($html . '</body></html>', $status);
    }

    private function log(string $id, Request $request, Throwable $e): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf(
            "[%s] %s %s %s %s: %s in %s:%d\n%s\n",
            date('c'),
            $id,
            $request->method(),
            self::loggablePath($request->rawPath()),
            $e::class,
            self::oneLine($e->getMessage()),
            $e->getFile(),
            $e->getLine(),
            self::traceWithoutArgs($e)
        );
        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /** Path untuk log: karakter non-cetak jadi '?', segmen token rute dimasker (token sekali pakai tak boleh masuk log). */
    private static function loggablePath(string $rawPath): string
    {
        $path = preg_replace('/[^\x20-\x7E]/', '?', $rawPath) ?? '?';
        $path = preg_replace('#/(reset-password|verifikasi-email|undangan)/[^?]*#i', '/$1/***', $path) ?? '?';
        return strlen($path) > 200 ? substr($path, 0, 200) . '...' : $path;
    }

    private static function oneLine(string $text): string
    {
        return preg_replace('/[\x00-\x1F\x7F]+/', ' ', $text) ?? '';
    }

    /** Jejak tanpa argumen: argumen dapat memuat kata sandi DB, token, atau data pengguna. */
    private static function traceWithoutArgs(Throwable $e): string
    {
        $lines = [];
        foreach ($e->getTrace() as $i => $frame) {
            $where = isset($frame['file']) ? $frame['file'] . '(' . ($frame['line'] ?? 0) . ')' : '[internal function]';
            $call = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '') . '()';
            $lines[] = '#' . $i . ' ' . $where . ': ' . $call;
        }
        $lines[] = '#' . count($lines) . ' {main}';
        return implode("\n", $lines);
    }
}
