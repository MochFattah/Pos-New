<?php
/**
 * Halaman publik (landing, masuk, daftar, error): satu kolom terpusat, tanpa menu aplikasi.
 * @var App\Core\View $this
 * @var string $title
 * @var string $content
 * @var string|null $cardClass modifier tambahan untuk .auth-card (mis. auth-card--wide)
 */
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
<main id="main-content" tabindex="-1" class="auth-page">
<div class="auth-card stack-lg<?= isset($cardClass) ? ' ' . $this->e($cardClass) : '' ?>">
<p class="auth-brand">POS Retail</p>
<?= $this->e($content) ?>
</div>
</main>
<script type="module" src="/assets/js/app.js"></script>
</body>
</html>
