<?php
/**
 * @var App\Core\View $this
 * @var string $name
 * @var string|null $demoRemaining
 */
?>
<div class="page-header">
<div>
<h1 class="page-header__title">Beranda</h1>
<p class="page-header__subtitle">Selamat datang, <?= $this->e($name) ?>.</p>
</div>
</div>
<section class="card" aria-labelledby="status-demo">
<h2 class="card__title" id="status-demo">Status akun</h2>
<?php if ($demoRemaining !== null): ?>
<p><span class="badge badge-info">Masa demo berjalan</span></p>
<p class="mt-2">Sisa waktu demo: <strong><?= $this->e($demoRemaining) ?></strong>.</p>
<?php else: ?>
<p><span class="badge badge-neutral">Tidak ada masa demo yang berlaku</span></p>
<?php endif; ?>
</section>
<div class="empty-state">
<p class="empty-state__title">Ringkasan usaha belum tersedia</p>
<p class="empty-state__body">Ringkasan akan tampil di sini seiring fitur baru diaktifkan. Untuk saat ini, atur cabang, pengguna, dan pengaturan bisnis dari menu.</p>
</div>
