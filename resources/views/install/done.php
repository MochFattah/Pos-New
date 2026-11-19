<?php
/**
 * @var App\Core\View $this
 * @var list<string> $applied
 */
?>
<h1>Pemasangan selesai</h1>
<div class="alert alert-success" role="status">Tabel database dan data referensi sudah dibuat. Halaman pemasangan kini nonaktif permanen.</div>
<?php if ($applied !== []): ?>
<p>Migrasi diterapkan: <?= $this->e(implode(', ', $applied)) ?>.</p>
<?php endif; ?>
<section class="stack-sm" aria-labelledby="next-title">
<h2 id="next-title">Langkah berikutnya</h2>
<ol class="stack-sm">
<li>Kosongkan nilai <code>INSTALL_TOKEN</code> pada berkas <code>.env</code> (dianjurkan; penginstal sudah terkunci oleh <code>storage/installed.lock</code>).</li>
<li>Buat akun Super Admin pertama lewat terminal, karena akun ini tidak dapat dibuat dari halaman web:
<br><code>php scripts/create-platform-admin.php "Nama Admin" email@domain.com</code>
<br>Kata sandi diminta lewat prompt, bukan argumen.</li>
<li>Bila hosting Anda tidak menyediakan SSH, jalankan perintah itu di Terminal cPanel (bila tersedia) atau minta penyedia hosting menjalankannya. Aplikasi ini belum menyediakan cara lain untuk membuat Super Admin.</li>
<li>Pastikan <code>APP_ENV=production</code>, <code>APP_DEBUG=false</code>, dan <code>APP_URL</code> sudah benar, lalu uji pendaftaran dan pengiriman email.</li>
</ol>
</section>
<p><a href="/">Ke beranda</a></p>
