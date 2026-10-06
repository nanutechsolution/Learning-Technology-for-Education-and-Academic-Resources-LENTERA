<?php
$fmt        = static fn ($d): string => empty($d) ? '-' : date('d/m/Y H:i', strtotime((string) $d));
$formatSize = static function ($bytes): string {
    $bytes = (int) $bytes;
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    }

    return number_format(max($bytes, 0) / 1024, 1, ',', '.') . ' KB';
};
$hasSub  = $submission !== null && ! empty($submission['submitted_at']);
$graded  = $hasSub && ! empty($submission['graded_at']);
$hasFile = $hasSub && ! empty($submission['file_path']);
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<a href="<?= site_url('siswa/courses/' . (int) $assignment['course_id'] . '/assignments') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Daftar Tugas
</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small mb-3">
                    <?= esc($assignment['course_title'] ?? '-') ?> &middot; <?= esc($assignment['subject_name'] ?? '-') ?>
                    <?php if (! empty($assignment['published_at'])): ?>
                        &middot; Dipublikasikan <?= esc($fmt($assignment['published_at'])) ?>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <span class="text-muted small d-block">Batas Waktu Pengumpulan</span>
                    <?php if (empty($assignment['due_at'])): ?>
                        <span class="fw-semibold">Tanpa batas waktu</span>
                    <?php else: ?>
                        <span class="fw-semibold"><?= esc($fmt($assignment['due_at'])) ?></span>
                        <?php if ($isOverdue): ?>
                            <span class="badge text-bg-danger ms-1">Sudah lewat</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <h2 class="h6 text-muted text-uppercase">Petunjuk Tugas</h2>
                <?php if (! empty($assignment['description'])): ?>
                    <div><?= nl2br(esc($assignment['description'])) ?></div>
                <?php else: ?>
                    <div class="text-muted">Tidak ada petunjuk tertulis.</div>
                <?php endif; ?>

                <?php if (! empty($assignment['attachment_path'])): ?>
                    <h2 class="h6 text-muted text-uppercase mt-4">Lampiran Tugas</h2>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <i class="bi bi-file-earmark-text fs-4 text-secondary"></i>
                        <div>
                            <div class="fw-semibold"><?= esc($assignment['attachment_name'] ?? 'Lampiran') ?></div>
                            <?php if (! empty($assignment['attachment_size'])): ?>
                                <div class="small text-muted"><?= esc($formatSize($assignment['attachment_size'])) ?></div>
                            <?php endif; ?>
                        </div>
                        <a href="<?= site_url('siswa/assignments/' . (int) $assignment['id'] . '/download') ?>" class="btn btn-sm btn-primary ms-md-auto">
                            <i class="bi bi-download me-1"></i>Download
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Jawaban Saya</div>
            <div class="card-body">
                <?php if ($hasSub): ?>
                    <div class="small text-muted mb-2">
                        Dikumpulkan <?= esc($fmt($submission['submitted_at'])) ?>
                        <?php if (! empty($assignment['due_at']) && strtotime((string) $submission['submitted_at']) > strtotime((string) $assignment['due_at'])): ?>
                            <span class="badge text-bg-danger ms-1">Terlambat</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($hasFile): ?>
                    <div class="d-flex flex-wrap align-items-center gap-2 border rounded p-2 mb-3">
                        <i class="bi bi-paperclip fs-5 text-secondary"></i>
                        <div class="small">
                            <div class="fw-semibold"><?= esc($submission['file_name'] ?? 'File jawaban') ?></div>
                            <?php if (! empty($submission['file_size'])): ?>
                                <div class="text-muted"><?= esc($formatSize($submission['file_size'])) ?></div>
                            <?php endif; ?>
                        </div>
                        <a href="<?= site_url('siswa/assignments/' . (int) $assignment['id'] . '/submission/download') ?>" class="btn btn-sm btn-outline-primary ms-auto">
                            <i class="bi bi-download me-1"></i>Download
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($canSubmit): ?>
                    <form method="post" action="<?= site_url('siswa/assignments/' . (int) $assignment['id'] . '/submit') ?>" enctype="multipart/form-data" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label for="answer_text" class="form-label">Jawaban</label>
                            <textarea name="answer_text" id="answer_text" rows="8" class="form-control"><?= esc(old('answer_text', $submission['answer_text'] ?? '')) ?></textarea>
                            <div class="form-text">
                                <?= $hasSub ? 'Anda dapat memperbarui jawaban sampai batas waktu berakhir atau sampai dinilai guru.' : 'Tulis jawaban Anda, unggah file, atau keduanya.' ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="answer_file" class="form-label">File Jawaban</label>
                            <input type="file" name="answer_file" id="answer_file" class="form-control"
                                accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip">
                            <div class="form-text">
                                Opsional. PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, atau ZIP. Maksimal 10 MB.
                                <?= $hasFile ? 'Memilih file baru akan menggantikan file sebelumnya.' : '' ?>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i><?= $hasSub ? 'Perbarui Jawaban' : 'Kumpulkan Jawaban' ?>
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning small">
                        <i class="bi bi-lock me-1"></i><?= esc((string) $closedReason) ?>
                    </div>

                    <?php if ($hasSub): ?>
                        <?php if (! empty($submission['answer_text'])): ?>
                            <div class="border rounded p-3 bg-light"><?= nl2br(esc($submission['answer_text'])) ?></div>
                        <?php elseif (! $hasFile): ?>
                            <div class="text-muted small">Anda tidak menulis jawaban teks.</div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-muted small">Anda belum mengumpulkan jawaban untuk tugas ini.</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($graded): ?>
            <div class="card shadow-sm border-success">
                <div class="card-header bg-white fw-semibold text-success">
                    <i class="bi bi-patch-check me-1"></i>Hasil Penilaian
                </div>
                <div class="card-body">
                    <div class="text-muted small">Nilai</div>
                    <div class="fs-2 fw-semibold"><?= esc(number_format((float) $submission['score'], 2, ',', '.')) ?></div>
                    <div class="small text-muted mb-2">Dinilai pada <?= esc($fmt($submission['graded_at'])) ?></div>

                    <?php if (! empty($submission['feedback'])): ?>
                        <div class="text-muted small">Feedback guru</div>
                        <div><?= nl2br(esc($submission['feedback'])) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>