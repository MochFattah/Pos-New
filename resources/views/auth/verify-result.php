<?php /** @var App\Core\View $this @var bool $success */ ?>
<?php if ($success): ?>
<div class="stack">
<p><span class="badge badge-success">Berhasil</span></p>
<h1>Email terverifikasi</h1>
<p>Akun Anda sudah aktif. Silakan masuk untuk mulai memakai POS Retail.</p>
</div>
<a class="btn btn-primary" href="/masuk">Masuk</a>
<?php else: ?>
<div class="stack">
<h1>Tautan tidak valid</h1>
<p>Tautan tidak valid atau sudah digunakan.</p>
</div>
<a class="btn btn-primary" href="/verifikasi-email">Kirim ulang email verifikasi</a>
<?php endif; ?>
