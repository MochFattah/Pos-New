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
    'heading' => 'Terjadi kesalahan',
    'hint' => 'Kami tidak dapat menyelesaikan permintaan Anda. Coba lagi beberapa saat lagi.',
    'message' => $message,
    'requestId' => $requestId ?? null,
    'detail' => $detail ?? null,
]) ?>
