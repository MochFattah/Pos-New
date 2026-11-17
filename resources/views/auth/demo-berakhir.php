<?php /** @var App\Core\View $this @var string $csrf */ ?>
<div class="stack">
<h1>Masa demo berakhir</h1>
<p>Akses operasional untuk usaha Anda sedang dinonaktifkan karena masa demo telah berakhir. Data Anda tetap tersimpan dan tidak dihapus.</p>
<p>Hubungi kami untuk memulihkan akses: <a href="/bantuan">halaman bantuan</a>.</p>
</div>
<form method="post" action="/keluar">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<button type="submit" class="btn btn-secondary">Keluar</button>
</form>
