<?php
/**
 * @var App\Core\View $this
 * @var string $action
 * @var bool $isEdit
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 * @var string $csrf
 */
// [nama, label, type, autocomplete, wajib, inputmode]
$fields = [
    ['code', 'Kode cabang', 'text', 'off', true, null],
    ['name', 'Nama cabang', 'text', 'off', true, null],
    ['address', 'Alamat', 'text', 'street-address', false, null],
    ['phone', 'Nomor telepon', 'tel', 'tel', false, 'tel'],
];
?>
<div class="page-header">
<h1 class="page-header__title"><?= $isEdit ? 'Ubah cabang' : 'Tambah cabang' ?></h1>
</div>
<?= $this->partial('error-summary', ['errors' => $errors]) ?>
<form method="post" action="<?= $this->e($action) ?>" class="card stack max-w-2xl" novalidate>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<?php foreach ($fields as [$name, $label, $type, $autocomplete, $required, $inputmode]): ?>
<?= $this->partial('field', ['name' => $name, 'label' => $label, 'type' => $type, 'value' => (string) ($old[$name] ?? ''), 'autocomplete' => $autocomplete, 'inputmode' => $inputmode, 'required' => $required, 'errors' => $errors]) ?>
<?php endforeach; ?>
<div class="cluster">
<button type="submit" class="btn btn-primary">Simpan</button>
<a class="btn btn-tertiary" href="/cabang">Batal</a>
</div>
</form>
