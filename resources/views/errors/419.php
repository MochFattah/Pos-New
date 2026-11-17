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
    'heading' => 'Sesi kedaluwarsa',
    'hint' => 'Muat ulang halaman sebelumnya lalu coba lagi.',
    'message' => $message,
    'requestId' => $requestId ?? null,
    'detail' => $detail ?? null,
]) ?>
