<?php
/**
 * Kerangka Super Admin (/platform): terpisah dari kerangka tenant; tanpa menu tenant.
 * @var App\Core\View $this
 * @var string $title
 * @var string $content
 * @var string|null $name nama Super Admin
 * @var string|null $csrf
 */
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $this->e($title) ?> - POS Retail Platform</title>
<meta name="color-scheme" content="light dark">
<link rel="stylesheet" href="/assets/css/app.css">
<script src="/assets/js/theme.js"></script>
</head>
<body>
<a class="skip-link" href="#main-content">Lewati ke konten utama</a>
<header class="topbar">
<p class="topbar__title">POS Retail <span class="badge badge-info">Platform</span></p>
<div class="topbar__actions">
<?php if (($name ?? '') !== ''): ?>
<span class="text-text-secondary"><?= $this->e($name) ?></span>
<?php endif; ?>
<?= $this->partial('theme-toggle') ?>
<form method="post" action="/platform/keluar">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf ?? '') ?>">
<button type="submit" class="btn btn-secondary">Keluar</button>
</form>
</div>
</header>
<main id="main-content" tabindex="-1" class="app-shell__main">
<div class="app-shell__content stack-lg">
<?= $this->e($content) ?>
</div>
</main>
<script type="module" src="/assets/js/app.js"></script>
</body>
</html>
