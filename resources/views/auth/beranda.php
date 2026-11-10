<?php /** @var App\Core\View $this @var string $name @var string $csrf */ ?>
<h1>Beranda</h1>
<p>Selamat datang, <?= $this->e($name) ?>.</p>
<form method="post" action="/keluar">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<button type="submit">Keluar</button>
</form>
