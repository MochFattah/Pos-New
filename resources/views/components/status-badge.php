<?php
/**
 * Lencana status: selalu teks (glyph CSS hanya penambah pembeda bentuk).
 * @var App\Core\View $this
 * @var string $status
 */
$map = [
    'active' => ['Aktif', 'badge-success'],
    'inactive' => ['Nonaktif', 'badge-neutral'],
    'pending_verification' => ['Menunggu undangan diterima', 'badge-warning'],
];
[$label, $class] = $map[$status] ?? [$status, 'badge-neutral'];
?>
<span class="badge <?= $this->e($class) ?>"><?= $this->e($label) ?></span>
