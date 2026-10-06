<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= view('guru/assignments/_form', [
    'formAction'  => site_url('guru/courses/' . (int) $courseId . '/assignments'),
    'cancelUrl'   => site_url('guru/courses/' . (int) $courseId . '/assignments'),
    'submitLabel' => 'Simpan Tugas',
    'courses'     => $courses,
    'courseId'    => $courseId,
    'assignment'  => null,
]) ?>
<?= $this->endSection() ?>