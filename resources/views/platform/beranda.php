<?php /** @var App\Core\View $this @var string $name */ ?>
<div class="page-header">
<div>
<h1 class="page-header__title">Beranda platform</h1>
<p class="page-header__subtitle">Selamat datang, <?= $this->e($name) ?>.</p>
</div>
</div>
<div class="empty-state">
<p class="empty-state__title">Belum ada data platform</p>
<p class="empty-state__body">Metrik dan pengelolaan tenant akan tersedia di tahap berikutnya.</p>
</div>
