<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\Mail\MailException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security\Csrf;
use App\Core\Security\RateLimiter;
use App\Core\Security\Session;
use App\Core\Validation\Validator;
use App\Core\View;
use App\Modules\Auth\Services\PasswordResetService;

final class PasswordResetController
{
    private const TOO_MANY = 'Terlalu banyak percobaan. Coba lagi beberapa saat lagi.';
    private const NEUTRAL = 'Jika email terdaftar dan sudah terverifikasi, kami telah mengirim tautan untuk mengatur ulang kata sandi.';

    public function __construct(
        private readonly PasswordResetService $service,
        private readonly RateLimiter $limiter,
    ) {
    }

    public function showRequestForm(Request $request): Response
    {
        return $this->requestPage($request, [], '', null, 200);
    }

    public function submitRequest(Request $request): Response
    {
        $result = Validator::make($request->all(), ['email' => 'required|email|max:190'], ['email' => 'email']);
        $raw = $request->input('email', '');
        $emailInput = is_string($raw) ? trim($raw) : '';
        if ($result->fails()) {
            return $this->requestPage($request, $result->errors(), $emailInput, null, 422);
        }
        $email = $result->validated()['email'];
        $ip = $request->ip();

        $perEmail = 'reset:' . Validator::normalizeEmail($email);
        if ($this->limiter->tooManyAttempts($perEmail, $ip) || $this->limiter->tooManyAttempts('reset', $ip)) {
            return $this->notice('Terlalu banyak percobaan', self::TOO_MANY, 429);
        }
        $this->limiter->record($perEmail, $ip, false);
        $this->limiter->record('reset', $ip, false);

        try {
            $this->service->request($email, $ip);
        } catch (MailException) {
            // Cadangan: layanan sudah menelan kegagalan kirim; respons harus tetap netral.
        }
        return $this->requestPage($request, [], '', self::NEUTRAL, 200);
    }

    public function showResetForm(Request $request): Response
    {
        $token = (string) $request->attribute('token', '');
        if (!$this->service->isValidToken($token)) {
            return $this->invalid();
        }
        return $this->resetPage($request, $token, [], 200);
    }

    public function submitReset(Request $request): Response
    {
        $token = (string) $request->attribute('token', '');
        $result = Validator::make($request->all(), [
            'password' => 'required|min:8|maxbytes:72|nonul|confirmed',
            'password_confirmation' => 'required',
        ], ['password' => 'kata sandi', 'password_confirmation' => 'konfirmasi kata sandi']);
        if ($result->fails()) {
            // Jangan menampilkan ulang token rute yang tidak valid/kedaluwarsa di formulir.
            if (!$this->service->isValidToken($token)) {
                return $this->invalid();
            }
            return $this->resetPage($request, $token, $result->errors(), 422);
        }
        if (!$this->service->reset($token, $result->validated()['password'])) {
            return $this->invalid();
        }
        $session = $request->attribute('session');
        if ($session instanceof Session) {
            $session->flash('notice', 'Kata sandi berhasil diubah. Silakan masuk dengan kata sandi baru.');
        }
        return Response::redirect('/masuk');
    }

    private function invalid(): Response
    {
        return Response::html(View::render('auth/reset-invalid', ['title' => 'Tautan tidak valid']), 404)->sensitive();
    }

    /** @param array<string, string> $errors */
    private function requestPage(Request $request, array $errors, string $email, ?string $notice, int $status): Response
    {
        return Response::html(View::render('auth/lupa-password', [
            'title' => 'Lupa kata sandi',
            'csrf' => $this->csrf($request),
            'errors' => $errors,
            'email' => $email,
            'notice' => $notice,
        ]), $status)->sensitive();
    }

    /** @param array<string, string> $errors */
    private function resetPage(Request $request, string $token, array $errors, int $status): Response
    {
        return Response::html(View::render('auth/reset-password', [
            'title' => 'Atur ulang kata sandi',
            'csrf' => $this->csrf($request),
            'errors' => $errors,
            'token' => $token,
        ]), $status)->sensitive();
    }

    private function notice(string $heading, string $message, int $status): Response
    {
        return Response::html(View::render('auth/notice', ['title' => $heading, 'heading' => $heading, 'message' => $message]), $status)->sensitive();
    }

    private function csrf(Request $request): string
    {
        $session = $request->attribute('session');
        return $session instanceof Session ? Csrf::token($session) : '';
    }
}
