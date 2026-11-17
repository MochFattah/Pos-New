<?php
/**
 * @var App\Core\View $this
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 * @var string $csrf
 */
// [nama, label, type, autocomplete, wajib, inputmode]
$fields = [
    ['name', 'Nama', 'text', 'off', true, null],
    ['email', 'Email', 'email', 'off', true, 'email'],
    ['phone', 'Nomor telepon (opsional)', 'tel', 'off', false, 'tel'],
];
?>
<div class="page-header">
<div>
<h1 class="page-header__title">Undang pengguna</h1>
<p class="page-header__subtitle">Pengguna menerima email berisi tautan untuk menetapkan kata sandi. Tautan berlaku 24 jam.</p>
</div>
</div>
<?= $this->partial('error-summary', ['errors' => $errors]) ?>
<form method="post" action="/pengguna/undang" class="card stack max-w-2xl" novalidate>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<?php foreach ($fields as [$name, $label, $type, $autocomplete, $required, $inputmode]): ?>
<?= $this->partial('field', ['name' => $name, 'label' => $label, 'type' => $type, 'value' => (string) ($old[$name] ?? ''), 'autocomplete' => $autocomplete, 'inputmode' => $inputmode, 'required' => $required, 'errors' => $errors]) ?>
<?php endforeach; ?>
<div class="cluster">
<button type="submit" class="btn btn-primary">Kirim undangan</button>
<a class="btn btn-tertiary" href="/pengguna">Batal</a>
</div>
</form>
