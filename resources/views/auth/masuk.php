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
<p role="status"><?= $this->e($notice) ?></p>
<?php endif; ?>
<?php if ($error !== null): ?>
<p id="form-error" role="alert"><?= $this->e($error) ?></p>
<?php if ($offerResend): ?>
<p><a href="/verifikasi-email">Kirim ulang email verifikasi</a></p>
<?php endif; ?>
<?php endif; ?>
<form method="post" action="/masuk" novalidate<?php if ($error !== null): ?> aria-describedby="form-error"<?php endif; ?>>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<p>
<label for="email">Email</label><br>
<input id="email" name="email" type="email" autocomplete="username" required value="<?= $this->e($email) ?>"<?php if (isset($errors['email'])): ?> aria-invalid="true" aria-describedby="email-error"<?php endif; ?>>
<?php if (isset($errors['email'])): ?>
<br><span id="email-error"><?= $this->e($errors['email']) ?></span>
<?php endif; ?>
</p>
<p>
<label for="password">Kata sandi</label><br>
<input id="password" name="password" type="password" autocomplete="current-password" required<?php if (isset($errors['password'])): ?> aria-invalid="true" aria-describedby="password-error"<?php endif; ?>>
<?php if (isset($errors['password'])): ?>
<br><span id="password-error"><?= $this->e($errors['password']) ?></span>
<?php endif; ?>
</p>
<p><button type="submit">Masuk</button></p>
</form>
<p><a href="/lupa-password">Lupa kata sandi?</a></p>
<p><a href="/daftar">Daftar akun usaha</a></p>
