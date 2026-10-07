<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= view('announcements/_form', [
    'formAction'   => site_url('admin/announcements'),
    'cancelUrl'    => site_url('admin/announcements'),
    'submitLabel'  => 'Simpan Pengumuman',
    'announcement' => null,
]) ?>
<?= $this->endSection() ?>
