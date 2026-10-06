<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
// Default centang: aktif untuk data baru; saat validasi gagal, ikuti input sebelumnya.
if (old('code') !== null) {
    $isActive = old('is_active') !== null;
} else {
    $isActive = $subject === null ? true : ! empty($subject['is_active']);
}
?>

<div class="card shadow-sm" style="max-width:640px;">
    <div class="card-body">
        <form method="post" action="<?= esc($action, 'attr') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="code" class="form-label">Kode</label>
                    <input type="text" class="form-control text-uppercase" id="code" name="code" maxlength="20"
                        placeholder="MTK" required
                        value="<?= old('code', $subject['code'] ?? '') ?>">
                </div>
                <div class="col-md-8 mb-3">
                    <label for="name" class="form-label">Nama Mata Pelajaran</label>
                    <input type="text" class="form-control" id="name" name="name" maxlength="100"
                        placeholder="Matematika" required
                        value="<?= old('name', $subject['name'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Deskripsi <span class="text-muted">(opsional)</span></label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= old('description', $subject['description'] ?? '') ?></textarea>
            </div>

            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                    <?= $isActive ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active">Aktif (bisa dipilih saat membuat course)</label>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
                <a href="<?= base_url('admin/subjects') ?>" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>