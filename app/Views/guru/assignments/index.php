<?php
$fmt = static fn ($d): string => empty($d) ? '-' : date('d/m/Y H:i', strtotime((string) $d));
$now = date('Y-m-d H:i:s');

$cards = [
    ['label' => 'Total Tugas', 'value' => $counts['total'],     'color' => 'primary',   'icon' => 'bi-clipboard-check'],
    ['label' => 'Dipublish',   'value' => $counts['published'], 'color' => 'success',   'icon' => 'bi-check2-circle'],
    ['label' => 'Draft',       'value' => $counts['draft'],     'color' => 'secondary', 'icon' => 'bi-pencil-square'],
];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<a href="<?= site_url('guru/courses') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Course Saya
</a>
<a href="<?= site_url('guru/courses/' . (int) $course['id'] . '/assignments/create') ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Tambah Tugas
</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

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

<?php if (empty($assignments)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center text-muted py-5">
            Belum ada tugas pada course ini.
        </div>
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Judul</th>
                        <th>Batas Waktu</th>
                        <th>Status</th>
                        <th class="text-center">Terkumpul</th>
                        <th class="text-center">Dinilai</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $a): ?>
                        <?php
                        $published = (int) $a['is_published'] === 1;
                        $overdue   = ! empty($a['due_at']) && strtotime($now) > strtotime((string) $a['due_at']);
                        ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('guru/assignments/' . (int) $a['id']) ?>" class="fw-semibold text-decoration-none">
                                    <?= esc($a['title']) ?>
                                </a>
                                <?php if ($published && ! empty($a['published_at'])): ?>
                                    <div class="small text-muted">Dipublish <?= esc($fmt($a['published_at'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <?php if (empty($a['due_at'])): ?>
                                    <span class="text-muted">Tanpa batas</span>
                                <?php else: ?>
                                    <?= esc($fmt($a['due_at'])) ?>
                                    <?php if ($overdue): ?>
                                        <span class="badge text-bg-danger ms-1">Lewat</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge text-bg-<?= $published ? 'success' : 'secondary' ?>">
                                    <?= $published ? 'Published' : 'Draft' ?>
                                </span>
                            </td>
                            <td class="text-center"><?= (int) $a['submitted_total'] ?> / <?= (int) $a['student_total'] ?></td>
                            <td class="text-center"><?= (int) $a['graded_total'] ?> / <?= (int) $a['submitted_total'] ?></td>
                            <td class="text-end">
                                <div class="d-flex flex-wrap justify-content-end gap-1">
                                    <a href="<?= site_url('guru/assignments/' . (int) $a['id']) ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                                    <a href="<?= site_url('guru/assignments/' . (int) $a['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">Edit</a>

                                    <form method="post" action="<?= site_url('guru/assignments/' . (int) $a['id'] . '/toggle-publish') ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-<?= $published ? 'warning' : 'success' ?>">
                                            <?= $published ? 'Jadikan Draft' : 'Publish' ?>
                                        </button>
                                    </form>

                                    <form method="post" action="<?= site_url('guru/assignments/' . (int) $a['id'] . '/delete') ?>" class="d-inline"
                                        data-confirm="Hapus tugas ini beserta semua pengumpulan siswa? Tindakan tidak dapat dibatalkan.">
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