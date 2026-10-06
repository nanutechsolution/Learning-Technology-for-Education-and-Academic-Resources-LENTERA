<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= view('guru/quizzes/_form', [
    'formAction'  => site_url('guru/courses/' . (int) $course['id'] . '/quizzes'),
    'cancelUrl'   => site_url('guru/courses/' . (int) $course['id'] . '/quizzes'),
    'submitLabel' => 'Simpan Quiz',
    'course'      => $course,
    'quiz'        => null,
    'locked'      => false,
]) ?>
<?= $this->endSection() ?>