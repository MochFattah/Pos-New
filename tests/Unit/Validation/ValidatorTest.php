<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use App\Core\Validation\Validator;
use Tests\Support\TestCase;

final class ValidatorTest extends TestCase
{
    public function testRequiredMessageIsIndonesianAndFieldSpecific(): void
    {
        $r = Validator::make(['phone' => '  ', 'name' => 'Budi'], ['phone' => 'required', 'name' => 'required'], ['phone' => 'Nomor telepon']);
        $this->assertTrue($r->fails());
        $this->assertSame(['phone' => 'Nomor telepon wajib diisi.'], $r->errors());
        $this->assertSame(['name' => 'Budi'], $r->validated());
    }

    public function testDefaultLabelIsFieldName(): void
    {
        $r = Validator::make([], ['kode' => 'required']);
        $this->assertSame('Kode wajib diisi.', $r->errors()['kode']);
    }

    public function testEmailRuleTrimsAndRejectsMalformed(): void
    {
        $ok = Validator::make(['email' => '  Budi@Example.COM '], ['email' => 'required|email'], ['email' => 'Email']);
        $this->assertFalse($ok->fails());
        $this->assertSame(['email' => 'Budi@Example.COM'], $ok->validated());

        foreach (['bukan-email', 'a@b', "a@b.com\nBcc: x@y.com", "a@b\r.com", str_repeat('a', 185) . '@b.com'] as $bad) {
            $r = Validator::make(['email' => $bad], ['email' => 'required|email'], ['email' => 'Email']);
            $this->assertSame('Format email tidak valid.', $r->errors()['email'] ?? '', $bad);
        }
        $this->assertSame('budi@example.com', Validator::normalizeEmail('  Budi@Example.COM '));
    }

    public function testMinPasswordLengthIs8(): void
    {
        $r = Validator::make(['password' => 'pendek1'], ['password' => 'required|min:8'], ['password' => 'Kata sandi']);
        $this->assertSame('Kata sandi minimal 8 karakter.', $r->errors()['password']);
        $this->assertFalse(Validator::make(['password' => 'panjang12'], ['password' => 'min:8'])->fails());
    }

    public function testMinMaxCountCharactersNotBytes(): void
    {
        $this->assertFalse(Validator::make(['n' => 'éééé'], ['n' => 'min:4|max:4'])->fails());
        $this->assertSame('N maksimal 3 karakter.', Validator::make(['n' => 'éééé'], ['n' => 'max:3'])->errors()['n']);
    }

    public function testConfirmedRequiresMatch(): void
    {
        $bad = Validator::make(['password' => 'abcdefgh', 'password_confirmation' => 'abcdefgx'], ['password' => 'confirmed'], ['password' => 'Kata sandi']);
        $this->assertSame('Konfirmasi kata sandi tidak cocok.', $bad->errors()['password']);
        $ok = Validator::make(['password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh'], ['password' => 'confirmed']);
        $this->assertFalse($ok->fails());
    }

    public function testPhoneNormalizesAndValidates(): void
    {
        $r = Validator::make(['p' => '+62 812-3456 789'], ['p' => 'phone']);
        $this->assertSame(['p' => '+628123456789'], $r->validated());
        $this->assertSame(['p' => '08123456789'], Validator::make(['p' => '(0812) 3456-789'], ['p' => 'phone'])->validated());
        foreach (['abc', '123', '0812-abc-999', '+' . str_repeat('1', 16), '08+123456789'] as $bad) {
            $this->assertSame('Format p tidak valid.', Validator::make(['p' => $bad], ['p' => 'phone'])->errors()['p'] ?? '', $bad);
        }
    }

    public function testInRule(): void
    {
        $this->assertFalse(Validator::make(['r' => 'owner'], ['r' => 'in:owner,cashier'])->fails());
        $this->assertSame('Peran harus salah satu dari: owner, cashier.', Validator::make(['r' => 'x'], ['r' => 'in:owner,cashier'], ['r' => 'Peran'])->errors()['r']);
    }

    public function testOptionalEmptyFieldSkipsOtherRulesAndFirstFailureWins(): void
    {
        $this->assertFalse(Validator::make(['e' => ''], ['e' => 'email|min:5'])->fails());
        $r = Validator::make(['e' => 'x'], ['e' => 'required|min:5|email']);
        $this->assertSame('E minimal 5 karakter.', $r->errors()['e']);
    }

    public function testNonScalarFailsGenerically(): void
    {
        $r = Validator::make(['name' => ['a']], ['name' => 'required|min:2'], ['name' => 'Nama']);
        $this->assertSame('Nama tidak valid.', $r->errors()['name']);
    }

    public function testPasswordIsNotTrimmedAndReturnedRaw(): void
    {
        $r = Validator::make(
            ['password' => '  pass word ', 'password_confirmation' => '  pass word '],
            ['password' => 'required|min:8|confirmed']
        );
        $this->assertFalse($r->fails());
        $this->assertSame('  pass word ', $r->validated()['password']);
    }

    public function testPasswordMinCountsSpaces(): void
    {
        $this->assertFalse(Validator::make(['password' => '   a    '], ['password' => 'required|min:8'])->fails());
        $this->assertTrue(Validator::make(['password' => ' abc '], ['password' => 'required|min:8'])->fails());
    }

    public function testPasswordConfirmationComparesRawStrings(): void
    {
        $r = Validator::make(['password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh '], ['password' => 'required|confirmed']);
        $this->assertSame(['password' => 'Konfirmasi password tidak cocok.'], $r->errors());
    }

    public function testPasswordOfOnlySpacesSatisfiesRequiredButEmptyDoesNot(): void
    {
        $this->assertFalse(Validator::make(['password' => '   '], ['password' => 'required'])->fails());
        $this->assertTrue(Validator::make(['password' => ''], ['password' => 'required'])->fails());
    }

    public function testOtherPasswordFieldNamesAreRaw(): void
    {
        foreach (['current_password', 'new_password'] as $f) {
            $r = Validator::make([$f => ' x '], [$f => 'required']);
            $this->assertSame(' x ', $r->validated()[$f]);
        }
    }

    public function testNonPasswordFieldsStillTrimmed(): void
    {
        $r = Validator::make(['name' => '  Budi  '], ['name' => 'required']);
        $this->assertSame('Budi', $r->validated()['name']);
    }

    public function testRawRuleOptsOutOfTrimmingOnArbitraryField(): void
    {
        $r = Validator::make(
            ['pin' => ' 12 34 ', 'pin_confirmation' => ' 12 34 '],
            ['pin' => 'required|raw|min:7|confirmed']
        );
        $this->assertFalse($r->fails());
        $this->assertSame(' 12 34 ', $r->validated()['pin']);
        $bad = Validator::make(['pin' => ' 12 34 ', 'pin_confirmation' => '12 34'], ['pin' => 'raw|confirmed']);
        $this->assertTrue($bad->fails());
    }
}
