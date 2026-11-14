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
$statusLabels = ['active' => 'Aktif', 'inactive' => 'Nonaktif', 'pending_verification' => 'Menunggu undangan diterima'];
?>
<h1>Pengguna</h1>
<?php if ($notice !== null): ?>
<p role="status"><?= $this->e($notice) ?></p>
<?php endif; ?>
<?php if ($error !== null): ?>
<p role="alert"><?= $this->e($error) ?></p>
<?php endif; ?>
<?php if ($canManage): ?>
<p><a href="/pengguna/undang">Undang pengguna</a></p>
<?php endif; ?>
<table>
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
<th scope="row"><?= $this->e($row['name']) ?><?= $isOwner ? ' (Owner)' : '' ?></th>
<td><?= $this->e($row['email']) ?></td>
<td><?= $this->e(implode(', ', $row['role_names'])) ?></td>
<td><?= $this->e($statusLabels[$row['status']] ?? $row['status']) ?></td>
<?php if ($canManage): ?>
<td><a href="/pengguna/<?= $this->e($row['id']) ?>/ubah" aria-label="Ubah pengguna <?= $this->e($row['name']) ?>">Ubah</a></td>
<?php endif; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<p><a href="/beranda">Kembali ke beranda</a></p>
