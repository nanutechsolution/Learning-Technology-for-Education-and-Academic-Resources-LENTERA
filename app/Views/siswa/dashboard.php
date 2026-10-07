<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<p class="text-muted">
    Selamat datang, <strong><?= esc(auth_name()) ?></strong>.
</p>

<?= view('announcements/_widget', [
    'items'        => $announcements ?? [],
    'detailPrefix' => 'siswa/announcements',
    'allUrl'       => 'siswa/announcements',
]) ?>

<?php if (! $hasProfile): ?>
    <div class="alert alert-warning">
        Akun Anda belum terhubung dengan data siswa. Hubungi admin.
    </div>
<?php else: ?>
    <?php
    $percent = max(0, min(100, (float) $progress['percent']));
    $fmt     = static fn ($d): string => empty($d) ? 'Tanpa batas waktu' : date('d/m/Y H:i', strtotime((string) $d));
    ?>

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center me-3" style="width:56px;height:56px;">
                        <i class="bi bi-door-open fs-3"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Kelas</div>
                        <div class="fs-4 fw-semibold"><?= esc($className ?? 'Belum ada kelas') ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center me-3" style="width:56px;height:56px;">
                        <i class="bi bi-collection fs-3"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Course Diikuti</div>
                        <div class="fs-3 fw-semibold"><?= count($courses) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ((int) $progress['total'] === 0): ?>
        <div class="alert alert-light border mb-3">
            <i class="bi bi-journal-x me-1"></i> Belum ada materi pembelajaran.
        </div>
    <?php else: ?>
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center me-3" style="width:56px;height:56px;">
                            <i class="bi bi-journal-text fs-3"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Total Materi</div>
                            <div class="fs-3 fw-semibold"><?= (int) $progress['total'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center me-3" style="width:56px;height:56px;">
                            <i class="bi bi-check2-circle fs-3"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Materi Sudah Dibuka</div>
                            <div class="fs-3 fw-semibold"><?= (int) $progress['viewed'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-baseline">
                            <span class="text-muted small">Progress Materi</span>
                            <span class="fs-4 fw-semibold"><?= esc(number_format($percent, 0, ',', '.')) ?>%</span>
                        </div>
                        <div class="progress mt-2" role="progressbar" aria-valuenow="<?= esc((string) $percent, 'attr') ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar bg-success" style="width: <?= esc((string) $percent, 'attr') ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ((int) $assignmentStats['total'] === 0): ?>
        <div class="alert alert-light border mb-4">
            <i class="bi bi-clipboard-x me-1"></i> Belum ada tugas.
        </div>
    <?php else: ?>
        <?php
        $assignmentCards = [
            ['label' => 'Belum Dikumpulkan', 'value' => (string) (int) $assignmentStats['open'],      'color' => 'warning',   'icon' => 'bi-hourglass-split'],
            ['label' => 'Sudah Dikumpulkan', 'value' => (string) (int) $assignmentStats['submitted'], 'color' => 'info',      'icon' => 'bi-send-check'],
            [
                'label' => 'Sudah Dinilai',
                'value' => (string) (int) $assignmentStats['graded'],
                'color' => 'success',
                'icon'  => 'bi-patch-check',
                'note'  => $assignmentStats['average'] === null ? null : 'Rata-rata ' . number_format((float) $assignmentStats['average'], 2, ',', '.'),
            ],
            ['label' => 'Ditutup (Tidak Dikumpulkan)', 'value' => (string) (int) $assignmentStats['closed'], 'color' => 'danger', 'icon' => 'bi-lock'],
        ];
        ?>

        <div class="row g-3 mb-3">
            <?php foreach ($assignmentCards as $card): ?>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="rounded bg-<?= $card['color'] ?> bg-opacity-10 text-<?= $card['color'] ?> d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:56px;height:56px;">
                                <i class="bi <?= $card['icon'] ?> fs-3"></i>
                            </div>
                            <div>
                                <div class="text-muted small"><?= esc($card['label']) ?></div>
                                <div class="fs-3 fw-semibold"><?= esc($card['value']) ?></div>
                                <?php if (! empty($card['note'])): ?>
                                    <div class="small text-muted"><?= esc($card['note']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">Tugas yang Perlu Dikerjakan</div>
            <?php if (empty($upcoming)): ?>
                <div class="card-body text-center text-muted py-3">Tidak ada tugas yang menunggu. Semua sudah dikumpulkan atau ditutup.</div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($upcoming as $t): ?>
                        <a href="<?= site_url('siswa/assignments/' . (int) $t['id']) ?>"
                            class="list-group-item list-group-item-action d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <div class="fw-semibold"><?= esc($t['title']) ?></div>
                                <div class="small text-muted"><?= esc($t['course_title'] ?? '-') ?></div>
                            </div>
                            <div class="small text-nowrap">
                                <i class="bi bi-clock me-1"></i><?= esc($fmt($t['due_at'])) ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ((int) $quizStats['total'] === 0): ?>
        <div class="alert alert-light border mb-4">
            <i class="bi bi-patch-question me-1"></i> Belum ada quiz.
        </div>
    <?php else: ?>
        <?php
        $quizCards = [
            ['label' => 'Dapat Dikerjakan', 'value' => (string) (int) $quizStats['available'], 'color' => 'primary', 'icon' => 'bi-play-circle'],
            [
                'label' => 'Sudah Selesai',
                'value' => (string) (int) $quizStats['submitted'],
                'color' => 'success',
                'icon'  => 'bi-patch-check',
                'note'  => $quizStats['average'] === null ? null : 'Rata-rata ' . number_format((float) $quizStats['average'], 2, ',', '.'),
            ],
            ['label' => 'Belum Dibuka', 'value' => (string) (int) $quizStats['upcoming'], 'color' => 'info', 'icon' => 'bi-hourglass-split'],
            ['label' => 'Ditutup (Tidak Dikerjakan)', 'value' => (string) (int) $quizStats['closed'], 'color' => 'danger', 'icon' => 'bi-lock'],
        ];
        $fmtQuiz = static fn ($d): string => empty($d) ? 'Tanpa batas akhir' : date('d/m/Y H:i', strtotime((string) $d));
        ?>

        <div class="row g-3 mb-3">
            <?php foreach ($quizCards as $card): ?>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="rounded bg-<?= $card['color'] ?> bg-opacity-10 text-<?= $card['color'] ?> d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:56px;height:56px;">
                                <i class="bi <?= $card['icon'] ?> fs-3"></i>
                            </div>
                            <div>
                                <div class="text-muted small"><?= esc($card['label']) ?></div>
                                <div class="fs-3 fw-semibold"><?= esc($card['value']) ?></div>
                                <?php if (! empty($card['note'])): ?>
                                    <div class="small text-muted"><?= esc($card['note']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">Quiz yang Perlu Dikerjakan</div>
            <?php if (empty($quizUpcoming)): ?>
                <div class="card-body text-center text-muted py-3">Tidak ada quiz yang menunggu. Semua sudah selesai, belum dibuka, atau ditutup.</div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($quizUpcoming as $z): ?>
                        <a href="<?= site_url('siswa/quizzes/' . (int) $z['id']) ?>"
                            class="list-group-item list-group-item-action d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <div class="fw-semibold">
                                    <?= esc($z['title']) ?>
                                    <?php if (($z['state'] ?? '') === 'in_progress'): ?>
                                        <span class="badge text-bg-warning text-dark ms-1">Sedang dikerjakan</span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted"><?= esc($z['course_title'] ?? '-') ?></div>
                            </div>
                            <div class="small text-nowrap">
                                <i class="bi bi-clock me-1"></i><?= esc($fmtQuiz($z['end_at'])) ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php
    $badges = ['draft' => 'secondary', 'active' => 'success', 'archived' => 'dark'];
    ?>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Course Saya</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Judul</th>
                        <th>Mata Pelajaran</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($courses)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">Belum mengikuti course.</td></tr>
                    <?php else: ?>
                        <?php foreach ($courses as $c): ?>
                            <tr>
                                <td><?= esc($c['title']) ?></td>
                                <td><?= esc($subjectNames[$c['subject_id']] ?? '-') ?></td>
                                <td>
                                    <span class="badge text-bg-<?= esc($badges[$c['status']] ?? 'secondary', 'attr') ?>">
                                        <?= esc(ucfirst($c['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex flex-wrap justify-content-end gap-1">
                                        <a href="<?= site_url('siswa/courses/' . (int) $c['id']) ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-box-arrow-in-right me-1"></i>Buka
                                        </a>
                                        <a href="<?= site_url('siswa/courses/' . (int) $c['id'] . '/assignments') ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-clipboard-check me-1"></i>Tugas
                                        </a>
                                        <a href="<?= site_url('siswa/courses/' . (int) $c['id'] . '/quizzes') ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-patch-question me-1"></i>Quiz
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>