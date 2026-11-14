<?php
/**
 * @var App\Core\View $this
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 * @var string $csrf
 */
$fields = [
    ['name', 'Nama', 'text', 'name', true],
    ['email', 'Email', 'email', 'off', true],
    ['phone', 'Nomor telepon (opsional)', 'tel', 'off', false],
];
?>
<h1>Undang pengguna</h1>
<?php if ($errors !== []): ?>
<p role="alert"><?= $this->e($errors['form'] ?? 'Periksa kembali isian Anda.') ?></p>
<?php endif; ?>
<p>Pengguna menerima email berisi tautan untuk menetapkan kata sandi. Tautan berlaku 24 jam.</p>
<form method="post" action="/pengguna/undang" novalidate>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<?php foreach ($fields as [$name, $label, $type, $autocomplete, $required]): ?>
<p>
<label for="<?= $this->e($name) ?>"><?= $this->e($label) ?></label><br>
<input id="<?= $this->e($name) ?>" name="<?= $this->e($name) ?>" type="<?= $this->e($type) ?>" autocomplete="<?= $this->e($autocomplete) ?>"<?= $required ? ' required' : '' ?> value="<?= $this->e($old[$name] ?? '') ?>"<?php if (isset($errors[$name])): ?> aria-invalid="true" aria-describedby="<?= $this->e($name) ?>-error"<?php endif; ?>>
<?php if (isset($errors[$name])): ?>
<br><span id="<?= $this->e($name) ?>-error"><?= $this->e($errors[$name]) ?></span>
<?php endif; ?>
</p>
<?php endforeach; ?>
<p><button type="submit">Kirim undangan</button> <a href="/pengguna">Batal</a></p>
</form>
