<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<a href="<?= base_url('admin/classes/create') ?>" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg"></i> Tambah
</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:50px;">#</th>
                    <th>Nama Kelas</th>
                    <th>Tingkat</th>
                    <th>Tahun Akademik</th>
                    <th>Wali Kelas</th>
                    <th>Jumlah Siswa</th>
                    <th class="text-end" style="width:180px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($classes)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-3">Belum ada data kelas.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($classes as $i => $c): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td class="fw-semibold"><?= esc($c['name']) ?></td>
                            <td>Kelas <?= esc($c['grade']) ?></td>
                            <td><?= esc($c['academic_year_name'] ?? '-') ?></td>
                            <td><?= esc($c['homeroom_teacher_name'] ?? '-') ?></td>
                            <td><?= (int) ($c['student_count'] ?? 0) ?></td>
                            <td class="text-end">
                                <a href="<?= base_url('admin/classes/' . (int) $c['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <form method="post" action="<?= base_url('admin/classes/' . (int) $c['id'] . '/delete') ?>"
                                    class="d-inline" data-confirm="Hapus kelas <?= esc($c['name'], 'attr') ?>? Siswa di kelas ini akan menjadi belum punya kelas.">
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