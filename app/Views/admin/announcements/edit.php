<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= view('announcements/_form', [
    'formAction'   => site_url('admin/announcements/' . (int) $announcement['id'] . '/update'),
    'cancelUrl'    => site_url('admin/announcements/' . (int) $announcement['id']),
    'submitLabel'  => 'Simpan Perubahan',
    'announcement' => $announcement,
]) ?>
<?= $this->endSection() ?>
