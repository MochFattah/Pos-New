<?php
/**
 * @var App\Core\View $this
 * @var list<array<string, mixed>> $branches
 * @var bool $canManage
 * @var string $csrf
 * @var string|null $notice
 * @var string|null $error
 */
?>
<h1>Cabang</h1>
<?php if ($notice !== null): ?>
<p role="status"><?= $this->e($notice) ?></p>
<?php endif; ?>
<?php if ($error !== null): ?>
<p role="alert"><?= $this->e($error) ?></p>
<?php endif; ?>
<?php if ($canManage): ?>
<p><a href="/cabang/baru">Tambah cabang</a></p>
<?php endif; ?>
<?php if ($branches === []): ?>
<p>Belum ada cabang.</p>
<?php else: ?>
<table>
<caption>Daftar cabang</caption>
<thead>
<tr>
<th scope="col">Kode</th>
<th scope="col">Nama</th>
<th scope="col">Alamat</th>
<th scope="col">Telepon</th>
<th scope="col">Status</th>
<?php if ($canManage): ?>
<th scope="col">Tindakan</th>
<?php endif; ?>
</tr>
</thead>
<tbody>
<?php foreach ($branches as $branch): ?>
<?php $isActive = $branch['status'] === 'active'; $isMain = (int) $branch['is_main'] === 1; ?>
<tr>
<th scope="row"><?= $this->e($branch['code']) ?></th>
<td><?= $this->e($branch['name']) ?><?= $isMain ? ' (utama)' : '' ?></td>
<td><?= $this->e($branch['address'] ?? '') ?></td>
<td><?= $this->e($branch['phone'] ?? '') ?></td>
<td><?= $isActive ? 'Aktif' : 'Nonaktif' ?></td>
<?php if ($canManage): ?>
<td>
<a href="/cabang/<?= $this->e($branch['id']) ?>/ubah" aria-label="Ubah cabang <?= $this->e($branch['name']) ?>">Ubah</a>
<?php if (!$isMain): ?>
<form method="post" action="/cabang/<?= $this->e($branch['id']) ?>/<?= $isActive ? 'nonaktifkan' : 'aktifkan' ?>">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<button type="submit" aria-label="<?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?> cabang <?= $this->e($branch['name']) ?>"><?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?></button>
</form>
<?php endif; ?>
</td>
<?php endif; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
<p><a href="/beranda">Kembali ke beranda</a></p>
