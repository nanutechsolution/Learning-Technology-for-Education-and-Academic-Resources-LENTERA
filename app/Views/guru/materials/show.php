<?php
$published  = (int) $material['is_published'] === 1;
$percent    = max(0, min(100, (float) ($stats['percent'] ?? 0)));
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
<div class="d-flex flex-wrap gap-1">
    <a href="<?= site_url('guru/courses/' . (int) $material['course_id'] . '/materials') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Daftar Materi
    </a>
    <a href="<?= site_url('guru/materials/' . (int) $material['id'] . '/edit') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-pencil me-1"></i>Edit
    </a>
    <form method="post" action="<?= site_url('guru/materials/' . (int) $material['id'] . '/toggle-publish') ?>" class="d-inline">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline-<?= $published ? 'warning' : 'success' ?>">
            <?= $published ? 'Jadikan Draft' : 'Publish' ?>
        </button>
    </form>
    <form method="post" action="<?= site_url('guru/materials/' . (int) $material['id'] . '/delete') ?>" class="d-inline"
        data-confirm="Hapus materi ini? Tindakan tidak dapat dibatalkan.">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
    </form>
</div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    <span class="badge text-bg-<?= $published ? 'success' : 'secondary' ?>"><?= $published ? 'Published' : 'Draft' ?></span>
                    <span class="text-muted small">
                        <?= esc($material['course_title'] ?? '-') ?> &middot; <?= esc($material['subject_name'] ?? '-') ?>
                    </span>
                    <?php if ($published && ! empty($material['published_at'])): ?>
                        <span class="text-muted small">&middot; Dipublish <?= esc(date('d/m/Y H:i', strtotime($material['published_at']))) ?></span>
                    <?php endif; ?>
                </div>

                <?php if (! empty($material['description'])): ?>
                    <p class="lead fs-6"><?= nl2br(esc($material['description'])) ?></p>
                <?php endif; ?>

                <?php if (! empty($material['content'])): ?>
                    <h2 class="h6 text-muted text-uppercase mt-4">Isi Materi</h2>
                    <div><?= nl2br(esc($material['content'])) ?></div>
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
                        <a href="<?= site_url('guru/materials/' . (int) $material['id'] . '/download') ?>" class="btn btn-sm btn-outline-primary ms-md-auto">
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
    </div>

    <div class="col-12 col-xl-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase">Statistik Akses</h2>
                <div class="fs-5 fw-semibold">
                    Sudah dibuka <?= (int) ($stats['viewed'] ?? 0) ?> siswa
                </div>
                <div class="text-muted small mb-2">
                    dari <?= (int) ($stats['total'] ?? 0) ?> siswa terdaftar
                    (<?= esc(number_format($percent, 1, ',', '.')) ?>%)
                </div>
                <div class="progress" role="progressbar" aria-valuenow="<?= esc((string) $percent, 'attr') ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-success" style="width: <?= esc((string) $percent, 'attr') ?>%"></div>
                </div>
                <?php if (! $published): ?>
                    <div class="small text-muted mt-2">Materi masih draft, siswa belum dapat melihatnya.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold">Daftar Siswa</div>
    <?php if (empty($students)): ?>
        <div class="card-body text-center text-muted">Belum ada siswa yang terdaftar pada course ini.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nama</th>
                        <th>NIS</th>
                        <th>Kelas</th>
                        <th>Waktu Dibuka</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s): ?>
                        <tr>
                            <td><?= esc($s['name']) ?></td>
                            <td><?= esc($s['nis'] ?? '-') ?></td>
                            <td><?= esc($s['class_name'] ?? '-') ?></td>
                            <td>
                                <?php if (! empty($s['viewed_at'])): ?>
                                    <i class="bi bi-check-circle-fill text-success me-1"></i>
                                    <?= esc(date('d/m/Y H:i', strtotime($s['viewed_at']))) ?>
                                <?php else: ?>
                                    <span class="text-muted"><i class="bi bi-circle me-1"></i>Belum</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>