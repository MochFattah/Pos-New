<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\NotFoundException;
use App\Core\Middleware\MiddlewareInterface;
use Closure;

final class Router
{
    /** @var list<array{method: string, regex: string, handler: callable|array<int, string>, middleware: array<int, MiddlewareInterface|string>}> */
    private array $routes = [];

    /** @var Closure(string): object */
    private Closure $resolver;

    /**
     * @param (callable(string): object)|null $resolver membuat instance controller/middleware dari nama class
     */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver !== null
            ? Closure::fromCallable($resolver)
            : static fn (string $class): object => new $class();
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     * @param array<int, MiddlewareInterface|string> $middleware
     */
    public function get(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     * @param array<int, MiddlewareInterface|string> $middleware
     */
    public function post(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function dispatch(Request $request): Response
    {
        $path = self::normalize($request->rawPath());
        $allowed = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }
            if ($route['method'] !== $request->method()) {
                $allowed[] = $route['method'];
                continue;
            }
            foreach ($matches as $name => $value) {
                if (is_string($name)) {
                    $request = $request->withAttribute($name, rawurldecode($value));
                }
            }
            return $this->run($route['handler'], $route['middleware'], $request);
        }

        if ($allowed !== []) {
            return Response::html('Metode tidak diizinkan.', 405)
                ->withHeader('Allow', implode(', ', array_unique($allowed)));
        }

        throw new NotFoundException();
    }

    /**
     * @param callable|array<int, string> $handler
     * @param array<int, MiddlewareInterface|string> $middleware
     */
    private function add(string $method, string $pattern, callable|array $handler, array $middleware): void
    {
        $parts = preg_split('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', self::normalize($pattern), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $quoted = '';
        foreach ($parts as $i => $part) {
            $quoted .= $i % 2 === 1 ? '(?P<' . $part . '>[^/]+)' : preg_quote($part, '#');
        }
        $this->routes[] = [
            'method' => $method,
            'regex' => '#^' . $quoted . '$#',
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    /**
     * @param callable|array<int, string> $handler
     * @param array<int, MiddlewareInterface|string> $middleware
     */
    private function run(callable|array $handler, array $middleware, Request $request): Response
    {
        $core = function (Request $req) use ($handler): Response {
            if (is_array($handler) && !is_callable($handler)) {
                $handler = [($this->resolver)($handler[0]), $handler[1]];
            }
            /** @var callable $handler */
            return $handler($req);
        };

        $next = $core;
        foreach (array_reverse($middleware) as $item) {
            $instance = is_string($item) ? ($this->resolver)($item) : $item;
            $next = static fn (Request $req): Response => $instance->handle($req, $next);
        }

        return $next($request);
    }

    private static function normalize(string $path): string
    {
        $trimmed = rtrim($path, '/');
        return $trimmed === '' ? '/' : $trimmed;
    }
}
