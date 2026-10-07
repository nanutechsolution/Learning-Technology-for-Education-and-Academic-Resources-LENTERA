<?php /* Variabel: $course (array|null), $announcements, $teacherId */ ?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<?php if ($course !== null): ?>
    <a href="<?= site_url('guru/courses') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Course Saya
    </a>
    <a href="<?= site_url('guru/courses/' . (int) $course['id'] . '/announcements/create') ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Tambah Pengumuman
    </a>
<?php else: ?>
    <a href="<?= site_url('guru/announcements/create') ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Tambah Pengumuman
    </a>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php if ($course !== null): ?>
    <p class="text-muted">
        <?= esc($course['subject_name'] ?? '-') ?> &middot; Kelas <?= esc($course['class_name'] ?? '-') ?>
        &middot; <?= esc($course['academic_year_name'] ?? '-') ?>
    </p>
<?php endif; ?>

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
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($announcements as $a): ?>
                        <?php
                        $published = (int) $a['is_published'] === 1;
                        $isSchool  = $a['course_id'] === null;
                        $mine      = ! $isSchool && (int) $a['course_teacher_id'] === (int) $teacherId;
                        ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('guru/announcements/' . (int) $a['id']) ?>" class="fw-semibold text-decoration-none text-break">
                                    <?= esc($a['title']) ?>
                                </a>
                                <div class="small text-muted">
                                    <?= $published && ! empty($a['published_at'])
                                        ? 'Dipublish ' . esc(date('d/m/Y H:i', strtotime($a['published_at'])))
                                        : 'Dibuat ' . esc(date('d/m/Y H:i', strtotime((string) $a['created_at']))) ?>
                                    &middot; <?= esc($a['author_name'] ?? '-') ?>
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
                            <td>
                                <span class="badge text-bg-<?= $published ? 'success' : 'secondary' ?>">
                                    <?= $published ? 'Published' : 'Draft' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex flex-wrap justify-content-end gap-1">
                                    <a href="<?= site_url('guru/announcements/' . (int) $a['id']) ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                                    <?php if ($mine): ?>
                                        <a href="<?= site_url('guru/announcements/' . (int) $a['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">Edit</a>

                                        <form method="post" action="<?= site_url('guru/announcements/' . (int) $a['id'] . '/toggle-publish') ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-<?= $published ? 'warning' : 'success' ?>">
                                                <?= $published ? 'Jadikan Draft' : 'Publish' ?>
                                            </button>
                                        </form>

                                        <form method="post" action="<?= site_url('guru/announcements/' . (int) $a['id'] . '/delete') ?>" class="d-inline"
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
