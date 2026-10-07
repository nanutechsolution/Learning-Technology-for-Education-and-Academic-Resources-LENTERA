<?php
// Variabel: $announcement (array dengan relasi dari AnnouncementModel)
$isSchool    = $announcement['course_id'] === null;
$isPublished = (int) $announcement['is_published'] === 1;
$dateSource  = $isPublished && ! empty($announcement['published_at']) ? $announcement['published_at'] : null;
?>
<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <span class="badge text-bg-<?= $isSchool ? 'primary' : 'info' ?>"><?= $isSchool ? 'Sekolah' : 'Course' ?></span>
            <span class="badge text-bg-<?= $isPublished ? 'success' : 'secondary' ?>"><?= $isPublished ? 'Published' : 'Draft' ?></span>
        </div>

        <dl class="row small mb-3 gy-1">
            <dt class="col-4 col-md-2 fw-normal text-muted">Penulis</dt>
            <dd class="col-8 col-md-10 mb-0"><?= esc($announcement['author_name'] ?? 'Akun telah dihapus') ?></dd>

            <dt class="col-4 col-md-2 fw-normal text-muted">Cakupan</dt>
            <dd class="col-8 col-md-10 mb-0">
                <?php if ($isSchool): ?>
                    Seluruh sekolah
                <?php else: ?>
                    <?= esc($announcement['course_title'] ?? '-') ?>
                    <span class="text-muted">&middot; <?= esc($announcement['subject_name'] ?? '-') ?> &middot; Kelas <?= esc($announcement['class_name'] ?? '-') ?></span>
                <?php endif; ?>
            </dd>

            <dt class="col-4 col-md-2 fw-normal text-muted">Tanggal</dt>
            <dd class="col-8 col-md-10 mb-0">
                <?= $dateSource !== null
                    ? esc(date('d/m/Y H:i', strtotime($dateSource)))
                    : '<span class="text-muted">Belum dipublikasikan</span>' ?>
            </dd>
        </dl>

        <hr>

        <?php /* Isi adalah teks biasa: di-escape lalu baris baru dipertahankan (anti-XSS). */ ?>
        <div class="text-break"><?= nl2br(esc($announcement['content'])) ?></div>
    </div>
</div>
