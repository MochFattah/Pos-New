<?php
/**
 * @var App\Core\View $this
 * @var string $csrf
 * @var array<string, string> $errors
 * @var string $token
 */
?>
<h1>Atur ulang kata sandi</h1>
<?= $this->partial('error-summary', ['errors' => $errors]) ?>
<form method="post" action="/reset-password/<?= $this->e($token) ?>" class="stack" novalidate>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<?= $this->partial('field', ['name' => 'password', 'label' => 'Kata sandi baru', 'type' => 'password', 'autocomplete' => 'new-password', 'hint' => 'Minimal 8 karakter, maksimal 72 byte.', 'required' => true, 'errors' => $errors]) ?>
<?= $this->partial('field', ['name' => 'password_confirmation', 'label' => 'Konfirmasi kata sandi baru', 'type' => 'password', 'autocomplete' => 'new-password', 'required' => true, 'errors' => $errors]) ?>
<button type="submit" class="btn btn-primary">Simpan kata sandi</button>
</form>
