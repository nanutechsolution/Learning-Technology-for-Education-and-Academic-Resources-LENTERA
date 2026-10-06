<?php
// Variabel: $formAction, $cancelUrl, $submitLabel, $courses, $courseId, $assignment (array|null)
$assignment = $assignment ?? null;

$formatSize = static function ($bytes): string {
    $bytes = (int) $bytes;
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    }

    return number_format(max($bytes, 0) / 1024, 1, ',', '.') . ' KB';
};

$dueValue = old('due_at');
if ($dueValue === null) {
    $dueValue = ! empty($assignment['due_at']) ? date('Y-m-d\TH:i', strtotime((string) $assignment['due_at'])) : '';
}
?>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="<?= esc($formAction, 'attr') ?>" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <label for="course_id" class="form-label">Course <span class="text-danger">*</span></label>
                    <select name="course_id" id="course_id" class="form-select" required>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"
                                <?= (string) old('course_id', (string) $courseId) === (string) $c['id'] ? 'selected' : '' ?>>
                                <?= esc($c['title']) ?> (<?= esc($c['class_name'] ?? '-') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-lg-6">
                    <label for="title" class="form-label">Judul Tugas <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="title" class="form-control" maxlength="255" required
                        value="<?= esc(old('title', $assignment['title'] ?? ''), 'attr') ?>">
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Petunjuk / Deskripsi Tugas</label>
                    <textarea name="description" id="description" rows="8" class="form-control"><?= esc(old('description', $assignment['description'] ?? '')) ?></textarea>
                    <div class="form-text">Teks biasa. Baris baru akan dipertahankan saat ditampilkan ke siswa.</div>
                </div>

                <div class="col-12">
                    <label for="attachment" class="form-label">Lampiran Tugas</label>

                    <?php if (! empty($assignment['attachment_path'])): ?>
                        <div class="alert alert-light border py-2 small mb-2">
                            <i class="bi bi-paperclip me-1"></i>
                            Lampiran saat ini: <strong><?= esc($assignment['attachment_name'] ?? 'Lampiran') ?></strong>
                            <?php if (! empty($assignment['attachment_size'])): ?>
                                (<?= esc($formatSize($assignment['attachment_size'])) ?>)
                            <?php endif; ?>
                            &middot; pilih file baru di bawah untuk menggantinya.
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="remove_attachment" id="remove_attachment" value="1"
                                    <?= old('remove_attachment') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="remove_attachment">Hapus lampiran ini (jika tidak memilih file baru)</label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <input type="file" name="attachment" id="attachment" class="form-control"
                        accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip">
                    <div class="form-text">Opsional. PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, atau ZIP. Maksimal 10 MB.</div>
                </div>

                <div class="col-12 col-lg-6">
                    <label for="due_at" class="form-label">Batas Waktu Pengumpulan</label>
                    <input type="datetime-local" name="due_at" id="due_at" class="form-control"
                        value="<?= esc((string) $dueValue, 'attr') ?>">
                    <div class="form-text">Kosongkan jika tugas tidak memiliki batas waktu. Setelah batas waktu lewat, siswa tidak dapat mengumpulkan atau mengubah jawaban.</div>
                </div>

                <div class="col-12 col-lg-6">
                    <label class="form-label d-block">Status</label>
                    <?php $statusValue = (string) old('is_published', (string) ($assignment['is_published'] ?? '0')); ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="is_published" id="status_draft" value="0"
                            <?= $statusValue !== '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="status_draft">Draft</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="is_published" id="status_published" value="1"
                            <?= $statusValue === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="status_published">Published</label>
                    </div>
                    <div class="form-text">Siswa hanya melihat tugas yang berstatus Published.</div>
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