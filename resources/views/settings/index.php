<?php
/**
 * @var App\Core\View $this
 * @var array<string, string> $old
 * @var array<string, string> $errors
 * @var string|null $logoError
 * @var bool $hasLogo
 * @var bool $canManage
 * @var list<string> $numberFormats
 * @var string|null $notice
 * @var string $csrf
 */
// [nama, label, type, autocomplete, inputmode, petunjuk]
$fields = [
    ['business_type', 'Jenis usaha', 'text', 'off', null, null],
    ['address', 'Alamat', 'text', 'street-address', null, null],
    ['phone', 'Nomor telepon', 'tel', 'tel', 'tel', null],
    ['timezone', 'Zona waktu', 'text', 'off', null, 'Nama zona waktu IANA, mis. Asia/Jakarta.'],
    ['currency_code', 'Kode mata uang', 'text', 'off', null, '3 huruf, mis. IDR.'],
];
$formatError = $errors['number_format'] ?? null;
?>
<div class="page-header">
<div>
<h1 class="page-header__title">Pengaturan bisnis</h1>
<?php if (!$canManage): ?>
<p class="page-header__subtitle">Anda hanya dapat melihat pengaturan ini.</p>
<?php endif; ?>
</div>
</div>
<?= $this->partial('flash', ['notice' => $notice]) ?>
<?= $this->partial('error-summary', ['errors' => $errors]) ?>
<form method="post" action="/pengaturan" class="card stack max-w-2xl" novalidate>
<h2 class="card__title">Profil dan format</h2>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<?php foreach ($fields as [$name, $label, $type, $autocomplete, $inputmode, $hint]): ?>
<?= $this->partial('field', ['name' => $name, 'label' => $label, 'type' => $type, 'value' => (string) ($old[$name] ?? ''), 'autocomplete' => $autocomplete, 'inputmode' => $inputmode, 'hint' => $hint, 'readonly' => !$canManage, 'errors' => $errors]) ?>
<?php endforeach; ?>
<div class="field-group">
<label class="field-label" for="number_format">Format angka</label>
<select class="field" id="number_format" name="number_format"<?= $canManage ? '' : ' disabled' ?><?php if ($formatError !== null): ?> aria-invalid="true" aria-describedby="number_format-error"<?php endif; ?>>
<?php foreach ($numberFormats as $format): ?>
<option value="<?= $this->e($format) ?>"<?= ($old['number_format'] ?? '') === $format ? ' selected' : '' ?>><?= $this->e($format) ?></option>
<?php endforeach; ?>
</select>
<?php if ($formatError !== null): ?>
<p class="field-error" id="number_format-error"><?= $this->e($formatError) ?></p>
<?php endif; ?>
</div>
<?php if ($canManage): ?>
<div><button type="submit" class="btn btn-primary">Simpan pengaturan</button></div>
<?php endif; ?>
</form>

<section class="card stack max-w-2xl" aria-labelledby="logo-bisnis">
<h2 class="card__title" id="logo-bisnis">Logo</h2>
<?php if ($hasLogo): ?>
<p><img src="/pengaturan/logo" alt="Logo bisnis saat ini" width="120"></p>
<?php else: ?>
<p class="text-text-secondary">Belum ada logo.</p>
<?php endif; ?>
<?php if ($logoError !== null): ?>
<div class="alert alert-danger" role="alert" id="logo-error"><?= $this->e($logoError) ?></div>
<?php endif; ?>
<?php if ($canManage): ?>
<form method="post" action="/pengaturan/logo" enctype="multipart/form-data" class="stack">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<div class="field-group">
<label class="field-label" for="logo">Berkas logo</label>
<input class="field" id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" required aria-describedby="logo-hint<?= $logoError !== null ? ' logo-error' : '' ?>"<?php if ($logoError !== null): ?> aria-invalid="true"<?php endif; ?>>
<p class="field-hint" id="logo-hint">PNG, JPEG, atau WebP, maksimal 1 MB.</p>
</div>
<div><button type="submit" class="btn btn-primary"><?= $hasLogo ? 'Ganti logo' : 'Unggah logo' ?></button></div>
</form>
<?php if ($hasLogo): ?>
<div>
<?= $this->partial('confirm-form', [
    'id' => 'hapus-logo',
    'action' => '/pengaturan/logo/hapus',
    'csrf' => $csrf,
    'label' => 'Hapus logo',
    'title' => 'Hapus logo bisnis?',
    'body' => 'Logo akan dihapus dari pengaturan. Anda dapat mengunggah logo baru kapan saja.',
]) ?>
</div>
<?php endif; ?>
<?php endif; ?>
</section>
