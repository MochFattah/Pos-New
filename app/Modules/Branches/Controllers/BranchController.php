<?php

declare(strict_types=1);

namespace App\Modules\Branches\Controllers;

use App\Core\Authorization\PolicyService;
use App\Core\Exceptions\NotFoundException;
use App\Core\Http\ControllerSupport;
use App\Core\Exceptions\ValidationException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security\Session;
use App\Modules\Branches\Services\BranchService;

final class BranchController
{
    use ControllerSupport;

    private const NOT_FOUND = 'Cabang tidak ditemukan.';

    public function __construct(private readonly BranchService $branches, private readonly PolicyService $policy)
    {
    }

    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $session = $request->attribute('session');
        $notice = $session instanceof Session ? $session->pullFlash('notice') : null;
        $error = $session instanceof Session ? $session->pullFlash('error') : null;
        $branches = $this->branches->list($user);
        return Response::html($this->view($request, 'branches/index', [
            'title' => 'Cabang',
            'branches' => $branches,
            'noAccess' => !$user->isOwner && $branches === [],
            'canManage' => $this->policy->can($user, BranchService::MANAGE),
            'csrf' => $this->csrf($request),
            'notice' => is_string($notice) ? $notice : null,
            'error' => is_string($error) ? $error : null,
        ]))->sensitive();
    }

    public function createForm(Request $request): Response
    {
        return $this->form($request, null, [], [], 200);
    }

    public function store(Request $request): Response
    {
        $user = $this->user($request);
        $input = $this->input($request);
        try {
            $this->branches->create($user, $input);
        } catch (ValidationException $e) {
            return $this->form($request, null, $e->errors(), $input, 422);
        }
        return $this->done($request, 'Cabang berhasil ditambahkan.');
    }

    public function editForm(Request $request): Response
    {
        $branch = $this->branches->forEdit($this->user($request), $this->id($request, self::NOT_FOUND));
        return $this->form($request, $branch, [], [
            'code' => (string) $branch['code'],
            'name' => (string) $branch['name'],
            'address' => (string) ($branch['address'] ?? ''),
            'phone' => (string) ($branch['phone'] ?? ''),
        ], 200);
    }

    public function update(Request $request): Response
    {
        $user = $this->user($request);
        $id = $this->id($request, self::NOT_FOUND);
        $input = $this->input($request);
        try {
            $this->branches->update($user, $id, $input);
        } catch (ValidationException $e) {
            return $this->form($request, $this->branches->forEdit($user, $id), $e->errors(), $input, 422);
        }
        return $this->done($request, 'Perubahan cabang disimpan.');
    }

    public function deactivate(Request $request): Response
    {
        return $this->toggle($request, false);
    }

    public function activate(Request $request): Response
    {
        return $this->toggle($request, true);
    }

    private function toggle(Request $request, bool $activate): Response
    {
        $user = $this->user($request);
        $id = $this->id($request, self::NOT_FOUND);
        try {
            $activate ? $this->branches->activate($user, $id) : $this->branches->deactivate($user, $id);
        } catch (ValidationException $e) {
            $session = $request->attribute('session');
            if ($session instanceof Session) {
                $session->flash('error', $e->errors()['status'] ?? $e->getMessage());
            }
            return Response::redirect('/cabang');
        }
        return $this->done($request, $activate ? 'Cabang diaktifkan.' : 'Cabang dinonaktifkan.');
    }

    /** @param array<string, mixed>|null $branch @param array<string, string> $errors @param array<string, mixed> $old */
    private function form(Request $request, ?array $branch, array $errors, array $old, int $status): Response
    {
        $this->policy->authorize($this->user($request), BranchService::MANAGE);
        $isEdit = $branch !== null;
        return Response::html($this->view($request, 'branches/form', [
            'title' => $isEdit ? 'Ubah cabang' : 'Tambah cabang',
            'action' => $isEdit ? '/cabang/' . (int) $branch['id'] : '/cabang',
            'isEdit' => $isEdit,
            'errors' => $errors,
            'old' => $old,
            'csrf' => $this->csrf($request),
        ]), $status)->sensitive();
    }

    private function done(Request $request, string $message): Response
    {
        $session = $request->attribute('session');
        if ($session instanceof Session) {
            $session->flash('notice', $message);
        }
        return Response::redirect('/cabang');
    }

    /** @return array<string, mixed> hanya field formulir; tenant_id dan field lain dari request diabaikan */
    private function input(Request $request): array
    {
        $out = [];
        foreach (['code', 'name', 'address', 'phone'] as $field) {
            $out[$field] = $request->input($field);
        }
        return $out;
    }
}
