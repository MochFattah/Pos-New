<?php
/**
 * Kolom isian bertanda label + petunjuk + pesan kesalahan yang terhubung lewat aria-describedby.
 * @var App\Core\View $this
 * @var string $name
 * @var string $label
 * @var string $type
 * @var string $value
 * @var array<string, string> $errors
 * @var string|null $autocomplete
 * @var string|null $inputmode
 * @var string|null $hint
 * @var bool $required
 * @var bool $readonly
 * @var int|null $maxlength
 */
$id = $id ?? $name;
$type ??= 'text';
$value ??= '';
$errors ??= [];
$required ??= false;
$readonly ??= false;
$error = $errors[$name] ?? null;
$hint ??= null;
$describedBy = trim(($hint !== null ? $id . '-hint ' : '') . ($error !== null ? $id . '-error' : ''));
?>
<div class="field-group">
<label class="field-label" for="<?= $this->e($id) ?>"><?= $this->e($label) ?><?php if ($required): ?> <span aria-hidden="true">*</span><span class="visually-hidden">(wajib diisi)</span><?php endif; ?></label>
<input class="field" id="<?= $this->e($id) ?>" name="<?= $this->e($name) ?>" type="<?= $this->e($type) ?>"<?php if (($autocomplete ?? null) !== null): ?> autocomplete="<?= $this->e($autocomplete) ?>"<?php endif; ?><?php if (($inputmode ?? null) !== null): ?> inputmode="<?= $this->e($inputmode) ?>"<?php endif; ?><?php if (($maxlength ?? null) !== null): ?> maxlength="<?= $this->e($maxlength) ?>"<?php endif; ?><?= $required ? ' required' : '' ?><?= $readonly ? ' readonly' : '' ?><?php if ($type !== 'password'): ?> value="<?= $this->e($value) ?>"<?php endif; ?><?php if ($error !== null): ?> aria-invalid="true"<?php endif; ?><?php if ($describedBy !== ''): ?> aria-describedby="<?= $this->e($describedBy) ?>"<?php endif; ?>>
<?php if ($hint !== null): ?>
<p class="field-hint" id="<?= $this->e($id) ?>-hint"><?= $this->e($hint) ?></p>
<?php endif; ?>
<?php if ($error !== null): ?>
<p class="field-error" id="<?= $this->e($id) ?>-error"><?= $this->e($error) ?></p>
<?php endif; ?>
</div>
