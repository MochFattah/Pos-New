<?php /** @var App\Core\View $this @var string $csrf */ ?>
<h1>Masa demo berakhir</h1>
<p>Akses operasional untuk usaha Anda sedang dinonaktifkan karena masa demo telah berakhir. Data Anda tetap tersimpan dan tidak dihapus.</p>
<p>Hubungi kami untuk memulihkan akses: <a href="/bantuan">halaman bantuan</a>.</p>
<form method="post" action="/keluar">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<button type="submit">Keluar</button>
</form>
