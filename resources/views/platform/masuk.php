<?php
/**
 * @var App\Core\View $this
 * @var string $csrf
 * @var array<string, string> $errors
 * @var string $email
 * @var string|null $error
 */
?>
<h1>Masuk platform</h1>
<?php if ($error !== null): ?>
<div class="alert alert-danger" id="form-error" role="alert"><?= $this->e($error) ?></div>
<?php endif; ?>
<?= $this->partial('error-summary', ['errors' => $errors]) ?>
<form method="post" action="/platform/masuk" class="stack" novalidate<?php if ($error !== null): ?> aria-describedby="form-error"<?php endif; ?>>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<?= $this->partial('field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $email, 'autocomplete' => 'username', 'inputmode' => 'email', 'required' => true, 'errors' => $errors]) ?>
<?= $this->partial('field', ['name' => 'password', 'label' => 'Kata sandi', 'type' => 'password', 'autocomplete' => 'current-password', 'required' => true, 'errors' => $errors]) ?>
<button type="submit" class="btn btn-primary">Masuk</button>
</form>
