<?php
/**
 * Daftar centang izin per modul.
 * @var App\Core\View $this
 * @var list<array{code: string, module: string, description: string, grantable: bool}> $catalog
 * @var list<string> $selected
 */
$lastModule = null;
?>
<fieldset>
<legend>Izin</legend>
<?php foreach ($catalog as $item): ?>
<?php if ($item['module'] !== $lastModule): $lastModule = $item['module']; ?>
<p><strong><?= $this->e($item['module']) ?></strong></p>
<?php endif; ?>
<?php $checked = in_array($item['code'], $selected, true); $locked = !$item['grantable']; ?>
<p>
<input id="perm-<?= $this->e($item['code']) ?>" type="checkbox"<?= $locked ? '' : ' name="codes[]"' ?> value="<?= $this->e($item['code']) ?>"<?= $checked ? ' checked' : '' ?><?= $locked ? ' disabled' : '' ?>>
<?php if ($locked && $checked): ?>
<input type="hidden" name="codes[]" value="<?= $this->e($item['code']) ?>">
<?php endif; ?>
<label for="perm-<?= $this->e($item['code']) ?>"><?= $this->e($item['code']) ?></label>
<span>- <?= $this->e($item['description']) ?><?= $locked ? ' (tidak dapat Anda ubah)' : '' ?></span>
</p>
<?php endforeach; ?>
</fieldset>
