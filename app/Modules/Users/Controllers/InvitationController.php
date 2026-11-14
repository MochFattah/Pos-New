<?php

declare(strict_types=1);

namespace App\Modules\Users\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Security\Csrf;
use App\Core\Security\Session;
use App\Core\Validation\Validator;
use App\Core\View;
use App\Modules\Auth\Services\VerifyEmailService;

/** Halaman publik penerimaan undangan: tautan di email -> tetapkan kata sandi. Token tidak valid selalu 404 netral. */
final class InvitationController
{
    public function __construct(private readonly VerifyEmailService $verify)
    {
    }

    public function show(Request $request): Response
    {
        $token = (string) $request->attribute('token', '');
        if (!$this->verify->isValidInvitation($token)) {
            return $this->invalid();
        }
        return $this->page($request, $token, [], 200);
    }

    public function accept(Request $request): Response
    {
        $token = (string) $request->attribute('token', '');
        $result = Validator::make($request->all(), [
            'password' => 'required|min:8|maxbytes:72|nonul|confirmed',
            'password_confirmation' => 'required',
        ], ['password' => 'kata sandi', 'password_confirmation' => 'konfirmasi kata sandi']);
        if ($result->fails()) {
            // Gagal validasi tidak mengonsumsi token; token yang sudah mati tetap dijawab netral.
            return $this->verify->isValidInvitation($token) ? $this->page($request, $token, $result->errors(), 422) : $this->invalid();
        }
        if (!$this->verify->verifyAndSetPassword($token, $result->validated()['password'])) {
            return $this->invalid();
        }
        $session = $request->attribute('session');
        if ($session instanceof Session) {
            $session->flash('notice', 'Akun Anda aktif. Silakan masuk dengan kata sandi yang baru ditetapkan.');
        }
        return Response::redirect('/masuk');
    }

    private function invalid(): Response
    {
        return Response::html(View::render('users/invitation-invalid', ['title' => 'Undangan tidak valid']), 404)->sensitive();
    }

    /** @param array<string, string> $errors */
    private function page(Request $request, string $token, array $errors, int $status): Response
    {
        $session = $request->attribute('session');
        return Response::html(View::render('users/accept-invitation', [
            'title' => 'Terima undangan',
            'csrf' => $session instanceof Session ? Csrf::token($session) : '',
            'errors' => $errors,
            'token' => $token,
        ]), $status)->sensitive();
    }
}
