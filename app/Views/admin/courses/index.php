<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
    <a href="<?= base_url('admin/courses/create') ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg"></i> Tambah
    </a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php $badges = ['draft' => 'secondary', 'active' => 'success', 'archived' => 'dark']; ?>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:50px;">#</th>
                    <th>Judul</th>
                    <th>Mapel</th>
                    <th>Guru</th>
                    <th>Kelas</th>
                    <th>Tahun</th>
                    <th>Siswa</th>
                    <th>Status</th>
                    <th class="text-end" style="width:270px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($courses)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-3">Belum ada data course.</td></tr>
                <?php else: ?>
                    <?php foreach ($courses as $i => $c): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td class="fw-semibold"><?= esc($c['title']) ?></td>
                            <td><?= esc($c['subject_name'] ?? '-') ?></td>
                            <td><?= esc($c['teacher_name'] ?? '-') ?></td>
                            <td><?= esc($c['class_name'] ?? '-') ?></td>
                            <td><?= esc($c['academic_year_name'] ?? '-') ?></td>
                            <td><?= (int) ($c['student_count'] ?? 0) ?></td>
                            <td>
                                <span class="badge text-bg-<?= esc($badges[$c['status']] ?? 'secondary', 'attr') ?>">
                                    <?= esc(ucfirst($c['status'])) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="<?= base_url('admin/courses/' . (int) $c['id'] . '/students') ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-people"></i> Siswa
                                </a>
                                <a href="<?= base_url('admin/courses/' . (int) $c['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <form method="post" action="<?= base_url('admin/courses/' . (int) $c['id'] . '/delete') ?>"
                                      class="d-inline" data-confirm="Hapus course <?= esc($c['title'], 'attr') ?>? Data keikutsertaan siswa ikut terhapus.">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i> Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>