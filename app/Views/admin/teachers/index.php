<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<a href="<?= base_url('admin/teachers/create') ?>" class="btn btn-primary btn-sm">
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
                    <th>Nama</th>
                    <th>Username</th>
                    <th>NIP</th>
                    <th>Telepon</th>
                    <th>Status</th>
                    <th class="text-end" style="width:180px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($teachers)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-3">Belum ada data guru.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($teachers as $i => $t): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td class="fw-semibold"><?= esc($t['name']) ?></td>
                            <td><?= esc($t['username']) ?></td>
                            <td><?= esc($t['nip'] ?? '-') ?: '-' ?></td>
                            <td><?= esc($t['phone'] ?? '-') ?: '-' ?></td>
                            <td>
                                <?php if (! empty($t['is_active'])): ?>
                                    <span class="badge text-bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= base_url('admin/teachers/' . (int) $t['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <form method="post" action="<?= base_url('admin/teachers/' . (int) $t['id'] . '/delete') ?>"
                                    class="d-inline" data-confirm="Hapus guru <?= esc($t['name'], 'attr') ?> beserta akun loginnya?">
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