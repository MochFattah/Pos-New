<?php /** @var App\Core\View $this @var bool $success */ ?>
<?php if ($success): ?>
<h1>Email terverifikasi</h1>
<p>Akun Anda sudah aktif. Silakan masuk untuk mulai memakai POS Retail.</p>
<p><a href="/masuk">Masuk</a></p>
<?php else: ?>
<h1>Tautan tidak valid</h1>
<p>Tautan tidak valid atau sudah digunakan.</p>
<p><a href="/verifikasi-email">Kirim ulang email verifikasi</a></p>
<?php endif; ?>
