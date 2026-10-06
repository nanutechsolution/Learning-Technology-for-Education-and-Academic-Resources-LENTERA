<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
$isEdit = $teacher !== null;

if (old('name') !== null) {
    $isActive = old('is_active') !== null;
} else {
    $isActive = $isEdit ? ! empty($teacher['is_active']) : true;
}
?>

<div class="card shadow-sm" style="max-width:720px;">
    <div class="card-body">
        <form method="post" action="<?= esc($action, 'attr') ?>" novalidate autocomplete="off">
            <?= csrf_field() ?>

            <h2 class="h6 text-muted text-uppercase mb-3">Akun Login</h2>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Nama Lengkap</label>
                    <input type="text" class="form-control" id="name" name="name" maxlength="100" required
                        value="<?= old('name', $teacher['name'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" maxlength="50" required
                        value="<?= old('username', $teacher['username'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email <span class="text-muted">(opsional)</span></label>
                <input type="email" class="form-control" id="email" name="email" maxlength="100"
                    value="<?= old('email', $teacher['email'] ?? '') ?>">
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="password" class="form-label">
                        Password <?= $isEdit ? '<span class="text-muted">(kosongkan jika tidak diganti)</span>' : '' ?>
                    </label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" maxlength="72"
                            autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                        <button type="button" class="btn btn-outline-secondary" data-toggle-password="#password" aria-label="Tampilkan password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="form-text">Minimal 8 karakter.</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="password_confirm" class="form-label">Konfirmasi Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" maxlength="72"
                            autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                        <button type="button" class="btn btn-outline-secondary" data-toggle-password="#password_confirm" aria-label="Tampilkan konfirmasi password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                    <?= $isActive ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">Akun aktif (bisa login)</label>
            </div>

            <h2 class="h6 text-muted text-uppercase mb-3">Profil Guru</h2>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="nip" class="form-label">NIP <span class="text-muted">(opsional)</span></label>
                    <input type="text" class="form-control" id="nip" name="nip" maxlength="30"
                        value="<?= old('nip', $teacher['nip'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">Telepon <span class="text-muted">(opsional)</span></label>
                    <input type="text" class="form-control" id="phone" name="phone" maxlength="20"
                        value="<?= old('phone', $teacher['phone'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-4">
                <label for="address" class="form-label">Alamat <span class="text-muted">(opsional)</span></label>
                <textarea class="form-control" id="address" name="address" rows="2"><?= old('address', $teacher['address'] ?? '') ?></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
                <a href="<?= base_url('admin/teachers') ?>" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>