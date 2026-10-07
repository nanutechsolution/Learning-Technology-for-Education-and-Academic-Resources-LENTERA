<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= view('announcements/_form', [
    'formAction'   => site_url('guru/announcements/' . (int) $announcement['id'] . '/update'),
    'cancelUrl'    => site_url('guru/announcements/' . (int) $announcement['id']),
    'submitLabel'  => 'Simpan Perubahan',
    'announcement' => $announcement,
    'courseLabel'  => (string) ($announcement['course_title'] ?? ''),
]) ?>
<?= $this->endSection() ?>
