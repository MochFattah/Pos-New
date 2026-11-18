<?php
/**
 * @var App\Core\View $this
 * @var list<array<string, mixed>> $roles
 * @var bool $canManage
 * @var string|null $notice
 * @var string|null $error
 */
?>
<div class="page-header">
<div>
<h1 class="page-header__title">Role dan izin</h1>
<p class="page-header__subtitle">Role menentukan apa yang boleh dilakukan pengguna.</p>
</div>
<?php if ($canManage): ?>
<div class="page-header__actions">
<a class="btn btn-primary" href="/role/baru">Tambah role</a>
</div>
<?php endif; ?>
</div>
<?= $this->partial('flash', ['notice' => $notice, 'error' => $error]) ?>
<div class="table-wrap" tabindex="0" role="region" aria-label="Daftar role">
<table class="table-data">
<caption>Daftar role</caption>
<thead>
<tr>
<th scope="col">Nama</th>
<th scope="col">Jenis</th>
<th scope="col" class="num">Jumlah izin</th>
<th scope="col" class="num">Jumlah pengguna</th>
</tr>
</thead>
<tbody>
<?php foreach ($roles as $role): ?>
<tr>
<th scope="row"><a href="/role/<?= $this->e($role['id']) ?>"><?= $this->e($role['name']) ?></a></th>
<td><span class="badge badge-neutral"><?= (int) $role['is_system'] === 1 ? 'Sistem' : 'Kustom' ?></span></td>
<td class="num"><?= $role['slug'] === 'owner' && (int) $role['is_system'] === 1 ? 'Semua' : $this->e($role['permission_count']) ?></td>
<td class="num"><?= $this->e($role['user_count']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
