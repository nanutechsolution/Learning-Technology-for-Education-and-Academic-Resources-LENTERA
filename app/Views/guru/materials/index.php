<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<a href="<?= site_url('guru/courses') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Course Saya
</a>
<a href="<?= site_url('guru/courses/' . (int) $course['id'] . '/materials/create') ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Tambah Materi
</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$cards = [
    ['label' => 'Total Materi', 'value' => $counts['total'],     'color' => 'primary',   'icon' => 'bi-journal-text'],
    ['label' => 'Dipublish',    'value' => $counts['published'], 'color' => 'success',   'icon' => 'bi-check2-circle'],
    ['label' => 'Draft',        'value' => $counts['draft'],     'color' => 'secondary', 'icon' => 'bi-pencil-square'],
];
?>

<p class="text-muted">
    <?= esc($course['subject_name'] ?? '-') ?> &middot; Kelas <?= esc($course['class_name'] ?? '-') ?>
    &middot; <?= esc($course['academic_year_name'] ?? '-') ?>
</p>

<div class="row g-3 mb-3">
    <?php foreach ($cards as $card): ?>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded bg-<?= $card['color'] ?> bg-opacity-10 text-<?= $card['color'] ?> d-flex align-items-center justify-content-center flex-shrink-0"
                        style="width:56px;height:56px">
                        <i class="bi <?= $card['icon'] ?> fs-3"></i>
                    </div>
                    <div>
                        <div class="text-muted small"><?= esc($card['label']) ?></div>
                        <div class="fs-4 fw-semibold"><?= (int) $card['value'] ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (empty($materials)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center text-muted py-5">
            Belum ada materi pada course ini.
        </div>
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Judul</th>
                        <th>Isi</th>
                        <th>Status</th>
                        <th class="text-center">Dibuka</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materials as $m): ?>
                        <?php $published = (int) $m['is_published'] === 1; ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('guru/materials/' . (int) $m['id']) ?>" class="fw-semibold text-decoration-none">
                                    <?= esc($m['title']) ?>
                                </a>
                                <?php if ($published && ! empty($m['published_at'])): ?>
                                    <div class="small text-muted">Dipublish <?= esc(date('d/m/Y H:i', strtotime($m['published_at']))) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <?php if (! empty($m['content'])): ?>
                                    <i class="bi bi-card-text text-secondary me-1" title="Teks"></i>
                                <?php endif; ?>
                                <?php if (! empty($m['file_path'])): ?>
                                    <i class="bi bi-paperclip text-secondary me-1" title="File"></i>
                                <?php endif; ?>
                                <?php if (! empty($m['video_url'])): ?>
                                    <i class="bi bi-youtube text-danger" title="Video"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge text-bg-<?= $published ? 'success' : 'secondary' ?>">
                                    <?= $published ? 'Published' : 'Draft' ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?= (int) ($m['view_count'] ?? 0) ?> / <?= (int) ($m['student_total'] ?? 0) ?>
                            </td>
                            <td class="text-end">
                                <div class="d-flex flex-wrap justify-content-end gap-1">
                                    <a href="<?= site_url('guru/materials/' . (int) $m['id']) ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                                    <a href="<?= site_url('guru/materials/' . (int) $m['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">Edit</a>

                                    <form method="post" action="<?= site_url('guru/materials/' . (int) $m['id'] . '/toggle-publish') ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-<?= $published ? 'warning' : 'success' ?>">
                                            <?= $published ? 'Jadikan Draft' : 'Publish' ?>
                                        </button>
                                    </form>

                                    <form method="post" action="<?= site_url('guru/materials/' . (int) $m['id'] . '/delete') ?>" class="d-inline"
                                        data-confirm="Hapus materi ini? Tindakan tidak dapat dibatalkan.">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>