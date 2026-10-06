<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
    <a href="<?= base_url('admin/courses') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php $courseUrl = base_url('admin/courses/' . (int) $course['id'] . '/students'); ?>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="fw-semibold fs-5"><?= esc($course['title']) ?></div>
        <div class="text-muted small">
            <?= esc($course['subject_name'] ?? '-') ?> &middot;
            Guru: <?= esc($course['teacher_name'] ?? '-') ?> &middot;
            Kelas: <?= esc($course['class_name'] ?? '-') ?> &middot;
            <?= esc($course['academic_year_name'] ?? '-') ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Tambah Satu Siswa</div>
            <div class="card-body">
                <form method="post" action="<?= esc($courseUrl . '/add', 'attr') ?>">
                    <?= csrf_field() ?>
                    <div class="input-group">
                        <select class="form-select" name="student_id" required aria-label="Pilih siswa">
                            <option value="">-- Pilih siswa --</option>
                            <?php foreach ($candidates as $s): ?>
                                <option value="<?= (int) $s['id'] ?>">
                                    <?= esc($s['name']) ?> (<?= esc($s['nis']) ?><?= ! empty($s['class_name']) ? ', ' . esc($s['class_name']) : '' ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-person-plus"></i> Tambah</button>
                    </div>
                    <?php if (empty($candidates)): ?>
                        <div class="form-text">Semua siswa aktif sudah terdaftar di course ini.</div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Tambah Satu Kelas Sekaligus</div>
            <div class="card-body">
                <form method="post" action="<?= esc($courseUrl . '/add-class', 'attr') ?>"
                      data-confirm="Daftarkan semua siswa di kelas yang dipilih ke course ini?">
                    <?= csrf_field() ?>
                    <div class="input-group">
                        <select class="form-select" name="class_id" required aria-label="Pilih kelas">
                            <option value="">-- Pilih kelas --</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === (int) $course['class_id'] ? 'selected' : '' ?>>
                                    <?= esc($c['name']) ?> (<?= esc($c['academic_year_name'] ?? '-') ?>) - <?= (int) ($c['student_count'] ?? 0) ?> siswa
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-success"><i class="bi bi-people"></i> Tambah Kelas</button>
                    </div>
                    <div class="form-text">Siswa yang sudah terdaftar akan dilewati.</div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Siswa Terdaftar (<?= count($enrolled) ?>)</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:50px;">#</th>
                    <th>Nama</th>
                    <th>NIS</th>
                    <th>NISN</th>
                    <th>Kelas</th>
                    <th>Terdaftar</th>
                    <th class="text-end" style="width:130px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($enrolled)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">Belum ada siswa terdaftar.</td></tr>
                <?php else: ?>
                    <?php foreach ($enrolled as $i => $s): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td class="fw-semibold"><?= esc($s['name']) ?></td>
                            <td><?= esc($s['nis']) ?></td>
                            <td><?= esc($s['nisn'] ?? '-') ?: '-' ?></td>
                            <td><?= esc($s['class_name'] ?? '-') ?: '-' ?></td>
                            <td><?= ! empty($s['enrolled_at']) ? esc(date('d-m-Y', strtotime($s['enrolled_at']))) : '-' ?></td>
                            <td class="text-end">
                                <form method="post" action="<?= esc($courseUrl . '/' . (int) $s['student_id'] . '/remove', 'attr') ?>"
                                      class="d-inline" data-confirm="Keluarkan <?= esc($s['name'], 'attr') ?> dari course ini?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-person-dash"></i> Keluarkan
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>