<?php
/**
 * @var App\Core\View $this
 * @var list<array<string, mixed>> $users
 * @var bool $canManage
 * @var int $currentUserId
 * @var string $csrf
 * @var string|null $notice
 * @var string|null $error
 */
?>
<div class="page-header">
<div>
<h1 class="page-header__title">Pengguna</h1>
<p class="page-header__subtitle">Anggota tim yang dapat masuk ke usaha Anda.</p>
</div>
<?php if ($canManage): ?>
<div class="page-header__actions">
<a class="btn btn-primary" href="/pengguna/undang">Undang pengguna</a>
</div>
<?php endif; ?>
</div>
<?= $this->partial('flash', ['notice' => $notice, 'error' => $error]) ?>
<div class="table-wrap">
<table class="table-data">
<caption>Daftar pengguna</caption>
<thead>
<tr>
<th scope="col">Nama</th>
<th scope="col">Email</th>
<th scope="col">Role</th>
<th scope="col">Status</th>
<?php if ($canManage): ?>
<th scope="col">Tindakan</th>
<?php endif; ?>
</tr>
</thead>
<tbody>
<?php foreach ($users as $row): ?>
<?php $isOwner = (int) $row['is_owner'] === 1; ?>
<tr>
<th scope="row" class="cell-wrap"><?= $this->e($row['name']) ?><?php if ($isOwner): ?> <span class="badge badge-info">Owner</span><?php endif; ?></th>
<td class="cell-wrap"><?= $this->e($row['email']) ?></td>
<td><?= $this->e(implode(', ', $row['role_names'])) ?></td>
<td><?= $this->partial('status-badge', ['status' => (string) $row['status']]) ?></td>
<?php if ($canManage): ?>
<td><a class="btn btn-tertiary" href="/pengguna/<?= $this->e($row['id']) ?>/ubah" aria-label="Ubah pengguna <?= $this->e($row['name']) ?>">Ubah</a></td>
<?php endif; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
