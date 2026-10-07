<?php $published = (int) $announcement['is_published'] === 1; ?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<div class="d-flex flex-wrap gap-1">
    <a href="<?= site_url('admin/announcements') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Daftar Pengumuman
    </a>
    <?php if ($canManage): ?>
        <a href="<?= site_url('admin/announcements/' . (int) $announcement['id'] . '/edit') ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <form method="post" action="<?= site_url('admin/announcements/' . (int) $announcement['id'] . '/toggle-publish') ?>" class="d-inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-outline-<?= $published ? 'warning' : 'success' ?>">
                <?= $published ? 'Jadikan Draft' : 'Publish' ?>
            </button>
        </form>
        <form method="post" action="<?= site_url('admin/announcements/' . (int) $announcement['id'] . '/delete') ?>" class="d-inline"
            data-confirm="Hapus pengumuman ini? Tindakan tidak dapat dibatalkan.">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
        </form>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= view('announcements/_detail', ['announcement' => $announcement]) ?>
<?php if (! $canManage): ?>
    <div class="alert alert-light border small mt-3 mb-0">
        <i class="bi bi-info-circle me-1"></i>
        Ini pengumuman course milik guru. Admin hanya dapat melihatnya.
    </div>
<?php endif; ?>
<?= $this->endSection() ?>
