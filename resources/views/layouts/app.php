<?php
/**
 * Kerangka aplikasi tenant: sidebar (>= 1024 px) / drawer (mobile), topbar dengan identitas bisnis,
 * pengguna, tema, dan keluar. Menu berupa daftar tautan biasa sehingga drawer tetap bisa dipakai
 * tanpa JS (:target); drawer.js (15b) hanya menyempurnakan perilakunya.
 * @var App\Core\View $this
 * @var string $title
 * @var string $content
 * @var App\Core\Layout\AppShell|null $shell
 */
$shell ??= App\Core\Layout\AppShell::minimal('', '');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $this->e($title) ?> - POS Retail</title>
<meta name="color-scheme" content="light dark">
<link rel="stylesheet" href="/assets/css/app.css">
<script src="/assets/js/theme.js"></script>
</head>
<body>
<a class="skip-link" href="#main-content">Lewati ke konten utama</a>
<div class="app-shell">
<nav class="sidebar" id="navigasi" aria-label="Menu utama" data-drawer>
<div class="cluster justify-between">
<p class="sidebar__brand">POS Retail</p>
<a class="btn btn-tertiary lg:hidden" href="#main-content" data-drawer-close>Tutup menu</a>
</div>
<?php if ($shell->nav !== []): ?>
<ul class="stack-sm list-none p-0 m-0">
<?php foreach ($shell->nav as $item): ?>
<li><a class="nav-item" href="<?= $this->e($item['href']) ?>"<?= $item['current'] ? ' aria-current="page"' : '' ?>><?= $this->e($item['label']) ?></a></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</nav>
<div class="sidebar-scrim" hidden data-drawer-scrim></div>
<header class="topbar">
<a class="btn btn-secondary topbar__menu" href="#navigasi" data-drawer-open aria-controls="navigasi">Menu</a>
<p class="topbar__title"><?= $this->e($shell->businessName) ?></p>
<div class="topbar__actions">
<?php if ($shell->userName !== ''): ?>
<span class="text-text-secondary"><?= $this->e($shell->userName) ?></span>
<?php endif; ?>
<?= $this->partial('theme-toggle') ?>
<form method="post" action="/keluar">
<input type="hidden" name="_csrf" value="<?= $this->e($shell->csrf) ?>">
<button type="submit" class="btn btn-secondary">Keluar</button>
</form>
</div>
</header>
<main id="main-content" tabindex="-1" class="app-shell__main">
<div class="app-shell__content stack-lg">
<?= $this->e($content) ?>
</div>
</main>
</div>
<div class="toast-region" role="status" aria-live="polite" data-toast-region></div>
<script type="module" src="/assets/js/app.js"></script>
</body>
</html>
