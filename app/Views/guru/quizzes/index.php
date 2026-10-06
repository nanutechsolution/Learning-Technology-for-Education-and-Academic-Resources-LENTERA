<?php
$fmt = static fn ($d): string => empty($d) ? '-' : date('d/m/Y H:i', strtotime((string) $d));

$stateLabel = [
    \App\Models\QuizModel::STATE_DRAFT    => ['label' => 'Draft',       'class' => 'secondary'],
    \App\Models\QuizModel::STATE_UPCOMING => ['label' => 'Belum mulai', 'class' => 'info'],
    \App\Models\QuizModel::STATE_OPEN     => ['label' => 'Dibuka',      'class' => 'success'],
    \App\Models\QuizModel::STATE_CLOSED   => ['label' => 'Ditutup',     'class' => 'dark'],
];

$cards = [
    ['label' => 'Total Quiz', 'value' => $counts['total'],     'color' => 'primary',   'icon' => 'bi-patch-question'],
    ['label' => 'Dipublish',  'value' => $counts['published'], 'color' => 'success',   'icon' => 'bi-check2-circle'],
    ['label' => 'Draft',      'value' => $counts['draft'],     'color' => 'secondary', 'icon' => 'bi-pencil-square'],
];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<a href="<?= site_url('guru/courses') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Course Saya
</a>
<a href="<?= site_url('guru/courses/' . (int) $course['id'] . '/quizzes/create') ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Tambah Quiz
</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<p class="text-muted">
    <?= esc($course['subject_name'] ?? '-') ?> &middot; Kelas <?= esc($course['class_name'] ?? '-') ?>
    &middot; <?= esc($course['academic_year_name'] ?? '-') ?>
</p>

<div class="row g-3 mb-3">
    <?php foreach ($cards as $card): ?>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded bg-<?= $card['color'] ?> bg-opacity-10 text-<?= $card['color'] ?> d-flex align-items-center justify-content-center flex-shrink-0"
                        style="width:56px;height:56px">
                        <i class="bi <?= $card['icon'] ?> fs-3"></i>
                    </div>
                    <div>
                        <div class="text-muted small"><?= esc($card['label']) ?></div>
                        <div class="fs-4 fw-semibold"><?= (int) $card['value'] ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (empty($quizzes)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center text-muted py-5">
            Belum ada quiz pada course ini.
        </div>
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Judul</th>
                        <th>Jadwal</th>
                        <th class="text-center">Durasi</th>
                        <th class="text-center">Soal</th>
                        <th>Status</th>
                        <th class="text-center">Selesai</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quizzes as $q): ?>
                        <?php
                        $published = (int) $q['is_published'] === 1;
                        $state     = $stateLabel[\App\Models\QuizModel::accessState($q)];
                        ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('guru/quizzes/' . (int) $q['id']) ?>" class="fw-semibold text-decoration-none">
                                    <?= esc($q['title']) ?>
                                </a>
                                <?php if ($published && ! empty($q['published_at'])): ?>
                                    <div class="small text-muted">Dipublish <?= esc($fmt($q['published_at'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="small text-nowrap">
                                <?php if (empty($q['start_at']) && empty($q['end_at'])): ?>
                                    <span class="text-muted">Tanpa jadwal</span>
                                <?php else: ?>
                                    <div>Mulai: <?= esc($fmt($q['start_at'])) ?></div>
                                    <div>Berakhir: <?= esc($fmt($q['end_at'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center text-nowrap">
                                <?= empty($q['duration_minutes']) ? '<span class="text-muted">-</span>' : (int) $q['duration_minutes'] . ' menit' ?>
                            </td>
                            <td class="text-center text-nowrap">
                                <?= (int) $q['question_count'] ?> soal
                                <div class="small text-muted"><?= (int) $q['total_points'] ?> poin</div>
                            </td>
                            <td>
                                <span class="badge text-bg-<?= esc($state['class'], 'attr') ?>"><?= esc($state['label']) ?></span>
                            </td>
                            <td class="text-center"><?= (int) $q['submitted_total'] ?> / <?= (int) $q['student_total'] ?></td>
                            <td class="text-end">
                                <div class="d-flex flex-wrap justify-content-end gap-1">
                                    <a href="<?= site_url('guru/quizzes/' . (int) $q['id']) ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                                    <a href="<?= site_url('guru/quizzes/' . (int) $q['id'] . '/results') ?>" class="btn btn-sm btn-outline-success">Hasil</a>
                                    <a href="<?= site_url('guru/quizzes/' . (int) $q['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">Edit</a>

                                    <form method="post" action="<?= site_url('guru/quizzes/' . (int) $q['id'] . '/toggle-publish') ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-<?= $published ? 'warning' : 'success' ?>">
                                            <?= $published ? 'Jadikan Draft' : 'Publish' ?>
                                        </button>
                                    </form>

                                    <form method="post" action="<?= site_url('guru/quizzes/' . (int) $q['id'] . '/delete') ?>" class="d-inline"
                                        data-confirm="Hapus quiz ini beserta semua soal dan hasil pengerjaan siswa? Tindakan tidak dapat dibatalkan.">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                    </form>
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