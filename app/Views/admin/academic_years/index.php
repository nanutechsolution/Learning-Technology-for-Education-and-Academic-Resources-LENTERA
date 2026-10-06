<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<a href="<?= base_url('admin/academic-years/create') ?>" class="btn btn-primary btn-sm">
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
                    <th>Mulai</th>
                    <th>Selesai</th>
                    <th>Status</th>
                    <th class="text-end" style="width:260px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($years)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-3">Belum ada data tahun akademik.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($years as $i => $y): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td class="fw-semibold"><?= esc($y['name']) ?></td>
                            <td><?= esc(date('d-m-Y', strtotime($y['start_date']))) ?></td>
                            <td><?= esc(date('d-m-Y', strtotime($y['end_date']))) ?></td>
                            <td>
                                <?php if (! empty($y['is_active'])): ?>
                                    <span class="badge text-bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Tidak aktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if (empty($y['is_active'])): ?>
                                    <form method="post" action="<?= base_url('admin/academic-years/' . (int) $y['id'] . '/activate') ?>"
                                        class="d-inline" data-confirm="Aktifkan tahun akademik <?= esc($y['name'], 'attr') ?>? Tahun lain akan dinonaktifkan.">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-check-circle"></i> Aktifkan
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <a href="<?= base_url('admin/academic-years/' . (int) $y['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <form method="post" action="<?= base_url('admin/academic-years/' . (int) $y['id'] . '/delete') ?>"
                                    class="d-inline" data-confirm="Hapus tahun akademik <?= esc($y['name'], 'attr') ?>?">
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