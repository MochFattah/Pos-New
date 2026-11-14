<?php
/**
 * @var App\Core\View $this
 * @var array<string, mixed> $target
 * @var list<array<string, mixed>> $roles
 * @var list<array<string, mixed>> $branches
 * @var bool $isSelf
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 * @var string $csrf
 * @var string|null $notice
 * @var string|null $error
 */
$id = (int) $target['id'];
$isOwner = (int) $target['is_owner'] === 1;
$status = (string) $target['status'];
$statusLabels = ['active' => 'Aktif', 'inactive' => 'Nonaktif', 'pending_verification' => 'Menunggu undangan diterima'];
?>
<h1>Ubah pengguna</h1>
<?php if ($notice !== null): ?>
<p role="status"><?= $this->e($notice) ?></p>
<?php endif; ?>
<?php if ($error !== null): ?>
<p role="alert"><?= $this->e($error) ?></p>
<?php endif; ?>
<?php if ($errors !== []): ?>
<p role="alert">Periksa kembali isian Anda.</p>
<?php endif; ?>
<p>Email: <?= $this->e($target['email']) ?> | Status: <?= $this->e($statusLabels[$status] ?? $status) ?><?= $isOwner ? ' | Owner' : '' ?></p>

<h2>Profil</h2>
<form method="post" action="/pengguna/<?= $this->e($id) ?>" novalidate>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<p>
<label for="name">Nama</label><br>
<input id="name" name="name" type="text" autocomplete="off" required value="<?= $this->e($old['name'] ?? '') ?>"<?php if (isset($errors['name'])): ?> aria-invalid="true" aria-describedby="name-error"<?php endif; ?>>
<?php if (isset($errors['name'])): ?>
<br><span id="name-error"><?= $this->e($errors['name']) ?></span>
<?php endif; ?>
</p>
<p>
<label for="phone">Nomor telepon</label><br>
<input id="phone" name="phone" type="tel" autocomplete="off" value="<?= $this->e($old['phone'] ?? '') ?>"<?php if (isset($errors['phone'])): ?> aria-invalid="true" aria-describedby="phone-error"<?php endif; ?>>
<?php if (isset($errors['phone'])): ?>
<br><span id="phone-error"><?= $this->e($errors['phone']) ?></span>
<?php endif; ?>
</p>
<p><button type="submit">Simpan profil</button></p>
</form>

<h2>Role</h2>
<form method="post" action="/pengguna/<?= $this->e($id) ?>/role">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<fieldset>
<legend>Role pengguna</legend>
<?php foreach ($roles as $role): ?>
<?php $locked = (bool) $role['locked']; $checked = (bool) $role['checked']; ?>
<p>
<input id="role-<?= $this->e($role['id']) ?>" type="checkbox"<?= $locked ? '' : ' name="role_ids[]"' ?> value="<?= $this->e($role['id']) ?>"<?= $checked ? ' checked' : '' ?><?= $locked ? ' disabled' : '' ?>>
<?php if ($locked && $checked): ?>
<input type="hidden" name="role_ids[]" value="<?= $this->e($role['id']) ?>">
<?php endif; ?>
<label for="role-<?= $this->e($role['id']) ?>"><?= $this->e($role['name']) ?></label>
<?php if ($locked): ?>
<span>(tidak dapat Anda ubah)</span>
<?php endif; ?>
</p>
<?php endforeach; ?>
</fieldset>
<p><button type="submit">Simpan role</button></p>
</form>

<h2>Akses cabang</h2>
<?php if ($isOwner): ?>
<p>Owner otomatis dapat mengakses semua cabang.</p>
<?php else: ?>
<form method="post" action="/pengguna/<?= $this->e($id) ?>/cabang">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<fieldset>
<legend>Cabang yang dapat diakses</legend>
<?php foreach ($branches as $branch): ?>
<?php $locked = (bool) $branch['locked']; $checked = (bool) $branch['checked']; ?>
<p>
<input id="branch-<?= $this->e($branch['id']) ?>" type="checkbox"<?= $locked ? '' : ' name="branch_ids[]"' ?> value="<?= $this->e($branch['id']) ?>"<?= $checked ? ' checked' : '' ?><?= $locked ? ' disabled' : '' ?>>
<?php if ($locked && $checked): ?>
<input type="hidden" name="branch_ids[]" value="<?= $this->e($branch['id']) ?>">
<?php endif; ?>
<label for="branch-<?= $this->e($branch['id']) ?>"><?= $this->e($branch['name']) ?> (<?= $this->e($branch['code']) ?>)</label>
<?php if ($locked): ?>
<span>(di luar akses Anda)</span>
<?php endif; ?>
</p>
<?php endforeach; ?>
</fieldset>
<p><button type="submit">Simpan akses cabang</button></p>
</form>
<?php endif; ?>

<h2>Status akun</h2>
<?php if ($status === 'pending_verification'): ?>
<form method="post" action="/pengguna/<?= $this->e($id) ?>/kirim-ulang-undangan">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<p><button type="submit">Kirim ulang undangan</button></p>
</form>
<?php endif; ?>
<?php if ($isOwner): ?>
<p>Akun Owner tidak dapat dinonaktifkan.</p>
<?php elseif ($isSelf): ?>
<p>Anda tidak dapat menonaktifkan akun Anda sendiri.</p>
<?php elseif ($status === 'inactive' && $target['email_verified_at'] !== null): ?>
<form method="post" action="/pengguna/<?= $this->e($id) ?>/aktifkan">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<p><button type="submit">Aktifkan pengguna</button></p>
</form>
<?php elseif ($status !== 'inactive'): ?>
<form method="post" action="/pengguna/<?= $this->e($id) ?>/nonaktifkan">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<p><button type="submit"><?= $status === 'pending_verification' ? 'Batalkan undangan' : 'Nonaktifkan pengguna' ?></button></p>
</form>
<?php endif; ?>
<p><a href="/pengguna">Kembali ke daftar pengguna</a></p>
