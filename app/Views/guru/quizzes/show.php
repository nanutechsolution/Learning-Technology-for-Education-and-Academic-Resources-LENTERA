<?php
$fmt       = static fn ($d): string => empty($d) ? '-' : date('d/m/Y H:i', strtotime((string) $d));
$published = (int) $quiz['is_published'] === 1;

$stateLabel = [
    \App\Models\QuizModel::STATE_DRAFT    => ['label' => 'Draft',       'class' => 'secondary'],
    \App\Models\QuizModel::STATE_UPCOMING => ['label' => 'Belum mulai', 'class' => 'info'],
    \App\Models\QuizModel::STATE_OPEN     => ['label' => 'Dibuka',      'class' => 'success'],
    \App\Models\QuizModel::STATE_CLOSED   => ['label' => 'Ditutup',     'class' => 'dark'],
];
$st = $stateLabel[$state] ?? $stateLabel[\App\Models\QuizModel::STATE_DRAFT];

$cards = [
    ['label' => 'Jumlah Soal', 'value' => (int) $quiz['question_count'], 'color' => 'primary', 'icon' => 'bi-patch-question'],
    ['label' => 'Total Poin',  'value' => (int) $quiz['total_points'],   'color' => 'success', 'icon' => 'bi-trophy'],
    ['label' => 'Durasi',      'value' => empty($quiz['duration_minutes']) ? 'Bebas' : (int) $quiz['duration_minutes'] . ' mnt', 'color' => 'secondary', 'icon' => 'bi-stopwatch'],
];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<div class="d-flex flex-wrap gap-1">
    <a href="<?= site_url('guru/courses/' . (int) $quiz['course_id'] . '/quizzes') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Daftar Quiz
    </a>
    <a href="<?= site_url('guru/quizzes/' . (int) $quiz['id'] . '/results') ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-bar-chart me-1"></i>Hasil
    </a>
    <a href="<?= site_url('guru/quizzes/' . (int) $quiz['id'] . '/edit') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-pencil me-1"></i>Edit
    </a>
    <form method="post" action="<?= site_url('guru/quizzes/' . (int) $quiz['id'] . '/toggle-publish') ?>" class="d-inline">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline-<?= $published ? 'warning' : 'success' ?>">
            <?= $published ? 'Jadikan Draft' : 'Publish' ?>
        </button>
    </form>
    <form method="post" action="<?= site_url('guru/quizzes/' . (int) $quiz['id'] . '/delete') ?>" class="d-inline"
        data-confirm="Hapus quiz ini beserta semua soal dan hasil pengerjaan siswa? Tindakan tidak dapat dibatalkan.">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
    </form>
</div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    <span class="badge text-bg-<?= esc($st['class'], 'attr') ?>"><?= esc($st['label']) ?></span>
                    <span class="text-muted small">
                        <?= esc($quiz['course_title'] ?? '-') ?> &middot; <?= esc($quiz['subject_name'] ?? '-') ?>
                    </span>
                    <?php if ($published && ! empty($quiz['published_at'])): ?>
                        <span class="text-muted small">&middot; Dipublish <?= esc($fmt($quiz['published_at'])) ?></span>
                    <?php endif; ?>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <span class="text-muted small d-block">Waktu Mulai</span>
                        <span class="fw-semibold"><?= empty($quiz['start_at']) ? 'Langsung dibuka' : esc($fmt($quiz['start_at'])) ?></span>
                    </div>
                    <div class="col-12 col-md-6">
                        <span class="text-muted small d-block">Waktu Berakhir</span>
                        <span class="fw-semibold"><?= empty($quiz['end_at']) ? 'Tanpa batas akhir' : esc($fmt($quiz['end_at'])) ?></span>
                    </div>
                </div>

                <?php if (! empty($quiz['description'])): ?>
                    <h2 class="h6 text-muted text-uppercase">Instruksi</h2>
                    <div><?= nl2br(esc($quiz['description'])) ?></div>
                <?php else: ?>
                    <div class="text-muted">Tidak ada instruksi tertulis.</div>
                <?php endif; ?>

                <?php if (! $published): ?>
                    <div class="small text-muted mt-3">Quiz masih draft, siswa belum dapat melihatnya.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="row g-3">
            <?php foreach ($cards as $card): ?>
                <div class="col-12 col-md-4 col-xl-12">
                    <div class="card shadow-sm h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="rounded bg-<?= $card['color'] ?> bg-opacity-10 text-<?= $card['color'] ?> d-flex align-items-center justify-content-center flex-shrink-0"
                                style="width:56px;height:56px">
                                <i class="bi <?= $card['icon'] ?> fs-3"></i>
                            </div>
                            <div>
                                <div class="text-muted small"><?= esc($card['label']) ?></div>
                                <div class="fs-4 fw-semibold"><?= esc((string) $card['value']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($locked): ?>
    <div class="alert alert-warning small">
        <i class="bi bi-lock me-1"></i>
        Quiz ini sudah dikerjakan siswa. Pertanyaan tidak dapat ditambah, diubah, atau dihapus agar penilaian tetap adil.
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-semibold">Daftar Pertanyaan</span>
        <?php if (! $locked): ?>
            <a href="<?= site_url('guru/quizzes/' . (int) $quiz['id'] . '/questions/create') ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Tambah Pertanyaan
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($questions)): ?>
        <div class="card-body text-center text-muted py-5">
            Belum ada pertanyaan. Quiz tanpa pertanyaan tidak dapat dipublikasikan.
        </div>
    <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($questions as $i => $qs): ?>
                <li class="list-group-item py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                        <div class="fw-semibold">
                            <?= (int) $qs['order_number'] ?>. <?= nl2br(esc($qs['question_text'])) ?>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-1">
                            <span class="badge text-bg-light border"><?= (int) $qs['points'] ?> poin</span>
                            <?php if (! $locked): ?>
                                <a href="<?= site_url('guru/questions/' . (int) $qs['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <form method="post" action="<?= site_url('guru/questions/' . (int) $qs['id'] . '/delete') ?>" class="d-inline"
                                    data-confirm="Hapus pertanyaan ini beserta opsinya? Tindakan tidak dapat dibatalkan.">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 ms-md-3">
                        <?php foreach ($qs['options'] as $opt): ?>
                            <?php $correct = (int) $opt['is_correct'] === 1; ?>
                            <li class="d-flex gap-2 align-items-start py-1 <?= $correct ? 'text-success fw-semibold' : '' ?>">
                                <span class="badge text-bg-<?= $correct ? 'success' : 'light border text-muted' ?>"><?= esc($opt['option_key']) ?></span>
                                <span><?= nl2br(esc($opt['option_text'])) ?></span>
                                <?php if ($correct): ?>
                                    <i class="bi bi-check-circle-fill" title="Jawaban benar"></i>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>