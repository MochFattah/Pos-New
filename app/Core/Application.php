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
    ) {
    }

    public function handle(Request $request): Response
    {
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
            $id = bin2hex(random_bytes(6));
            $this->log($id, $request, $e);
            $detail = $this->debug ? $e::class . ': ' . $e->getMessage() : null;
            return self::page(500, 'Terjadi kesalahan pada server.', $id, $detail);
        }
    }

    private static function page(int $status, string $message, ?string $requestId = null, ?string $detail = null): Response
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
            $request->path(),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
