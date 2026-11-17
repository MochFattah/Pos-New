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
<div class="page-header">
<h1 class="page-header__title">Tambah role</h1>
</div>
<?= $this->partial('error-summary', ['errors' => $errors]) ?>
<form method="post" action="/role" class="card stack max-w-2xl" novalidate>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<?= $this->partial('field', ['name' => 'name', 'label' => 'Nama role', 'value' => (string) ($old['name'] ?? ''), 'autocomplete' => 'off', 'required' => true, 'errors' => $errors]) ?>
<?php include __DIR__ . '/_permissions.php'; ?>
<div class="cluster">
<button type="submit" class="btn btn-primary">Simpan role</button>
<a class="btn btn-tertiary" href="/role">Batal</a>
</div>
</form>
