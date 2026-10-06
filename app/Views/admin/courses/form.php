<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
$selSubject = (string) old('subject_id', $course['subject_id'] ?? '');
$selTeacher = (string) old('teacher_id', $course['teacher_id'] ?? '');
$selClass   = (string) old('class_id', $course['class_id'] ?? '');
$selYear    = (string) old('academic_year_id', $course['academic_year_id'] ?? ($defaultYear ?? ''));
$selStatus  = (string) old('status', $course['status'] ?? 'draft');
$statuses   = ['draft' => 'Draft', 'active' => 'Aktif', 'archived' => 'Diarsipkan'];
?>

<div class="card shadow-sm" style="max-width:720px;">
    <div class="card-body">
        <form method="post" action="<?= esc($action, 'attr') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="title" class="form-label">Judul Course</label>
                <input type="text" class="form-control" id="title" name="title" maxlength="150" required
                    placeholder="Matematika VII A" value="<?= old('title', $course['title'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Deskripsi <span class="text-muted">(opsional)</span></label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= old('description', $course['description'] ?? '') ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="subject_id" class="form-label">Mata Pelajaran</label>
                    <select class="form-select" id="subject_id" name="subject_id" required>
                        <option value="">-- Pilih mapel --</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= $selSubject === (string) $s['id'] ? 'selected' : '' ?>>
                                <?= esc($s['code']) ?> - <?= esc($s['name']) ?><?= empty($s['is_active']) ? ' (nonaktif)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="teacher_id" class="form-label">Guru Pengajar</label>
                    <select class="form-select" id="teacher_id" name="teacher_id" required>
                        <option value="">-- Pilih guru --</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" <?= $selTeacher === (string) $t['id'] ? 'selected' : '' ?>>
                                <?= esc($t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
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
                <div class="col-md-6 mb-3">
                    <label for="class_id" class="form-label">Kelas</label>
                    <select class="form-select" id="class_id" name="class_id" required>
                        <option value="">-- Pilih kelas --</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $selClass === (string) $c['id'] ? 'selected' : '' ?>>
                                <?= esc($c['name']) ?> (<?= esc($c['academic_year_name'] ?? '-') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Kelas harus berasal dari tahun akademik yang dipilih.</div>
                </div>
            </div>

            <div class="mb-4" style="max-width:260px;">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status" required>
                    <?php foreach ($statuses as $val => $label): ?>
                        <option value="<?= esc($val, 'attr') ?>" <?= $selStatus === $val ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
                <a href="<?= base_url('admin/courses') ?>" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>