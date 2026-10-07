<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<p class="text-muted">
    Selamat datang, <strong><?= esc(auth_name()) ?></strong>.
</p>

<?php if (! $hasProfile): ?>
    <div class="alert alert-warning">
        Akun Anda belum terhubung dengan data guru. Hubungi admin.
    </div>
<?php else: ?>
    <?= view('announcements/_widget', [
        'items'        => $announcements ?? [],
        'detailPrefix' => 'guru/announcements',
        'allUrl'       => 'guru/announcements',
    ]) ?>

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center me-3" style="width:56px;height:56px;">
                        <i class="bi bi-collection fs-3"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Course Diampu</div>
                        <div class="fs-3 fw-semibold"><?= (int) $courseCount ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center me-3" style="width:56px;height:56px;">
                        <i class="bi bi-door-open fs-3"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Kelas Diampu</div>
                        <div class="fs-3 fw-semibold"><?= (int) $classCount ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center me-3" style="width:56px;height:56px;">
                        <i class="bi bi-journal-text fs-3"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Jumlah Materi</div>
                        <div class="fs-3 fw-semibold"><?= (int) $materialStats['total'] ?></div>
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
                        <div class="text-muted small">Materi Dipublish</div>
                        <div class="fs-3 fw-semibold"><?= (int) $materialStats['published'] ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded bg-secondary bg-opacity-10 text-secondary d-flex align-items-center justify-content-center me-3" style="width:56px;height:56px;">
                        <i class="bi bi-pencil-square fs-3"></i>
                    </div>
                    <div>
                        <div class="text-muted small">Materi Draft</div>
                        <div class="fs-3 fw-semibold"><?= (int) $materialStats['draft'] ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    $assignmentCards = [
        ['label' => 'Jumlah Tugas',      'value' => $assignmentStats['total'],     'color' => 'primary',   'icon' => 'bi-clipboard-check'],
        ['label' => 'Tugas Dipublish',   'value' => $assignmentStats['published'], 'color' => 'success',   'icon' => 'bi-check2-square'],
        ['label' => 'Tugas Draft',       'value' => $assignmentStats['draft'],     'color' => 'secondary', 'icon' => 'bi-pencil-square'],
        ['label' => 'Perlu Dinilai',     'value' => $assignmentStats['pending'],   'color' => 'info',      'icon' => 'bi-hourglass-split'],
    ];
    ?>

    <div class="row g-3 mb-3">
        <?php foreach ($assignmentCards as $card): ?>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded bg-<?= $card['color'] ?> bg-opacity-10 text-<?= $card['color'] ?> d-flex align-items-center justify-content-center me-3" style="width:56px;height:56px;">
                            <i class="bi <?= $card['icon'] ?> fs-3"></i>
                        </div>
                        <div>
                            <div class="text-muted small"><?= esc($card['label']) ?></div>
                            <div class="fs-3 fw-semibold"><?= (int) $card['value'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php
    $quizCards = [
        ['label' => 'Jumlah Quiz',        'value' => $quizStats['total'],     'color' => 'primary',   'icon' => 'bi-patch-question'],
        ['label' => 'Quiz Dipublish',     'value' => $quizStats['published'], 'color' => 'success',   'icon' => 'bi-check2-square'],
        ['label' => 'Quiz Draft',         'value' => $quizStats['draft'],     'color' => 'secondary', 'icon' => 'bi-pencil-square'],
        ['label' => 'Pengerjaan Selesai', 'value' => $quizStats['submitted'], 'color' => 'info',      'icon' => 'bi-bar-chart'],
    ];
    ?>

    <div class="row g-3 mb-4">
        <?php foreach ($quizCards as $card): ?>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="rounded bg-<?= $card['color'] ?> bg-opacity-10 text-<?= $card['color'] ?> d-flex align-items-center justify-content-center me-3" style="width:56px;height:56px;">
                            <i class="bi <?= $card['icon'] ?> fs-3"></i>
                        </div>
                        <div>
                            <div class="text-muted small"><?= esc($card['label']) ?></div>
                            <div class="fs-3 fw-semibold"><?= (int) $card['value'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

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
                        <th>Tugas</th>
                        <th>Quiz</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($courses)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">Belum ada course.</td></tr>
                    <?php else: ?>
                        <?php foreach ($courses as $c): ?>
                            <?php
                            $ac = $assignmentByCourse[(int) $c['id']] ?? ['total' => 0, 'published' => 0, 'pending' => 0];
                            $qc = $quizByCourse[(int) $c['id']] ?? ['total' => 0, 'published' => 0, 'submitted' => 0];
                            ?>
                            <tr>
                                <td><?= esc($c['title']) ?></td>
                                <td><?= esc($subjectNames[$c['subject_id']] ?? '-') ?></td>
                                <td>
                                    <span class="badge text-bg-<?= esc($badges[$c['status']] ?? 'secondary', 'attr') ?>">
                                        <?= esc(ucfirst($c['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-nowrap">
                                    <span class="fw-semibold"><?= (int) $ac['total'] ?></span>
                                    <?php if ((int) $ac['pending'] > 0): ?>
                                        <span class="badge text-bg-info ms-1"><?= (int) $ac['pending'] ?> perlu dinilai</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <span class="fw-semibold"><?= (int) $qc['total'] ?></span>
                                    <?php if ((int) $qc['submitted'] > 0): ?>
                                        <span class="badge text-bg-success ms-1"><?= (int) $qc['submitted'] ?> pengerjaan selesai</span>
                                    <?php endif; ?>
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
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>