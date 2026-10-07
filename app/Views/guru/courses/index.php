<?php
$statusClass = ['draft' => 'secondary', 'active' => 'success', 'archived' => 'dark'];
$statusLabel = ['draft' => 'Draft', 'active' => 'Aktif', 'archived' => 'Diarsipkan'];

// Ringkasan jumlah (semua nilai integer, aman tanpa esc).
$counts = static fn(int $total, int $published, int $draft): string => '<span class="fw-semibold">' . $total . '</span>'
    . ' <span class="badge text-bg-success ms-1">' . $published . ' publish</span>'
    . ' <span class="badge text-bg-secondary">' . $draft . ' draft</span>';

// Tombol aksi; $block = true untuk tampilan kartu (tombol sama lebar).
$actions = static function (int $id, bool $block): string {
    $cls = $block ? ' flex-fill' : '';

    return '<a href="' . site_url('guru/courses/' . $id . '/materials') . '" class="btn btn-sm btn-primary' . $cls . '"><i class="bi bi-journal-text me-1"></i>Materi</a>'
        . '<a href="' . site_url('guru/courses/' . $id . '/assignments') . '" class="btn btn-sm btn-outline-primary' . $cls . '"><i class="bi bi-clipboard-check me-1"></i>Tugas</a>'
        . '<a href="' . site_url('guru/courses/' . $id . '/quizzes') . '" class="btn btn-sm btn-outline-primary' . $cls . '"><i class="bi bi-patch-question me-1"></i>Quiz</a>';
};
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

    <!-- Layar besar (lg ke atas): tabel -->
    <div class="card shadow-sm d-none d-lg-block">
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
                            <td><?= $counts((int) $c['material_total'], (int) $c['material_published'], (int) $c['material_draft']) ?></td>
                            <td><?= $counts((int) $c['assignment_total'], (int) $c['assignment_published'], (int) $c['assignment_draft']) ?></td>
                            <td><?= $counts((int) $c['quiz_total'], (int) $c['quiz_published'], (int) $c['quiz_draft']) ?></td>
                            <td>
                                <span class="badge text-bg-<?= esc($statusClass[$c['status']] ?? 'secondary', 'attr') ?>">
                                    <?= esc($statusLabel[$c['status']] ?? $c['status']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex flex-wrap justify-content-end gap-1">
                                    <?= $actions((int) $c['id'], false) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Layar kecil (di bawah lg): kartu per course -->
    <div class="d-lg-none">
        <div class="row g-3">
            <?php foreach ($courses as $c): ?>
                <div class="col-12 col-md-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div class="min-w-0">
                                    <div class="fw-semibold text-break"><?= esc($c['title']) ?></div>
                                    <div class="small text-muted"><?= esc($c['subject_name'] ?? '-') ?></div>
                                </div>
                                <span class="badge text-bg-<?= esc($statusClass[$c['status']] ?? 'secondary', 'attr') ?> flex-shrink-0">
                                    <?= esc($statusLabel[$c['status']] ?? $c['status']) ?>
                                </span>
                            </div>

                            <div class="small text-muted mb-3">
                                <i class="bi bi-door-open me-1"></i><?= esc($c['class_name'] ?? '-') ?>
                                &middot; <i class="bi bi-calendar3 me-1"></i><?= esc($c['academic_year_name'] ?? '-') ?>
                                &middot; <i class="bi bi-people me-1"></i><?= (int) ($c['student_count'] ?? 0) ?> siswa
                            </div>

                            <dl class="row small mb-3 gy-1">
                                <dt class="col-4 fw-normal text-muted">Materi</dt>
                                <dd class="col-8 mb-0"><?= $counts((int) $c['material_total'], (int) $c['material_published'], (int) $c['material_draft']) ?></dd>
                                <dt class="col-4 fw-normal text-muted">Tugas</dt>
                                <dd class="col-8 mb-0"><?= $counts((int) $c['assignment_total'], (int) $c['assignment_published'], (int) $c['assignment_draft']) ?></dd>
                                <dt class="col-4 fw-normal text-muted">Quiz</dt>
                                <dd class="col-8 mb-0"><?= $counts((int) $c['quiz_total'], (int) $c['quiz_published'], (int) $c['quiz_draft']) ?></dd>
                            </dl>

                            <div class="d-flex flex-wrap gap-1">
                                <?= $actions((int) $c['id'], true) ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

<?php endif; ?>

<?= $this->endSection() ?>