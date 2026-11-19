<?php
/**
 * @var App\Core\View $this
 * @var string $csrf
 * @var list<array{label: string, ok: bool, message: string}> $checks
 * @var string|null $error
 */
$allOk = true;
foreach ($checks as $check) {
    $allOk = $allOk && $check['ok'];
}
?>
<h1>Pemasangan POS Retail</h1>
<p>Halaman ini hanya dipakai sekali untuk membuat tabel database dan data referensi. Setelah selesai, halaman ini dinonaktifkan permanen.</p>
<?php if ($error !== null): ?>
<div class="alert alert-danger" id="form-error" role="alert"><?= $this->e($error) ?></div>
<?php endif; ?>
<section class="stack-sm" aria-labelledby="preflight-title">
<h2 id="preflight-title">Pemeriksaan server</h2>
<ul class="stack-sm">
<?php foreach ($checks as $check): ?>
<li>
<span class="badge <?= $check['ok'] ? 'badge-success' : 'badge-danger' ?>"><?= $check['ok'] ? 'Lulus' : 'Gagal' ?></span>
<strong><?= $this->e($check['label']) ?></strong>
<?php if (!$check['ok']): ?><br><?= $this->e($check['message']) ?><?php endif; ?>
</li>
<?php endforeach; ?>
</ul>
</section>
<form method="post" action="/install" class="stack" novalidate<?php if ($error !== null): ?> aria-describedby="form-error"<?php endif; ?>>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<?= $this->partial('field', ['name' => 'token', 'label' => 'Token pemasangan', 'type' => 'password', 'autocomplete' => 'off', 'required' => true, 'hint' => 'Nilai INSTALL_TOKEN pada berkas .env di server.', 'errors' => []]) ?>
<button type="submit" class="btn btn-primary"<?= $allOk ? '' : ' aria-disabled="true"' ?>>Jalankan pemasangan</button>
<?php if (!$allOk): ?>
<p class="field-hint">Perbaiki pemeriksaan yang gagal terlebih dahulu; pemasangan akan ditolak selama masih ada yang gagal.</p>
<?php endif; ?>
</form>
