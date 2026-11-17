<?php
/**
 * @var App\Core\View $this
 * @var string $csrf
 * @var array<string, string> $errors
 * @var string $email
 * @var string|null $error
 * @var string|null $notice
 * @var bool $offerResend
 */
?>
<h1>Masuk</h1>
<?php if ($notice !== null): ?>
<div class="alert alert-success" role="status"><?= $this->e($notice) ?></div>
<?php endif; ?>
<?php if ($error !== null): ?>
<div class="alert alert-danger stack-sm" id="form-error" role="alert">
<p><?= $this->e($error) ?></p>
<?php if ($offerResend): ?>
<p><a href="/verifikasi-email">Kirim ulang email verifikasi</a></p>
<?php endif; ?>
</div>
<?php endif; ?>
<?= $this->partial('error-summary', ['errors' => $errors]) ?>
<form method="post" action="/masuk" class="stack" novalidate<?php if ($error !== null): ?> aria-describedby="form-error"<?php endif; ?>>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<?= $this->partial('field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $email, 'autocomplete' => 'username', 'inputmode' => 'email', 'required' => true, 'errors' => $errors]) ?>
<?= $this->partial('field', ['name' => 'password', 'label' => 'Kata sandi', 'type' => 'password', 'autocomplete' => 'current-password', 'required' => true, 'errors' => $errors]) ?>
<button type="submit" class="btn btn-primary">Masuk</button>
</form>
<p class="stack-sm">
<a href="/lupa-password">Lupa kata sandi?</a>
<a href="/daftar">Daftar akun usaha</a>
</p>
