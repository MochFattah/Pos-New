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
<div class="page-header">
<div>
<h1 class="page-header__title">Cabang</h1>
<p class="page-header__subtitle">Lokasi usaha Anda. Cabang utama tidak dapat dinonaktifkan.</p>
</div>
<?php if ($canManage): ?>
<div class="page-header__actions">
<a class="btn btn-primary" href="/cabang/baru">Tambah cabang</a>
</div>
<?php endif; ?>
</div>
<?= $this->partial('flash', ['notice' => $notice, 'error' => $error]) ?>
<?php if ($branches === []): ?>
<div class="empty-state">
<p class="empty-state__title">Belum ada cabang</p>
<p class="empty-state__body">Tambahkan cabang pertama untuk mulai mengatur lokasi usaha Anda.</p>
<?php if ($canManage): ?>
<a class="btn btn-primary" href="/cabang/baru">Tambah cabang</a>
<?php endif; ?>
</div>
<?php else: ?>
<div class="table-wrap" tabindex="0" role="region" aria-label="Daftar cabang">
<table class="table-data">
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
<td class="cell-wrap"><?= $this->e($branch['name']) ?><?php if ($isMain): ?> <span class="badge badge-info">Utama</span><?php endif; ?></td>
<td class="cell-wrap"><?= $this->e($branch['address'] ?? '') ?></td>
<td><?= $this->e($branch['phone'] ?? '') ?></td>
<td><?= $this->partial('status-badge', ['status' => (string) $branch['status']]) ?></td>
<?php if ($canManage): ?>
<td>
<div class="cluster">
<a class="btn btn-tertiary" href="/cabang/<?= $this->e($branch['id']) ?>/ubah" aria-label="Ubah cabang <?= $this->e($branch['name']) ?>">Ubah</a>
<?php if (!$isMain && $isActive): ?>
<?= $this->partial('confirm-form', [
    'id' => 'nonaktifkan-cabang-' . (int) $branch['id'],
    'action' => '/cabang/' . (int) $branch['id'] . '/nonaktifkan',
    'csrf' => $csrf,
    'label' => 'Nonaktifkan',
    'ariaLabel' => 'Nonaktifkan cabang ' . $branch['name'],
    'title' => 'Nonaktifkan cabang ' . $branch['name'] . '?',
    'body' => 'Cabang yang nonaktif tidak dapat dipilih untuk operasional. Data cabang tidak dihapus dan dapat diaktifkan kembali.',
]) ?>
<?php elseif (!$isMain): ?>
<form method="post" action="/cabang/<?= $this->e($branch['id']) ?>/aktifkan">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<button type="submit" class="btn btn-secondary" aria-label="Aktifkan cabang <?= $this->e($branch['name']) ?>">Aktifkan</button>
</form>
<?php endif; ?>
</div>
</td>
<?php endif; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
