<?php
// Variabel: $formAction, $cancelUrl, $submitLabel, $announcement (array|null),
//           $courses (opsional; hanya form guru saat membuat), $courseId (opsional),
//           $courseLabel (opsional; teks cakupan tetap saat edit)
$announcement = $announcement ?? null;
$courses      = $courses ?? null;
?>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="<?= esc($formAction, 'attr') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="row g-3">
                <?php if (is_array($courses)): ?>
                    <div class="col-12 col-lg-6">
                        <label for="course_id" class="form-label">Course <span class="text-danger">*</span></label>
                        <select name="course_id" id="course_id" class="form-select" required>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"
                                    <?= (string) old('course_id', (string) ($courseId ?? '')) === (string) $c['id'] ? 'selected' : '' ?>>
                                    <?= esc($c['title']) ?> (<?= esc($c['class_name'] ?? '-') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Hanya peserta course ini yang dapat melihat pengumuman.</div>
                    </div>
                <?php elseif (! empty($courseLabel)): ?>
                    <div class="col-12 col-lg-6">
                        <label class="form-label">Cakupan</label>
                        <input type="text" class="form-control" value="<?= esc($courseLabel, 'attr') ?>" disabled>
                    </div>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-light border small mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Pengumuman sekolah ditujukan kepada seluruh pengguna (admin, guru, dan siswa).
                        </div>
                    </div>
                <?php endif; ?>

                <div class="col-12">
                    <label for="title" class="form-label">Judul <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="title" class="form-control" maxlength="255" required
                        value="<?= esc(old('title', $announcement['title'] ?? ''), 'attr') ?>">
                </div>

                <div class="col-12">
                    <label for="content" class="form-label">Isi Pengumuman <span class="text-danger">*</span></label>
                    <textarea name="content" id="content" rows="8" maxlength="10000" class="form-control" required><?= esc(old('content', $announcement['content'] ?? '')) ?></textarea>
                    <div class="form-text">Teks biasa (maksimal 10.000 karakter). Baris baru akan dipertahankan saat ditampilkan.</div>
                </div>

                <div class="col-12">
                    <label class="form-label d-block">Status</label>
                    <?php $statusValue = (string) old('is_published', (string) ($announcement['is_published'] ?? '0')); ?>
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
                    <div class="form-text">Siswa hanya melihat pengumuman yang berstatus Published.</div>
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
