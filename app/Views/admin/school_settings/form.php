<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
// Nilai lama (mentah) lalu di-esc satu kali di tempat dipakai.
$val     = static fn(string $key): string => (string) old($key, $setting[$key] ?? '', false);
$hasLogo = ! empty($setting['logo_path']);
$logoUrl = base_url('admin/school-settings/logo') . '?v=' . rawurlencode((string) ($setting['updated_at'] ?? ''));
?>

<form method="post" action="<?= esc($action, 'attr') ?>" enctype="multipart/form-data" novalidate style="max-width:860px;">
    <?= csrf_field() ?>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-building me-1"></i> Identitas Sekolah</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="school_name" class="form-label">Nama Sekolah <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="school_name" name="school_name" maxlength="150" required
                        value="<?= esc($val('school_name'), 'attr') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="npsn" class="form-label">NPSN <span class="text-muted">(opsional)</span></label>
                    <input type="text" class="form-control" id="npsn" name="npsn" maxlength="8" inputmode="numeric"
                        placeholder="8 digit angka"
                        value="<?= esc($val('npsn'), 'attr') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-geo-alt me-1"></i> Alamat</div>
        <div class="card-body">
            <div class="mb-3">
                <label for="address" class="form-label">Alamat <span class="text-muted">(opsional)</span></label>
                <textarea class="form-control" id="address" name="address" rows="2" maxlength="255"><?= esc($val('address')) ?></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="village" class="form-label">Desa/Kelurahan</label>
                    <input type="text" class="form-control" id="village" name="village" maxlength="100"
                        value="<?= esc($val('village'), 'attr') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="district" class="form-label">Kecamatan</label>
                    <input type="text" class="form-control" id="district" name="district" maxlength="100"
                        value="<?= esc($val('district'), 'attr') ?>">
                </div>
                <div class="col-md-6 mb-3 mb-md-0">
                    <label for="regency" class="form-label">Kabupaten</label>
                    <input type="text" class="form-control" id="regency" name="regency" maxlength="100"
                        value="<?= esc($val('regency'), 'attr') ?>">
                </div>
                <div class="col-md-6">
                    <label for="province" class="form-label">Provinsi</label>
                    <input type="text" class="form-control" id="province" name="province" maxlength="100"
                        value="<?= esc($val('province'), 'attr') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-telephone me-1"></i> Kontak</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" maxlength="150"
                        value="<?= esc($val('email'), 'attr') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">Nomor Telepon</label>
                    <input type="tel" class="form-control" id="phone" name="phone" maxlength="30"
                        value="<?= esc($val('phone'), 'attr') ?>">
                </div>
                <div class="col-12">
                    <label for="website" class="form-label">Website</label>
                    <input type="text" class="form-control" id="website" name="website" maxlength="255" inputmode="url"
                        placeholder="https://contoh.sch.id"
                        value="<?= esc($val('website'), 'attr') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-person-badge me-1"></i> Kepala Sekolah</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-7 mb-3 mb-md-0">
                    <label for="principal_name" class="form-label">Nama Kepala Sekolah</label>
                    <input type="text" class="form-control" id="principal_name" name="principal_name" maxlength="150"
                        value="<?= esc($val('principal_name'), 'attr') ?>">
                </div>
                <div class="col-md-5">
                    <label for="principal_nip" class="form-label">NIP</label>
                    <input type="text" class="form-control" id="principal_nip" name="principal_nip" maxlength="30" inputmode="numeric"
                        value="<?= esc($val('principal_nip'), 'attr') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-image me-1"></i> Logo</div>
        <div class="card-body">
            <?php if ($hasLogo): ?>
                <div class="mb-3">
                    <div class="small text-muted mb-1">Logo saat ini</div>
                    <img src="<?= esc($logoUrl, 'attr') ?>" alt="Logo sekolah saat ini"
                        class="img-thumbnail" style="max-height:120px; max-width:100%;">
                </div>
            <?php else: ?>
                <p class="text-muted small mb-3">Belum ada logo. Aplikasi memakai ikon bawaan.</p>
            <?php endif; ?>

            <div class="mb-3">
                <label for="logo" class="form-label"><?= $hasLogo ? 'Ganti logo' : 'Unggah logo' ?> <span class="text-muted">(opsional)</span></label>
                <input type="file" class="form-control" id="logo" name="logo"
                    accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
                <div class="form-text">Format PNG, JPG, atau WEBP. Maksimal <?= esc($logoMax) ?>. Dimensi 16-4000 piksel. SVG tidak diterima.</div>
            </div>

            <?php if ($hasLogo): ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remove_logo" name="remove_logo" value="1"
                        <?= old('remove_logo') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="remove_logo">Hapus logo saat ini (diabaikan jika Anda mengunggah logo baru)</label>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
        <a href="<?= base_url('admin/dashboard') ?>" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>

<?= $this->endSection() ?>