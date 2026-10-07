<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<a href="<?= site_url('siswa/announcements') ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Daftar Pengumuman
</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= view('announcements/_detail', ['announcement' => $announcement]) ?>
<?= $this->endSection() ?>
