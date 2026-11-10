<?php
/**
 * @var App\Core\View $this
 * @var string $csrf
 * @var array<string, string> $errors
 * @var string $token
 */
?>
<h1>Atur ulang kata sandi</h1>
<form method="post" action="/reset-password/<?= $this->e($token) ?>" novalidate>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<p>
<label for="password">Kata sandi baru</label><br>
<input id="password" name="password" type="password" autocomplete="new-password" required aria-describedby="password-hint<?= isset($errors['password']) ? ' password-error' : '' ?>"<?php if (isset($errors['password'])): ?> aria-invalid="true"<?php endif; ?>>
<br><span id="password-hint">Minimal 8 karakter, maksimal 72 byte.</span>
<?php if (isset($errors['password'])): ?>
<br><span id="password-error"><?= $this->e($errors['password']) ?></span>
<?php endif; ?>
</p>
<p>
<label for="password_confirmation">Konfirmasi kata sandi baru</label><br>
<input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required<?php if (isset($errors['password_confirmation'])): ?> aria-invalid="true" aria-describedby="password_confirmation-error"<?php endif; ?>>
<?php if (isset($errors['password_confirmation'])): ?>
<br><span id="password_confirmation-error"><?= $this->e($errors['password_confirmation']) ?></span>
<?php endif; ?>
</p>
<p><button type="submit">Simpan kata sandi</button></p>
</form>
