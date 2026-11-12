<?php

declare(strict_types=1);

namespace App\Modules\Branches\Validators;

use App\Core\Validation\ValidationResult;
use App\Core\Validation\Validator;

final class BranchValidator
{
    /** @param array<string, mixed> $input */
    public static function validate(array $input): ValidationResult
    {
        $result = Validator::make($input, [
            'code' => 'required|max:30',
            'name' => 'required|max:150|nonul',
            'address' => 'max:500|nonul',
            'phone' => 'phone',
        ], ['code' => 'kode cabang', 'name' => 'nama cabang', 'address' => 'alamat', 'phone' => 'nomor telepon']);

        $errors = $result->errors();
        $data = $result->validated();
        if (!isset($errors['code'])) {
            $code = strtoupper((string) ($data['code'] ?? ''));
            if (preg_match('/^[A-Z0-9][A-Z0-9_-]*\z/', $code) !== 1) {
                $errors['code'] = 'Kode cabang hanya boleh berisi huruf, angka, strip, dan garis bawah.';
            } else {
                $data['code'] = $code;
            }
        }
        return new ValidationResult($errors, $data);
    }
}
