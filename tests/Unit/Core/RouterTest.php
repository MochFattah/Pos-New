<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Application;
use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Middleware\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use RuntimeException;
use Tests\Support\Fixtures\DummyController;
use Tests\Support\Fixtures\TraceMiddleware;
use Tests\Support\Fixtures\TraceMiddlewareB;
use Tests\Support\TestCase;

final class RouterTest extends TestCase
{
    /** @var list<string> */
    public static array $trace = [];
    private string $log = '';

    protected function setUp(): void
    {
        self::$trace = [];
        $this->log = sys_get_temp_dir() . '/pos-app-' . bin2hex(random_bytes(6)) . '.log';
    }

    protected function tearDown(): void
    {
        @unlink($this->log);
    }

    public function testMatchesParameterizedRoute(): void
    {
        $router = new Router();
        $router->get('/branches/{id}', fn (Request $r): Response => Response::html('cabang ' . $r->attribute('id')));
        $res = $router->dispatch(Request::create('GET', '/branches/42'));
        $this->assertSame('cabang 42', $res->body());
        $res = $router->dispatch(Request::create('GET', '/branches/7/'));
        $this->assertSame('cabang 7', $res->body());
    }

    public function testParamsAreDecodedExactlyOnce(): void
    {
        $router = new Router();
        $router->get('/x/{v}', fn (Request $r): Response => Response::html((string) $r->attribute('v')));
        $this->assertSame('%41', $router->dispatch(Request::create('GET', '/x/%2541'))->body());
        $this->assertSame('100%', $router->dispatch(Request::create('GET', '/x/100%25'))->body());
        $this->assertSame('abc-1', $router->dispatch(Request::create('GET', '/x/abc-1'))->body());
    }

    public function testEncodedSlashDoesNotChangeSegmentBoundaries(): void
    {
        $router = new Router();
        $router->get('/x/{v}', fn (Request $r): Response => Response::html((string) $r->attribute('v')));
        $router->get('/y/{a}/{b}', fn (): Response => Response::html('dua'));
        $this->assertSame('a/b', $router->dispatch(Request::create('GET', '/x/a%2Fb'))->body());
        $this->assertThrows(NotFoundException::class, fn () => $router->dispatch(Request::create('GET', '/y/a%2Fb')));
    }

    public function testUnknownPathThrowsNotFound(): void
    {
        $router = new Router();
        $router->get('/a', fn (): Response => Response::html('a'));
        $this->assertThrows(NotFoundException::class, fn () => $router->dispatch(Request::create('GET', '/b')));
    }

    public function testWrongMethodReturns405(): void
    {
        $router = new Router();
        $router->post('/a', fn (): Response => Response::html('a'));
        $res = $router->dispatch(Request::create('GET', '/a'));
        $this->assertSame(405, $res->status());
        $this->assertSame('POST', $res->header('Allow'));
    }

    public function testMiddlewareRunsInDeclaredOrder(): void
    {
        $router = new Router();
        $router->get('/x', function (): Response {
            self::$trace[] = 'handler';
            return Response::html('ok');
        }, [new TraceMiddleware('A'), TraceMiddlewareB::class]);
        $res = $router->dispatch(Request::create('GET', '/x'));
        $this->assertSame('ok', $res->body());
        $this->assertSame(['A>', 'B>', 'handler', '<B', '<A'], self::$trace);
    }

    public function testControllerArrayHandlerUsesResolver(): void
    {
        $router = new Router(fn (string $c): object => new $c('dep'));
        $router->get('/c/{n}', [DummyController::class, 'show']);
        $res = $router->dispatch(Request::create('GET', '/c/5'));
        $this->assertSame('dep-5', $res->body());
    }

    public function testQueryStringIsIgnoredForMatching(): void
    {
        $router = new Router();
        $router->get('/q', fn (Request $r): Response => Response::html((string) $r->input('a')));
        $this->assertSame('1', $router->dispatch(Request::create('GET', '/q?a=1'))->body());
    }

    public function testApplicationHidesExceptionDetail(): void
    {
        $router = new Router();
        $router->get('/boom', function (): Response {
            throw new RuntimeException("SQLSTATE[42S02]: Table 'pos.secret' not found");
        });
        $app = new Application($router, $this->log, false);
        $res = $app->handle(Request::create('GET', '/boom'));
        $this->assertSame(500, $res->status());
        $this->assertFalse(str_contains($res->body(), 'RuntimeException'));
        $this->assertFalse(str_contains($res->body(), 'SQLSTATE'));
        $this->assertFalse(str_contains($res->body(), '.php'));
        $this->assertContains('Terjadi kesalahan pada server.', $res->body());
        $logged = (string) file_get_contents($this->log);
        $this->assertContains('SQLSTATE', $logged);
    }

    public function testApplicationMapsHttpExceptions(): void
    {
        $router = new Router();
        $router->get('/f', function (): Response {
            throw new ForbiddenException();
        });
        $router->post('/v', function (): Response {
            throw new ValidationException(['nama' => 'wajib']);
        });
        $app = new Application($router, $this->log, false);
        $this->assertSame(404, $app->handle(Request::create('GET', '/none'))->status());
        $this->assertContains('Halaman tidak ditemukan.', $app->handle(Request::create('GET', '/none'))->body());
        $f = $app->handle(Request::create('GET', '/f'));
        $this->assertSame(403, $f->status());
        $this->assertContains('Anda tidak memiliki izin untuk melakukan tindakan ini.', $f->body());
        $this->assertSame(422, $app->handle(Request::create('POST', '/v'))->status());
    }

    public function testDebugModeShowsMessageButNeverTrace(): void
    {
        $router = new Router();
        $router->get('/boom', function (): Response {
            throw new RuntimeException('detail-debug');
        });
        $res = (new Application($router, $this->log, true))->handle(Request::create('GET', '/boom'));
        $this->assertContains('detail-debug', $res->body());
        $this->assertFalse(str_contains($res->body(), 'Stack trace'));
    }

    public function testLogNeverContainsExceptionArgs(): void
    {
        $prev = ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');
        try {
            $thrower = static function (string $password): void {
                throw new RuntimeException('gagal koneksi');
            };
            $router = new Router();
            $router->get('/boom', function () use ($thrower): Response {
                $thrower('SUPER-SECRET-PASSWORD');
                return Response::html('x');
            });
            (new Application($router, $this->log, false))->handle(Request::create('GET', '/boom'));
        } finally {
            ini_set('zend.exception_ignore_args', (string) $prev);
        }
        $logged = (string) file_get_contents($this->log);
        $this->assertContains('gagal koneksi', $logged);
        $this->assertContains('{main}', $logged);
        $this->assertFalse(str_contains($logged, 'SUPER-SECRET-PASSWORD'));
    }

    public function testLogSanitisesPathAndMasksTokenSegments(): void
    {
        $router = new Router();
        $router->get('/reset-password/{token}', function (): Response {
            throw new RuntimeException('boom');
        });
        $router->get('/x', function (): Response {
            throw new RuntimeException("baris1\nFORGED line");
        });
        $app = new Application($router, $this->log, false);
        $app->handle(Request::create('GET', '/reset-password/TOKEN123abc'));
        $app->handle(Request::create('GET', '/x'));
        $logged = (string) file_get_contents($this->log);
        $this->assertFalse(str_contains($logged, 'TOKEN123abc'));
        $this->assertContains('/reset-password/***', $logged);
        $this->assertFalse(str_contains($logged, "\nFORGED"));
        foreach (explode("\n", $logged) as $line) {
            $this->assertFalse(str_contains($line, "\x01"));
        }
        $m = new \ReflectionMethod(Application::class, 'loggablePath');
        $m->setAccessible(true);
        $this->assertSame('/x??/y', $m->invoke(null, "/x\x01\n/y"));
        $this->assertSame('/undangan/***', $m->invoke(null, '/undangan/ABC%0Aforge'));
        $this->assertSame('/verifikasi-email/***', $m->invoke(null, '/verifikasi-email/tok'));
    }
}
