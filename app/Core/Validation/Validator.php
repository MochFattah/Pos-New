<?php

declare(strict_types=1);

namespace App\Core\Validation;

final class Validator
{
    private const EMAIL_MAX = 190;

    /** Field yang nilainya tidak boleh di-trim atau dinormalisasi. */
    private const RAW_FIELDS = ['password', 'password_confirmation', 'current_password', 'new_password'];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules mis. ['email' => 'required|email|max:190']
     * @param array<string, string> $labels label tampilan per field (bawaan: nama field)
     */
    public static function make(array $data, array $rules, array $labels = []): ValidationResult
    {
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $ruleString) {
            $field = (string) $field;
            $label = $labels[$field] ?? $field;
            $raw = $data[$field] ?? null;
            $ruleList = array_values(array_filter(explode('|', $ruleString), static fn (string $r): bool => $r !== ''));

            if ($raw !== null && !is_scalar($raw)) {
                $errors[$field] = self::upper($label) . ' tidak valid.';
                continue;
            }

            $isRaw = in_array($field, self::RAW_FIELDS, true) || in_array('raw', $ruleList, true);
            $value = $isRaw ? (string) ($raw ?? '') : trim((string) ($raw ?? ''));
            if ($value === '') {
                if (in_array('required', $ruleList, true)) {
                    $errors[$field] = self::upper($label) . ' wajib diisi.';
                } else {
                    $validated[$field] = '';
                }
                continue;
            }

            $error = null;
            foreach ($ruleList as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, '');
                $error = self::check($name, $arg, $value, $field, $data, $label, $isRaw);
                if ($error !== null) {
                    break;
                }
                if ($name === 'phone') {
                    $value = self::stripPhone($value);
                }
            }

            if ($error !== null) {
                $errors[$field] = $error;
            } else {
                $validated[$field] = $value;
            }
        }

        return new ValidationResult($errors, $validated);
    }

    /** Bentuk kanonik email untuk pencarian/penyimpanan; dipakai bersama oleh registrasi dan login. */
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /** @param array<string, mixed> $data */
    private static function check(string $rule, string $arg, string $value, string $field, array $data, string $label, bool $isRaw = false): ?string
    {
        switch ($rule) {
            case 'required':
            case 'raw':
                return null;
            case 'email':
                if (mb_strlen($value) > self::EMAIL_MAX
                    || preg_match('/[\r\n]/', $value) === 1
                    || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    return 'Format ' . self::lower($label) . ' tidak valid.';
                }
                return null;
            case 'min':
                return mb_strlen($value) < (int) $arg
                    ? self::upper($label) . ' minimal ' . (int) $arg . ' karakter.' : null;
            case 'max':
                return mb_strlen($value) > (int) $arg
                    ? self::upper($label) . ' maksimal ' . (int) $arg . ' karakter.' : null;
            case 'maxbytes':
                return strlen($value) > (int) $arg
                    ? self::upper($label) . ' maksimal ' . (int) $arg . ' byte.' : null;
            case 'nonul':
                return str_contains($value, "\0")
                    ? self::upper($label) . ' mengandung karakter tidak valid.' : null;
            case 'confirmed':
                $other = $data[$field . '_confirmation'] ?? null;
                if (is_scalar($other)) {
                    $other = (string) $other;
                    $isRaw = $isRaw || in_array($field . '_confirmation', self::RAW_FIELDS, true);
                    $other = $isRaw ? $other : trim($other);
                } else {
                    $other = null;
                }
                return $other === $value ? null : 'Konfirmasi ' . self::lower($label) . ' tidak cocok.';
            case 'phone':
                if (preg_match('/^\+?[\d\s\-()]+$/', $value) !== 1
                    || preg_match('/^\+?\d{8,15}$/', self::stripPhone($value)) !== 1) {
                    return 'Format ' . self::lower($label) . ' tidak valid.';
                }
                return null;
            case 'in':
                $allowed = explode(',', $arg);
                return in_array($value, $allowed, true)
                    ? null : self::upper($label) . ' harus salah satu dari: ' . implode(', ', $allowed) . '.';
            default:
                throw new \InvalidArgumentException('Aturan validasi tidak dikenal: ' . $rule);
        }
    }

    private static function stripPhone(string $value): string
    {
        return (string) preg_replace('/[\s\-()]/', '', $value);
    }

    private static function upper(string $label): string
    {
        return mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1);
    }

    private static function lower(string $label): string
    {
        return mb_strtolower($label);
    }
}
