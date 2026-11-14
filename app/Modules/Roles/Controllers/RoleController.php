<?php

declare(strict_types=1);

namespace App\Modules\Roles\Controllers;

use App\Core\Authorization\PolicyService;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\ControllerSupport;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Roles\Services\RoleService;

final class RoleController
{
    use ControllerSupport;

    private const NOT_FOUND = 'Role tidak ditemukan.';

    public function __construct(private readonly RoleService $roles, private readonly PolicyService $policy)
    {
    }

    public function index(Request $request): Response
    {
        $user = $this->user($request);
        return Response::html(View::render('roles/index', [
            'title' => 'Role dan izin',
            'roles' => $this->roles->list($user),
            'canManage' => $this->policy->can($user, RoleService::MANAGE),
            'notice' => $this->pull($request, 'notice'),
            'error' => $this->pull($request, 'error'),
        ]))->sensitive();
    }

    public function createForm(Request $request): Response
    {
        return $this->createPage($request, [], [], [], 200);
    }

    public function store(Request $request): Response
    {
        $user = $this->user($request);
        $name = $request->input('name');
        $codes = $this->listInput($request, 'codes');
        try {
            $id = $this->roles->createCustom($user, is_string($name) ? $name : '', $codes);
        } catch (ValidationException $e) {
            return $this->createPage($request, $e->errors(), ['name' => is_string($name) ? $name : ''], $codes, 422);
        }
        $this->flash($request, 'notice', 'Role berhasil dibuat.');
        return Response::redirect('/role/' . $id);
    }

    public function show(Request $request): Response
    {
        $user = $this->user($request);
        $role = $this->roles->get($user, $this->id($request, self::NOT_FOUND));
        return Response::html(View::render('roles/show', [
            'title' => 'Role ' . $role['name'],
            'role' => $role,
            'catalog' => $this->roles->catalog($user),
            'isOwnerRole' => \App\Modules\Tenants\Repositories\RoleRepository::isOwnerRole($role),
            'canManage' => $this->policy->can($user, RoleService::MANAGE),
            'csrf' => $this->csrf($request),
            'notice' => $this->pull($request, 'notice'),
            'error' => $this->pull($request, 'error'),
        ]))->sensitive();
    }

    public function syncPermissions(Request $request): Response
    {
        $id = $this->id($request, self::NOT_FOUND);
        return $this->guarded($request, $id, 'Izin role disimpan.', fn () => $this->roles->syncPermissions($this->user($request), $id, $this->listInput($request, 'codes')));
    }

    public function rename(Request $request): Response
    {
        $id = $this->id($request, self::NOT_FOUND);
        $name = $request->input('name');
        return $this->guarded($request, $id, 'Nama role disimpan.', fn () => $this->roles->rename($this->user($request), $id, is_string($name) ? $name : ''));
    }

    public function delete(Request $request): Response
    {
        $id = $this->id($request, self::NOT_FOUND);
        try {
            $this->roles->delete($this->user($request), $id);
        } catch (ValidationException $e) {
            $this->flash($request, 'error', $this->firstError($e->errors()));
            return Response::redirect('/role/' . $id);
        }
        $this->flash($request, 'notice', 'Role dihapus.');
        return Response::redirect('/role');
    }

    private function guarded(Request $request, int $id, string $success, callable $action): Response
    {
        try {
            $action();
        } catch (ValidationException $e) {
            $this->flash($request, 'error', $this->firstError($e->errors()));
            return Response::redirect('/role/' . $id);
        }
        $this->flash($request, 'notice', $success);
        return Response::redirect('/role/' . $id);
    }

    /** @param array<string, string> $errors @param array<string, string> $old @param list<mixed> $selected */
    private function createPage(Request $request, array $errors, array $old, array $selected, int $status): Response
    {
        $user = $this->user($request);
        $this->policy->authorize($user, RoleService::MANAGE);
        return Response::html(View::render('roles/form', [
            'title' => 'Tambah role',
            'catalog' => $this->roles->catalog($user),
            'errors' => $errors,
            'old' => $old,
            'selected' => array_values(array_filter($selected, 'is_string')),
            'csrf' => $this->csrf($request),
        ]), $status)->sensitive();
    }
}
