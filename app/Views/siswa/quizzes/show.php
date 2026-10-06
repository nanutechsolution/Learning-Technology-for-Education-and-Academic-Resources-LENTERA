<?php
$fmt = static fn ($d): string => empty($d) ? '-' : date('d/m/Y H:i', strtotime((string) $d));

$stateLabel = [
    'submitted'   => ['class' => 'success',           'text' => 'Selesai'],
    'in_progress' => ['class' => 'warning text-dark', 'text' => 'Sedang dikerjakan'],
    'open'        => ['class' => 'primary',           'text' => 'Dapat dikerjakan'],
    'upcoming'    => ['class' => 'info',              'text' => 'Belum dibuka'],
    'closed'      => ['class' => 'dark',              'text' => 'Ditutup'],
];
$st = $stateLabel[$state] ?? $stateLabel['open'];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<a href="<?= site_url('siswa/courses/' . (int) $quiz['course_id'] . '/quizzes') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Daftar Quiz
</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    <span class="badge text-bg-<?= $st['class'] ?>"><?= esc($st['text']) ?></span>
                    <span class="text-muted small">
                        <?= esc($quiz['course_title'] ?? '-') ?> &middot; <?= esc($quiz['subject_name'] ?? '-') ?>
                    </span>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3">
                        <span class="text-muted small d-block">Jumlah Soal</span>
                        <span class="fw-semibold"><?= (int) $quiz['question_count'] ?></span>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="text-muted small d-block">Total Poin</span>
                        <span class="fw-semibold"><?= (int) $quiz['total_points'] ?></span>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="text-muted small d-block">Durasi</span>
                        <span class="fw-semibold"><?= empty($quiz['duration_minutes']) ? 'Tanpa batas' : (int) $quiz['duration_minutes'] . ' menit' ?></span>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="text-muted small d-block">Percobaan</span>
                        <span class="fw-semibold">1 kali</span>
                    </div>
                    <div class="col-12 col-md-6">
                        <span class="text-muted small d-block">Waktu Mulai</span>
                        <span class="fw-semibold"><?= empty($quiz['start_at']) ? 'Langsung dibuka' : esc($fmt($quiz['start_at'])) ?></span>
                    </div>
                    <div class="col-12 col-md-6">
                        <span class="text-muted small d-block">Waktu Berakhir</span>
                        <span class="fw-semibold"><?= empty($quiz['end_at']) ? 'Tanpa batas akhir' : esc($fmt($quiz['end_at'])) ?></span>
                    </div>
                </div>

                <h2 class="h6 text-muted text-uppercase">Instruksi</h2>
                <?php if (! empty($quiz['description'])): ?>
                    <div><?= nl2br(esc($quiz['description'])) ?></div>
                <?php else: ?>
                    <div class="text-muted">Tidak ada instruksi tertulis.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <?php if ($state === 'submitted'): ?>
            <div class="card shadow-sm border-success">
                <div class="card-header bg-white fw-semibold text-success">
                    <i class="bi bi-patch-check me-1"></i>Hasil Quiz
                </div>
                <div class="card-body">
                    <div class="text-muted small">Nilai</div>
                    <div class="fs-2 fw-semibold"><?= esc(number_format((float) ($attempt['score'] ?? 0), 2, ',', '.')) ?></div>
                    <?php if ($points !== null): ?>
                        <div class="small text-muted">
                            Poin diperoleh: <?= (int) $points['earned'] ?> dari <?= (int) $points['total'] ?>
                        </div>
                    <?php endif; ?>
                    <div class="small text-muted mt-2">
                        Mulai <?= esc($fmt($attempt['started_at'] ?? null)) ?>
                        &middot; Dikumpulkan <?= esc($fmt($attempt['submitted_at'] ?? null)) ?>
                    </div>
                    <?php if (! $reviewAllowed): ?>
                        <div class="alert alert-info small mt-3 mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Pembahasan jawaban akan tersedia setelah quiz ditutup pada <?= esc($fmt($quiz['end_at'])) ?>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($state === 'in_progress'): ?>
            <div class="card shadow-sm border-warning">
                <div class="card-body">
                    <div class="fw-semibold mb-1">Quiz sedang Anda kerjakan</div>
                    <div class="small text-muted mb-3">
                        Dimulai <?= esc($fmt($attempt['started_at'] ?? null)) ?>. Anda hanya punya satu kesempatan.
                    </div>
                    <a href="<?= site_url('siswa/quizzes/' . (int) $quiz['id'] . '/take') ?>" class="btn btn-warning">
                        <i class="bi bi-pencil-square me-1"></i>Lanjutkan Mengerjakan
                    </a>
                </div>
            </div>

        <?php elseif ($state === 'open'): ?>
            <div class="card shadow-sm border-primary">
                <div class="card-body">
                    <div class="fw-semibold mb-1">Siap mengerjakan?</div>
                    <ul class="small text-muted ps-3">
                        <li>Quiz hanya dapat dikerjakan satu kali dan tidak dapat diulang.</li>
                        <?php if (! empty($quiz['duration_minutes'])): ?>
                            <li>Waktu <?= (int) $quiz['duration_minutes'] ?> menit dihitung sejak Anda menekan tombol mulai.</li>
                        <?php endif; ?>
                        <?php if (! empty($quiz['end_at'])): ?>
                            <li>Quiz otomatis diselesaikan pada <?= esc($fmt($quiz['end_at'])) ?> jika waktu tersebut lebih dulu.</li>
                        <?php endif; ?>
                        <li>Soal yang belum dijawab bernilai 0.</li>
                    </ul>
                    <?php if ($canStart): ?>
                        <form method="post" action="<?= site_url('siswa/quizzes/' . (int) $quiz['id'] . '/start') ?>"
                            data-confirm="Mulai quiz sekarang? Waktu pengerjaan akan mulai dihitung dan quiz tidak dapat diulang.">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-play-circle me-1"></i>Mulai Quiz
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-warning small mb-0">Quiz ini belum memiliki pertanyaan.</div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($state === 'upcoming'): ?>
            <div class="alert alert-info mb-0">
                <i class="bi bi-hourglass-split me-1"></i>
                Quiz belum dibuka. Quiz dapat dikerjakan mulai <?= esc($fmt($quiz['start_at'])) ?>.
            </div>

        <?php else: ?>
            <div class="alert alert-secondary mb-0">
                <i class="bi bi-lock me-1"></i>
                Quiz sudah ditutup dan Anda tidak mengerjakannya.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($state === 'submitted' && $reviewAllowed && ! empty($review)): ?>
    <div class="card shadow-sm mt-3">
        <div class="card-header bg-white fw-semibold">Pembahasan Jawaban</div>
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
                            <span class="badge text-bg-light border"><?= (int) $qs['points'] ?> poin</span>
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
                                    <span class="small">(jawaban Anda)</span>
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
    </div>
<?php endif; ?>

<?= $this->endSection() ?>