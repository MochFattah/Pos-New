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
use App\Modules\Auth\Services\VerifyEmailService;
use App\Modules\Tenants\Exceptions\DuplicateEmailException;
use App\Modules\Tenants\Services\RegisterTenantService;

final class RegisterController
{
    private const TOO_MANY = 'Terlalu banyak percobaan. Coba lagi beberapa saat lagi.';
    private const RESEND_NOTICE = 'Jika email terdaftar dan belum terverifikasi, kami telah mengirim ulang tautan verifikasi.';
    private const FIELDS = ['owner_name', 'email', 'phone', 'business_name', 'business_type', 'address'];

    public function __construct(
        private readonly RegisterTenantService $register,
        private readonly VerifyEmailService $verify,
        private readonly RateLimiter $limiter,
        private readonly RateLimiter $registrationLimiter,
    ) {
    }

    public function showForm(Request $request): Response
    {
        return $this->form($request, [], [], 200);
    }

    public function submit(Request $request): Response
    {
        $ip = $request->ip();
        if ($this->registrationLimiter->tooManyAttempts('register', $ip)) {
            return $this->notice('Terlalu banyak percobaan', self::TOO_MANY, 429);
        }

        $data = $request->all();
        $result = Validator::make($data, [
            'owner_name' => 'required|max:150',
            'email' => 'required|email|max:190',
            'phone' => 'required|phone|max:30',
            'business_name' => 'required|max:150',
            'business_type' => 'max:100',
            'address' => 'max:500',
            'password' => 'required|min:8|maxbytes:72|nonul|confirmed',
            'password_confirmation' => 'required',
        ], [
            'owner_name' => 'nama pemilik',
            'email' => 'email',
            'phone' => 'nomor telepon',
            'business_name' => 'nama usaha',
            'business_type' => 'jenis usaha',
            'address' => 'alamat usaha',
            'password' => 'kata sandi',
            'password_confirmation' => 'konfirmasi kata sandi',
        ]);
        $old = $this->oldValues($data);
        if ($result->fails()) {
            return $this->form($request, $result->errors(), $old, 422);
        }
        $this->registrationLimiter->record('register', $ip, false);

        try {
            $this->register->register($result->validated());
        } catch (DuplicateEmailException) {
            return $this->form($request, ['email' => 'Email sudah terdaftar.'], $old, 422);
        } catch (MailException) {
            return $this->notice(
                'Pendaftaran belum berhasil',
                'Gagal mengirim email verifikasi. Pendaftaran dibatalkan; silakan coba lagi beberapa saat lagi.',
                503
            );
        }
        return Response::redirect('/daftar/cek-email');
    }

    public function sent(Request $request): Response
    {
        return Response::html(View::render('auth/verification-sent', ['title' => 'Cek email Anda']));
    }

    public function verifyLink(Request $request): Response
    {
        $ok = $this->verify->verify((string) $request->attribute('token', ''));
        return Response::html(
            View::render('auth/verify-result', ['title' => $ok ? 'Email terverifikasi' : 'Tautan tidak valid', 'success' => $ok]),
            $ok ? 200 : 404
        )->sensitive();
    }

    public function resendForm(Request $request): Response
    {
        return $this->resendPage($request, [], '', null, 200);
    }

    public function resend(Request $request): Response
    {
        $ip = $request->ip();
        $result = Validator::make($request->all(), ['email' => 'required|email|max:190'], ['email' => 'email']);
        $raw = $request->input('email', '');
        $emailInput = is_string($raw) ? trim($raw) : '';
        if ($result->fails()) {
            return $this->resendPage($request, $result->errors(), $emailInput, null, 422);
        }
        $email = $result->validated()['email'];

        $perEmail = 'resend:' . Validator::normalizeEmail($email);
        if ($this->limiter->tooManyAttempts($perEmail, $ip) || $this->limiter->tooManyAttempts('resend', $ip)) {
            return $this->notice('Terlalu banyak percobaan', self::TOO_MANY, 429);
        }
        $this->limiter->record($perEmail, $ip, false);
        $this->limiter->record('resend', $ip, false);

        try {
            $this->verify->resend($email, $ip);
        } catch (MailException) {
            // Tetap netral: kegagalan kirim tidak boleh membedakan email terdaftar dari yang tidak.
        }
        return $this->resendPage($request, [], '', self::RESEND_NOTICE, 200);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $old
     */
    private function form(Request $request, array $errors, array $old, int $status): Response
    {
        return Response::html(View::render('auth/register', [
            'title' => 'Daftar akun usaha',
            'csrf' => $this->csrf($request),
            'errors' => $errors,
            'old' => $old,
        ]), $status);
    }

    /** @param array<string, string> $errors */
    private function resendPage(Request $request, array $errors, string $email, ?string $notice, int $status): Response
    {
        return Response::html(View::render('auth/resend-verification', [
            'title' => 'Kirim ulang email verifikasi',
            'csrf' => $this->csrf($request),
            'errors' => $errors,
            'email' => $email,
            'notice' => $notice,
        ]), $status);
    }

    private function notice(string $heading, string $message, int $status): Response
    {
        return Response::html(View::render('auth/notice', ['title' => $heading, 'heading' => $heading, 'message' => $message]), $status);
    }

    private function csrf(Request $request): string
    {
        $session = $request->attribute('session');
        return $session instanceof Session ? Csrf::token($session) : '';
    }

    /**
     * Hanya field non-sandi; kata sandi tidak pernah dikembalikan ke halaman.
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function oldValues(array $data): array
    {
        $old = [];
        foreach (self::FIELDS as $field) {
            $value = $data[$field] ?? '';
            $old[$field] = is_scalar($value) ? (string) $value : '';
        }
        return $old;
    }
}
