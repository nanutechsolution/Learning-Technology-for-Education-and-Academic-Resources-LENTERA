<?php
$statusClass = ['draft' => 'secondary', 'active' => 'success', 'archived' => 'dark'];
$statusLabel = ['draft' => 'Draft', 'active' => 'Aktif', 'archived' => 'Diarsipkan'];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php if (! $hasProfile): ?>
    <div class="alert alert-warning mb-0">
        <i class="bi bi-exclamation-circle me-1"></i>
        Akun Anda belum terhubung dengan profil guru. Hubungi admin.
    </div>
<?php elseif (empty($courses)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center text-muted py-5">Belum ada course yang Anda ampu.</div>
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Course</th>
                        <th>Kelas</th>
                        <th>Tahun Akademik</th>
                        <th class="text-center">Siswa</th>
                        <th>Materi</th>
                        <th>Tugas</th>
                        <th>Quiz</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $c): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= esc($c['title']) ?></div>
                                <div class="small text-muted"><?= esc($c['subject_name'] ?? '-') ?></div>
                            </td>
                            <td><?= esc($c['class_name'] ?? '-') ?></td>
                            <td><?= esc($c['academic_year_name'] ?? '-') ?></td>
                            <td class="text-center"><?= (int) ($c['student_count'] ?? 0) ?></td>
                            <td>
                                <span class="fw-semibold"><?= (int) $c['material_total'] ?></span>
                                <span class="badge text-bg-success ms-1"><?= (int) $c['material_published'] ?> publish</span>
                                <span class="badge text-bg-secondary"><?= (int) $c['material_draft'] ?> draft</span>
                            </td>
                            <td>
                                <span class="fw-semibold"><?= (int) $c['assignment_total'] ?></span>
                                <span class="badge text-bg-success ms-1"><?= (int) $c['assignment_published'] ?> publish</span>
                                <span class="badge text-bg-secondary"><?= (int) $c['assignment_draft'] ?> draft</span>
                            </td>
                            <td>
                                <span class="fw-semibold"><?= (int) $c['quiz_total'] ?></span>
                                <span class="badge text-bg-success ms-1"><?= (int) $c['quiz_published'] ?> publish</span>
                                <span class="badge text-bg-secondary"><?= (int) $c['quiz_draft'] ?> draft</span>
                            </td>
                            <td>
                                <span class="badge text-bg-<?= esc($statusClass[$c['status']] ?? 'secondary', 'attr') ?>">
                                    <?= esc($statusLabel[$c['status']] ?? $c['status']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex flex-wrap justify-content-end gap-1">
                                    <a href="<?= site_url('guru/courses/' . (int) $c['id'] . '/materials') ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-journal-text me-1"></i>Materi
                                    </a>
                                    <a href="<?= site_url('guru/courses/' . (int) $c['id'] . '/assignments') ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-clipboard-check me-1"></i>Tugas
                                    </a>
                                    <a href="<?= site_url('guru/courses/' . (int) $c['id'] . '/quizzes') ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-patch-question me-1"></i>Quiz
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>