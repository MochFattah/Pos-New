<?php
/**
 * @var App\Core\View $this
 * @var string $csrf
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */
$fields = [
    ['owner_name', 'Nama pemilik', 'text', 'name'],
    ['email', 'Email', 'email', 'email'],
    ['phone', 'Nomor telepon', 'tel', 'tel'],
    ['business_name', 'Nama usaha', 'text', 'organization'],
    ['business_type', 'Jenis usaha', 'text', 'off'],
    ['address', 'Alamat usaha', 'text', 'street-address'],
    ['password', 'Kata sandi', 'password', 'new-password'],
    ['password_confirmation', 'Konfirmasi kata sandi', 'password', 'new-password'],
];
?>
<h1>Daftar akun usaha</h1>
<?php if ($errors !== []): ?>
<p role="alert">Periksa kembali isian Anda.</p>
<?php endif; ?>
<form method="post" action="/daftar" novalidate>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<?php foreach ($fields as [$name, $label, $type, $autocomplete]): ?>
<p>
<label for="<?= $this->e($name) ?>"><?= $this->e($label) ?></label><br>
<input id="<?= $this->e($name) ?>" name="<?= $this->e($name) ?>" type="<?= $this->e($type) ?>" autocomplete="<?= $this->e($autocomplete) ?>" required<?php if ($type !== 'password'): ?> value="<?= $this->e($old[$name] ?? '') ?>"<?php endif; ?><?php if (isset($errors[$name])): ?> aria-invalid="true" aria-describedby="<?= $this->e($name) ?>-error"<?php endif; ?>>
<?php if (isset($errors[$name])): ?>
<br><span id="<?= $this->e($name) ?>-error"><?= $this->e($errors[$name]) ?></span>
<?php endif; ?>
</p>
<?php endforeach; ?>
<p><button type="submit">Daftar</button></p>
</form>
<p><a href="/verifikasi-email">Kirim ulang email verifikasi</a></p>
