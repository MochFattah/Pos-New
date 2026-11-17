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
    'heading' => 'Data tidak dapat diproses',
    'hint' => 'Periksa isian Anda lalu coba lagi.',
    'message' => $message,
    'requestId' => $requestId ?? null,
    'detail' => $detail ?? null,
]) ?>
