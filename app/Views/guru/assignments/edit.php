<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= view('guru/assignments/_form', [
    'formAction'  => site_url('guru/assignments/' . (int) $assignment['id'] . '/update'),
    'cancelUrl'   => site_url('guru/assignments/' . (int) $assignment['id']),
    'submitLabel' => 'Simpan Perubahan',
    'courses'     => $courses,
    'courseId'    => $courseId,
    'assignment'  => $assignment,
]) ?>
<?= $this->endSection() ?>