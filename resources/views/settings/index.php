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
$fields = [
    ['business_type', 'Jenis usaha', 'text', 'off'],
    ['address', 'Alamat', 'text', 'street-address'],
    ['phone', 'Nomor telepon', 'tel', 'tel'],
    ['timezone', 'Zona waktu (IANA, mis. Asia/Jakarta)', 'text', 'off'],
    ['currency_code', 'Kode mata uang (3 huruf, mis. IDR)', 'text', 'off'],
];
$ro = $canManage ? '' : ' readonly';
?>
<h1>Pengaturan bisnis</h1>
<?php if ($notice !== null): ?>
<p role="status"><?= $this->e($notice) ?></p>
<?php endif; ?>
<?php if ($errors !== []): ?>
<p role="alert">Periksa kembali isian Anda.</p>
<?php endif; ?>
<form method="post" action="/pengaturan" novalidate>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<?php foreach ($fields as [$name, $label, $type, $autocomplete]): ?>
<p>
<label for="<?= $this->e($name) ?>"><?= $this->e($label) ?></label><br>
<input id="<?= $this->e($name) ?>" name="<?= $this->e($name) ?>" type="<?= $this->e($type) ?>" autocomplete="<?= $this->e($autocomplete) ?>" value="<?= $this->e($old[$name] ?? '') ?>"<?= $ro ?><?php if (isset($errors[$name])): ?> aria-invalid="true" aria-describedby="<?= $this->e($name) ?>-error"<?php endif; ?>>
<?php if (isset($errors[$name])): ?>
<br><span id="<?= $this->e($name) ?>-error"><?= $this->e($errors[$name]) ?></span>
<?php endif; ?>
</p>
<?php endforeach; ?>
<p>
<label for="number_format">Format angka</label><br>
<select id="number_format" name="number_format"<?= $canManage ? '' : ' disabled' ?><?php if (isset($errors['number_format'])): ?> aria-invalid="true" aria-describedby="number_format-error"<?php endif; ?>>
<?php foreach ($numberFormats as $format): ?>
<option value="<?= $this->e($format) ?>"<?= ($old['number_format'] ?? '') === $format ? ' selected' : '' ?>><?= $this->e($format) ?></option>
<?php endforeach; ?>
</select>
<?php if (isset($errors['number_format'])): ?>
<br><span id="number_format-error"><?= $this->e($errors['number_format']) ?></span>
<?php endif; ?>
</p>
<?php if ($canManage): ?>
<p><button type="submit">Simpan pengaturan</button></p>
<?php endif; ?>
</form>

<h2>Logo</h2>
<?php if ($hasLogo): ?>
<p><img src="/pengaturan/logo" alt="Logo bisnis saat ini" width="120"></p>
<?php else: ?>
<p>Belum ada logo.</p>
<?php endif; ?>
<?php if ($logoError !== null): ?>
<p role="alert" id="logo-error"><?= $this->e($logoError) ?></p>
<?php endif; ?>
<?php if ($canManage): ?>
<form method="post" action="/pengaturan/logo" enctype="multipart/form-data">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<p>
<label for="logo">Berkas logo (PNG, JPEG, atau WebP, maksimal 1 MB)</label><br>
<input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" required<?php if ($logoError !== null): ?> aria-invalid="true" aria-describedby="logo-error"<?php endif; ?>>
</p>
<p><button type="submit"><?= $hasLogo ? 'Ganti logo' : 'Unggah logo' ?></button></p>
</form>
<?php if ($hasLogo): ?>
<form method="post" action="/pengaturan/logo/hapus">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<p><button type="submit">Hapus logo</button></p>
</form>
<?php endif; ?>
<?php endif; ?>
<p><a href="/beranda">Kembali ke beranda</a></p>
