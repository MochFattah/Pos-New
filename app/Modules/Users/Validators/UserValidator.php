<?php

declare(strict_types=1);

namespace App\Modules\Users\Validators;

use App\Core\Validation\ValidationResult;
use App\Core\Validation\Validator;

final class UserValidator
{
    private const LABELS = ['name' => 'nama', 'email' => 'email', 'phone' => 'nomor telepon'];

    /** Undangan: nama, email, telepon opsional. Email dinormalisasi (trim + huruf kecil). @param array<string, mixed> $input */
    public static function validateInvite(array $input): ValidationResult
    {
        $result = self::run($input, [
            'name' => 'required|max:150|nonul',
            'email' => 'required|email|max:190',
            'phone' => 'phone',
        ]);
        $data = $result->validated();
        if (isset($data['email']) && $data['email'] !== '') {
            $data['email'] = Validator::normalizeEmail($data['email']);
        }
        return new ValidationResult($result->errors(), $data);
    }

    /** Ubah profil: hanya nama dan telepon; field lain di input (email, status, is_owner, tenant_id) diabaikan. @param array<string, mixed> $input */
    public static function validateProfile(array $input): ValidationResult
    {
        return self::run($input, ['name' => 'required|max:150|nonul', 'phone' => 'phone']);
    }

    /** @param array<string, mixed> $input @param array<string, string> $rules */
    private static function run(array $input, array $rules): ValidationResult
    {
        $result = Validator::make($input, $rules, self::LABELS);
        $errors = $result->errors();
        // trim() membuang NUL di tepi sehingga "X\0" lolos aturan nonul; periksa nilai mentahnya.
        foreach (array_keys($rules) as $field) {
            $raw = $input[$field] ?? null;
            if (is_string($raw) && str_contains($raw, "\0")) {
                $errors[$field] = ucfirst(self::LABELS[$field]) . ' mengandung karakter tidak valid.';
            }
        }
        return new ValidationResult($errors, $result->validated());
    }
}
