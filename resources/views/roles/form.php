<?php
/**
 * @var App\Core\View $this
 * @var list<array{code: string, module: string, description: string, grantable: bool}> $catalog
 * @var array<string, string> $errors
 * @var array<string, string> $old
 * @var list<string> $selected
 * @var string $csrf
 */
?>
<h1>Tambah role</h1>
<?php if ($errors !== []): ?>
<p role="alert"><?= $this->e($errors['permissions'] ?? 'Periksa kembali isian Anda.') ?></p>
<?php endif; ?>
<form method="post" action="/role" novalidate>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<p>
<label for="name">Nama role</label><br>
<input id="name" name="name" type="text" autocomplete="off" required value="<?= $this->e($old['name'] ?? '') ?>"<?php if (isset($errors['name'])): ?> aria-invalid="true" aria-describedby="name-error"<?php endif; ?>>
<?php if (isset($errors['name'])): ?>
<br><span id="name-error"><?= $this->e($errors['name']) ?></span>
<?php endif; ?>
</p>
<?php include __DIR__ . '/_permissions.php'; ?>
<p><button type="submit">Simpan role</button> <a href="/role">Batal</a></p>
</form>
