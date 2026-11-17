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
<div class="page-header">
<div>
<h1 class="page-header__title">Role <?= $this->e($role['name']) ?></h1>
<p class="page-header__subtitle"><span class="badge badge-neutral"><?= $isSystem ? 'Sistem' : 'Kustom' ?></span> Pengguna dengan role ini: <?= $this->e($role['user_count']) ?></p>
</div>
<div class="page-header__actions">
<a class="btn btn-secondary" href="/role">Kembali ke daftar role</a>
</div>
</div>
<?= $this->partial('flash', ['notice' => $notice, 'error' => $error]) ?>

<?php if ($isOwnerRole): ?>
<div class="alert">Role Owner memiliki seluruh izin dan tidak dapat diubah.</div>
<?php elseif ($canManage): ?>
<form method="post" action="/role/<?= $this->e($id) ?>/izin" class="card stack">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<?php include __DIR__ . '/_permissions.php'; ?>
<div><button type="submit" class="btn btn-primary">Simpan izin</button></div>
</form>
<?php else: ?>
<section class="card stack-sm" aria-labelledby="izin-role">
<h2 class="card__title" id="izin-role">Izin</h2>
<?php if ($selected === []): ?>
<p>Belum ada izin.</p>
<?php else: ?>
<ul>
<?php foreach ($selected as $code): ?>
<li><code><?= $this->e($code) ?></code></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</section>
<?php endif; ?>

<?php if ($canManage && !$isSystem): ?>
<form method="post" action="/role/<?= $this->e($id) ?>/nama" class="card stack max-w-2xl" novalidate>
<h2 class="card__title">Nama role</h2>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<?= $this->partial('field', ['name' => 'name', 'label' => 'Nama role', 'value' => (string) $role['name'], 'autocomplete' => 'off', 'required' => true, 'errors' => []]) ?>
<div><button type="submit" class="btn btn-primary">Simpan nama</button></div>
</form>
<section class="card stack-sm max-w-2xl" aria-labelledby="hapus-role">
<h2 class="card__title" id="hapus-role">Hapus role</h2>
<p>Role yang masih dipakai pengguna tidak dapat dihapus.</p>
<div>
<?= $this->partial('confirm-form', [
    'id' => 'hapus-role',
    'action' => '/role/' . $id . '/hapus',
    'csrf' => $csrf,
    'label' => 'Hapus role',
    'title' => 'Hapus role ' . $role['name'] . '?',
    'body' => 'Role akan dihapus permanen beserta pengaturan izinnya. Tindakan ini tidak dapat dibatalkan.',
]) ?>
</div>
</section>
<?php endif; ?>
