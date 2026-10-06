<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
$selYear    = (string) old('academic_year_id', $class['academic_year_id'] ?? ($defaultYear ?? ''));
$selGrade   = (string) old('grade', $class['grade'] ?? '');
$selTeacher = (string) old('homeroom_teacher_id', $class['homeroom_teacher_id'] ?? '');
?>

<div class="card shadow-sm" style="max-width:640px;">
    <div class="card-body">
        <form method="post" action="<?= esc($action, 'attr') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="academic_year_id" class="form-label">Tahun Akademik</label>
                <select class="form-select" id="academic_year_id" name="academic_year_id" required>
                    <option value="">-- Pilih tahun akademik --</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= (int) $y['id'] ?>" <?= $selYear === (string) $y['id'] ? 'selected' : '' ?>>
                            <?= esc($y['name']) ?><?= ! empty($y['is_active']) ? ' (aktif)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row">
                <div class="col-md-8 mb-3">
                    <label for="name" class="form-label">Nama Kelas</label>
                    <input type="text" class="form-control" id="name" name="name" maxlength="50"
                        placeholder="VII A" required value="<?= old('name', $class['name'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="grade" class="form-label">Tingkat</label>
                    <select class="form-select" id="grade" name="grade" required>
                        <option value="">-- Pilih --</option>
                        <?php foreach ([7, 8, 9] as $g): ?>
                            <option value="<?= $g ?>" <?= $selGrade === (string) $g ? 'selected' : '' ?>>Kelas <?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <label for="homeroom_teacher_id" class="form-label">Wali Kelas <span class="text-muted">(opsional)</span></label>
                <select class="form-select" id="homeroom_teacher_id" name="homeroom_teacher_id">
                    <option value="">-- Belum ditentukan --</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= $selTeacher === (string) $t['id'] ? 'selected' : '' ?>>
                            <?= esc($t['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
                <a href="<?= base_url('admin/classes') ?>" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>