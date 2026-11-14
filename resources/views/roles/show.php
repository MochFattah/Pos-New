<?php
/**
 * @var App\Core\View $this
 * @var array<string, mixed> $role
 * @var list<array{code: string, module: string, description: string, grantable: bool}> $catalog
 * @var bool $isOwnerRole
 * @var bool $canManage
 * @var string $csrf
 * @var string|null $notice
 * @var string|null $error
 */
$id = (int) $role['id'];
$isSystem = (int) $role['is_system'] === 1;
$selected = $role['permissions'];
?>
<h1>Role <?= $this->e($role['name']) ?></h1>
<?php if ($notice !== null): ?>
<p role="status"><?= $this->e($notice) ?></p>
<?php endif; ?>
<?php if ($error !== null): ?>
<p role="alert"><?= $this->e($error) ?></p>
<?php endif; ?>
<p>Jenis: <?= $isSystem ? 'Sistem' : 'Kustom' ?> | Pengguna: <?= $this->e($role['user_count']) ?></p>

<?php if ($isOwnerRole): ?>
<p>Role Owner memiliki seluruh izin dan tidak dapat diubah.</p>
<?php elseif ($canManage): ?>
<form method="post" action="/role/<?= $this->e($id) ?>/izin">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<?php include __DIR__ . '/_permissions.php'; ?>
<p><button type="submit">Simpan izin</button></p>
</form>
<?php else: ?>
<h2>Izin</h2>
<?php if ($selected === []): ?>
<p>Belum ada izin.</p>
<?php else: ?>
<ul>
<?php foreach ($selected as $code): ?>
<li><?= $this->e($code) ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php endif; ?>

<?php if ($canManage && !$isSystem): ?>
<h2>Nama role</h2>
<form method="post" action="/role/<?= $this->e($id) ?>/nama">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<p>
<label for="name">Nama role</label><br>
<input id="name" name="name" type="text" autocomplete="off" required value="<?= $this->e($role['name']) ?>">
</p>
<p><button type="submit">Simpan nama</button></p>
</form>
<h2>Hapus role</h2>
<form method="post" action="/role/<?= $this->e($id) ?>/hapus">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<p>Role yang masih dipakai pengguna tidak dapat dihapus.</p>
<p><button type="submit">Hapus role</button></p>
</form>
<?php endif; ?>
<p><a href="/role">Kembali ke daftar role</a></p>
