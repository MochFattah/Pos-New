<?php
/**
 * @var App\Core\View $this
 * @var list<array<string, mixed>> $roles
 * @var bool $canManage
 * @var string|null $notice
 * @var string|null $error
 */
?>
<h1>Role dan izin</h1>
<?php if ($notice !== null): ?>
<p role="status"><?= $this->e($notice) ?></p>
<?php endif; ?>
<?php if ($error !== null): ?>
<p role="alert"><?= $this->e($error) ?></p>
<?php endif; ?>
<?php if ($canManage): ?>
<p><a href="/role/baru">Tambah role</a></p>
<?php endif; ?>
<table>
<caption>Daftar role</caption>
<thead>
<tr>
<th scope="col">Nama</th>
<th scope="col">Jenis</th>
<th scope="col">Jumlah izin</th>
<th scope="col">Jumlah pengguna</th>
</tr>
</thead>
<tbody>
<?php foreach ($roles as $role): ?>
<tr>
<th scope="row"><a href="/role/<?= $this->e($role['id']) ?>"><?= $this->e($role['name']) ?></a></th>
<td><?= (int) $role['is_system'] === 1 ? 'Sistem' : 'Kustom' ?></td>
<td><?= $role['slug'] === 'owner' && (int) $role['is_system'] === 1 ? 'Semua' : $this->e($role['permission_count']) ?></td>
<td><?= $this->e($role['user_count']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<p><a href="/beranda">Kembali ke beranda</a></p>
