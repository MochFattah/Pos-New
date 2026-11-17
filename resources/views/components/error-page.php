<?php
/**
 * Isi halaman kesalahan: tanpa data internal; ID permintaan hanya diberikan untuk 500; detail hanya mode debug.
 * @var App\Core\View $this
 * @var int $status
 * @var string $heading
 * @var string $hint
 * @var string $message
 * @var string|null $requestId
 * @var string|null $detail
 */
?>
<div class="stack">
<p><span class="badge badge-danger">Kesalahan <?= $this->e($status) ?></span></p>
<h1><?= $this->e($heading) ?></h1>
<p><?= $this->e($message) ?></p>
<p class="text-text-secondary"><?= $this->e($hint) ?></p>
<?php if (($requestId ?? null) !== null): ?>
<p class="caption">ID permintaan: <code><?= $this->e($requestId) ?></code></p>
<?php endif; ?>
<?php if (($detail ?? null) !== null): ?>
<pre class="caption overflow-x-auto"><?= $this->e($detail) ?></pre>
<?php endif; ?>
</div>
<div class="cluster">
<a class="btn btn-primary" href="/beranda">Ke beranda</a>
<a class="btn btn-secondary" href="/bantuan">Bantuan</a>
</div>
