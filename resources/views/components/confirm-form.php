<?php
/**
 * Tindakan berisiko (nonaktifkan, hapus, batalkan undangan): form POST biasa yang tetap berfungsi tanpa JS.
 * dialog.js (15b) mencegat form[data-confirm-dialog], membuka <dialog> terkait, dan mengembalikan fokus ke pemicu.
 * @var App\Core\View $this
 * @var string $id unik di halaman
 * @var string $action
 * @var string $csrf
 * @var string $label teks tombol pemicu
 * @var string $title judul dialog
 * @var string $body penjelasan konsekuensi
 * @var string|null $confirmLabel teks tombol konfirmasi (bawaan: sama dengan $label)
 * @var string|null $ariaLabel nama aksesibel tombol pemicu (mis. menyebut objeknya)
 */
$confirmLabel ??= $label;
?>
<form method="post" action="<?= $this->e($action) ?>" id="<?= $this->e($id) ?>-form" data-confirm-dialog="<?= $this->e($id) ?>-dialog">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<button type="submit" class="btn btn-danger"<?php if (($ariaLabel ?? null) !== null): ?> aria-label="<?= $this->e($ariaLabel) ?>"<?php endif; ?>><?= $this->e($label) ?></button>
</form>
<dialog class="dialog-panel" id="<?= $this->e($id) ?>-dialog" aria-labelledby="<?= $this->e($id) ?>-title" aria-describedby="<?= $this->e($id) ?>-body">
<h2 class="dialog-panel__title" id="<?= $this->e($id) ?>-title"><?= $this->e($title) ?></h2>
<p class="dialog-panel__body" id="<?= $this->e($id) ?>-body"><?= $this->e($body) ?></p>
<div class="dialog-panel__actions">
<button type="button" class="btn btn-secondary" data-dialog-close>Batal</button>
<button type="submit" class="btn btn-danger" form="<?= $this->e($id) ?>-form" data-dialog-confirm><?= $this->e($confirmLabel) ?></button>
</div>
</dialog>
