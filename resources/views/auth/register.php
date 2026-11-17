<?php
/**
 * @var App\Core\View $this
 * @var string $csrf
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */
// [nama, label, type, autocomplete, wajib, inputmode]
$groups = [
    'Data pemilik' => [
        ['owner_name', 'Nama pemilik', 'text', 'name', true, null],
        ['email', 'Email', 'email', 'email', true, 'email'],
        ['phone', 'Nomor telepon', 'tel', 'tel', true, 'tel'],
    ],
    'Data usaha' => [
        ['business_name', 'Nama usaha', 'text', 'organization', true, null],
        ['business_type', 'Jenis usaha', 'text', 'off', false, null],
        ['address', 'Alamat usaha', 'text', 'street-address', false, null],
    ],
    'Keamanan akun' => [
        ['password', 'Kata sandi', 'password', 'new-password', true, null],
        ['password_confirmation', 'Konfirmasi kata sandi', 'password', 'new-password', true, null],
    ],
];
?>
<h1>Daftar akun usaha</h1>
<?= $this->partial('error-summary', ['errors' => $errors]) ?>
<form method="post" action="/daftar" class="stack-lg" novalidate>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<?php foreach ($groups as $legend => $fields): ?>
<fieldset class="fieldset stack">
<legend><?= $this->e($legend) ?></legend>
<?php foreach ($fields as [$name, $label, $type, $autocomplete, $required, $inputmode]): ?>
<?= $this->partial('field', ['name' => $name, 'label' => $label, 'type' => $type, 'value' => (string) ($old[$name] ?? ''), 'autocomplete' => $autocomplete, 'inputmode' => $inputmode, 'required' => $required, 'errors' => $errors]) ?>
<?php endforeach; ?>
</fieldset>
<?php endforeach; ?>
<button type="submit" class="btn btn-primary">Daftar</button>
</form>
<p class="stack-sm">
<a href="/masuk">Sudah punya akun? Masuk</a>
<a href="/verifikasi-email">Kirim ulang email verifikasi</a>
</p>
