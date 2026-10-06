<?php
// Variabel: $formAction, $cancelUrl, $submitLabel, $course, $quiz (array|null), $locked (bool)
$quiz   = $quiz ?? null;
$locked = ! empty($locked);

$toLocal = static function ($value): string {
    return empty($value) ? '' : date('Y-m-d\TH:i', strtotime((string) $value));
};

$startValue = old('start_at');
if ($startValue === null) {
    $startValue = $toLocal($quiz['start_at'] ?? null);
}

$endValue = old('end_at');
if ($endValue === null) {
    $endValue = $toLocal($quiz['end_at'] ?? null);
}

$durationValue = old('duration_minutes');
if ($durationValue === null) {
    $durationValue = $quiz['duration_minutes'] ?? '';
}
?>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="<?= esc($formAction, 'attr') ?>" novalidate>
            <?= csrf_field() ?>

            <?php if ($locked): ?>
                <div class="alert alert-warning small">
                    <i class="bi bi-lock me-1"></i>
                    Quiz ini sudah dikerjakan siswa. Durasi dan jadwal dikunci agar batas waktu siswa tidak bergeser.
                    Judul dan instruksi masih dapat diubah.
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <label class="form-label">Course</label>
                    <input type="text" class="form-control" value="<?= esc($course['title'] . ' (' . ($course['class_name'] ?? '-') . ')', 'attr') ?>" disabled>
                </div>

                <div class="col-12 col-lg-6">
                    <label for="title" class="form-label">Judul Quiz <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="title" class="form-control" maxlength="255" required
                        value="<?= esc(old('title', $quiz['title'] ?? ''), 'attr') ?>">
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Instruksi Quiz</label>
                    <textarea name="description" id="description" rows="6" class="form-control"><?= esc(old('description', $quiz['description'] ?? '')) ?></textarea>
                    <div class="form-text">Teks biasa. Baris baru akan dipertahankan saat ditampilkan ke siswa.</div>
                </div>

                <div class="col-12 col-lg-4">
                    <label for="duration_minutes" class="form-label">Durasi (menit)</label>
                    <input type="number" name="duration_minutes" id="duration_minutes" class="form-control" min="1" max="1440" step="1"
                        value="<?= esc((string) $durationValue, 'attr') ?>" <?= $locked ? 'disabled' : '' ?>>
                    <div class="form-text">Kosongkan jika tanpa batas durasi. Maksimal 1440 menit. Dihitung sejak siswa mulai mengerjakan.</div>
                </div>

                <div class="col-12 col-lg-4">
                    <label for="start_at" class="form-label">Waktu Mulai</label>
                    <input type="datetime-local" name="start_at" id="start_at" class="form-control"
                        value="<?= esc((string) $startValue, 'attr') ?>" <?= $locked ? 'disabled' : '' ?>>
                    <div class="form-text">Kosongkan jika quiz langsung dapat dikerjakan setelah dipublikasikan.</div>
                </div>

                <div class="col-12 col-lg-4">
                    <label for="end_at" class="form-label">Waktu Berakhir</label>
                    <input type="datetime-local" name="end_at" id="end_at" class="form-control"
                        value="<?= esc((string) $endValue, 'attr') ?>" <?= $locked ? 'disabled' : '' ?>>
                    <div class="form-text">Kosongkan jika tanpa batas akhir. Setelah lewat, siswa tidak dapat mengerjakan.</div>
                </div>

                <div class="col-12">
                    <div class="form-text">
                        Quiz baru berstatus draft. Tambahkan pertanyaan, lalu publikasikan dari daftar quiz.
                        Quiz tanpa pertanyaan tidak dapat dipublikasikan.
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i><?= esc($submitLabel) ?>
                </button>
                <a href="<?= esc($cancelUrl, 'attr') ?>" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>