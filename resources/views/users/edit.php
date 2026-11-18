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
?>
<div class="page-header">
<div>
<h1 class="page-header__title">Ubah pengguna</h1>
<p class="page-header__subtitle"><?= $this->e($target['email']) ?></p>
</div>
<div class="page-header__actions">
<a class="btn btn-secondary" href="/pengguna">Kembali ke daftar pengguna</a>
</div>
</div>
<?= $this->partial('flash', ['notice' => $notice, 'error' => $error]) ?>
<?= $this->partial('error-summary', ['errors' => $errors]) ?>
<dl class="meta-list">
<dt>Email</dt>
<dd><?= $this->e($target['email']) ?></dd>
<dt>Status</dt>
<dd><?= $this->partial('status-badge', ['status' => $status]) ?><?php if ($isOwner): ?> <span class="badge badge-info">Owner</span><?php endif; ?></dd>
</dl>

<form method="post" action="/pengguna/<?= $this->e($id) ?>" class="card stack max-w-2xl" novalidate>
<h2 class="card__title">Profil</h2>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<?= $this->partial('field', ['name' => 'name', 'label' => 'Nama', 'value' => (string) ($old['name'] ?? ''), 'autocomplete' => 'off', 'required' => true, 'errors' => $errors]) ?>
<?= $this->partial('field', ['name' => 'phone', 'label' => 'Nomor telepon', 'type' => 'tel', 'value' => (string) ($old['phone'] ?? ''), 'autocomplete' => 'off', 'inputmode' => 'tel', 'errors' => $errors]) ?>
<div><button type="submit" class="btn btn-primary">Simpan profil</button></div>
</form>

<form method="post" action="/pengguna/<?= $this->e($id) ?>/role" class="card stack max-w-2xl">
<h2 class="card__title">Role</h2>
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<fieldset class="fieldset stack-sm" id="role_ids" tabindex="-1">
<legend>Role pengguna</legend>
<?php foreach ($roles as $role): ?>
<?php $locked = (bool) $role['locked']; $checked = (bool) $role['checked']; ?>
<?php if ($locked && $checked): ?>
<input type="hidden" name="role_ids[]" value="<?= $this->e($role['id']) ?>">
<?php endif; ?>
<label class="check" for="role-<?= $this->e($role['id']) ?>">
<input id="role-<?= $this->e($role['id']) ?>" type="checkbox"<?= $locked ? '' : ' name="role_ids[]"' ?> value="<?= $this->e($role['id']) ?>"<?= $checked ? ' checked' : '' ?><?= $locked ? ' disabled' : '' ?>>
<span><?= $this->e($role['name']) ?><?php if ($locked): ?> <span class="field-hint">(tidak dapat Anda ubah)</span><?php endif; ?></span>
</label>
<?php endforeach; ?>
</fieldset>
<div><button type="submit" class="btn btn-primary">Simpan role</button></div>
</form>

<section class="card stack max-w-2xl" aria-labelledby="akses-cabang">
<h2 class="card__title" id="akses-cabang">Akses cabang</h2>
<?php if ($isOwner): ?>
<p id="branch_ids">Owner otomatis dapat mengakses semua cabang.</p>
<?php else: ?>
<form method="post" action="/pengguna/<?= $this->e($id) ?>/cabang" class="stack">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<fieldset class="fieldset stack-sm" id="branch_ids" tabindex="-1">
<legend>Cabang yang dapat diakses</legend>
<?php foreach ($branches as $branch): ?>
<?php $locked = (bool) $branch['locked']; $checked = (bool) $branch['checked']; ?>
<?php if ($locked && $checked): ?>
<input type="hidden" name="branch_ids[]" value="<?= $this->e($branch['id']) ?>">
<?php endif; ?>
<label class="check" for="branch-<?= $this->e($branch['id']) ?>">
<input id="branch-<?= $this->e($branch['id']) ?>" type="checkbox"<?= $locked ? '' : ' name="branch_ids[]"' ?> value="<?= $this->e($branch['id']) ?>"<?= $checked ? ' checked' : '' ?><?= $locked ? ' disabled' : '' ?>>
<span><?= $this->e($branch['name']) ?> (<?= $this->e($branch['code']) ?>)<?php if ($locked): ?> <span class="field-hint">(di luar akses Anda)</span><?php endif; ?></span>
</label>
<?php endforeach; ?>
</fieldset>
<div><button type="submit" class="btn btn-primary">Simpan akses cabang</button></div>
</form>
<?php endif; ?>
</section>

<section class="card stack max-w-2xl" id="status" tabindex="-1" aria-labelledby="status-akun">
<h2 class="card__title" id="status-akun">Status akun</h2>
<?php if ($status === 'pending_verification'): ?>
<form method="post" action="/pengguna/<?= $this->e($id) ?>/kirim-ulang-undangan">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<button type="submit" class="btn btn-secondary">Kirim ulang undangan</button>
</form>
<?php endif; ?>
<?php if ($isOwner): ?>
<p>Akun Owner tidak dapat dinonaktifkan.</p>
<?php elseif ($isSelf): ?>
<p>Anda tidak dapat menonaktifkan akun Anda sendiri.</p>
<?php elseif ($status === 'inactive' && $target['email_verified_at'] !== null): ?>
<form method="post" action="/pengguna/<?= $this->e($id) ?>/aktifkan">
<input type="hidden" name="_csrf" value="<?= $this->e($csrf) ?>">
<button type="submit" class="btn btn-secondary">Aktifkan pengguna</button>
</form>
<?php elseif ($status !== 'inactive'): ?>
<?php $pending = $status === 'pending_verification'; ?>
<div>
<?= $this->partial('confirm-form', [
    'id' => 'nonaktifkan-pengguna',
    'action' => '/pengguna/' . $id . '/nonaktifkan',
    'csrf' => $csrf,
    'label' => $pending ? 'Batalkan undangan' : 'Nonaktifkan pengguna',
    'title' => ($pending ? 'Batalkan undangan untuk ' : 'Nonaktifkan ') . $target['name'] . '?',
    'body' => $pending
        ? 'Tautan undangan tidak akan berlaku lagi. Anda dapat mengundang ulang kapan saja.'
        : 'Pengguna ini tidak dapat masuk lagi sampai diaktifkan kembali. Datanya tidak dihapus.',
]) ?>
</div>
<?php endif; ?>
</section>
