<?php
$fmt        = static fn ($d): string => empty($d) ? '-' : date('d/m/Y H:i', strtotime((string) $d));
$formatSize = static function ($bytes): string {
    $bytes = (int) $bytes;
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    }

    return number_format(max($bytes, 0) / 1024, 1, ',', '.') . ' KB';
};
$graded = ! empty($submission['graded_at']);

$scoreValue = old('score');
if ($scoreValue === null) {
    $scoreValue = $submission['score'] !== null ? number_format((float) $submission['score'], 2, ',', '') : '';
}
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<a href="<?= site_url('guru/assignments/' . (int) $submission['assignment_id']) ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Kembali ke Tugas
</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="row g-2 small mb-3">
                    <div class="col-12 col-md-6"><span class="text-muted d-block">Siswa</span><span class="fw-semibold"><?= esc($submission['student_name']) ?></span> (NIS <?= esc($submission['student_nis'] ?? '-') ?>)</div>
                    <div class="col-12 col-md-6"><span class="text-muted d-block">Tugas</span><?= esc($submission['assignment_title']) ?></div>
                    <div class="col-12 col-md-6">
                        <span class="text-muted d-block">Waktu Dikumpulkan</span>
                        <?= esc($fmt($submission['submitted_at'])) ?>
                        <?php if ($isLate): ?>
                            <span class="badge text-bg-danger ms-1">Terlambat</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <span class="text-muted d-block">Batas Waktu</span>
                        <?= empty($submission['due_at']) ? 'Tanpa batas waktu' : esc($fmt($submission['due_at'])) ?>
                    </div>
                </div>

                <h2 class="h6 text-muted text-uppercase">Jawaban Teks</h2>
                <?php if (! empty($submission['answer_text'])): ?>
                    <div class="border rounded p-3 bg-light"><?= nl2br(esc($submission['answer_text'])) ?></div>
                <?php else: ?>
                    <div class="text-muted">Siswa tidak menulis jawaban teks.</div>
                <?php endif; ?>

                <h2 class="h6 text-muted text-uppercase mt-4">File Jawaban</h2>
                <?php if (! empty($submission['file_path'])): ?>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <i class="bi bi-file-earmark-text fs-4 text-secondary"></i>
                        <div>
                            <div class="fw-semibold"><?= esc($submission['file_name'] ?? 'File jawaban') ?></div>
                            <?php if (! empty($submission['file_size'])): ?>
                                <div class="small text-muted"><?= esc($formatSize($submission['file_size'])) ?></div>
                            <?php endif; ?>
                        </div>
                        <a href="<?= site_url('guru/submissions/' . (int) $submission['id'] . '/download') ?>" class="btn btn-sm btn-outline-primary ms-md-auto">
                            <i class="bi bi-download me-1"></i>Download
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-muted">Siswa tidak melampirkan file.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Penilaian</div>
            <div class="card-body">
                <?php if ($graded): ?>
                    <div class="alert alert-success py-2 small">
                        <i class="bi bi-check-circle me-1"></i>
                        Dinilai pada <?= esc($fmt($submission['graded_at'])) ?>. Anda masih dapat mengubah nilai di bawah.
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= site_url('guru/submissions/' . (int) $submission['id'] . '/grade') ?>" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="score" class="form-label">Nilai (0 - 100) <span class="text-danger">*</span></label>
                        <input type="text" inputmode="decimal" name="score" id="score" class="form-control" required
                            placeholder="Contoh: 85 atau 87,5"
                            value="<?= esc((string) $scoreValue, 'attr') ?>">
                        <div class="form-text">Boleh memakai koma desimal. Maksimal 2 angka di belakang koma.</div>
                    </div>

                    <div class="mb-3">
                        <label for="feedback" class="form-label">Feedback</label>
                        <textarea name="feedback" id="feedback" rows="6" class="form-control"><?= esc(old('feedback', $submission['feedback'] ?? '')) ?></textarea>
                        <div class="form-text">Catatan untuk siswa. Siswa dapat membacanya setelah nilai disimpan.</div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i><?= $graded ? 'Perbarui Nilai' : 'Simpan Nilai' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>