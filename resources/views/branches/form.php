<?php
/**
 * @var App\Core\View $this
 * @var string $action
 * @var bool $isEdit
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 * @var string $csrf
 */
$fields = [
    ['code', 'Kode cabang', 'text', 'off', true],
    ['name', 'Nama cabang', 'text', 'off', true],
    ['address', 'Alamat', 'text', 'street-address', false],
    ['phone', 'Nomor telepon', 'tel', 'tel', false],
];
?>
<h1><?= $isEdit ? 'Ubah cabang' : 'Tambah cabang' ?></h1>
<?php if ($errors !== []): ?>
<p role="alert">Periksa kembali isian Anda.</p>
<?php endif; ?>
<form method="post" action="<?= $this->e($action) ?>" novalidate>
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
<p><button type="submit">Simpan</button> <a href="/cabang">Batal</a></p>
</form>
