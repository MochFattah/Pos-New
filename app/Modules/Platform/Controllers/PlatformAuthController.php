<?php

declare(strict_types=1);

namespace App\Modules\Platform\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Security\Csrf;
use App\Core\Security\Session;
use App\Core\Validation\Validator;
use App\Core\View;
use App\Modules\Platform\Services\PlatformAuthService;

final class PlatformAuthController
{
    private const INVALID = 'Email atau kata sandi salah.';
    private const LOCKED = 'Terlalu banyak percobaan masuk. Coba lagi dalam beberapa menit.';

    public function __construct(private readonly PlatformAuthService $auth)
    {
    }

    public function showForm(Request $request): Response
    {
        $session = $request->attribute('session');
        if ($session instanceof Session && $this->auth->userFromSession($session) !== null) {
            return Response::redirect('/platform');
        }
        return $this->form($request, [], '', null, 200);
    }

    public function submit(Request $request): Response
    {
        $session = $request->attribute('session');
        if (!$session instanceof Session) {
            return Response::html('Sesi tidak tersedia.', 500);
        }
        $result = Validator::make($request->all(), [
            'email' => 'required|max:190',
            'password' => 'required|raw',
        ], ['email' => 'email', 'password' => 'kata sandi']);
        $raw = $request->input('email', '');
        $emailInput = is_string($raw) ? trim($raw) : '';
        if ($result->fails()) {
            return $this->form($request, $result->errors(), $emailInput, null, 422);
        }
        $data = $result->validated();
        return match ($this->auth->attempt($data['email'], $data['password'], $request->ip(), $session)) {
            PlatformAuthService::OK => Response::redirect('/platform'),
            PlatformAuthService::LOCKED => $this->form($request, [], $emailInput, self::LOCKED, 429),
            default => $this->form($request, [], $emailInput, self::INVALID, 422),
        };
    }

    public function logout(Request $request): Response
    {
        $session = $request->attribute('session');
        if ($session instanceof Session) {
            $this->auth->logout($session);
        }
        return Response::redirect('/platform/masuk');
    }

    /** @param array<string, string> $errors */
    private function form(Request $request, array $errors, string $email, ?string $error, int $status): Response
    {
        $session = $request->attribute('session');
        return Response::html(View::render('platform/masuk', [
            'title' => 'Masuk platform',
            'csrf' => $session instanceof Session ? Csrf::token($session) : '',
            'errors' => $errors,
            'email' => $email,
            'error' => $error,
        ]), $status)->sensitive();
    }
}
