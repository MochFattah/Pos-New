<?php
/**
 * @var App\Core\View $this
 * @var int $status
 * @var string $message
 * @var string|null $requestId
 * @var string|null $detail
 */
?>
<?= $this->partial('error-page', [
    'status' => $status,
    'heading' => 'Halaman tidak ditemukan',
    'hint' => 'Periksa alamat yang Anda buka, atau kembali ke beranda.',
    'message' => $message,
    'requestId' => $requestId ?? null,
    'detail' => $detail ?? null,
]) ?>
