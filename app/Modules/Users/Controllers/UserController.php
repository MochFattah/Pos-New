<?php

declare(strict_types=1);

namespace App\Modules\Users\Controllers;

use App\Core\Authorization\PolicyService;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\ControllerSupport;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Users\Services\UserService;

final class UserController
{
    use ControllerSupport;

    private const NOT_FOUND = 'Pengguna tidak ditemukan.';

    public function __construct(private readonly UserService $users, private readonly PolicyService $policy)
    {
    }

    public function index(Request $request): Response
    {
        $user = $this->user($request);
        return Response::html(View::render('users/index', [
            'title' => 'Pengguna',
            'users' => $this->users->list($user),
            'canManage' => $this->policy->can($user, UserService::MANAGE),
            'currentUserId' => $user->id,
            'csrf' => $this->csrf($request),
            'notice' => $this->pull($request, 'notice'),
            'error' => $this->pull($request, 'error'),
        ]))->sensitive();
    }

    public function inviteForm(Request $request): Response
    {
        $this->policy->authorize($this->user($request), UserService::MANAGE);
        return $this->invitePage($request, [], [], 200);
    }

    public function invite(Request $request): Response
    {
        $user = $this->user($request);
        $input = ['name' => $request->input('name'), 'email' => $request->input('email'), 'phone' => $request->input('phone')];
        try {
            $id = $this->users->invite($user, $input);
        } catch (ValidationException $e) {
            return $this->invitePage($request, $e->errors(), $input, 422);
        }
        $this->flash($request, 'notice', 'Undangan telah dikirim. Pengguna aktif setelah menetapkan kata sandi lewat tautan di email.');
        return Response::redirect('/pengguna/' . $id . '/ubah');
    }

    public function editForm(Request $request): Response
    {
        $context = $this->users->editContext($this->user($request), $this->id($request, self::NOT_FOUND));
        return $this->editPage($request, $context, [], null, 200);
    }

    public function update(Request $request): Response
    {
        $actor = $this->user($request);
        $id = $this->id($request, self::NOT_FOUND);
        $input = ['name' => $request->input('name'), 'phone' => $request->input('phone')];
        try {
            $this->users->update($actor, $id, $input);
        } catch (ValidationException $e) {
            $context = $this->users->editContext($actor, $id);
            return $this->editPage($request, $context, $e->errors(), $input, 422);
        }
        return $this->back($request, $id, 'Perubahan pengguna disimpan.');
    }

    public function deactivate(Request $request): Response
    {
        return $this->status($request, 'inactive', 'Pengguna dinonaktifkan.');
    }

    public function activate(Request $request): Response
    {
        return $this->status($request, 'active', 'Pengguna diaktifkan.');
    }

    public function assignRoles(Request $request): Response
    {
        $id = $this->id($request, self::NOT_FOUND);
        return $this->guarded($request, $id, 'Role pengguna disimpan.', fn () => $this->users->assignRoles($this->user($request), $id, $this->listInput($request, 'role_ids')));
    }

    public function assignBranches(Request $request): Response
    {
        $id = $this->id($request, self::NOT_FOUND);
        return $this->guarded($request, $id, 'Akses cabang disimpan.', fn () => $this->users->assignBranches($this->user($request), $id, $this->listInput($request, 'branch_ids')));
    }

    public function resendInvite(Request $request): Response
    {
        $id = $this->id($request, self::NOT_FOUND);
        return $this->guarded($request, $id, 'Undangan dikirim ulang.', fn () => $this->users->resendInvite($this->user($request), $id));
    }

    private function status(Request $request, string $status, string $message): Response
    {
        $id = $this->id($request, self::NOT_FOUND);
        return $this->guarded($request, $id, $message, fn () => $this->users->setStatus($this->user($request), $id, $status));
    }

    /** Menjalankan aksi; kesalahan validasi menjadi pesan flash di halaman ubah (bukan error server). */
    private function guarded(Request $request, int $id, string $success, callable $action): Response
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return $this->back($request, $id, null, $this->firstError($e->errors()));
        }
        return $this->back($request, $id, $success);
    }

    private function back(Request $request, int $id, ?string $notice, ?string $error = null): Response
    {
        if ($notice !== null) {
            $this->flash($request, 'notice', $notice);
        }
        if ($error !== null) {
            $this->flash($request, 'error', $error);
        }
        return Response::redirect('/pengguna/' . $id . '/ubah');
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function invitePage(Request $request, array $errors, array $old, int $status): Response
    {
        return Response::html(View::render('users/invite', [
            'title' => 'Undang pengguna',
            'errors' => $errors,
            'old' => $old,
            'csrf' => $this->csrf($request),
        ]), $status)->sensitive();
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, string> $errors
     * @param array<string, mixed>|null $old
     */
    private function editPage(Request $request, array $context, array $errors, ?array $old, int $status): Response
    {
        $user = $context['user'];
        $old ??= ['name' => (string) $user['name'], 'phone' => (string) ($user['phone'] ?? '')];
        return Response::html(View::render('users/edit', [
            'title' => 'Ubah pengguna',
            'target' => $user,
            'roles' => $context['roles'],
            'branches' => $context['branches'],
            'isSelf' => (int) $user['id'] === $this->user($request)->id,
            'errors' => $errors,
            'old' => $old,
            'csrf' => $this->csrf($request),
            'notice' => $this->pull($request, 'notice'),
            'error' => $this->pull($request, 'error'),
        ]), $status)->sensitive();
    }
}
