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
    'heading' => 'Akses ditolak',
    'hint' => 'Anda tidak memiliki izin untuk membuka halaman ini. Hubungi pemilik akun bila Anda merasa seharusnya bisa mengaksesnya.',
    'message' => $message,
    'requestId' => $requestId ?? null,
    'detail' => $detail ?? null,
]) ?>
