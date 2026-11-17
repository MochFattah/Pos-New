<?php
/**
 * Ringkasan kesalahan formulir: role="alert", tautan ke tiap field, tabindex=-1 agar fokusnya bisa diatur
 * (error-summary di JS 15b memindahkan fokus ke sini). Kunci tanpa field (mis. "form") tampil sebagai teks.
 * @var App\Core\View $this
 * @var array<string, string> $errors
 * @var string|null $title
 */
$title ??= 'Periksa kembali isian Anda';
?>
<?php if ($errors !== []): ?>
<div class="error-summary" id="error-summary" role="alert" tabindex="-1" data-error-summary>
<p class="error-summary__title"><?= $this->e($title) ?></p>
<ul>
<?php foreach ($errors as $key => $message): ?>
<?php if ($key === 'form'): ?>
<li><?= $this->e($message) ?></li>
<?php else: ?>
<li><a href="#<?= $this->e($key) ?>"><?= $this->e($message) ?></a></li>
<?php endif; ?>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>
