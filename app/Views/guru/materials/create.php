<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= view('guru/materials/_form', [
    'formAction'  => site_url('guru/courses/' . (int) $courseId . '/materials'),
    'cancelUrl'   => site_url('guru/courses/' . (int) $courseId . '/materials'),
    'submitLabel' => 'Simpan Materi',
    'courses'     => $courses,
    'courseId'    => $courseId,
    'material'    => null,
]) ?>
<?= $this->endSection() ?>