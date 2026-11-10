<?php /** @var App\Core\View $this @var string $supportEmail */ ?>
<h1>Bantuan</h1>
<?php if ($supportEmail !== ''): ?>
<p>Hubungi tim dukungan kami melalui email: <a href="mailto:<?= $this->e($supportEmail) ?>"><?= $this->e($supportEmail) ?></a>.</p>
<?php else: ?>
<p>Hubungi administrator layanan Anda untuk bantuan akses akun.</p>
<?php endif; ?>
<p><a href="/masuk">Kembali ke halaman masuk</a></p>
