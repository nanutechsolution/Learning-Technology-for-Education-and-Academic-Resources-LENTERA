<?php
$formatSize = static function ($bytes): string {
    $bytes = (int) $bytes;
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    }

    return number_format(max($bytes, 0) / 1024, 1, ',', '.') . ' KB';
};
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<a href="<?= site_url('siswa/courses/' . (int) $material['course_id']) ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Kembali ke Course
</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="text-muted small mb-3">
            <?= esc($material['course_title'] ?? '-') ?> &middot; <?= esc($material['subject_name'] ?? '-') ?>
            <?php if (! empty($material['published_at'])): ?>
                &middot; Dipublikasikan <?= esc(date('d/m/Y', strtotime($material['published_at']))) ?>
            <?php endif; ?>
        </div>

        <?php if (! empty($material['description'])): ?>
            <p class="lead fs-6"><?= nl2br(esc($material['description'])) ?></p>
        <?php endif; ?>

        <?php if (! empty($material['content'])): ?>
            <div class="mt-3"><?= nl2br(esc($material['content'])) ?></div>
        <?php endif; ?>

        <?php if (! empty($material['file_path'])): ?>
            <h2 class="h6 text-muted text-uppercase mt-4">File Materi</h2>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <i class="bi bi-file-earmark-text fs-4 text-secondary"></i>
                <div>
                    <div class="fw-semibold"><?= esc($material['file_name'] ?? 'File materi') ?></div>
                    <?php if (! empty($material['file_size'])): ?>
                        <div class="small text-muted"><?= esc($formatSize($material['file_size'])) ?></div>
                    <?php endif; ?>
                </div>
                <a href="<?= site_url('siswa/materials/' . (int) $material['id'] . '/download') ?>" class="btn btn-sm btn-primary ms-md-auto">
                    <i class="bi bi-download me-1"></i>Download
                </a>
            </div>
        <?php endif; ?>

        <?php if (! empty($youtubeId)): ?>
            <h2 class="h6 text-muted text-uppercase mt-4">Video</h2>
            <div class="ratio ratio-16x9">
                <iframe src="https://www.youtube-nocookie.com/embed/<?= esc($youtubeId, 'attr') ?>"
                    title="Video materi" loading="lazy" allowfullscreen
                    referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>