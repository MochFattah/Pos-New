<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Security\Session;
use Closure;

final class SessionMiddleware implements MiddlewareInterface
{
    private Closure $factory;

    private Closure $clock;

    /**
     * @param callable(): Session $factory
     * @param (callable(): int)|null $clock
     */
    public function __construct(callable $factory, private readonly int $idleSeconds, ?callable $clock = null)
    {
        $this->factory = Closure::fromCallable($factory);
        $this->clock = $clock !== null ? Closure::fromCallable($clock) : static fn (): int => time();
    }

    public function handle(Request $request, callable $next): Response
    {
        $session = ($this->factory)();
        $session->start();
        if ($session->isIdleExpired($this->idleSeconds)) {
            $session->destroy();
            $session->start();
        }
        $session->set('_last_activity', ($this->clock)());

        return $next($request->withAttribute('session', $session));
    }
}
