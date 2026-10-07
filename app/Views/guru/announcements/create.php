<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= view('announcements/_form', [
    'formAction'   => site_url('guru/announcements'),
    'cancelUrl'    => site_url('guru/courses/' . (int) $courseId . '/announcements'),
    'submitLabel'  => 'Simpan Pengumuman',
    'announcement' => null,
    'courses'      => $courses,
    'courseId'     => $courseId,
]) ?>
<?= $this->endSection() ?>
