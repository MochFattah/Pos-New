<?php

declare(strict_types=1);

namespace App\Modules\Tenants\Validators;

use App\Core\Validation\ValidationResult;
use App\Core\Validation\Validator;

final class SettingsValidator
{
    /** Format angka yang didukung (kolom tenant_settings.number_format, bawaan id-ID). */
    public const NUMBER_FORMATS = ['id-ID', 'en-US'];

    /** @param array<string, mixed> $input */
    public static function validate(array $input): ValidationResult
    {
        // Hanya kunci yang dikenal yang dibaca; sisanya (tenant_id, logo_path, dst.) diabaikan.
        $known = array_intersect_key($input, array_flip(['business_type', 'address', 'phone', 'timezone', 'currency_code', 'number_format']));
        $result = Validator::make($known, [
            'business_type' => 'max:100|nonul',
            'address' => 'max:500|nonul',
            'phone' => 'phone',
            'timezone' => 'required|max:64|nonul',
            'currency_code' => 'required|max:3|nonul',
            'number_format' => 'required|in:' . implode(',', self::NUMBER_FORMATS),
        ], [
            'business_type' => 'jenis usaha', 'address' => 'alamat', 'phone' => 'nomor telepon',
            'timezone' => 'zona waktu', 'currency_code' => 'kode mata uang', 'number_format' => 'format angka',
        ]);

        $errors = $result->errors();
        $data = $result->validated();

        if (!isset($errors['timezone']) && !in_array($data['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            $errors['timezone'] = 'Zona waktu tidak dikenal. Pilih zona waktu IANA, misalnya Asia/Jakarta.';
        }
        if (!isset($errors['currency_code'])) {
            $code = strtoupper($data['currency_code']);
            if (preg_match('/^[A-Z]{3}\z/', $code) !== 1) {
                $errors['currency_code'] = 'Kode mata uang harus 3 huruf, misalnya IDR.';
            } else {
                $data['currency_code'] = $code;
            }
        }
        return new ValidationResult($errors, $data);
    }
}
