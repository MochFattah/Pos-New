<?php
/**
 * @var App\Core\View $this
 * @var string $csrf
 * @var array<string, string> $errors
 * @var string $email
 * @var string|null $notice
 */
?>
<h1>Lupa kata sandi</h1>
<p>Masukkan email akun Anda. Kami akan mengirim tautan untuk mengatur ulang kata sandi.</p>
<?php if ($notice !== null): ?>
<div class="alert" role="status"><?= $this->e($notice) ?></div>
<?php endif; ?>
<?= $this->partial('error-summary', ['errors' => $errors]) ?>
<form method="post" action="/lupa-password" class="stack" novalidate>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<?= $this->partial('field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $email, 'autocomplete' => 'email', 'inputmode' => 'email', 'required' => true, 'errors' => $errors]) ?>
<button type="submit" class="btn btn-primary">Kirim tautan</button>
</form>
<p><a href="/masuk">Kembali ke halaman masuk</a></p>
