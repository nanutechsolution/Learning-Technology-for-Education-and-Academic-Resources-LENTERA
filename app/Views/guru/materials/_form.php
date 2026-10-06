<?php
// Variabel: $formAction, $cancelUrl, $submitLabel, $courses, $courseId, $material (array|null)
$material   = $material ?? null;
$formatSize = static function ($bytes): string {
    $bytes = (int) $bytes;
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    }

    return number_format(max($bytes, 0) / 1024, 1, ',', '.') . ' KB';
};
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
                    <label for="title" class="form-label">Judul <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="title" class="form-control" maxlength="255" required
                        value="<?= esc(old('title', $material['title'] ?? ''), 'attr') ?>">
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Deskripsi</label>
                    <textarea name="description" id="description" rows="2" class="form-control"><?= esc(old('description', $material['description'] ?? '')) ?></textarea>
                    <div class="form-text">Ringkasan singkat yang tampil di daftar materi.</div>
                </div>

                <div class="col-12">
                    <label for="content" class="form-label">Isi Materi</label>
                    <textarea name="content" id="content" rows="10" class="form-control"><?= esc(old('content', $material['content'] ?? '')) ?></textarea>
                    <div class="form-text">Teks biasa. Baris baru akan dipertahankan saat ditampilkan ke siswa.</div>
                </div>

                <div class="col-12">
                    <label for="material_file" class="form-label">File Materi</label>

                    <?php if (! empty($material['file_path'])): ?>
                        <div class="alert alert-light border py-2 small mb-2">
                            <i class="bi bi-paperclip me-1"></i>
                            File saat ini: <strong><?= esc($material['file_name'] ?? 'File materi') ?></strong>
                            <?php if (! empty($material['file_size'])): ?>
                                (<?= esc($formatSize($material['file_size'])) ?>)
                            <?php endif; ?>
                            &middot; pilih file baru di bawah untuk menggantinya.
                        </div>
                    <?php endif; ?>

                    <input type="file" name="material_file" id="material_file" class="form-control"
                        accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx">
                    <div class="form-text">PDF, DOC, DOCX, PPT, PPTX, XLS, atau XLSX. Maksimal 10 MB.</div>
                </div>

                <div class="col-12 col-lg-8">
                    <label for="video_url" class="form-label">Video URL (YouTube)</label>
                    <input type="url" name="video_url" id="video_url" class="form-control" maxlength="500"
                        placeholder="https://www.youtube.com/watch?v=..."
                        value="<?= esc(old('video_url', $material['video_url'] ?? ''), 'attr') ?>">
                    <div class="form-text">Hanya tautan YouTube (youtube.com/watch?v=... atau youtu.be/...). Video tidak diunggah ke server.</div>
                </div>

                <div class="col-12 col-lg-4">
                    <label class="form-label d-block">Status</label>
                    <?php $statusValue = (string) old('is_published', (string) ($material['is_published'] ?? '0')); ?>
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
                    <div class="form-text">Siswa hanya melihat materi yang berstatus Published.</div>
                </div>
            </div>

            <div class="alert alert-light border small mt-3 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Minimal salah satu dari isi materi, file, atau video harus diisi.
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