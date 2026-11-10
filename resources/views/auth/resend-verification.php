<?php
/**
 * @var App\Core\View $this
 * @var string $csrf
 * @var array<string, string> $errors
 * @var string $email
 * @var string|null $notice
 */
?>
<h1>Kirim ulang email verifikasi</h1>
<?php if ($notice !== null): ?>
<p role="status"><?= $this->e($notice) ?></p>
<?php endif; ?>
<form method="post" action="/verifikasi-email/kirim-ulang" novalidate>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<p>
<label for="email">Email</label><br>
<input id="email" name="email" type="email" autocomplete="email" required value="<?= $this->e($email) ?>"<?php if (isset($errors['email'])): ?> aria-invalid="true" aria-describedby="email-error"<?php endif; ?>>
<?php if (isset($errors['email'])): ?>
<br><span id="email-error"><?= $this->e($errors['email']) ?></span>
<?php endif; ?>
</p>
<p><button type="submit">Kirim ulang</button></p>
</form>
