<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<a href="<?= site_url('admin/announcements/create') ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Tambah Pengumuman Sekolah
</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php if (empty($announcements)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center text-muted py-5">Belum ada pengumuman.</div>
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Judul</th>
                        <th>Cakupan</th>
                        <th>Penulis</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($announcements as $a): ?>
                        <?php
                        $published = (int) $a['is_published'] === 1;
                        $isSchool  = $a['course_id'] === null;
                        ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('admin/announcements/' . (int) $a['id']) ?>" class="fw-semibold text-decoration-none text-break">
                                    <?= esc($a['title']) ?>
                                </a>
                                <div class="small text-muted">
                                    <?= $published && ! empty($a['published_at'])
                                        ? 'Dipublish ' . esc(date('d/m/Y H:i', strtotime($a['published_at'])))
                                        : 'Dibuat ' . esc(date('d/m/Y H:i', strtotime((string) $a['created_at']))) ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($isSchool): ?>
                                    <span class="badge text-bg-primary">Sekolah</span>
                                <?php else: ?>
                                    <span class="badge text-bg-info">Course</span>
                                    <div class="small text-muted"><?= esc($a['course_title'] ?? '-') ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($a['author_name'] ?? '-') ?></td>
                            <td>
                                <span class="badge text-bg-<?= $published ? 'success' : 'secondary' ?>">
                                    <?= $published ? 'Published' : 'Draft' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex flex-wrap justify-content-end gap-1">
                                    <a href="<?= site_url('admin/announcements/' . (int) $a['id']) ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                                    <?php if ($isSchool): ?>
                                        <a href="<?= site_url('admin/announcements/' . (int) $a['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">Edit</a>

                                        <form method="post" action="<?= site_url('admin/announcements/' . (int) $a['id'] . '/toggle-publish') ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-<?= $published ? 'warning' : 'success' ?>">
                                                <?= $published ? 'Jadikan Draft' : 'Publish' ?>
                                            </button>
                                        </form>

                                        <form method="post" action="<?= site_url('admin/announcements/' . (int) $a['id'] . '/delete') ?>" class="d-inline"
                                            data-confirm="Hapus pengumuman ini? Tindakan tidak dapat dibatalkan.">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                        </form>
                                    <?php endif; ?>
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
