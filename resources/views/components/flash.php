<?php
/**
 * Pesan sekali tampil dari redirect: notice (berhasil) dan error (gagal).
 * @var App\Core\View $this
 * @var string|null $notice
 * @var string|null $error
 */
?>
<?php if (($notice ?? null) !== null): ?>
<div class="alert alert-success" role="status"><?= $this->e($notice) ?></div>
<?php endif; ?>
<?php if (($error ?? null) !== null): ?>
<div class="alert alert-danger" role="alert"><?= $this->e($error) ?></div>
<?php endif; ?>
