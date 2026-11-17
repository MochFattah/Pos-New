<?php

declare(strict_types=1);

namespace App\Modules\Tenants\Controllers;

use App\Core\Authorization\PolicyService;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\Http\ControllerSupport;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Tenants\Services\TenantSettingsService;
use App\Modules\Tenants\Validators\SettingsValidator;

final class SettingsController
{
    use ControllerSupport;

    private const FIELDS = ['business_type', 'address', 'phone', 'timezone', 'currency_code', 'number_format'];

    public function __construct(private readonly TenantSettingsService $settings, private readonly PolicyService $policy)
    {
    }

    public function show(Request $request): Response
    {
        $data = $this->settings->get($this->user($request));
        $old = [];
        foreach (self::FIELDS as $f) {
            $old[$f] = (string) ($data['profile'][$f] ?? $data['settings'][$f] ?? '');
        }
        return $this->page($request, $data['hasLogo'], $old, [], [], 200);
    }

    public function update(Request $request): Response
    {
        $user = $this->user($request);
        $input = [];
        foreach (self::FIELDS as $f) {
            $input[$f] = $request->input($f);
        }
        try {
            $this->settings->update($user, $input);
        } catch (ValidationException $e) {
            $hasLogo = $this->settings->get($user)['hasLogo'];
            return $this->page($request, $hasLogo, array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $input), $e->errors(), [], 422);
        }
        $this->flash($request, 'notice', 'Pengaturan bisnis disimpan.');
        return Response::redirect('/pengaturan');
    }

    public function uploadLogo(Request $request): Response
    {
        $user = $this->user($request);
        $file = $request->file('logo');
        try {
            $this->settings->uploadLogo($user, $file ?? ['error' => UPLOAD_ERR_NO_FILE]);
        } catch (ValidationException $e) {
            $data = $this->settings->get($user);
            $old = [];
            foreach (self::FIELDS as $f) {
                $old[$f] = (string) ($data['profile'][$f] ?? $data['settings'][$f] ?? '');
            }
            return $this->page($request, $data['hasLogo'], $old, [], $e->errors(), 422);
        }
        $this->flash($request, 'notice', 'Logo disimpan.');
        return Response::redirect('/pengaturan');
    }

    public function removeLogo(Request $request): Response
    {
        $this->settings->removeLogo($this->user($request));
        $this->flash($request, 'notice', 'Logo dihapus.');
        return Response::redirect('/pengaturan');
    }

    /** Menyajikan logo tenant dari sesi saja; tidak ada parameter yang memilih tenant atau berkas. */
    public function logo(Request $request): Response
    {
        $logo = $this->settings->logo($this->user($request));
        $bytes = $logo === null ? false : @file_get_contents($logo['path']);
        if ($logo === null || $bytes === false) {
            throw new NotFoundException('Logo belum diunggah.');
        }
        // Nama berkas acak dan unik per unggahan, jadi cukup sebagai validator ETag.
        $etag = '"' . pathinfo($logo['path'], PATHINFO_FILENAME) . '"';
        $res = (new Response($bytes))
            ->withHeader('ETag', $etag)
            ->withHeader('Content-Type', $logo['mime'])
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'private, max-age=0, must-revalidate')
            ->withHeader('Content-Disposition', 'inline')
            ->withHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        $match = $request->header('If-None-Match');
        if ($match !== null && in_array($etag, array_map('trim', explode(',', $match)), true)) {
            return (new Response('', 304))
                ->withHeader('ETag', $etag)
                ->withHeader('Cache-Control', 'private, max-age=0, must-revalidate')
                ->withHeader('X-Content-Type-Options', 'nosniff');
        }
        return $res->withHeader('Content-Length', (string) strlen($bytes));
    }

    /**
     * @param array<string, string> $old
     * @param array<string, string> $errors
     * @param array<string, string> $logoErrors
     */
    private function page(Request $request, bool $hasLogo, array $old, array $errors, array $logoErrors, int $status): Response
    {
        $canManage = $this->policy->can($this->user($request), TenantSettingsService::MANAGE);
        return Response::html($this->view($request, 'settings/index', [
            'title' => 'Pengaturan bisnis',
            'old' => $old,
            'errors' => $errors,
            'logoError' => $logoErrors['logo'] ?? null,
            'hasLogo' => $hasLogo,
            'canManage' => $canManage,
            'numberFormats' => SettingsValidator::NUMBER_FORMATS,
            'notice' => $this->pull($request, 'notice'),
            'csrf' => $this->csrf($request),
        ]), $status)->sensitive();
    }
}
