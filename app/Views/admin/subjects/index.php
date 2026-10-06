<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<a href="<?= base_url('admin/subjects/create') ?>" class="btn btn-primary btn-sm">
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
                    <th style="width:110px;">Kode</th>
                    <th>Nama</th>
                    <th>Deskripsi</th>
                    <th style="width:110px;">Status</th>
                    <th class="text-end" style="width:180px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subjects)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-3">Belum ada data mata pelajaran.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($subjects as $i => $s): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><span class="badge text-bg-light border"><?= esc($s['code']) ?></span></td>
                            <td class="fw-semibold"><?= esc($s['name']) ?></td>
                            <td class="text-muted small"><?= esc(mb_strimwidth((string) ($s['description'] ?? ''), 0, 80, '...')) ?: '-' ?></td>
                            <td>
                                <?php if (! empty($s['is_active'])): ?>
                                    <span class="badge text-bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= base_url('admin/subjects/' . (int) $s['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <form method="post" action="<?= base_url('admin/subjects/' . (int) $s['id'] . '/delete') ?>"
                                    class="d-inline" data-confirm="Hapus mata pelajaran <?= esc($s['name'], 'attr') ?>?">
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