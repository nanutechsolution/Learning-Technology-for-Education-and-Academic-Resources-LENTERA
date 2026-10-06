<?php
$fmt = static fn ($d): string => empty($d) ? '-' : date('d/m/Y H:i', strtotime((string) $d));

$seconds  = max(strtotime((string) $attempt['submitted_at']) - strtotime((string) $attempt['started_at']), 0);
$lamaText = intdiv($seconds, 60) . ' mnt ' . ($seconds % 60) . ' dtk';

$correctCount = 0;
foreach ($review as $qs) {
    if ($qs['is_correct'] === true) {
        $correctCount++;
    }
}
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<a href="<?= site_url('guru/quizzes/' . (int) $quiz['id'] . '/results') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Hasil Quiz
</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-7">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <span class="text-muted small d-block">Siswa</span>
                        <span class="fw-semibold"><?= esc($attempt['student_name']) ?></span>
                        <span class="text-muted small">(NIS <?= esc($attempt['student_nis'] ?? '-') ?>)</span>
                    </div>
                    <div class="col-12 col-md-6">
                        <span class="text-muted small d-block">Quiz</span>
                        <span class="fw-semibold"><?= esc($quiz['title']) ?></span>
                    </div>
                    <div class="col-12 col-md-4">
                        <span class="text-muted small d-block">Mulai</span>
                        <span class="fw-semibold"><?= esc($fmt($attempt['started_at'])) ?></span>
                    </div>
                    <div class="col-12 col-md-4">
                        <span class="text-muted small d-block">Dikumpulkan</span>
                        <span class="fw-semibold"><?= esc($fmt($attempt['submitted_at'])) ?></span>
                    </div>
                    <div class="col-12 col-md-4">
                        <span class="text-muted small d-block">Lama Pengerjaan</span>
                        <span class="fw-semibold"><?= esc($lamaText) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="card shadow-sm border-success h-100">
            <div class="card-body">
                <div class="text-muted small">Nilai</div>
                <div class="fs-2 fw-semibold"><?= esc(number_format((float) ($attempt['score'] ?? 0), 2, ',', '.')) ?></div>
                <div class="small text-muted">
                    Poin diperoleh: <?= (int) $points['earned'] ?> dari <?= (int) $points['total'] ?>
                    &middot; Benar <?= (int) $correctCount ?> dari <?= count($review) ?> soal
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Jawaban per Soal</div>
    <?php if (empty($review)): ?>
        <div class="card-body text-center text-muted">Quiz ini tidak memiliki pertanyaan.</div>
    <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($review as $qs): ?>
                <?php
                $answered = $qs['selected_option_id'] !== null;
                $ok       = $qs['is_correct'] === true;
                ?>
                <li class="list-group-item py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                        <div class="fw-semibold"><?= (int) $qs['order_number'] ?>. <?= nl2br(esc($qs['question_text'])) ?></div>
                        <div class="d-flex gap-1">
                            <span class="badge text-bg-light border"><?= $ok ? (int) $qs['points'] : 0 ?> / <?= (int) $qs['points'] ?> poin</span>
                            <?php if ($ok): ?>
                                <span class="badge text-bg-success">Benar</span>
                            <?php elseif ($answered): ?>
                                <span class="badge text-bg-danger">Salah</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Tidak dijawab</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 ms-md-3">
                        <?php foreach ($qs['options'] as $opt): ?>
                            <?php
                            $isCorrect  = (int) $opt['id'] === (int) $qs['correct_option_id'];
                            $isSelected = $answered && (int) $opt['id'] === (int) $qs['selected_option_id'];
                            $class      = $isCorrect ? 'text-success fw-semibold' : ($isSelected ? 'text-danger fw-semibold' : '');
                            ?>
                            <li class="d-flex gap-2 align-items-start py-1 <?= $class ?>">
                                <span class="badge text-bg-<?= $isCorrect ? 'success' : ($isSelected ? 'danger' : 'light border text-muted') ?>"><?= esc($opt['option_key']) ?></span>
                                <span><?= nl2br(esc($opt['option_text'])) ?></span>
                                <?php if ($isSelected): ?>
                                    <span class="small">(dipilih siswa)</span>
                                <?php endif; ?>
                                <?php if ($isCorrect): ?>
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