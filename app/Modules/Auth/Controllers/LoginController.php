<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Security\Csrf;
use App\Core\Security\Session;
use App\Core\Validation\Validator;
use App\Core\View;
use App\Modules\Auth\Services\AuthResult;
use App\Modules\Auth\Services\AuthService;

final class LoginController
{
    private const INVALID = 'Email atau kata sandi salah.';
    private const LOCKED = 'Terlalu banyak percobaan masuk. Coba lagi dalam beberapa menit.';
    private const UNVERIFIED = 'Email Anda belum diverifikasi. Periksa kotak masuk Anda atau kirim ulang tautan verifikasi.';

    public function __construct(private readonly AuthService $auth)
    {
    }

    public function showForm(Request $request): Response
    {
        $session = $request->attribute('session');
        if ($session instanceof Session && $this->auth->userFromSession($session) !== null) {
            return Response::redirect('/beranda');
        }
        $notice = $session instanceof Session ? $session->pullFlash('notice') : null;
        return $this->form($request, [], '', null, is_string($notice) ? $notice : null, false, 200);
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
            return $this->form($request, $result->errors(), $emailInput, null, null, false, 422);
        }
        $data = $result->validated();

        $outcome = $this->auth->attempt($data['email'], $data['password'], $request->ip(), $session);
        return match ($outcome->status) {
            AuthResult::OK => Response::redirect('/beranda'),
            AuthResult::LOCKED => $this->form($request, [], $emailInput, self::LOCKED, null, false, 429),
            AuthResult::UNVERIFIED => $this->form($request, [], $emailInput, self::UNVERIFIED, null, true, 403),
            default => $this->form($request, [], $emailInput, self::INVALID, null, false, 422),
        };
    }

    public function logout(Request $request): Response
    {
        $session = $request->attribute('session');
        if ($session instanceof Session) {
            $this->auth->logout($session);
        }
        return Response::redirect('/masuk');
    }

    /** @param array<string, string> $errors */
    private function form(Request $request, array $errors, string $email, ?string $error, ?string $notice, bool $offerResend, int $status): Response
    {
        $session = $request->attribute('session');
        return Response::html(View::render('auth/masuk', [
            'title' => 'Masuk',
            'csrf' => $session instanceof Session ? Csrf::token($session) : '',
            'errors' => $errors,
            'email' => $email,
            'error' => $error,
            'notice' => $notice,
            'offerResend' => $offerResend,
        ]), $status);
    }
}
