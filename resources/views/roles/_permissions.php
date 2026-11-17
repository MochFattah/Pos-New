<?php
/**
 * Daftar centang izin per modul. Izin yang tidak boleh diubah pengguna ditampilkan nonaktif;
 * bila sudah terpilih, nilainya tetap dikirim lewat input tersembunyi.
 * @var App\Core\View $this
 * @var list<array{code: string, module: string, description: string, grantable: bool}> $catalog
 * @var list<string> $selected
 */
$lastModule = null;
?>
<fieldset class="fieldset stack" id="permissions">
<legend>Izin</legend>
<?php foreach ($catalog as $item): ?>
<?php if ($item['module'] !== $lastModule): $lastModule = $item['module']; ?>
<p class="field-label"><?= $this->e($item['module']) ?></p>
<?php endif; ?>
<?php $checked = in_array($item['code'], $selected, true); $locked = !$item['grantable']; ?>
<?php if ($locked && $checked): ?>
<input type="hidden" name="codes[]" value="<?= $this->e($item['code']) ?>">
<?php endif; ?>
<label class="check" for="perm-<?= $this->e($item['code']) ?>">
<input id="perm-<?= $this->e($item['code']) ?>" type="checkbox"<?= $locked ? '' : ' name="codes[]"' ?> value="<?= $this->e($item['code']) ?>"<?= $checked ? ' checked' : '' ?><?= $locked ? ' disabled' : '' ?>>
<span><code><?= $this->e($item['code']) ?></code> <span class="field-hint"><?= $this->e($item['description']) ?><?= $locked ? ' (tidak dapat Anda ubah)' : '' ?></span></span>
</label>
<?php endforeach; ?>
</fieldset>
