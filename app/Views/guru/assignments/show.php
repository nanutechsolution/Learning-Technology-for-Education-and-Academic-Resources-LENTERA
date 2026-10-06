<?php
$published  = (int) $assignment['is_published'] === 1;
$fmt        = static fn ($d): string => empty($d) ? '-' : date('d/m/Y H:i', strtotime((string) $d));
$formatSize = static function ($bytes): string {
    $bytes = (int) $bytes;
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    }

    return number_format(max($bytes, 0) / 1024, 1, ',', '.') . ' KB';
};
$dueTs     = ! empty($assignment['due_at']) ? strtotime((string) $assignment['due_at']) : null;
$total     = (int) ($stats['total'] ?? 0);
$submitted = (int) ($stats['submitted'] ?? 0);
$percent   = $total > 0 ? round($submitted / $total * 100, 1) : 0;
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<div class="d-flex flex-wrap gap-1">
    <a href="<?= site_url('guru/courses/' . (int) $assignment['course_id'] . '/assignments') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Daftar Tugas
    </a>
    <a href="<?= site_url('guru/assignments/' . (int) $assignment['id'] . '/edit') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-pencil me-1"></i>Edit
    </a>
    <form method="post" action="<?= site_url('guru/assignments/' . (int) $assignment['id'] . '/toggle-publish') ?>" class="d-inline">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline-<?= $published ? 'warning' : 'success' ?>">
            <?= $published ? 'Jadikan Draft' : 'Publish' ?>
        </button>
    </form>
    <form method="post" action="<?= site_url('guru/assignments/' . (int) $assignment['id'] . '/delete') ?>" class="d-inline"
        data-confirm="Hapus tugas ini beserta semua pengumpulan siswa? Tindakan tidak dapat dibatalkan.">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
    </form>
</div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    <span class="badge text-bg-<?= $published ? 'success' : 'secondary' ?>"><?= $published ? 'Published' : 'Draft' ?></span>
                    <span class="text-muted small">
                        <?= esc($assignment['course_title'] ?? '-') ?> &middot; <?= esc($assignment['subject_name'] ?? '-') ?>
                    </span>
                    <?php if ($published && ! empty($assignment['published_at'])): ?>
                        <span class="text-muted small">&middot; Dipublish <?= esc($fmt($assignment['published_at'])) ?></span>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <span class="text-muted small d-block">Batas Waktu Pengumpulan</span>
                    <?php if ($dueTs === null): ?>
                        <span class="fw-semibold">Tanpa batas waktu</span>
                    <?php else: ?>
                        <span class="fw-semibold"><?= esc($fmt($assignment['due_at'])) ?></span>
                        <?php if ($isOverdue): ?>
                            <span class="badge text-bg-danger ms-1">Sudah lewat</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if (! empty($assignment['description'])): ?>
                    <h2 class="h6 text-muted text-uppercase">Petunjuk Tugas</h2>
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
                        <a href="<?= site_url('guru/assignments/' . (int) $assignment['id'] . '/download') ?>" class="btn btn-sm btn-outline-primary ms-md-auto">
                            <i class="bi bi-download me-1"></i>Download
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 text-muted text-uppercase">Ringkasan Pengumpulan</h2>
                <div class="fs-5 fw-semibold">Terkumpul <?= $submitted ?> dari <?= $total ?> siswa</div>
                <div class="progress my-2" role="progressbar" aria-valuenow="<?= esc((string) $percent, 'attr') ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-success" style="width: <?= esc((string) $percent, 'attr') ?>%"></div>
                </div>
                <ul class="list-unstyled small mb-0">
                    <li class="d-flex justify-content-between"><span class="text-muted">Belum mengumpulkan</span><span class="fw-semibold"><?= (int) ($stats['not_submitted'] ?? 0) ?></span></li>
                    <li class="d-flex justify-content-between"><span class="text-muted">Sudah dinilai</span><span class="fw-semibold"><?= (int) ($stats['graded'] ?? 0) ?></span></li>
                    <li class="d-flex justify-content-between"><span class="text-muted">Terlambat</span><span class="fw-semibold"><?= (int) ($stats['late'] ?? 0) ?></span></li>
                    <li class="d-flex justify-content-between"><span class="text-muted">Rata-rata nilai</span>
                        <span class="fw-semibold"><?= $stats['average'] === null ? '-' : esc(number_format((float) $stats['average'], 2, ',', '.')) ?></span>
                    </li>
                </ul>
                <?php if (! $published): ?>
                    <div class="small text-muted mt-2">Tugas masih draft, siswa belum dapat melihatnya.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold">Daftar Siswa</div>
    <?php if (empty($roster)): ?>
        <div class="card-body text-center text-muted">Belum ada siswa yang terdaftar pada course ini.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nama</th>
                        <th>NIS</th>
                        <th>Kelas</th>
                        <th>Status</th>
                        <th>Waktu Kumpul</th>
                        <th class="text-center">Nilai</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roster as $r): ?>
                        <?php
                        $hasSub = ! empty($r['submission_id']) && ! empty($r['submitted_at']);
                        $late   = $hasSub && $dueTs !== null && strtotime((string) $r['submitted_at']) > $dueTs;
                        ?>
                        <tr>
                            <td><?= esc($r['name']) ?></td>
                            <td><?= esc($r['nis'] ?? '-') ?></td>
                            <td><?= esc($r['class_name'] ?? '-') ?></td>
                            <td>
                                <?php if (! $hasSub): ?>
                                    <span class="badge text-bg-light border text-muted">Belum mengumpulkan</span>
                                <?php elseif (! empty($r['graded_at'])): ?>
                                    <span class="badge text-bg-success">Dinilai</span>
                                <?php else: ?>
                                    <span class="badge text-bg-info">Perlu dinilai</span>
                                <?php endif; ?>
                                <?php if ($late): ?>
                                    <span class="badge text-bg-danger">Terlambat</span>
                                <?php endif; ?>
                                <?php if ($hasSub && ! empty($r['has_file'])): ?>
                                    <i class="bi bi-paperclip text-secondary ms-1" title="Ada file jawaban"></i>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= $hasSub ? esc($fmt($r['submitted_at'])) : '-' ?></td>
                            <td class="text-center">
                                <?= ($hasSub && $r['score'] !== null) ? esc(number_format((float) $r['score'], 2, ',', '.')) : '-' ?>
                            </td>
                            <td class="text-end">
                                <?php if ($hasSub): ?>
                                    <a href="<?= site_url('guru/submissions/' . (int) $r['submission_id']) ?>" class="btn btn-sm btn-outline-primary">
                                        <?= ! empty($r['graded_at']) ? 'Lihat / Ubah Nilai' : 'Lihat &amp; Nilai' ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
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