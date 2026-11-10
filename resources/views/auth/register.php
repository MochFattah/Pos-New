<?php
/**
 * @var App\Core\View $this
 * @var string $csrf
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */
$fields = [
    ['owner_name', 'Nama pemilik', 'text', 'name', true],
    ['email', 'Email', 'email', 'email', true],
    ['phone', 'Nomor telepon', 'tel', 'tel', true],
    ['business_name', 'Nama usaha', 'text', 'organization', true],
    ['business_type', 'Jenis usaha', 'text', 'off', false],
    ['address', 'Alamat usaha', 'text', 'street-address', false],
    ['password', 'Kata sandi', 'password', 'new-password', true],
    ['password_confirmation', 'Konfirmasi kata sandi', 'password', 'new-password', true],
];
?>
<h1>Daftar akun usaha</h1>
<?php if ($errors !== []): ?>
<p role="alert">Periksa kembali isian Anda.</p>
<?php endif; ?>
<form method="post" action="/daftar" novalidate>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<?php foreach ($fields as [$name, $label, $type, $autocomplete, $required]): ?>
<p>
<label for="<?= $this->e($name) ?>"><?= $this->e($label) ?></label><br>
<input id="<?= $this->e($name) ?>" name="<?= $this->e($name) ?>" type="<?= $this->e($type) ?>" autocomplete="<?= $this->e($autocomplete) ?>"<?= $required ? ' required' : '' ?><?php if ($type !== 'password'): ?> value="<?= $this->e($old[$name] ?? '') ?>"<?php endif; ?><?php if (isset($errors[$name])): ?> aria-invalid="true" aria-describedby="<?= $this->e($name) ?>-error"<?php endif; ?>>
<?php if (isset($errors[$name])): ?>
<br><span id="<?= $this->e($name) ?>-error"><?= $this->e($errors[$name]) ?></span>
<?php endif; ?>
</p>
<?php endforeach; ?>
<p><button type="submit">Daftar</button></p>
</form>
<p><a href="/verifikasi-email">Kirim ulang email verifikasi</a></p>
